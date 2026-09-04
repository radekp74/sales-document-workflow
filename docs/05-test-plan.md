# Plan testów

## Status

**Gotowy**

## Cel

Plan określa minimalne regresje wymagane do zamknięcia zadania i sposób ich przypisania do poziomów z ADR-004.

## Baseline

Baseline został zmierzony w P005 na izolowanym stacku TEST (PHP 8.4.25, PostgreSQL 16.14). Pełny zapis: [`evidence/P005-infrastructure-bootstrap.md`](evidence/P005-infrastructure-bootstrap.md).

```text
Tests: 6, Assertions: 12, Errors: 2, Failures: 1     exit code 2
```

| Test | Baseline | Właściciel naprawy |
|---|---|---|
| `ApproveSalesDocumentTest::testApprovingAQuoteSpawnsALinkedOrderAndNotifiesBothParties` | PASS | musi pozostać zielony |
| `ApproveSalesDocumentTest::testApprovalDoesNotFailTheCallerWhenTheNotificationChannelFails` | ERROR | P007 |
| `SalesDocumentControllerTest::testCreateAndApproveThroughHttp` | PASS | musi pozostać zielony |
| `SalesDocumentControllerTest::testApprovingMissingDocumentCurrentlyReturns500` | PASS | P006 — test dokumentuje błędny baseline i zostanie zmieniony |
| `RejectSalesDocumentHandlerTest::testRejectingADraftQuoteMarksItRejected` | ERROR | P009 — brak klasy `RejectSalesDocument` |
| `RejectSalesDocumentHandlerTest::testRejectingAnAlreadyApprovedDocumentIsRejectedByTheDomain` | FAILURE | P009 — brak klasy `RejectSalesDocument` |

Poziomy Unit i Integration nie zawierają jeszcze testów (`NO_TESTS_PRESENT`) i powstają w P010.

Baseline statycznej analizy:

```text
CS_CHECK=PASS
DEPTRAC=PASS
PHPSTAN=FAIL   17 błędów w kodzie baseline, zakres P006–P010
```

## Macierz regresji

Stan po P010. Wszystkie pozycje mają pokrycie; brak wpisów `N/A`.

| ID | Scenariusz | Poziom | Test | Wynik |
|---|---|---|---|---|
| T001 | Create przez HTTP | Functional/E2E | `SalesDocumentControllerTest::testCreateAndApproveThroughHttp`, `E2E-001` | PASS |
| T002 | Create zachowuje `contractor_id` | Functional | `SalesDocumentOwnershipTest::testHttpCreatePersistsOwnershipFieldsWithoutSwappingThem` | PASS |
| T003 | Create zachowuje `created_by` | Functional | jw. | PASS |
| T004 | Approve quote tworzy powiązany order | Functional | `ApproveSalesDocumentTest::testApprovingAQuoteSpawnsALinkedOrderAndNotifiesBothParties` | PASS |
| T005 | Awaria pierwszej notyfikacji | Functional | `ApproveSalesDocumentTest::testApprovalDoesNotFailTheCallerWhenTheNotificationChannelFails` | PASS |
| T006 | Awaria pierwszej nie blokuje drugiej | Functional | `ApproveSalesDocumentTest::testFailureOfTheFirstNotificationDoesNotBlockTheSecondRecipient` | PASS |
| T007 | Approve nieistniejącego id | Functional/E2E | `SalesDocumentControllerTest::testApprovingMissingDocumentReturns404`, `E2E-002` | PASS |
| T008 | Approve w niedozwolonym stanie | Functional/E2E | `SalesDocumentControllerTest::testApprovingAnAlreadyApprovedDocumentReturns409`, `E2E-003` | PASS |
| T009 | Nieoczekiwany błąd techniczny | Functional | `SalesDocumentControllerTest::testUnexpectedTechnicalFailureReturnsGeneric500WithoutLeakingTheException` | PASS |
| T010 | Controller nie wykonuje raw SQL | Unit | `SalesDocumentControllerBoundaryTest` | PASS |
| T011 | Reject draft | Functional | `RejectSalesDocumentHandlerTest::testRejectingADraftQuoteMarksItRejected` | PASS |
| T012 | Reject approved | Functional | `RejectSalesDocumentHandlerTest::testRejectingAnAlreadyApprovedDocumentIsRejectedByTheDomain` | PASS |
| T013 | Reject zapisuje `rejectedBy` | Integration | `SalesDocumentPersistenceTest::testRejectionAuditColumnsRoundTripThroughPostgres` | PASS |
| T014 | Reject zapisuje `rejectedAt` | Integration | jw. | PASS |
| T015 | Full E2E create -> approve | E2E | `E2E-001` | PASS |
| T016 | Full E2E ownership | E2E | `E2E-004` | PASS |

### Regresje uzupełniające ponad macierz

| Scenariusz | Poziom | Test |
|---|---|---|
| Awaria drugiej notyfikacji nie psuje approval | Functional | `ApproveSalesDocumentTest::testFailureOfTheSecondNotificationKeepsTheApprovalSuccessful` |
| Jeden timestamp dla całej operacji approval | Functional | `ApproveSalesDocumentTest::testApprovalUsesASingleTimestampForTheWholeOperation` |
| Approval po awarii notyfikacji nadal 2xx przez HTTP | Functional | `SalesDocumentControllerTest::testApprovalStaysSuccessfulOverHttpWhenTheNotificationChannelFails` |
| Niepoprawne wejście HTTP -> 400 | Functional/E2E | `SalesDocumentControllerTest::testInvalidCreatePayloadReturns400`, `E2E-005` |
| Ownership przetrwa approve (snapshot i order) | Functional | `SalesDocumentOwnershipTest::testApprovalPropagatesTheCorrectedOwnershipToSnapshotAndOrder` |
| Reject dokumentu już odrzuconego | Functional | `RejectSalesDocumentTest::testRejectingAnAlreadyRejectedDocumentIsAnInvalidStateTransition` |
| Approve dokumentu odrzuconego | Functional | `RejectSalesDocumentTest::testApprovingARejectedDocumentIsAnInvalidStateTransition` |
| Reject nieistniejącego dokumentu | Functional | `RejectSalesDocumentTest::testRejectingAMissingDocumentReportsNotFound` |
| Nieudane approve nie zostawia częściowego zamówienia | Integration | `SalesDocumentPersistenceTest::testFailedApprovalRollsBackWithoutLeavingAPartialOrder` |
| Kontrakt typów wyjątków aplikacyjnych | Unit | `SalesDocumentExceptionContractTest` |

## Testy jednostkowe

Dodajemy tylko tam, gdzie pojawi się naturalna, izolowalna logika. Nie tworzymy sztucznych klas wyłącznie dla liczby testów.

Zrealizowany zakres (`tests/Unit`, bez Kernela i bazy):

- kontrakt typów wyjątków aplikacyjnych — zgodność z `RuntimeException` wymagana przez dostarczone testy oraz rozdzielność obu typów, na której opiera się mapowanie HTTP,
- granica kontrolera — brak zależności od `EntityManagerInterface` i brak surowego SQL.

Nie powstały testy jednostkowe dla reguł przejść stanów: logika żyje naturalnie w handlerach i wymaga repozytorium, więc jest sensowniej pokryta na poziomie funkcjonalnym.

## Testy integracyjne

Korzystają z realnego PostgreSQL TEST (`tests/Integration`) i obejmują:

- round-trip kolumn `rejected_by` / `rejected_at` dodanych migracją, wraz z konwersją na `DateTimeImmutable`,
- trwałość approval oraz powiązanie quote → order,
- brak częściowego zapisu po nieudanej transakcji,
- zachowanie repozytorium dla nieistniejącego identyfikatora.

Poziom celowo nie powiela testów funkcjonalnych — sprawdza to, czego nie widać przez Kernel/HTTP.

## Testy funkcjonalne

Główna warstwa regresyjna dla tego zadania.

Testy powinny korzystać z realnego Symfony Kernel i synchronicznego `command.bus`.

## E2E

Skrypt `infrastructure/scripts/e2e-api.sh` uruchamiany przez `make test-e2e`:

```text
E2E-001  create -> approve                 201, następnie 200 z type=order i parent_quote_id
E2E-002  approve nieistniejącego           404
E2E-003  approve w niedozwolonym stanie    409
E2E-004  poprawna semantyka ownership      contractor_id=77, created_by=5
E2E-005  niepoprawne wejście               400
```

Każda operacja biznesowa jest wykonywana wyłącznie przez HTTP na stacku TEST, więc żądanie przechodzi przez `HTTP -> Symfony -> Messenger -> Doctrine -> PostgreSQL`. Zapytanie SQL służy jedynie do weryfikacji stanu końcowego w E2E-004, ponieważ publiczne API nie udostępnia odczytu dokumentu, a nie tworzymy endpointu wyłącznie na potrzeby testu.

Nie stosujemy automatyzacji przeglądarki — API jest w całości JSON-owe.

## Quality gate

Finalnie:

```bash
make cs-check
make phpstan
make deptrac
make test
make verify
```

`make cs-fix` nie jest częścią niemodyfikującego quality gate.

## Zasady zmian istniejących testów

Nie zmieniamy istniejących poprawnych happy-path assertions.

Wyjątek wymagany przez zadanie:

```text
testApprovingMissingDocumentCurrentlyReturns500
```

musi zostać zmieniony tak, aby oczekiwał poprawnego zachowania 404 oraz otrzymał nazwę opisującą stan docelowy.
