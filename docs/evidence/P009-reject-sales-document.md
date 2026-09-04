# P009 — Reject Sales Document — dowód weryfikacji

## Metadane

```text
ZADANIE=P009 Reject Sales Document
DATA=2026-09-04
START_HEAD=d63c27a (P008)
END_HEAD=patrz commit `feat: implement sales document rejection`
```

## Kontrakt wyprowadzony z dostarczonego testu

`tests/Functional/RejectSalesDocumentHandlerTest.php` wymagał klas, których nie było w kodzie:

```text
App\Message\Command\RejectSalesDocument   (documentId, rejectedBy)
App\MessageHandler\RejectSalesDocumentHandler
App\Enum\SalesDocumentStatus::Rejected
```

Plik dostarczony z zadaniem nie został zmodyfikowany — ani jedna asercja.

## Zaimplementowane elementy

| Plik | Zmiana |
|---|---|
| `src/Message/Command/RejectSalesDocument.php` | nowy command w stylu istniejących |
| `src/MessageHandler/RejectSalesDocumentHandler.php` | nowy handler na `command.bus`, zwraca `void` |
| `src/Enum/SalesDocumentStatus.php` | nowy case `Rejected = 'rejected'` |
| `src/Entity/SalesDocument.php` | pola `rejectedBy`, `rejectedAt` + akcesory, typy tablicowe `sellerSnapshot` |
| `migrations/Version20260904130000.php` | `rejected_by INT NULL`, `rejected_at TIMESTAMP NULL` |

Handler zwraca `void`, ponieważ żaden wywołujący nie potrzebuje wyniku. Nie wymyślano sztucznego identyfikatora ani obiektu odpowiedzi.

## Model przejść stanów

```text
Draft     -> Rejected   dozwolone
Approved  -> Rejected   niedozwolone
Rejected  -> Rejected   niedozwolone
Rejected  -> Approved   niedozwolone
```

Decyzja o `Rejected -> Rejected`: `TASK.MD` nie rozstrzyga tego przypadku. Przyjęto najprostszy spójny model — odrzucić można wyłącznie dokument w stanie `Draft`. Dzięki temu reguła jest jednym warunkiem, a nie listą wyjątków, i chroni oryginalne metadane odrzucenia przed nadpisaniem.

Symetrycznie `Approve` nadal wymaga stanu `Draft`, więc odrzucony dokument nie może zostać zatwierdzony.

## Kontrakt błędów

Handler używa typów wprowadzonych w P006:

```text
brak dokumentu     -> SalesDocumentNotFound
niedozwolony stan  -> InvalidSalesDocumentState
```

Oba rozszerzają `RuntimeException`, dlatego dostarczony test oczekujący `RuntimeException` przechodzi bez zmian.

## Zakres świadomie pominięty

Nie dodano: endpointu HTTP `reject`, `rejectionReason`, katalogu powodów, operacji cofnięcia odrzucenia, notyfikacji po odrzuceniu ani silnika workflow. Zadanie dostarcza kontrakt handlera, nie wymóg nowego endpointu.

## Wykonane komendy

| Komenda | Exit code | Wynik |
|---|---:|---|
| `php bin/console doctrine:migrations:migrate` | 0 | `Successfully migrated to version: DoctrineMigrations\Version20260904130000` |
| `php bin/console doctrine:schema:validate` | 0 | `The database schema is in sync with the mapping files` |
| `php bin/phpunit tests/Functional/RejectSalesDocumentHandlerTest.php` | 0 | `OK (2 tests, 2 assertions)` — dostarczony test |
| `php bin/phpunit tests/Functional` | 0 | `OK (22 tests, 73 assertions)` |
| `phpstan analyse` | 0 | `[OK] No errors` |
| `php-cs-fixer fix --dry-run` | 0 | `Found 0 of 26 files that can be fixed` |
| `deptrac analyse` | 0 | `Violations 0, Skipped violations 1` |

## Testy

Dostarczone (bez zmian):

```text
testRejectingADraftQuoteMarksItRejected                        PASS
testRejectingAnAlreadyApprovedDocumentIsRejectedByTheDomain    PASS
```

Nowy plik `tests/Functional/RejectSalesDocumentTest.php`:

```text
testRejectionPersistsTheAuditMetadata                           PASS
testRejectingAnApprovedDocumentIsAnInvalidStateTransition       PASS
testRejectingAnAlreadyRejectedDocumentIsAnInvalidStateTransition PASS
testRejectingAMissingDocumentReportsNotFound                    PASS
testApprovingARejectedDocumentIsAnInvalidStateTransition        PASS
```

Testy sprawdzają konkretny typ wyjątku, a nie tylko fakt jego wystąpienia, oraz odczytują stan z PostgreSQL po wyczyszczeniu identity map.

## Zamknięcie baseline PHPStan

Przy okazji domknięte zostały cztery pozostałe błędy PHPStan w encji: typy tablicowe `sellerSnapshot` oraz `property.unusedType` dla identyfikatora nadawanego przez Doctrine (jeden, opisany, inline `@phpstan-ignore` przy tej właściwości — bez `ignoreErrors` w konfiguracji i bez obniżania poziomu).

```text
baseline P005: 17 błędów
po P006:        9
po P007:        4
po P009:        0
```

## Wynik

```text
P009=DONE_AND_VERIFIED
```

## Odchylenia

Brak.
