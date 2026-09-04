# Sales Document Workflow — Audyt techniczny i analiza

## 1. Cel dokumentu

Celem dokumentu jest techniczna analiza istniejącej implementacji oraz ustalenie przyczyn problemów opisanych w [`01-problem-statement.md`](01-problem-statement.md).

Dokument obejmuje:

- mapę aktualnej architektury,
- analizę przepływów `create` i `approve`,
- root cause analysis dla trzech zgłoszonych problemów,
- analizę kontraktu brakującej operacji `RejectSalesDocument`,
- ocenę testów i luk w pokryciu,
- identyfikację ryzyk technicznych,
- rekomendowany kierunek zmian.

Dokument nie stanowi jeszcze finalnego projektu rozwiązania. Szczegółowe decyzje implementacyjne zostaną zapisane w `04-solution-design.md`.

---

## 2. Zakres i metoda audytu

Analiza została wykonana statycznie na kodzie dostarczonym wraz z zadaniem, w szczególności na:

- `TASK.MD`,
- `src/Controller/SalesDocumentController.php`,
- `src/Message/Command/*`,
- `src/MessageHandler/*`,
- `src/Entity/SalesDocument.php`,
- `src/Enum/*`,
- `src/Repository/SalesDocumentRepository.php`,
- `src/Notification/*`,
- konfiguracji Symfony Messenger i Doctrine,
- migracji bazy danych,
- dostarczonych testach funkcjonalnych.

Na tym etapie nie wykonano jeszcze zmian w kodzie produkcyjnym.

Runtime baseline i pełne wykonanie testów zostaną wykonane jako osobny krok przed implementacją.

---

# 3. Aktualna architektura

## 3.1. Główne komponenty

Aktualny przepływ jest prostym CQRS opartym o Symfony Messenger:

```text
HTTP
 ↓
SalesDocumentController
 ↓
command.bus
 ↓
Command
 ↓
MessageHandler
 ↓
Doctrine ORM / NotifierPort
```

Najważniejsze komponenty:

| Warstwa / rola | Komponent |
|---|---|
| HTTP | `SalesDocumentController` |
| Command | `CreateSalesDocument` |
| Command | `ApproveSalesDocument` |
| Handler | `CreateSalesDocumentHandler` |
| Handler | `ApproveSalesDocumentHandler` |
| Persistencja | `SalesDocumentRepository` |
| Encja | `SalesDocument` |
| Efekt uboczny | `NotifierPort` |
| Implementacja produkcyjna notyfikacji | `LogNotifier` |
| Implementacja testowa notyfikacji | `InMemoryNotifier` |

Messenger pracuje synchronicznie. Konfiguracja nie routuje commandów do transportu asynchronicznego.

Ma to istotne znaczenie dla Problem 1: wyjątek rzucony przez handler lub efekt uboczny wraca bezpośrednio do wywołującego HTTP.

---

## 3.2. Model danych `SalesDocument`

Encja zawiera obecnie pola:

```text
id
contractorId
createdBy
type
status
approvedBy
approvedAt
parentQuoteId
sellerSnapshot
```

Typ dokumentu:

```text
quote
order
```

Status dokumentu:

```text
draft
approved
```

Stan `rejected` nie istnieje jeszcze w enumie ani w aktualnej migracji.

---

# 4. Przepływ CREATE — stan obecny

## 4.1. Wejście HTTP

Endpoint:

```text
POST /sales-documents
```

przyjmuje dane:

```json
{
  "contractor_id": 77,
  "created_by": 5
}
```

Kontroler wykonuje podstawowe sprawdzenie obecności pól, a następnie wywołuje:

```php
$ids = $this->resolveDocumentOwnership($payload);
```

Po czym buduje command:

```php
new CreateSalesDocument(
    contractorId: $ids['contractorId'],
    createdBy: $ids['createdBy'],
)
```

## 4.2. Handler

`CreateSalesDocumentHandler` mapuje command bezpośrednio na encję:

```text
command.contractorId → SalesDocument.contractorId
command.createdBy    → SalesDocument.createdBy
```

Handler sam w sobie zachowuje poprawną semantykę danych.

## 4.3. Wniosek

Ścieżka command-handler jest poprawna względem znaczenia pól.

Defekt danych zgłoszony przez support powstaje wcześniej — podczas budowania commandu przez HTTP.

---

# 5. Przepływ APPROVE — stan obecny

## 5.1. Wejście HTTP

Endpoint:

```text
POST /sales-documents/{id}/approve
```

odczytuje:

```json
{
  "approved_by": 9
}
```

Następnie synchronicznie dispatchuje:

```text
ApproveSalesDocument(documentId, approvedBy)
```

## 5.2. Handler — część transakcyjna

`ApproveSalesDocumentHandler` uruchamia:

```php
$this->entityManager->wrapInTransaction(...)
```

Wewnątrz transakcji:

1. pobierany jest dokument,
2. sprawdzane jest jego istnienie,
3. sprawdzany jest status `draft`,
4. quote otrzymuje status `approved`,
5. zapisywane są `approvedBy`, `approvedAt` i snapshot,
6. jeżeli dokument jest `quote`, tworzony jest powiązany `order`,
7. order jest oznaczany jako `approved`,
8. wykonywany jest `flush`,
9. zwracane jest ID dokumentu wynikowego.

Po poprawnym zakończeniu closure `wrapInTransaction()` Doctrine wykonuje commit.

## 5.3. Handler — część po transakcji

Dopiero po zakończeniu `wrapInTransaction()` handler:

1. ponownie pobiera zatwierdzony dokument,
2. wysyła notyfikację do `createdBy`,
3. wysyła notyfikację do `contractorId`,
4. zwraca ID.

Notyfikacje nie znajdują się w transakcji bazodanowej.

To jest właściwe z punktu widzenia trwałości zatwierdzenia, ale wyjątek z notyfikatora nie jest obecnie izolowany.

---

# 6. RCA — Problem 1: HTTP 500 mimo poprawnego zatwierdzenia

## 6.1. Objaw

Klient otrzymuje błąd, ale dokument jest już zatwierdzony w bazie.

## 6.2. Bezpośrednia przyczyna

Po poprawnym commicie wykonywane są synchroniczne wywołania:

```php
$this->notifier->notify(...);
$this->notifier->notify(...);
```

`NotifierPort::notify()` może rzucić wyjątek.

Dostarczony `InMemoryNotifier` został celowo zaprojektowany tak, aby dało się zasymulować awarię konkretnego wywołania:

```text
failOnCallNumber = 1
```

Jeżeli pierwsza notyfikacja rzuci wyjątek:

```text
DB transaction → COMMIT
                  ↓
notification → exception
                  ↓
command.bus → exception
                  ↓
controller → HTTP 500
```

## 6.3. Dlaczego dane pozostają zapisane

Wyjątek powstaje już po zakończeniu `wrapInTransaction()`.

Nie istnieje aktywna transakcja, którą można byłoby wycofać.

Z punktu widzenia bazy operacja zakończyła się sukcesem.

Z punktu widzenia synchronicznego command busa handler nie zakończył się jednak poprawnie, ponieważ wyjątek został propagowany do caller'a.

## 6.4. Root cause

**Brak rozdzielenia semantyki sukcesu głównej operacji biznesowej od awarii efektu ubocznego wykonywanego po commicie.**

Problemem nie jest brak rollbacku. Rollback po commicie nie jest już możliwy i nie byłby właściwym rozwiązaniem.

## 6.5. Potwierdzenie w testach

Test:

```text
testApprovalDoesNotFailTheCallerWhenTheNotificationChannelFails
```

ustawia notifier, który rzuca wyjątek na pierwszej notyfikacji.

Asercja oczekuje, że caller nie dostanie wyjątku, a stan dokumentu pozostanie `approved`.

Test dokładnie odzwierciedla opisany problem.

## 6.6. Rekomendowany kierunek

Notyfikacja powinna być traktowana jako efekt uboczny typu best-effort względem zatwierdzenia dokumentu.

Minimalny kierunek zgodny z wymaganiami zadania:

- pozostawić zatwierdzenie w aktualnej transakcji,
- nie dodawać drugiej kolejki,
- izolować awarię notyfikacji,
- rejestrować błąd techniczny,
- nie zmieniać wyniku zatwierdzenia po poprawnym commicie,
- rozważyć niezależną próbę wysłania obu notyfikacji, aby awaria pierwszej nie blokowała drugiej.

Finalna forma zostanie określona w `04-solution-design.md`.

---

# 7. RCA — Problem 2: wszystkie błędy mapowane na HTTP 500

## 7.1. Stan obecny

`SalesDocumentController::approve()` zawiera:

```php
catch (\Throwable $e) {
    return new JsonResponse(['error' => $e->getMessage()], 500);
}
```

Każdy wyjątek jest więc klasyfikowany jako błąd serwera.

## 7.2. Aktualne wyjątki handlera

Handler wykorzystuje `RuntimeException` zarówno dla:

### Dokument nie istnieje

```text
Document {id} not found
```

### Niedozwolony stan

```text
Document cannot be approved in its current status
```

Obie sytuacje mają inny charakter biznesowy, ale aktualnie mają ten sam typ wyjątku i ten sam kod HTTP.

## 7.3. Skutki

Aktualna implementacja nie pozwala kontrolerowi wiarygodnie rozpoznać:

- `404 Not Found`,
- konfliktu stanu biznesowego,
- rzeczywistego `500 Internal Server Error`.

Dodatkowo API zwraca:

```php
$e->getMessage()
```

czyli publiczny kontrakt API zależy bezpośrednio od technicznego tekstu wyjątku.

## 7.4. Root cause

**Brak jawnej klasyfikacji błędów aplikacyjnych/biznesowych oraz zbyt szeroki `catch (Throwable)` w warstwie HTTP.**

## 7.5. Oczekiwane rozdzielenie

Minimalnie potrzebne są osobne semantyki dla:

```text
not found
invalid state / conflict
unexpected technical failure
```

Rekomendowany kontrakt HTTP:

| Sytuacja | HTTP |
|---|---:|
| Dokument nie istnieje | 404 |
| Operacja niedozwolona w aktualnym stanie | 409 |
| Niepoprawne wejście | 400 |
| Nieoczekiwany błąd techniczny | 500 |

`409 Conflict` jest preferowany dla błędu przejścia stanu, ponieważ zasób istnieje, ale jego aktualny stan koliduje z żądaną operacją.

## 7.6. Repozytorium omijane przez kontroler

Po wykonaniu commandu kontroler wykonuje bezpośredni SQL:

```sql
SELECT id, type, status, parent_quote_id
FROM sales_document
WHERE id = ?
```

Mimo że istnieje `SalesDocumentRepository`.

Konsekwencje:

- kontroler zna strukturę tabeli,
- warstwa HTTP jest sprzężona z SQL,
- dostęp do danych omija istniejącą abstrakcję,
- logika odczytu jest niepotrzebnie duplikowana.

Rekomendowany kierunek: pobranie dokumentu przez istniejące repozytorium i zbudowanie response z encji.

---

# 8. RCA — Problem 3: zamieniony kontrahent i twórca dokumentu

## 8.1. Zgłoszenie

Support zgłasza, że część dokumentów ma zamienione:

```text
contractor
createdBy
```

Problem nie występuje zawsze i nie jest wykrywany przez obecne testy.

## 8.2. Faktyczna przyczyna

W `SalesDocumentController::resolveDocumentOwnership()` znajduje się mapowanie:

```php
return [
    'contractorId' => (int) $payload['created_by'],
    'createdBy' => (int) $payload['contractor_id'],
];
```

Dla requestu:

```json
{
  "contractor_id": 77,
  "created_by": 5
}
```

powstaje command:

```text
contractorId = 5
createdBy    = 77
```

czyli dokładne odwrócenie semantyki danych.

## 8.3. Dlaczego problem występuje „nie za każdym razem”

Defekt nie jest losowy.

Istnieją co najmniej dwie ścieżki utworzenia dokumentu:

### Ścieżka A — bezpośrednio przez command

Testy handlerów wykonują:

```php
new CreateSalesDocument(contractorId: 77, createdBy: 5)
```

`CreateSalesDocumentHandler` zapisuje wartości poprawnie.

### Ścieżka B — przez HTTP

Kontroler odwraca wartości przed stworzeniem commandu.

Wynik:

```text
command path → poprawnie
HTTP path    → niepoprawnie
```

To tłumaczy pozorną nieregularność zgłaszaną przez support.

## 8.4. Dlaczego istniejące testy tego nie widzą

Test HTTP `testCreateAndApproveThroughHttp()` po utworzeniu dokumentu sprawdza jedynie:

- status HTTP create,
- ID dokumentu,
- później typ `order`,
- `parent_quote_id`.

Nie sprawdza wartości:

```text
contractorId
createdBy
```

Z kolei testy command-handler omijają błędną metodę kontrolera i podają poprawne wartości bezpośrednio.

W efekcie oba zestawy testów mogą być zielone mimo niepoprawnych danych w ścieżce HTTP.

## 8.5. Root cause

**Błąd mapowania DTO/request → command w warstwie HTTP oraz brak regresyjnej asercji semantyki pól ownership.**

## 8.6. Rekomendowana regresja

Po HTTP `POST /sales-documents` test powinien pobrać dokument z repozytorium i potwierdzić:

```text
contractorId = 77
createdBy    = 5
```

Taki test powinien failować na baseline i przechodzić po naprawie.

---

# 9. Analiza brakującej operacji `RejectSalesDocument`

## 9.1. Kontrakt wynikający z testu

Dostarczony test wymaga klas:

```text
App\Message\Command\RejectSalesDocument
App\MessageHandler\RejectSalesDocumentHandler
```

Command otrzymuje:

```text
documentId
rejectedBy
```

## 9.2. Dozwolone przejście

Test wymaga:

```text
draft → rejected
```

Po wykonaniu commandu dokument musi mieć:

```text
SalesDocumentStatus::Rejected
```

## 9.3. Niedozwolone przejście

Test zatwierdza dokument, a następnie próbuje go odrzucić.

Oczekiwany jest wyjątek kompatybilny z:

```text
RuntimeException
```

Czyli przejście:

```text
approved → rejected
```

musi zostać zablokowane.

## 9.4. Brakujące elementy

W aktualnym kodzie nie istnieją:

- `RejectSalesDocument`,
- `RejectSalesDocumentHandler`,
- `SalesDocumentStatus::Rejected`.

## 9.5. `rejectedBy` — obserwacja

Test przekazuje `rejectedBy`, ale aktualna encja nie posiada:

```text
rejectedBy
rejectedAt
```

Sam dostarczony test sprawdza wyłącznie status.

Są dwa możliwe podejścia:

### Minimalne

Użyć `rejectedBy` wyłącznie jako część kontraktu commandu, ale nie utrwalać go.

### Pełniejsze semantycznie

Dodać:

```text
rejectedBy
rejectedAt
```

do encji i migracji.

Drugie podejście lepiej uzasadnia obecność `rejectedBy` w commandzie, ale zwiększa zakres zmiany.

Decyzja powinna zostać jawnie podjęta w `04-solution-design.md`.

---

# 10. Analiza testów

## 10.1. `ApproveSalesDocumentTest`

### Happy path

Test poprawnie weryfikuje, że:

- zatwierdzenie quote tworzy osobny order,
- quote staje się `approved`,
- order ma typ `order`,
- order wskazuje `parentQuoteId`,
- wykonywane są dwie notyfikacje.

### Failure path notyfikacji

Drugi test celowo wymusza awarię pierwszej notyfikacji.

Jego kontrakt jest jednoznaczny:

```text
awaria kanału notyfikacji ≠ niepowodzenie zatwierdzenia
```

Asercji tego testu nie należy zmieniać.

---

## 10.2. `SalesDocumentControllerTest`

### Happy path HTTP

Test potwierdza podstawowy przepływ create + approve, ale nie kontroluje danych ownership.

To jest luka, która umożliwiła Problem 3.

### Missing document

Test:

```text
testApprovingMissingDocumentCurrentlyReturns500
```

świadomie dokumentuje błędny baseline.

Po naprawie powinien oczekiwać `404` i zostać odpowiednio przemianowany.

---

## 10.3. `RejectSalesDocumentHandlerTest`

Test stanowi kontrakt nowej funkcjonalności.

Wymaga:

- poprawnego odrzucenia draftu,
- blokady odrzucenia dokumentu approved.

Nie należy modyfikować jego założeń, jeśli nie ma ku temu jednoznacznego powodu.

---

# 11. Dodatkowe obserwacje jakościowe

Poniższe punkty nie są głównymi problemami zadania, ale warto je uwzględnić przy implementacji.

## 11.1. Walidacja JSON i `approved_by`

`approve()` zamienia brak `approved_by` na:

```text
0
```

przez:

```php
(int) ($payload['approved_by'] ?? 0)
```

Nie istnieje jawna walidacja, że użytkownik `0` jest niedozwolony.

Podobnie `create()` opiera się na `empty()`, co miesza sprawdzenie obecności z semantyką wartości.

Nie jest to główny zakres zadania, ale minimalne uporządkowanie wejścia może być uzasadnione, jeśli pozostanie lokalne.

## 11.2. Jeden timestamp operacji approve

Aktualnie osobno tworzony jest czas dla:

- zatwierdzenia quote,
- zatwierdzenia order,
- `snapshot_at`.

W ramach jednej operacji biznesowej bardziej deterministyczne byłoby użycie jednego punktu czasu lub jawnego clocka.

Nie jest to wymagane przez testy i nie powinno prowadzić do niepotrzebnej rozbudowy.

## 11.3. `sellerSnapshot`

Nazwa `sellerSnapshot` zawiera obecnie wyłącznie:

```text
contractor_id
snapshot_at
```

Brakuje informacji, czy `contractor` zawsze oznacza sprzedającego.

Bez dodatkowych wymagań biznesowych nie należy zmieniać tej semantyki w ramach zadania.

## 11.4. Deklarowana wersja PHP

`composer.json` deklaruje:

```text
php >= 8.2
```

Jednocześnie zablokowana wersja PHPUnit 13 wymaga nowszej wersji PHP niż 8.2 dla środowiska developerskiego.

Przed finalizacją warto zweryfikować rzeczywisty minimalny runtime przy `composer install`.

To nie uzasadnia automatycznie aktualizacji Symfony.

## 11.5. Aktualizacja Symfony

Zadanie dopuszcza aktualizację, ale nie wymaga jej.

Na podstawie obecnego audytu żaden z problemów nie wynika z Symfony 7.4.

Wstępna rekomendacja:

**pozostać przy Symfony 7.4**, aby ograniczyć zakres i ryzyko zmian.

Decyzję należy krótko uzasadnić w finalnym README.

---

# 12. Ocena odpowiedzialności komponentów

| Komponent | Ocena | Uwagi |
|---|---|---|
| `CreateSalesDocument` | OK | Prosty, jednoznaczny command |
| `CreateSalesDocumentHandler` | OK | Poprawne mapowanie command → encja |
| `ApproveSalesDocument` | OK | Prosty kontrakt commandu |
| `ApproveSalesDocumentHandler` | Wymaga zmiany | Semantyka notyfikacji i typy wyjątków |
| `SalesDocumentController::create` | Wymaga zmiany | Błędne mapowanie ownership |
| `SalesDocumentController::approve` | Wymaga zmiany | `catch Throwable`, raw error, direct SQL |
| `SalesDocumentRepository` | OK / do wykorzystania | Istnieje, ale kontroler go omija |
| `NotifierPort` | OK | Dobra granica do testowania failure mode |
| `InMemoryNotifier` | OK | Celowo umożliwia deterministyczny failure test |
| `SalesDocumentStatus` | Wymaga rozszerzenia | Brak `Rejected` |
| Testy approve | Dobre | Precyzyjnie ujawniają Problem 1 |
| Testy HTTP | Niepełne | Nie sprawdzają ownership |
| Test reject | Kontrakt do implementacji | Jasno definiuje podstawowe przejścia |

---

# 13. Mapa zależności zmian

## Problem 1

Dotknięte komponenty:

```text
ApproveSalesDocumentHandler
NotifierPort / Logger
ApproveSalesDocumentTest
```

## Problem 2

Dotknięte komponenty:

```text
ApproveSalesDocumentHandler
SalesDocumentController
SalesDocumentRepository
SalesDocumentControllerTest
nowe wyjątki aplikacyjne
```

## Problem 3

Dotknięte komponenty:

```text
SalesDocumentController::create
resolveDocumentOwnership
SalesDocumentControllerTest
```

## Reject

Dotknięte komponenty:

```text
RejectSalesDocument [NEW]
RejectSalesDocumentHandler [NEW]
SalesDocumentStatus
SalesDocument [opcjonalnie rejectedBy/rejectedAt]
migracja [zależnie od decyzji]
RejectSalesDocumentHandlerTest
```

---

# 14. Główne ryzyka implementacyjne

## 14.1. Zbyt szeroki refactoring

Największym ryzykiem zadania rekrutacyjnego jest rozwiązanie poprawnych problemów poprzez niepotrzebną przebudowę całej aplikacji.

Zadanie jawnie oczekuje CQRS, nie DDD.

Rekomendacja: lokalne, czytelne zmiany z małym diffem.

## 14.2. Ukrycie błędu notyfikacji bez obserwowalności

Samo `catch` bez logowania naprawiłoby test, ale utrudniłoby operacyjne wykrywanie awarii kanału notyfikacji.

Błąd powinien zostać odseparowany od wyniku commandu, ale pozostawać obserwowalny.

## 14.3. Łapanie wyjątków po tekście

Nie należy mapować HTTP na podstawie treści:

```text
"not found"
"cannot be approved"
```

To byłoby kruche i sprzęgałoby API z tekstem komunikatu.

Potrzebne są jawne typy wyjątków.

## 14.4. Niedostateczna regresja dla Problem 3

Samo poprawienie dwóch linii mapowania bez testu pozostawiłoby ryzyko ponownego wprowadzenia identycznego błędu.

Regresja HTTP jest obowiązkowa.

## 14.5. Nieuzasadnione rozszerzenie modelu Reject

Dodanie `rejectedBy`/`rejectedAt` jest sensowne semantycznie, ale musi być świadomą decyzją, a nie automatycznym rozszerzeniem scope.

---

# 15. Rekomendowany kierunek rozwiązania

Na podstawie audytu rekomendowany jest następujący kierunek:

1. zachować aktualną architekturę CQRS,
2. pozostawić Symfony 7.4,
3. poprawić mapowanie ownership w kontrolerze,
4. dodać regresyjny test HTTP dla `contractorId` / `createdBy`,
5. wprowadzić jawne wyjątki aplikacyjne dla `not found` i niedozwolonego stanu,
6. mapować te wyjątki w warstwie HTTP na odpowiednie statusy,
7. usunąć direct SQL z kontrolera i użyć `SalesDocumentRepository`,
8. odseparować awarię notyfikacji od wyniku zatwierdzenia oraz ją logować,
9. implementować `RejectSalesDocument` i handler zgodnie z dostarczonym testem,
10. rozszerzyć enum o `Rejected`,
11. świadomie zdecydować, czy utrwalamy `rejectedBy`/`rejectedAt`,
12. uruchomić pełny baseline i finalną regresję testów.

---

# 16. Priorytety

| Priorytet | Element | Powód |
|---|---|---|
| P0 | Problem 3 — odwrócone dane ownership | Błąd integralności danych |
| P0 | Problem 1 — false failure po commicie | API podaje stan sprzeczny z bazą |
| P0 | Problem 2 — błędna klasyfikacja HTTP | Błędny kontrakt API i leakage wyjątków |
| P1 | `RejectSalesDocument` | Wymagana nowa funkcjonalność |
| P1 | Testy regresyjne | Ochrona wszystkich napraw |
| P2 | Drobna walidacja / timestamp cleanup | Jakość, poza głównym problemem |

---

# 17. Wnioski audytu

Wszystkie trzy zgłoszone problemy mają konkretne, deterministyczne źródła w kodzie.

### Problem 1

Nieudana notyfikacja po poprawnym commicie propaguje wyjątek przez synchroniczny command bus, przez co klient otrzymuje failure mimo trwałego sukcesu operacji.

### Problem 2

Kontroler spłaszcza wszystkie błędy do `500`, ujawnia surowe komunikaty i dodatkowo omija repozytorium przez bezpośredni SQL.

### Problem 3

Ścieżka HTTP odwraca `contractor_id` i `created_by` podczas mapowania requestu na command. Bezpośrednia ścieżka commandowa działa poprawnie, dlatego problem nie występuje dla wszystkich dokumentów. Obecny test HTTP nie sprawdza tych pól, więc defekt pozostaje niewidoczny.

### Reject

Test jednoznacznie wymaga obsługi:

```text
draft → rejected
```

oraz blokady:

```text
approved → rejected
```

Implementacja może pozostać mała i zgodna z istniejącym stylem CQRS.

---

# 18. Kolejny krok

Następny dokument:

```text
docs/04-solution-design.md
```

powinien zamknąć konkretne decyzje:

- hierarchię wyjątków,
- mapowanie HTTP,
- sposób izolacji i logowania notyfikacji,
- dokładny kontrakt `RejectSalesDocument`,
- decyzję o `rejectedBy` / `rejectedAt`,
- listę zmian plik po pliku,
- granice scope przed rozpoczęciem implementacji.
