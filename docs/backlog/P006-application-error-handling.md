# P006 — Application Error Handling

## Status

**DONE_AND_VERIFIED** — 2026-09-04

Dowód: [`../evidence/P006-application-error-handling.md`](../evidence/P006-application-error-handling.md)

## Priorytet

**P0**

## Problem

`SalesDocumentController::approve()` mapuje wszystkie wyjątki na HTTP 500, zwraca surowy komunikat wyjątku i po dispatchu wykonuje własny SQL.

## Cel

Wprowadzić stabilną klasyfikację błędów aplikacyjnych i poprawne mapowanie HTTP bez uzależnienia od tekstu wyjątku.

## Zależności

- P005
- ADR-002

## Scope

- semantyczny błąd „document not found”,
- semantyczny błąd „invalid state”,
- mapowanie 404,
- mapowanie 409,
- kontrolowane 500 bez raw technical message,
- usunięcie raw SQL z `SalesDocumentController`,
- odczyt wyniku przez `SalesDocumentRepository`,
- aktualizacja testu brakującego dokumentu,
- regresja invalid state.

## Out of scope

- globalny exception framework,
- Problem Details RFC jako osobny feature,
- reject,
- ownership,
- notification semantics.

## Acceptance Criteria

1. missing document daje HTTP 404,
2. invalid state daje HTTP 409,
3. unexpected technical failure daje HTTP 500,
4. nieoczekiwany 500 nie ujawnia surowego komunikatu technicznego,
5. kontroler nie wykonuje SQL przez Doctrine connection,
6. istniejący happy path HTTP pozostaje zielony,
7. handler nie rozróżnia błędów po stringach,
8. quality tooling dla zmienionych plików przechodzi lub ma jawny baseline.

## Definition of Done

- kod zaimplementowany,
- testy regresyjne zielone,
- ADR-002 zgodny z implementacją,
- refinement P006 spełniony,
- evidence zapisane,
- commit etapu wykonany.

## Refinement

[`../refinement/P006-refinement.md`](../refinement/P006-refinement.md)
