# P010 — Test Coverage Expansion

## Status

**DONE_AND_VERIFIED** — 2026-09-04

Dowód: [`../evidence/P010-test-coverage-expansion.md`](../evidence/P010-test-coverage-expansion.md)

## Priorytet

**P1**

## Cel

Domknąć regresje tak, aby wszystkie zdiagnozowane problemy były wykrywane automatycznie na właściwym poziomie testów.

## Zależności

- P006
- P007
- P008
- P009
- ADR-004
- `05-test-plan.md`

## Scope

- unit tylko dla naturalnej logiki,
- integration dla Doctrine/transakcji/audytu,
- functional dla API i command handlers,
- E2E dla krytycznych publicznych scenariuszy,
- deterministyczny reset test DB,
- brak zależności od DEV DB.

## Acceptance Criteria

1. T001–T016 z `05-test-plan.md` mają pokrycie lub jawne uzasadnienie N/A,
2. wszystkie dostarczone happy paths pozostają zielone,
3. ownership ma regresję,
4. 404 i 409 mają regresje,
5. notification failure ma regresję,
6. reject ma regresje,
7. E2E działa przez realny HTTP,
8. `make test` propaguje failure,
9. `make test` kończy się 0 na finalnym kodzie.

## Definition of Done

- macierz testów uzupełniona,
- pełne testy zielone,
- brak flaky zależności od kolejności,
- evidence zapisane,
- commit etapu wykonany.

## Refinement

[`../refinement/P010-refinement.md`](../refinement/P010-refinement.md)
