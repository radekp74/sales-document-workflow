# P005 — Infrastructure Bootstrap — dowód weryfikacji

## Metadane

```text
ZADANIE=P005 Infrastructure Bootstrap
DATA=2026-09-04
START_HEAD=57f2369619697e374f7b94a444f68253722378b0
IMPLEMENTATION_HEAD=3fabf8cacfd8dcb061c25e0df5c2efc87b7ec753
END_HEAD=patrz `git log -1` — commit domykający dokumentację P005
```

### Stan working tree przed pracą

Zmodyfikowane pliki dokumentacji P001–P005, usunięty rootowy `compose.yaml`, nieśledzone `Makefile`, `infrastructure/`, `phpstan.neon`, `.php-cs-fixer.php`, `deptrac.yaml`, `.gitattributes` oraz karty i refinementy P006–P011. Łącznie 34 wpisy w `git status --porcelain`.

Kod aplikacji w `src/` pozostawał identyczny z baseline'em zadania.

## Runtime — środowisko potwierdzone realnym uruchomieniem

```text
PHP          8.4.25 (cli)
Composer     2.8.12
Symfony      7.4.16
PostgreSQL   16.14
Docker Compose v5.3.1
Xdebug       Not installed
```

Kontenery DEV po `make setup`:

```text
sales-document-workflow-dev-php-1        Up   0.0.0.0:8000->8000/tcp
sales-document-workflow-dev-postgres-1   Up (healthy)   0.0.0.0:5432->5432/tcp
```

## Wykonane komendy

| Komenda | Exit code | Wynik | Istotny output |
|---|---:|---|---|
| `make docker-check` | 0 | PASS | `DOCKER_CHECK=PASS` |
| `make build` | 0 | PASS | `BUILD=PASS` — obrazy DEV i TEST |
| `make setup` | 0 | PASS | `COMPOSER_INSTALL=PASS`, `DEV_DB_READY=PASS`, `DEV_MIGRATE=PASS`, `SETUP=PASS` |
| `make runtime-info` | 0 | PASS | PHP 8.4.25, Composer 2.8.12, Symfony 7.4.16, PostgreSQL 16.14 |
| `make ps` | 0 | PASS | oba kontenery DEV `Up`, `postgres` `healthy` |
| `make cs-check` | 0 | PASS | `Found 0 of 19 files that can be fixed`, `CS_CHECK=PASS` |
| `make deptrac` | 0 | PASS | `Violations 0`, `Skipped violations 1`, `DEPTRAC=PASS` |
| `make phpstan` | 2 | APPLICATION_BASELINE_FAILURE | `[ERROR] Found 17 errors` |
| `make test-functional` | 2 | APPLICATION_BASELINE_FAILURE | `Tests: 6, Assertions: 12, Errors: 2, Failures: 1` |
| `make test-e2e` | 0 | PASS | `E2E_HTTP_READY=PASS`, `E2E_CREATE=PASS`, `E2E_PERSISTED_IN_POSTGRES=PASS`, `E2E=PASS` |
| `make test` | 2 | APPLICATION_BASELINE_FAILURE | `TEST_UNIT=NO_TESTS_PRESENT`, `TEST_INTEGRATION=NO_TESTS_PRESENT`, zatrzymanie na functional |
| `make verify` | 2 | APPLICATION_BASELINE_FAILURE | `TEST_PREPARE=PASS`, `composer.json is valid`, `PHP_SYNTAX=PASS`, `mapping files are correct`, `CS_CHECK=PASS`, zatrzymanie na PHPStan |
| `make export-source` | 0 | PASS | `EXPORT_SOURCE=PASS`, 120 wpisów w ZIP |
| `make export-source-committed` (tree dirty) | 2 | PASS (oczekiwana odmowa) | `EXPORT_SOURCE_COMMITTED=REFUSED_DIRTY_WORKTREE` |

## Klasyfikacja niepowodzeń

Żadne czerwone uruchomienie nie jest awarią infrastruktury. Infrastruktura w każdym przypadku poprawnie wykonała narzędzie i przekazała jego kod wyjścia.

### `APPLICATION_BASELINE_FAILURE` — PHPStan

17 błędów, wyłącznie w dostarczonym kodzie biznesowym:

```text
Controller/SalesDocumentController.php          7  HandledStamp nullable, offsetAccess na wyniku raw SQL, brak typu iterable
Entity/SalesDocument.php                        4  brak typów tablicowych sellerSnapshot, property.unusedType dla $id
MessageHandler/ApproveSalesDocumentHandler.php  5  find() nullable po commicie, zwracany int|null
MessageHandler/CreateSalesDocumentHandler.php   1  zwracany int|null
```

Wszystkie należą do zakresu P006–P010. Poziom PHPStan nie został obniżony, nie dodano `ignoreErrors`.

### `APPLICATION_BASELINE_FAILURE` — PHPUnit

```text
ApproveSalesDocumentTest::testApprovingAQuoteSpawnsALinkedOrderAndNotifiesBothParties         PASS
ApproveSalesDocumentTest::testApprovalDoesNotFailTheCallerWhenTheNotificationChannelFails     ERROR    -> P007
SalesDocumentControllerTest::testCreateAndApproveThroughHttp                                  PASS
SalesDocumentControllerTest::testApprovingMissingDocumentCurrentlyReturns500                  PASS     (dokumentuje błędny baseline, zmiana w P006)
RejectSalesDocumentHandlerTest::testRejectingADraftQuoteMarksItRejected                       ERROR    -> P009 (brak klasy RejectSalesDocument)
RejectSalesDocumentHandlerTest::testRejectingAnAlreadyApprovedDocumentIsRejectedByTheDomain    FAILURE  -> P009 (brak klasy RejectSalesDocument)
```

Baseline nie został podrasowany: żaden test nie został usunięty, pominięty ani wyciszony, konfiguracja PHPUnit nie została zmieniona, kody wyjścia nie zostały przechwycone.

### Propagacja kodów wyjścia

`make test` zatrzymuje się na pierwszym czerwonym poziomie i nie uruchamia kolejnych. `make verify` zatrzymuje się na PHPStan i nie uruchamia testów. Oba zwracają kod różny od zera.

## Defekty infrastruktury znalezione i naprawione w P005

### D1 — `src/.DS_Store` śledzony przez Git

- **Objaw:** `make export-source-committed` przerwałby pracę komunikatem `FAIL_FORBIDDEN_PATH_PRESENT`, ponieważ `git archive` umieściłby `.DS_Store` w archiwum.
- **Root cause:** plik metadanych macOS znalazł się w commicie bazowym zadania; wpis `export-ignore` w `.gitattributes` pokrywał wyłącznie ścieżkę główną, nie `src/`.
- **Klasyfikacja:** `INFRASTRUCTURE_FAILURE`, należy do P005 (kontrakt eksportu).
- **Fix:** `git rm --cached src/.DS_Store`, usunięcie pliku, globalny wzorzec `.DS_Store` w `.gitignore`.
- **Weryfikacja:** `git ls-files | grep -i ds_store` — brak wyników; kontrola archiwum: `FORBIDDEN_PATHS=PASS`.

### D2 — katalog `.idea/` śledzony przez Git

- **Objaw:** metadane IDE w repozytorium przekazywanym do review.
- **Root cause:** katalog trafił do commita bazowego zadania.
- **Klasyfikacja:** `INFRASTRUCTURE_FAILURE`, należy do P005 (czystość repozytorium).
- **Fix:** `git rm -r --cached .idea`, wpis `/.idea/` w `.gitignore`. `.gitattributes` nadal zabezpiecza eksport `HEAD`.
- **Weryfikacja:** `git ls-files | grep '^\.idea'` — brak wyników.

### D3 — `.deptrac.cache` w eksporcie working tree

- **Objaw:** `make export-source` umieszczał plik cache narzędzia jakościowego w paczce źródeł.
- **Root cause:** Deptrac domyślnie zapisuje cache w katalogu głównym projektu, a plik nie był ignorowany, więc `git ls-files -o --exclude-standard` traktował go jako nowy plik źródłowy.
- **Klasyfikacja:** `INFRASTRUCTURE_FAILURE`, należy do P005.
- **Fix:** `--cache-file=var/deptrac/deptrac.cache` w targecie `deptrac` (katalog `var/` jest już ignorowany) oraz wzorzec `/.deptrac.cache` w `.gitignore` dla plików ze starszych uruchomień.
- **Weryfikacja:** po `make deptrac` katalog główny nie zawiera `.deptrac.cache`; plik powstaje w `var/deptrac/`; ponowny `make export-source` — 120 wpisów, `FORBIDDEN_PATHS=PASS`.

### D4 — smoke E2E nie dowodził dotarcia do PostgreSQL

- **Objaw:** skrypt sprawdzał wyłącznie status HTTP 201 i obecność pola `id` w odpowiedzi. Nie odróżniał realnego zapisu od odpowiedzi wygenerowanej bez udziału bazy. Dodatkowo pętla oczekiwania na gotowość HTTP tworzyła dokumenty jako efekt uboczny.
- **Root cause:** smoke test zatrzymywał się na warstwie HTTP.
- **Klasyfikacja:** `INFRASTRUCTURE_FAILURE`, należy do P005 (ADR-004 wymaga E2E przez pełny stos).
- **Fix:** oczekiwanie na gotowość przez zapytanie o nieistniejącą ścieżkę (bez tworzenia danych) oraz potwierdzenie zapisu przez `php bin/console dbal:run-sql` na bazie TEST.
- **Weryfikacja:** `E2E_PERSISTED_IN_POSTGRES=PASS`, `E2E=PASS`, exit code 0.

## Korekty konfiguracji quality tooling

### PHP-CS-Fixer

Baseline nie przechodził dwóch czysto stylistycznych reguł zestawu `@Symfony`. Zamiast przepisywać dostarczony kod, doprecyzowano konfigurację:

```text
concat_space => ['spacing' => 'one']   (wartość zgodna z @PER-CS2.0, baseline używa spacji wokół ".")
yoda_style   => false                  (baseline używa naturalnej kolejności porównań)
```

Przyjęto trzy realne poprawki mechaniczne, bez wpływu na zachowanie:

```text
src/Kernel.php              + declare(strict_types=1)
tests/bootstrap.php         + declare(strict_types=1)
src/Notification/InMemoryNotifier.php   $this->calls++  ->  ++$this->calls
```

Poza tym kod aplikacji pozostał nietknięty.

### Deptrac

```text
przed:  Violations 1  (App\Entity\SalesDocument -> App\Repository\SalesDocumentRepository)
po:     Violations 0, Skipped violations 1
```

Naruszenie wynikało wyłącznie z atrybutu `#[ORM\Entity(repositoryClass: ...)]`, czyli z wymogu Doctrine. Reguła pozostała aktywna, a `skip_violations` zawężono do tej jednej pary klas.

## Izolacja TEST od DEV — potwierdzenie

```text
projekt Compose DEV    sales-document-workflow-dev
projekt Compose TEST   sales-document-workflow-test
baza DEV               app        @ postgres:5432        named volume postgres_dev_data
baza TEST              app_test   @ postgres-test:5432   tmpfs
```

Efemeryczność bazy TEST potwierdzona obserwacyjnie: w kolejnym uruchomieniu `make test-e2e` nowo utworzony dokument otrzymał `id = 1`, mimo wcześniejszych przebiegów tworzących dokumenty.

Stack TEST jest usuwany po każdym targecie testowym (`make test-down`), a `make down` nie usuwa danych DEV.

## Kontrakt eksportu źródeł

### `make export-source` — working tree

```text
EXPORT_SOURCE=PASS
EXPORT_MODE=WORKING_TREE
EXPORT_BASE_COMMIT=57f2369619697e374f7b94a444f68253722378b0
EXPORT_DIRTY_ENTRIES=42
wpisów w archiwum: 120
```

Nie wymaga clean tree. Obejmuje pliki śledzone, zmodyfikowane i nowe nieignorowane (`git ls-files -co --exclude-standard`).

### Kontrola zawartości archiwum

```text
FORBIDDEN_PATHS=PASS
```

Sprawdzono realnie listą plików ZIP-a, nie tylko przez `.gitignore`. Brak: `vendor/`, `var/`, `exports/`, `.git/`, `.idea/`, `.DS_Store`, `.deptrac.cache`.

Obecne: `README.md`, `TASK.MD`, `Makefile`, `src/`, `tests/`, `docs/`, `infrastructure/`, `phpstan.neon`, `deptrac.yaml`, `.php-cs-fixer.php`.

### `make export-source-committed` — `HEAD`

Twardy clean-tree gate zweryfikowany realnie na brudnym drzewie:

```text
EXPORT_SOURCE_COMMITTED=REFUSED_DIRTY_WORKTREE
exit code = 2
```

Gate nie został obejściem ani osłabiony.

Po commicie implementacji P005 target został uruchomiony na czystym drzewie:

```text
EXPORT_SOURCE_COMMITTED=PASS
EXPORT_MODE=COMMITTED_HEAD
EXPORT_PATH=/Users/demon/Downloads/sales-document-workflow-source-20260904T125158Z-3fabf8ca-committed.zip
EXPORT_SHA256=e7fe508beacb3c3cb30ecffa58e0f1ebbd07244bf203e050434d4e9f3a629080
EXPORT_COMMIT=3fabf8cacfd8dcb061c25e0df5c2efc87b7ec753
exit code = 0
```

Weryfikacja archiwum: 120 wpisów, `FORBIDDEN_PATHS=PASS`, lista plików identyczna z `git ls-tree -r --name-only HEAD`.

Obecne: `README.md`, `TASK.MD`, `Makefile`, `composer.json`, `src/`, `tests/`, `docs/`, `infrastructure/`, `migrations/`, `config/`, `public/`, `bin/`, `phpstan.neon`, `deptrac.yaml`, `.php-cs-fixer.php`, `.gitattributes`.

> Suma kontrolna archiwum commita nie może z definicji znaleźć się w tym samym commicie. Powyższe wartości dotyczą commita implementacyjnego. `git archive` jest deterministyczny, więc `make export-source-committed` uruchomiony ponownie na tym samym commicie odtworzy identyczną sumę SHA-256. Artefakt dla finalnego `HEAD` etapu jest podany w raporcie etapu i wyznaczany tą samą komendą.

## Zgodność dokumentacji z implementacją

Wykryto i naprawiono sprzeczność wewnątrz `docs/refinement/P005-refinement.md`:

- sekcje 13 i 14 wymagały clean working tree dla `make export-source` i zapisu paczki do `exports/`,
- sekcje 19 i 20 opisywały nowszą, świadomą decyzję o dwóch trybach eksportu i katalogu `$HOME/Downloads`.

Rozstrzygnięcie: obowiązuje wersja z sekcji 19–20, zgodna z implementacją. Sekcje 13 i 14 zostały poprawione z jawną adnotacją o korekcie, zamiast cichego usunięcia starej treści.

## Wynik

```text
P005=DONE_AND_VERIFIED
```

Potwierdzone realnym uruchomieniem: build obrazów, start DEV, start TEST, PostgreSQL 16, PHP 8.4, Composer, migracje DEV i TEST, PHPUnit, PHPStan, PHP-CS-Fixer, Deptrac, prawdziwe HTTP E2E aż do zapisu w bazie, propagacja kodów wyjścia przez Makefile, oba tryby eksportu wraz z kontrolą zabronionych ścieżek.

## Kontrola linków dokumentacji

```text
FILES=30  LOCAL_LINKS_CHECKED=102
DOC_LINKS=PASS
```

Sprawdzono wszystkie lokalne odnośniki Markdown w `README.md`, `TASK.MD` oraz `docs/**/*.md`. Brak martwych linków.

## Odchylenia

- `make test-unit` i `make test-integration` zwracają `NO_TESTS_PRESENT`. Te poziomy powstają w P010; target nie udaje pokrycia, którego nie ma.
- `make phpstan`, `make test-functional`, `make test` i `make verify` są czerwone z powodu kodu biznesowego objętego P006–P009. Jest to udokumentowany `APPLICATION_BASELINE_FAILURE`, nie awaria infrastruktury.
- Xdebug nie jest zainstalowany w obrazie. Nie był potrzebny do P005; diagnostyka problemu ownership w P008 nie może zatem powoływać się na jego użycie.
