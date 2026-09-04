# P007 — Notification Failure Semantics — dowód weryfikacji

## Metadane

```text
ZADANIE=P007 Notification Failure Semantics
DATA=2026-09-04
START_HEAD=cbdaea4 (P006)
END_HEAD=patrz commit `fix: preserve approval success after notification failures`
```

## Root cause

Po poprawnym `COMMIT` transakcji handler wykonywał dwie notyfikacje bez izolacji błędu. Wyjątek notyfikatora propagował się przez synchroniczny `command.bus` do kontrolera, który zwracał HTTP 500 — mimo że dokument był już trwale zatwierdzony.

Rollback nie był i nie jest tu rozwiązaniem: w momencie awarii nie ma już aktywnej transakcji.

## Zmiana

`src/MessageHandler/ApproveSalesDocumentHandler.php`:

1. Persystencja pozostaje w `wrapInTransaction()`, notyfikacje pozostają poza nią.
2. Każde wywołanie `NotifierPort::notify()` ma własny `try/catch` — wspólny blok blokowałby drugiego odbiorcę po awarii pierwszego.
3. Awaria jest logowana przez `LoggerInterface` z kontekstem: `documentId`, `userId`, klasa wyjątku, komunikat.
4. Handler zawsze zwraca identyfikator zatwierdzonego dokumentu po poprawnym commicie.
5. Jeden `DateTimeImmutable` opisuje całe zdarzenie approval (quote, order, `snapshot_at`) zamiast trzech osobnych.

Nie dodano transportu asynchronicznego, kolejki, outboxa, retry ani brokera.

## Wykonane komendy

| Komenda | Exit code | Wynik |
|---|---:|---|
| `php bin/phpunit tests/Functional/ApproveSalesDocumentTest.php` | 0 | `OK (5 tests, 17 assertions)` |
| `php bin/phpunit tests/Functional/SalesDocumentControllerTest.php` | 0 | `OK (7 tests, 22 assertions)` |
| `phpstan analyse` | 2 | `Found 4 errors` (z 9 po P006) |
| `php-cs-fixer fix --dry-run` | 0 | `Found 0 of 21 files that can be fixed` |

## Testy regresyjne

```text
testApprovingAQuoteSpawnsALinkedOrderAndNotifiesBothParties        PASS  (happy path, 2 notyfikacje)
testApprovalDoesNotFailTheCallerWhenTheNotificationChannelFails    PASS  (dostarczony test, asercje niezmienione)
testFailureOfTheFirstNotificationDoesNotBlockTheSecondRecipient    PASS  (nowy)
testFailureOfTheSecondNotificationKeepsTheApprovalSuccessful       PASS  (nowy)
testApprovalUsesASingleTimestampForTheWholeOperation               PASS  (nowy)
testApprovalStaysSuccessfulOverHttpWhenTheNotificationChannelFails PASS  (nowy, poziom HTTP)
```

Kluczowa asercja niezależności kanałów: przy `failOnCallNumber: 1` notyfikator rejestruje dokładnie jedną wysyłkę i jest nią powiadomienie kontrahenta (`userId = 77`). Dowodzi to, że po awarii pierwszego wywołania druga próba faktycznie nastąpiła.

Test HTTP wyłącza reboot kernela (`disableReboot()`), aby podmieniony notyfikator przetrwał oba żądania, i potwierdza jednocześnie odpowiedź `2xx` oraz trwały status `approved` w bazie.

Awarie notyfikacji są widoczne w wyjściu testów jako `[error] Approval notification failed`, co potwierdza obserwowalność.

## Wynik

```text
P007=DONE_AND_VERIFIED
```

## Odchylenia

Brak. Zgodne z ADR-003 i refinementem P007.
