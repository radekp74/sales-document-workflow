# Podsumowanie implementacji

## Status

**Zamknięte** — wszystkie etapy zrealizowane i zweryfikowane.

## Baseline

- commit bazowy zadania: `4aeeff0`
- dokumentacja baseline: P001–P004
- pierwszy zmierzony baseline runtime: P005

Stan zastany w kodzie aplikacji:

```text
PHPStan            17 błędów
PHPUnit            Tests: 6, Assertions: 12, Errors: 2, Failures: 1
tests/Unit         brak
tests/Integration  brak
```

## Wyniki etapów

| Etap | Status | Commit | Weryfikacja |
|---|---|---|---|
| P005 Infrastructure Bootstrap | DONE_AND_VERIFIED | `3fabf8c`, `8c96354` | [evidence](evidence/P005-infrastructure-bootstrap.md) |
| P006 Application Error Handling | DONE_AND_VERIFIED | `cbdaea4` | [evidence](evidence/P006-application-error-handling.md) |
| P007 Notification Failure Semantics | DONE_AND_VERIFIED | `2affaac` | [evidence](evidence/P007-notification-failure-semantics.md) |
| P008 Ownership Mapping Fix | DONE_AND_VERIFIED | `d63c27a` | [evidence](evidence/P008-ownership-mapping-fix.md) |
| P009 Reject Sales Document | DONE_AND_VERIFIED | `7a93f72` | [evidence](evidence/P009-reject-sales-document.md) |
| P010 Test Coverage Expansion | DONE_AND_VERIFIED | `8ff0292` | [evidence](evidence/P010-test-coverage-expansion.md) |
| P011 Final Documentation and Delivery | DONE_AND_VERIFIED | commit finalny | [evidence](evidence/P011-final-documentation-and-delivery.md) |

## Finalne RCA

### 1. Fałszywy failure po commicie

Notyfikacje wykonywane po zatwierdzeniu transakcji nie miały izolacji błędu. Wyjątek notyfikatora propagował się przez synchroniczny `command.bus` i zamieniał trwały sukces biznesowy w HTTP 500.

Rozwiązanie: każda notyfikacja ma własny `try/catch` z logowaniem, więc awaria jednego kanału nie zmienia wyniku komendy ani nie blokuje drugiego odbiorcy. Bez drugiej kolejki, outboxa i retry — zgodnie z [`ADR-003`](adr/ADR-003-post-commit-notification-semantics.md).

### 2. Błędna klasyfikacja HTTP i wyciek treści wyjątku

`catch (\Throwable)` spłaszczał wszystkie sytuacje do 500 i zwracał `$e->getMessage()`. Handler używał `RuntimeException` zarówno dla braku dokumentu, jak i dla niedozwolonego stanu, więc kontroler nie miał czego rozróżniać.

Rozwiązanie: dwa semantyczne typy wyjątków, mapowanie po klasie (404/409/400/500), generyczne ciało odpowiedzi dla 500 i logowanie pełnego wyjątku. Usunięto surowy SQL z kontrolera na rzecz `SalesDocumentRepository` — zgodnie z [`ADR-002`](adr/ADR-002-application-error-contract.md).

### 3. Zamienione dane ownership

`resolveDocumentOwnership()` odwracał `contractor_id` i `created_by`. Błąd dotyczył wyłącznie adaptera HTTP, więc dokumenty tworzone bezpośrednio komendą miały poprawne dane — stąd „nie za każdym razem". Istniejące testy HTTP nie asertowały tych pól.

Rozwiązanie: mapowanie 1:1 oraz regresja odczytująca realnie zapisane wartości z PostgreSQL. Skuteczność regresji potwierdzona empirycznie na obu wariantach mapowania.

### 4. Brakująca operacja reject

Kontrakt wyprowadzono z dostarczonego testu. Dodano komendę, handler, status `Rejected` oraz utrwalane pola audytowe `rejected_by` i `rejected_at`. Model przejść ograniczony do jednej reguły: odrzucić można wyłącznie dokument w stanie `Draft`.

## Zmiany w kodzie

### Nowe pliki

```text
src/Exception/SalesDocumentNotFound.php
src/Exception/InvalidSalesDocumentState.php
src/Message/Command/RejectSalesDocument.php
src/MessageHandler/RejectSalesDocumentHandler.php
migrations/Version20260904130000.php
tests/Unit/Exception/SalesDocumentExceptionContractTest.php
tests/Unit/Controller/SalesDocumentControllerBoundaryTest.php
tests/Integration/SalesDocumentPersistenceTest.php
tests/Functional/SalesDocumentOwnershipTest.php
tests/Functional/RejectSalesDocumentTest.php
```

### Zmodyfikowane pliki

```text
src/Controller/SalesDocumentController.php        kontrakt błędów, repozytorium zamiast SQL, poprawne ownership
src/MessageHandler/ApproveSalesDocumentHandler.php  semantyczne wyjątki, izolacja notyfikacji, jeden timestamp
src/MessageHandler/CreateSalesDocumentHandler.php   jawna asercja identyfikatora
src/Entity/SalesDocument.php                        pola rejectedBy/rejectedAt, typy tablicowe
src/Enum/SalesDocumentStatus.php                    case Rejected
tests/Functional/SalesDocumentControllerTest.php    404 zamiast 500, regresje 409/400/500
tests/Functional/ApproveSalesDocumentTest.php       nowe testy semantyki notyfikacji
```

### Pliki dostarczone z zadaniem, których nie zmodyfikowano

```text
tests/Functional/RejectSalesDocumentHandlerTest.php
```

Asercje obu happy-path (`testApprovingAQuoteSpawnsALinkedOrderAndNotifiesBothParties`, `testCreateAndApproveThroughHttp`) pozostały bez zmian i są zielone przed i po pracach.

## Final quality gate

```text
CS_CHECK=PASS
PHPSTAN=PASS
DEPTRAC=PASS
TEST_UNIT=PASS          5 testów, 14 asercji
TEST_INTEGRATION=PASS   4 testy, 15 asercji
TEST_FUNCTIONAL=PASS    22 testy, 73 asercje
TEST_E2E=PASS           5 scenariuszy
TEST=PASS
VERIFY=PASS
```

Baseline PHPStan został domknięty realnymi poprawkami kodu, bez `ignoreErrors` i bez obniżania poziomu:

```text
P005: 17 -> P006: 9 -> P007: 4 -> P009: 0
```

## Artefakt dostawy

Wartości finalnego artefaktu zapisane są w [`evidence/P011-final-documentation-and-delivery.md`](evidence/P011-final-documentation-and-delivery.md).

```text
make export-source-committed
```

Archiwum odpowiada dokładnie finalnemu `HEAD` i nie zawiera `vendor/`, `var/`, `exports/`, `.git/`, `.idea/` ani `.DS_Store`.
