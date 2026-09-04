# P007 — Notification Failure Semantics

## Status

**DONE_AND_VERIFIED** — 2026-09-04

Dowód: [`../evidence/P007-notification-failure-semantics.md`](../evidence/P007-notification-failure-semantics.md)

## Priorytet

**P0**

## Problem

Approval commit jest trwały, ale wyjątek notyfikacji po commicie propaguje się do caller'a i daje fałszywy failure.

## Cel

Zapewnić, że failure best-effort notification nie zmienia wyniku poprawnie zatwierdzonego dokumentu.

## Zależności

- P005
- ADR-003

## Scope

- pozostawienie persistence w transakcji,
- notyfikacje poza transakcją,
- niezależna obsługa błędu każdej notyfikacji,
- logowanie failure,
- utrzymanie return value handlera,
- test awarii notifiera.

## Out of scope

- async Messenger,
- outbox,
- retry queue,
- broker,
- zmiana kontraktu command bus.

## Acceptance Criteria

1. dostarczony test notification failure przechodzi bez zmiany assertions,
2. quote/order pozostaje trwale zapisany po failure notyfikacji,
3. błąd pierwszej notyfikacji nie blokuje próby drugiej,
4. wyjątek notifiera nie opuszcza handlera po poprawnym commicie,
5. failure jest logowany,
6. normalny happy path nadal wysyła dwie notyfikacje,
7. nie pojawia się druga kolejka ani transport async.

## Definition of Done

- implementacja zgodna z ADR-003,
- regresje zielone,
- brak poszerzenia zakresu,
- evidence zapisane,
- commit etapu wykonany.

## Refinement

[`../refinement/P007-refinement.md`](../refinement/P007-refinement.md)
