# P006 — Application Error Handling — dowód weryfikacji

## Metadane

```text
ZADANIE=P006 Application Error Handling
DATA=2026-09-04
START_HEAD=8c9635419d12b86a32d0cea1668d2c9a35a08f02
END_HEAD=patrz commit `fix: implement application error contract`
```

## Najważniejsze zmiany

| Plik | Zmiana |
|---|---|
| `src/Exception/SalesDocumentNotFound.php` | nowy — semantyczny błąd „dokument nie istnieje" |
| `src/Exception/InvalidSalesDocumentState.php` | nowy — semantyczny błąd niedozwolonego przejścia stanu |
| `src/MessageHandler/ApproveSalesDocumentHandler.php` | zamiana dwóch `RuntimeException` na typy semantyczne |
| `src/MessageHandler/CreateSalesDocumentHandler.php` | jawna asercja identyfikatora po `flush()` |
| `src/Controller/SalesDocumentController.php` | mapowanie HTTP po typie wyjątku, usunięcie raw SQL, odczyt przez repozytorium, generyczne 500 |
| `tests/Functional/SalesDocumentControllerTest.php` | 404 zamiast udokumentowanego 500, nowe regresje 409/400/500 |
| `deptrac.yaml` | nowa warstwa `Exception` z jawnymi regułami |

Oba wyjątki rozszerzają `RuntimeException`, dzięki czemu pozostają kompatybilne z dostarczonymi testami oczekującymi tego typu bazowego.

## Kontrakt HTTP

```text
brak dokumentu                 -> 404  {"error":"Sales document not found"}
niedozwolony stan              -> 409  {"error":"Sales document cannot be approved in its current state"}
brakujące/niepoprawne wejście  -> 400  {"error":"Missing fields"}
nieoczekiwany błąd techniczny  -> 500  {"error":"Internal server error"}
```

Kontroler nie analizuje treści wyjątku. Rozpakowuje `HandlerFailedException` Messengera przez `getPrevious()` i mapuje wyłącznie po typie klasy.

Nieoczekiwany wyjątek jest logowany przez `LoggerInterface` z pełnym kontekstem, ale odpowiedź HTTP zawiera wyłącznie stały, generyczny komunikat.

## Usunięcie raw SQL

`EntityManagerInterface` został usunięty z kontrolera. Odpowiedź `approve` jest budowana z encji pobranej przez `SalesDocumentRepository`. Kontroler nie zna już struktury tabeli `sales_document`.

Sytuacja, w której komenda zakończyła się sukcesem, ale repozytorium nie potrafi odczytać wyniku, jest traktowana jako niespójność techniczna (500), a nie brak zasobu (404) — zgodnie z punktem 7 refinementu.

## Wykonane komendy

| Komenda | Exit code | Wynik |
|---|---:|---|
| `php bin/phpunit tests/Functional/SalesDocumentControllerTest.php` | 0 | `OK (6 tests, 17 assertions)` |
| `php bin/phpunit tests/Functional` | 2 | `Tests: 10, Assertions: 24, Errors: 2, Failures: 1` |
| `php-cs-fixer fix --dry-run` | 0 | `Found 0 of 21 files that can be fixed` |
| `deptrac analyse` | 0 | `Violations 0, Skipped violations 1` |
| `phpstan analyse` | 2 | `Found 9 errors` (z 17 w baseline) |

## Testy regresyjne P006

```text
testApprovingMissingDocumentReturns404                                          PASS
testApprovingAnAlreadyApprovedDocumentReturns409                                PASS
testInvalidCreatePayloadReturns400                                              PASS
testApproveWithoutApproverReturns400                                            PASS
testUnexpectedTechnicalFailureReturnsGeneric500WithoutLeakingTheException        PASS
testCreateAndApproveThroughHttp                                                 PASS (happy path bez zmian w asercjach)
```

Test generycznego 500 podmienia `MessageBusInterface` na atrapę rzucającą `LogicException` z rozpoznawalnym sekretem i sprawdza, że ani sekret, ani nazwa klasy wyjątku nie pojawiają się w odpowiedzi. Wykorzystuje ten sam mechanizm podmiany usług, którego używają dostarczone testy.

## Stan pozostałych błędów

PHPStan zmniejszył się z 17 do 9 błędów. Kontroler i `CreateSalesDocumentHandler` są czyste. Pozostałe należą do:

```text
MessageHandler/ApproveSalesDocumentHandler.php  5  -> P007
Entity/SalesDocument.php                        4  -> P009/P010
```

PHPUnit: pozostałe 2 errors i 1 failure to awaria notyfikacji (P007) oraz brak `RejectSalesDocument` (P009).

## Wynik

```text
P006=DONE_AND_VERIFIED
```

## Odchylenia

Brak. Zakres nie został rozszerzony: nie powstał globalny listener wyjątków, hierarchia DTO odpowiedzi ani zależność RFC 7807.
