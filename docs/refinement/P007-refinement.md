# Refinement P007 — Notification Failure Semantics

## Status

**Gotowy do implementacji**

## Cel

Doprowadzić semantykę wyniku `ApproveSalesDocument` do zgodności z trwałym stanem DB.

## Plik główny

`src/MessageHandler/ApproveSalesDocumentHandler.php`

## Decyzje

### 1. Granica transakcji

Nie przenosimy notyfikacji do transakcji.

Persistence kończy się przed side effects.

### 2. Failure policy

Każdy call `NotifierPort::notify()` ma własny `try/catch`.

To ważne: jeden wspólny `try/catch` wokół obu calli spowodowałby, że failure pierwszego blokuje drugą próbę.

### 3. Logowanie

Handler może otrzymać `Psr\Log\LoggerInterface`.

Log powinien zawierać co najmniej:

```text
document id
recipient/user id
exception class
```

Nie logujemy wrażliwych danych, których tu nie potrzebujemy.

### 4. Return value

Handler zawsze zwraca `$approvedId` po poprawnym commicie, niezależnie od notifier failure.

### 5. Timestamp

Przy okazji można użyć jednego `DateTimeImmutable` dla wartości tego samego zdarzenia approval, ale nie jest to warunek rozwiązania problemu.

## Testy

Obowiązkowo istniejący:

```text
testApprovalDoesNotFailTheCallerWhenTheNotificationChannelFails
```

bez zmiany assertions.

Dodatkowo preferowany test, że przy failure call #1 następuje call #2.

Jeśli obecny `InMemoryNotifier` nie pozwala tego zaobserwować, można go rozszerzyć testowo bez zmiany publicznego kontraktu produkcyjnego.

## Zakaz scope creep

Nie dodajemy:

- async transport,
- queue,
- retry,
- outbox,
- event bus.

## Done

P007 kończy się, gdy trwały approval i command result są poprawne przy awarii notyfikacji, a normalny happy path nadal daje dwie notyfikacje.
