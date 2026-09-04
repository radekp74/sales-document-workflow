# P009 — Reject Sales Document

## Status

**DONE_AND_VERIFIED** — 2026-09-04

Dowód: [`../evidence/P009-reject-sales-document.md`](../evidence/P009-reject-sales-document.md)

## Priorytet

**P0**

## Problem

Test dostarczony z zadaniem definiuje `RejectSalesDocument` i `RejectSalesDocumentHandler`, których nie ma w kodzie.

## Cel

Dostarczyć operację reject w istniejącym stylu CQRS.

## Zależności

- P005
- P006 dla spójnego typu błędu invalid state, jeśli P006 zostanie wykonane wcześniej

## Scope

- command `RejectSalesDocument`,
- handler na `command.bus`,
- `SalesDocumentStatus::Rejected`,
- przejście Draft -> Rejected,
- zakaz Approved -> Rejected,
- zapis `rejectedBy`,
- zapis `rejectedAt`,
- migracja DB,
- testy dostarczone i rozszerzone.

## Out of scope

- endpoint HTTP reject,
- reason catalog,
- undo/reopen,
- notification po reject,
- workflow engine.

## Acceptance Criteria

1. dostarczony test kompiluje się,
2. draft może zostać odrzucony,
3. approved nie może zostać odrzucony,
4. rejectedBy jest zapisane,
5. rejectedAt jest zapisane,
6. migracja działa od baseline schema,
7. command jest obsługiwany przez `command.bus`,
8. brak DDD lub dodatkowego workflow frameworka.

## Definition of Done

- oba dostarczone testy Reject są zielone,
- dodatkowe assertions audit fields są zielone,
- schema validation przechodzi,
- evidence zapisane,
- commit etapu wykonany.

## Refinement

[`../refinement/P009-refinement.md`](../refinement/P009-refinement.md)
