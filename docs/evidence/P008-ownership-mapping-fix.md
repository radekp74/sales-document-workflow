# P008 — Ownership Mapping Fix — dowód weryfikacji

## Metadane

```text
ZADANIE=P008 Ownership Mapping Fix
DATA=2026-09-04
START_HEAD=2affaac (P007)
END_HEAD=patrz commit `fix: correct sales document ownership mapping`
```

## Root cause

`SalesDocumentController::resolveDocumentOwnership()` odwracał znaczenie pól:

```php
'contractorId' => (int) $payload['created_by'],
'createdBy' => (int) $payload['contractor_id'],
```

Dla żądania `{"contractor_id": 77, "created_by": 5}` powstawała komenda `contractorId = 5`, `createdBy = 77`.

## Dlaczego „nie za każdym razem"

Defekt dotyczył wyłącznie adaptera HTTP. Bezpośrednia ścieżka CQRS:

```php
new CreateSalesDocument(contractorId: 77, createdBy: 5)
```

zapisywała wartości poprawnie, bo omijała błędną metodę kontrolera. Dokumenty powstające inną drogą niż kontroler nie miały odwróconych danych — stąd pozorna nieregularność w zgłoszeniu supportu.

## Ścieżka diagnozy

Diagnoza została wykonana przez audyt przepływu danych, nie przez debugger. **Xdebug nie jest zainstalowany w obrazie i nie został użyty.**

Kolejność kroków:

1. porównanie payloadu HTTP z polami komendy `CreateSalesDocument`,
2. porównanie komendy z mapowaniem w `CreateSalesDocumentHandler` — handler okazał się poprawny,
3. zawężenie różnicy do jedynego miejsca transformacji, czyli `resolveDocumentOwnership()`,
4. zestawienie obu ścieżek zapisu (HTTP oraz bezpośrednia komenda), co wyjaśniło „nie za każdym razem",
5. potwierdzenie hipotezy testem odczytującym realnie zapisane wartości z PostgreSQL.

## Zmiana

Przywrócone mapowanie 1:1:

```php
'contractorId' => (int) $payload['contractor_id'],
'createdBy' => (int) $payload['created_by'],
```

Helper `resolveDocumentOwnership()` został zachowany — jego nazwa nadal oddziela semantykę mapowania wejścia HTTP na komendę. Nie powstała osobna warstwa mapperów dla dwóch pól.

> Uwaga o przebiegu prac: sama korekta dwóch linii trafiła do repozytorium razem z przepisaniem kontrolera w commicie P006, ponieważ metoda była częścią tego samego pliku i tej samej zmiany. Merytoryczną zawartością P008 jest regresja, która czyni tę klasę błędu niemożliwą do przeoczenia, wraz z empirycznym dowodem jej skuteczności poniżej.

## Dowód skuteczności regresji

Test został uruchomiony przeciwko obu wariantom mapowania.

### Z odwróconym mapowaniem (stan baseline)

```text
1) contractor_id must land in contractorId
   Failed asserting that 5 is identical to 77.
2) Failed asserting that 5 is identical to 77.

Tests: 3, Assertions: 10, Failures: 2
```

### Z poprawionym mapowaniem

```text
OK (3 tests, 13 assertions)
```

Test `testDirectCommandPathKeepsOwnershipCorrect` pozostaje zielony w obu wariantach — co jest dokładnie tym, co powodowało niewidoczność defektu w dotychczasowych testach.

Po dowodzie plik kontrolera został przywrócony do stanu z `HEAD` (`git diff` pusty).

## Testy regresyjne

Nowy plik `tests/Functional/SalesDocumentOwnershipTest.php`:

```text
testHttpCreatePersistsOwnershipFieldsWithoutSwappingThem            PASS
testDirectCommandPathKeepsOwnershipCorrect                          PASS
testApprovalPropagatesTheCorrectedOwnershipToSnapshotAndOrder       PASS
```

Testy czyszczą identity map Doctrine przed asercją, więc sprawdzają wartości odczytane z PostgreSQL, a nie obiekt pozostawiony w pamięci przez żądanie HTTP. Sam kod odpowiedzi HTTP nie jest uznawany za wystarczający dowód.

Trzeci test potwierdza dodatkowo, że `sellerSnapshot.contractor_id` oraz `contractorId` powiązanego zamówienia bazują już na poprawionej wartości.

## Wykonane komendy

| Komenda | Exit code | Wynik |
|---|---:|---|
| `php bin/phpunit tests/Functional/SalesDocumentOwnershipTest.php` (mapowanie odwrócone) | 2 | `Tests: 3, Failures: 2` — regresja wykrywa defekt |
| `php bin/phpunit tests/Functional/SalesDocumentOwnershipTest.php` (mapowanie poprawne) | 0 | `OK (3 tests, 13 assertions)` |
| `git diff src/Controller/SalesDocumentController.php` | 0 | brak zmian po eksperymencie |

## Wynik

```text
P008=DONE_AND_VERIFIED
```

## Odchylenia

Nazwa `sellerSnapshot` nie została zmieniona, model ownership nie został przebudowany, nie dodano migracji danych ani nowych endpointów — zgodnie z sekcją „Out of scope" karty P008.
