# Sales Document Workflow

Aplikacja backendowa w Symfony obsługująca workflow dokumentów sprzedażowych (oferty i zamówienia) w architekturze CQRS opartej o Symfony Messenger.

Repozytorium zawiera rozwiązanie zadania rekrutacyjnego opisanego w [`TASK.MD`](TASK.MD) oraz pełną dokumentację analizy, decyzji i implementacji w katalogu [`docs/`](docs/README.md).

## Status

Zadanie jest zamknięte. Wszystkie etapy P005–P011 mają status `DONE_AND_VERIFIED` z dowodami runtime w [`docs/evidence/`](docs/evidence/README.md).

```text
CS_CHECK=PASS   PHPSTAN=PASS   DEPTRAC=PASS
UNIT=PASS       INTEGRATION=PASS   FUNCTIONAL=PASS   E2E=PASS
TEST=PASS       VERIFY=PASS
```

---

## Szybki start

Na hoście wymagane są wyłącznie:

- Docker Desktop / Docker Engine,
- Docker Compose v2,
- `make`,
- Git.

PHP, Composer i PostgreSQL są dostarczane przez Docker — nie trzeba instalować ich lokalnie.

```bash
make setup     # zbudowanie obrazów, start DEV, composer install, migracje
make verify    # pełny quality gate: statyczna analiza + wszystkie poziomy testów
```

Aplikacja jest dostępna pod `http://localhost:8000`.

Pozostałe komendy wypisuje `make help`. Szczegóły środowiska: [`docs/03-development-environment.md`](docs/03-development-environment.md).

---

## Natura problemów i sposób ich rozwiązania

`TASK.MD` zgłasza trzy niezależne problemy oraz brakującą operację. Poniżej opis każdego z nich: przyczyna, sposób diagnozy i wprowadzona naprawa.

### Problem 1 — zatwierdzenie „wygląda na nieudane", choć dokument jest zatwierdzony

**Przyczyna.** `ApproveSalesDocumentHandler` zapisywał zmianę stanu wewnątrz `wrapInTransaction()`, a dwie notyfikacje wysyłał **po** commicie. Wyjątek notyfikatora nie miał żadnej izolacji, więc propagował się przez synchroniczny `command.bus` prosto do kontrolera, który zamieniał go na HTTP 500.

W tym momencie transakcja była już zatwierdzona i nie istniała żadna transakcja, którą można by wycofać. Z punktu widzenia bazy operacja zakończyła się sukcesem, a klient dostawał błąd — stąd rozbieżność między raportem klienta a stanem w bazie.

**Root cause.** Brak rozdzielenia semantyki sukcesu głównej operacji biznesowej od awarii efektu ubocznego wykonywanego po commicie. Problemem nie był brak rollbacku — rollback po commicie nie jest możliwy i nie byłby właściwym rozwiązaniem.

**Naprawa.** Notyfikacja jest traktowana jako efekt uboczny typu best-effort względem trwałego zatwierdzenia:

- persystencja pozostaje w transakcji, notyfikacje pozostają poza nią,
- **każde** wywołanie `NotifierPort::notify()` ma własny `try/catch` — jeden wspólny blok powodowałby, że awaria pierwszego odbiorcy blokuje powiadomienie drugiego,
- awaria jest logowana z kontekstem (`documentId`, `userId`, klasa wyjątku, komunikat), więc pozostaje obserwowalna operacyjnie,
- handler zawsze zwraca identyfikator zatwierdzonego dokumentu po poprawnym commicie.

Przy okazji cała operacja approval używa jednego znacznika czasu zamiast trzech osobnych `DateTimeImmutable` (quote, order, `snapshot_at`).

Zgodnie z sugestią w `TASK.MD` **nie** dodano drugiej szyny ani kolejki asynchronicznej. Nie ma tu również outboxa, retry ani brokera — decyzję i jej koszt opisuje [`ADR-003`](docs/adr/ADR-003-post-commit-notification-semantics.md).

### Problem 2 — każdy błąd jako HTTP 500 z surowym komunikatem wyjątku

**Przyczyna.** Kontroler zawierał:

```php
catch (\Throwable $e) {
    return new JsonResponse(['error' => $e->getMessage()], 500);
}
```

Trzy zupełnie różne klasy sytuacji — brak dokumentu, niedozwolone przejście stanu i faktyczna awaria techniczna — były nie do odróżnienia, ponieważ handler używał `RuntimeException` dla wszystkich. Publiczny kontrakt API zależał wprost od technicznej treści komunikatu wyjątku. Dodatkowo kontroler wykonywał własne zapytanie SQL zamiast skorzystać z istniejącego repozytorium.

**Naprawa.** Wprowadzono jawny kontrakt błędów oparty na typach, nie na tekście:

```text
App\Exception\SalesDocumentNotFound        -> HTTP 404
App\Exception\InvalidSalesDocumentState    -> HTTP 409
niepoprawne wejście HTTP                   -> HTTP 400
nieoczekiwany błąd techniczny              -> HTTP 500
```

Kontroler rozpakowuje `HandlerFailedException` Messengera i mapuje wyłącznie po klasie wyjątku. Odpowiedź 500 ma stałe, generyczne ciało `{"error":"Internal server error"}`, a pełny wyjątek trafia do logów — treść techniczna nigdy nie wycieka do klienta.

Oba wyjątki rozszerzają `RuntimeException`, dzięki czemu dostarczone testy oczekujące tego typu bazowego działają bez zmian.

Usunięto też `EntityManagerInterface` z kontrolera: odpowiedź `approve` jest budowana z encji pobranej przez `SalesDocumentRepository`, więc warstwa HTTP nie zna już struktury tabeli. Sytuacja, w której komenda zakończyła się sukcesem, ale wyniku nie da się odczytać, jest traktowana jako niespójność techniczna (500), a nie brak zasobu (404).

Test `testApprovingMissingDocumentCurrentlyReturns500` został zgodnie z poleceniem zastąpiony przez `testApprovingMissingDocumentReturns404`.

Szczegóły decyzji: [`ADR-002`](docs/adr/ADR-002-application-error-contract.md).

### Problem 3 — zamienione dane właściciela (zgłoszenie supportu)

**Przyczyna.** `SalesDocumentController::resolveDocumentOwnership()` odwracał znaczenie pól:

```php
'contractorId' => (int) $payload['created_by'],
'createdBy' => (int) $payload['contractor_id'],
```

Dla żądania `{"contractor_id": 77, "created_by": 5}` powstawała komenda `contractorId = 5`, `createdBy = 77`.

**Dlaczego „nie za każdym razem".** Defekt dotyczył wyłącznie adaptera HTTP. Bezpośrednia ścieżka CQRS — `new CreateSalesDocument(contractorId: 77, createdBy: 5)` — omijała błędną metodę kontrolera i zapisywała wartości poprawnie. Dokumenty powstające inną drogą niż kontroler nie miały więc odwróconych danych, co dokładnie odpowiada obserwacji supportu.

**Dlaczego nie widziały tego testy.** Istniejący test HTTP sprawdzał wyłącznie status odpowiedzi, identyfikator, typ dokumentu i `parent_quote_id`. Nigdy nie sięgał po `contractorId` ani `createdBy`. Z kolei testy handlerów podawały poprawne wartości bezpośrednio do komendy, omijając wadliwe mapowanie. Oba zestawy mogły być zielone mimo błędnych danych w bazie.

**Jak został zdiagnozowany.** Przez audyt przepływu danych, nie debugerem. Xdebug nie jest zainstalowany w obrazie i nie został użyty — nie deklarujemy narzędzia, którego nie użyliśmy. Kolejne kroki:

1. porównanie payloadu HTTP z polami komendy `CreateSalesDocument`,
2. porównanie komendy z mapowaniem w `CreateSalesDocumentHandler` — handler okazał się poprawny, więc źródło błędu leżało wcześniej,
3. zawężenie do jedynego miejsca transformacji danych, czyli `resolveDocumentOwnership()`,
4. zestawienie obu ścieżek zapisu, co wyjaśniło nieregularność zgłoszenia,
5. potwierdzenie hipotezy testem odczytującym realnie zapisane wartości z PostgreSQL.

**Naprawa.** Przywrócone mapowanie 1:1 oraz regresja, która czyni tę klasę błędu niemożliwą do przeoczenia. `tests/Functional/SalesDocumentOwnershipTest.php` czyści identity map Doctrine i sprawdza wartości odczytane z bazy — sam kod odpowiedzi HTTP nie jest uznawany za wystarczający dowód.

Skuteczność regresji została zweryfikowana empirycznie: z przywróconym odwróconym mapowaniem testy zgłaszają 2 błędy, po poprawce są zielone, a test ścieżki bezpośredniej pozostaje zielony w obu wariantach.

### Nowa operacja — `RejectSalesDocument`

Kontrakt został wyprowadzony z dostarczonego `tests/Functional/RejectSalesDocumentHandlerTest.php`, którego **nie zmodyfikowano**.

Dodano `RejectSalesDocument`, `RejectSalesDocumentHandler` na `command.bus` oraz `SalesDocumentStatus::Rejected`. Ponieważ komenda przenosi `rejectedBy`, informacja ta jest faktycznie utrwalana — encja i migracja otrzymały nullowalne `rejected_by` i `rejected_at`.

Model przejść stanów:

```text
draft    -> rejected   dozwolone
approved -> rejected   niedozwolone
rejected -> rejected   niedozwolone
rejected -> approved   niedozwolone
```

`TASK.MD` nie rozstrzyga przypadku powtórnego odrzucenia. Przyjęto najprostszy spójny model — odrzucić można wyłącznie dokument w stanie `Draft`. Dzięki temu reguła jest jednym warunkiem zamiast listy wyjątków i chroni oryginalne metadane odrzucenia przed nadpisaniem.

Błędy korzystają z kontraktu z problemu 2. Handler zwraca `void`, ponieważ żaden wywołujący nie potrzebuje wyniku. Nie dodano endpointu HTTP `reject`, katalogu powodów odrzucenia ani silnika workflow — zadanie dostarcza kontrakt handlera, nie wymóg nowego endpointu.

---

## Decyzja o wersjach zależności

**Pozostajemy przy Symfony 7.4** i nie aktualizujemy zależności.

Uzasadnienie: żaden z czterech problemów nie wynika z wersji frameworka. Wszystkie mają konkretne, deterministyczne źródła we własnym kodzie aplikacji — brak izolacji efektu ubocznego po commicie, zbyt szeroki `catch`, odwrócone mapowanie dwóch pól i brakująca klasa. Aktualizacja frameworka nie naprawiłaby żadnego z nich, a powiększyłaby diff i ryzyko regresji w zadaniu, którego istotą jest precyzyjna diagnoza.

Symfony 7.4 jest wydaniem LTS ze wsparciem do listopada 2028, więc pozostanie przy nim nie jest długiem technicznym.

Runtime został natomiast jawnie ustalony na **PHP 8.4**: `composer.json` deklaruje `php >=8.2`, ale zablokowany w `composer.lock` PHPUnit 13 wymaga nowszego PHP. Zamiast luzować lock, środowisko Docker zamraża wersję, która faktycznie spełnia wszystkie ograniczenia. Uzasadnienie w [`ADR-001`](docs/adr/ADR-001-containerized-development-and-test-environment.md).

Narzędzia jakościowe (PHPStan, PHP-CS-Fixer, Deptrac) są instalowane w obrazie Dockera poza `composer.json` aplikacji, żeby nie modyfikować dostarczonego `composer.lock` wyłącznie na potrzeby toolingu.

---

## Świadome kompromisy

- **Brak retry i outboxa dla notyfikacji.** Bez nich notyfikacja może zostać utracona. To akceptowany kompromis wynikający wprost z zakresu zadania, które odradza budowanie drugiej kolejki. Awaria pozostaje widoczna w logach.
- **Brak endpointu HTTP dla `reject`.** Zadanie definiuje kontrakt handlera, nie API. Endpoint zostałby dodany dopiero z realnym wymaganiem.
- **`sellerSnapshot` zachowuje obecną nazwę i strukturę.** Zmiana semantyki bez wymagania biznesowego byłaby rozszerzeniem zakresu.
- **Messenger pozostaje synchroniczny.** Nie ma potrzeby transportu asynchronicznego, a synchroniczny bus upraszcza testowanie.
- **Ownership w E2E weryfikowany zapytaniem SQL.** Publiczne API nie udostępnia odczytu dokumentu, a nie tworzymy endpointu wyłącznie na potrzeby testu. Operacja biznesowa jest wykonywana wyłącznie przez HTTP; SQL sprawdza jedynie stan końcowy.
- **Dwa odstępstwa od zestawu `@Symfony` w PHP-CS-Fixer** (`concat_space`, `yoda_style`), aby nie przepisywać stylu dostarczonego baseline'u. Opisane w [`docs/03-development-environment.md`](docs/03-development-environment.md).
- **Jeden wpis `skip_violations` w Deptrac** dla zależności `SalesDocument -> SalesDocumentRepository`, wymuszonej przez atrybut `#[ORM\Entity(repositoryClass: ...)]`. Reguła architektoniczna pozostaje aktywna.

---

## Testy i quality gate

```bash
make test-unit          # logika izolowana, bez Kernela i bazy
make test-integration   # Doctrine i realny PostgreSQL
make test-functional    # Symfony Kernel, kontrolery, command bus
make test-e2e           # black-box API przez prawdziwy HTTP
make test               # wszystkie poziomy po kolei
make verify             # test + composer validate + schema + cs-check + phpstan + deptrac
```

```bash
make cs-check           # PHP-CS-Fixer bez modyfikacji
make cs-fix             # PHP-CS-Fixer z zapisem zmian
make phpstan            # PHPStan level 8
make deptrac            # granice zależności między warstwami
```

Środowisko TEST jest w pełni odseparowane od DEV: osobny projekt Docker Compose, osobny PostgreSQL na `tmpfs`, baza `app_test`. Testy nigdy nie dotykają danych developerskich.

E2E przechodzi przez pełny stos `HTTP -> Symfony -> Messenger -> Doctrine -> PostgreSQL`. Nie używamy automatyzacji przeglądarki — API jest w całości JSON-owe.

Macierz regresji z przypisaniem testów do scenariuszy: [`docs/05-test-plan.md`](docs/05-test-plan.md).

---

## Struktura rozwiązania

Zachowano architekturę CQRS wskazaną w `TASK.MD`. Nie wprowadzono warstw Domain/Application/Infrastructure ani DDD.

```text
src/
├── Controller/       adapter HTTP: walidacja wejścia, mapowanie błędów na kody HTTP
├── Message/Command/  komendy
├── MessageHandler/   handlery na command.bus
├── Entity/           encje Doctrine
├── Enum/             typy i statusy dokumentu
├── Exception/        semantyczne błędy aplikacyjne
├── Notification/     port notyfikacji i jego implementacje
└── Repository/       dostęp do danych
```

Granice tych katalogów są egzekwowane automatycznie przez Deptrac (`deptrac.yaml`).

---

## Kontrakt eksportu źródeł

| Target | Wymaga clean tree | Zakres |
|---|---|---|
| `make export-source` | nie | aktualny working tree (tracked, modified, nowe nieignorowane) |
| `make export-source-committed` | tak | dokładnie `HEAD` |

Oba tryby zapisują paczkę w `$HOME/Downloads`, wypisują SHA-256 i commit, a po zbudowaniu archiwum weryfikują jego zawartość i przerywają pracę, jeżeli wykryją `vendor/`, `var/`, `exports/`, `.git/`, `.idea/` lub `.DS_Store`.

---

## Dokumentacja

Pełny indeks: [`docs/README.md`](docs/README.md).

| Dokument | Zawartość |
|---|---|
| [`docs/01-problem-statement.md`](docs/01-problem-statement.md) | znormalizowany opis problemów i kryteria sukcesu |
| [`docs/02-technical-analysis.md`](docs/02-technical-analysis.md) | audyt kodu i root cause analysis |
| [`docs/03-development-environment.md`](docs/03-development-environment.md) | środowisko DEV/TEST, Makefile, quality tooling |
| [`docs/04-solution-design.md`](docs/04-solution-design.md) | projekt rozwiązania |
| [`docs/05-test-plan.md`](docs/05-test-plan.md) | macierz regresji i quality gate |
| [`docs/06-implementation-summary.md`](docs/06-implementation-summary.md) | podsumowanie wykonania i wyniki |

Decyzje architektoniczne: [`ADR-001`](docs/adr/ADR-001-containerized-development-and-test-environment.md), [`ADR-002`](docs/adr/ADR-002-application-error-contract.md), [`ADR-003`](docs/adr/ADR-003-post-commit-notification-semantics.md), [`ADR-004`](docs/adr/ADR-004-automated-test-strategy-and-isolation.md).

Zakres wykonawczy i dowody: [`docs/backlog/BACKLOG.md`](docs/backlog/BACKLOG.md), [`docs/evidence/`](docs/evidence/README.md).
