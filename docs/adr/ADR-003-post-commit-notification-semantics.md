# ADR-003 — Semantyka notyfikacji po trwałym zatwierdzeniu dokumentu

## Status

**Zaakceptowane**

## Data

2026-09-04

## Kontekst

`ApproveSalesDocumentHandler` zapisuje zmianę stanu dokumentu w transakcji, a następnie wykonuje dwie notyfikacje.

Aktualny przepływ:

```text
transaction
  -> approve quote
  -> optional create order
  -> commit
after commit
  -> notify creator
  -> notify contractor
```

Jeżeli notifier rzuci wyjątek po commicie, wyjątek propaguje się przez synchroniczny `command.bus`. Klient otrzymuje informację o niepowodzeniu, mimo że dane zostały już trwale zapisane.

To jest źródło zgłoszenia „approval wygląda na nieudany, ale w bazie jest zatwierdzony”.

## Decyzja

Trwały wynik komendy `ApproveSalesDocument` jest nadrzędny wobec best-effort notyfikacji wykonywanych po commicie.

Po poprawnym commicie:

- błąd wysłania notyfikacji nie może zmienić wyniku komendy na failure,
- każda notyfikacja jest obsługiwana niezależnie,
- błąd jednej notyfikacji nie blokuje próby wysłania drugiej,
- błąd powinien zostać zalogowany z kontekstem wystarczającym do diagnostyki,
- handler nadal zwraca identyfikator zatwierdzonego dokumentu/orderu.

## Celowo nie wprowadzamy

- drugiej szyny Messenger,
- kolejki asynchronicznej,
- outbox pattern,
- retry infrastructure,
- dodatkowego brokera.

Specyfikacja zadania wskazuje, że problem ma być rozwiązany bez budowania drugiej kolejki.

## Atomiczność

Operacje wymagające atomiczności pozostają wewnątrz `wrapInTransaction()`:

- zmiana statusu quote,
- zapis `approvedBy`,
- zapis `approvedAt`,
- seller snapshot,
- utworzenie linked order dla quote.

Notyfikacje pozostają poza transakcją.

## Konsekwencje

Pozytywne:

- odpowiedź komendy odpowiada rzeczywistemu trwałemu stanowi,
- awaria zewnętrznego kanału nie daje fałszywego 500,
- oba kanały odbiorców mają niezależną próbę wysyłki.

Ograniczenie:

- bez outbox/retry notyfikacja może zostać utracona. To świadomy kompromis zgodny z zakresem zadania.

## Powiązania

- [`../02-technical-analysis.md`](../02-technical-analysis.md)
- [`../backlog/P007-notification-failure-semantics.md`](../backlog/P007-notification-failure-semantics.md)
- [`../refinement/P007-refinement.md`](../refinement/P007-refinement.md)
