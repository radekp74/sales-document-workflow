# P011 — Final Documentation and Delivery

## Status

**DONE_AND_VERIFIED** — 2026-09-04

Dowód: [`../evidence/P011-final-documentation-and-delivery.md`](../evidence/P011-final-documentation-and-delivery.md)

## Priorytet

**P1**

## Cel

Przygotować repozytorium do wysłania jako kompletne rozwiązanie zadania rekrutacyjnego.

## Zależności

- P005–P010

## Scope

- finalny README,
- finalne RCA wszystkich problemów,
- decyzja o pozostaniu na Symfony 7.4 z uzasadnieniem,
- aktualizacja solution design/test plan do stanu rzeczywistego,
- `06-implementation-summary.md`,
- finalny backlog/statusy,
- evidence,
- pełny quality gate,
- clean committed export,
- SHA-256 i commit SHA.

## Acceptance Criteria

1. README odpowiada wymaganiom `TASK.MD`,
2. opis problemu ownership zawiera rzeczywistą ścieżkę diagnozy,
3. wyjaśniony jest false failure po commicie,
4. wyjaśnione jest mapowanie HTTP,
5. wyjaśniona jest implementacja reject,
6. uzasadnione jest pozostanie przy Symfony 7.4,
7. `make cs-check` zielone,
8. `make phpstan` zielone,
9. `make deptrac` zielone,
10. `make test` zielone,
11. `make verify` zielone,
12. working tree czysty,
13. `make export-source-committed` daje paczkę bez vendor/var/.git,
14. SHA-256 i final commit są zapisane.

## Definition of Done

Repozytorium jest gotowe do przekazania bez dodatkowych lokalnych instrukcji poza README.

## Refinement

[`../refinement/P011-refinement.md`](../refinement/P011-refinement.md)
