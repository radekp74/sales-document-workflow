# P010 — Test Coverage Expansion — dowód weryfikacji

## Metadane

```text
ZADANIE=P010 Test Coverage Expansion
DATA=2026-09-04
START_HEAD=7a93f72 (P009)
END_HEAD=patrz commit `test: expand sales document regression coverage`
```

## Cel

Domknąć macierz T001–T016 z [`../05-test-plan.md`](../05-test-plan.md) na właściwych poziomach, bez powielania całej aplikacji na każdym poziomie i bez optymalizacji pod procent pokrycia.

## Nowe poziomy testów

Przed P010 `make test-unit` i `make test-integration` zwracały `NO_TESTS_PRESENT`. Oba poziomy powstały dopiero tam, gdzie mają realną wartość.

### `tests/Unit` — 5 testów

| Test | Chroni |
|---|---|
| `SalesDocumentExceptionContractTest` | zgodność obu wyjątków aplikacyjnych z `RuntimeException` (wymóg dostarczonych testów) oraz ich rozdzielność, na której opiera się mapowanie 404 vs 409 |
| `SalesDocumentControllerBoundaryTest` | T010 — kontroler nie zależy od `EntityManagerInterface` i nie zawiera surowego SQL |

Nie powstały testy jednostkowe reguł przejść stanów: logika żyje naturalnie w handlerach i wymaga repozytorium, więc jest sensowniej pokryta funkcjonalnie. Nie tworzono klas wyłącznie po to, aby zwiększyć liczbę testów jednostkowych.

### `tests/Integration` — 4 testy

`SalesDocumentPersistenceTest` na realnym PostgreSQL TEST:

```text
testRejectionAuditColumnsRoundTripThroughPostgres      T013, T014 — kolumny z migracji + konwersja DateTimeImmutable
testApprovalPersistsQuoteAndLinkedOrderInOneTransaction trwałość approval i powiązanie quote -> order
testFailedApprovalRollsBackWithoutLeavingAPartialOrder  brak częściowego zapisu po nieudanej transakcji
testRepositoryReturnsNullForAMissingDocument            zachowanie repozytorium dla brakującego id
```

Poziom celowo nie powiela testów funkcjonalnych — sprawdza to, czego nie widać przez Kernel/HTTP.

## E2E — ze smoke testu do black-box API

`infrastructure/scripts/e2e-smoke.sh` został rozwinięty i przemianowany na `infrastructure/scripts/e2e-api.sh`:

```text
E2E_001_CREATE=PASS            201 + id
E2E_001_APPROVE=PASS           200, type=order, status=approved, parent_quote_id
E2E_002_MISSING_DOCUMENT=PASS  404 + komunikat kontraktowy
E2E_003_INVALID_STATE=PASS     409 + komunikat kontraktowy
E2E_004_OWNERSHIP=PASS         contractor_id=77, created_by=5
E2E_005_INVALID_PAYLOAD=PASS   400
E2E=PASS
```

Każda operacja biznesowa jest wykonywana wyłącznie przez HTTP, więc żądanie przechodzi przez `HTTP -> Symfony -> Messenger -> Doctrine -> PostgreSQL`. SQL występuje jedynie jako weryfikacja stanu końcowego w E2E-004, ponieważ publiczne API nie udostępnia odczytu dokumentu, a nie tworzymy endpointu wyłącznie na potrzeby testu.

Brak automatyzacji przeglądarki — API jest w całości JSON-owe.

## Macierz T001–T016

Wszystkie 16 pozycji ma pokrycie. Brak wpisów `N/A`. Pełne odwzorowanie test po teście znajduje się w [`../05-test-plan.md`](../05-test-plan.md).

T016 (ownership na poziomie E2E) został pokryty mimo braku publicznego GET — przez asercję stanu bazy po operacji wykonanej wyłącznie przez HTTP.

## Wykonane komendy

| Komenda | Exit code | Wynik |
|---|---:|---|
| `php bin/phpunit tests/Unit` | 0 | `OK (5 tests, 14 assertions)` |
| `php bin/phpunit tests/Integration` | 0 | `OK (4 tests, 15 assertions)` |
| `php bin/phpunit tests/Functional` | 0 | `OK (22 tests, 73 assertions)` |
| `sh infrastructure/scripts/e2e-api.sh` | 0 | `E2E=PASS` |
| `make test` | 0 | wszystkie cztery poziomy zielone |

Łącznie 31 testów PHPUnit i 5 scenariuszy E2E.

## Wynik

```text
P010=DONE_AND_VERIFIED
```

## Odchylenia

Brak. Nie dodano testów bez wartości regresyjnej, nie mierzono ani nie optymalizowano procentu pokrycia.
