# Dokumentacja — Sales Document Workflow

Dokumentacja prowadzi od treści zadania i analizy root cause, przez decyzje architektoniczne i backlog, aż do implementacji, testów i finalnego artefaktu.

## Dokumenty główne

| Nr | Dokument | Status | Cel |
|---:|---|---|---|
| 01 | [`01-problem-statement.md`](01-problem-statement.md) | Gotowy | Znormalizowany opis problemów, zakres i kryteria sukcesu |
| 02 | [`02-technical-analysis.md`](02-technical-analysis.md) | Gotowy | Audyt kodu, przepływy, RCA i ryzyka |
| 03 | [`03-development-environment.md`](03-development-environment.md) | Zweryfikowany | Środowisko DEV/TEST, Makefile, quality tooling i eksport |
| 04 | [`04-solution-design.md`](04-solution-design.md) | Zrealizowany | Docelowy projekt rozwiązania P006–P011 |
| 05 | [`05-test-plan.md`](05-test-plan.md) | Zrealizowany | Macierz regresji T001–T016 i finalny quality gate |
| 06 | [`06-implementation-summary.md`](06-implementation-summary.md) | Zamknięty | Wyniki etapów i finalne podsumowanie |

## ADR

| ADR | Status | Decyzja |
|---|---|---|
| [`ADR-001`](adr/ADR-001-containerized-development-and-test-environment.md) | Zaakceptowane | Konteneryzowane DEV/TEST |
| [`ADR-002`](adr/ADR-002-application-error-contract.md) | Zaakceptowane | Semantyczne błędy aplikacyjne i HTTP 404/409/500 |
| [`ADR-003`](adr/ADR-003-post-commit-notification-semantics.md) | Zaakceptowane | Best-effort notifications po trwałym commicie |
| [`ADR-004`](adr/ADR-004-automated-test-strategy-and-isolation.md) | Zaakceptowane | Unit/Integration/Functional/E2E i izolowany TEST |

## Backlog

Nadrzędny backlog:

- [`backlog/BACKLOG.md`](backlog/BACKLOG.md)

Karty:

- [`P005 — Infrastructure Bootstrap`](backlog/P005-infrastructure-bootstrap.md)
- [`P006 — Application Error Handling`](backlog/P006-application-error-handling.md)
- [`P007 — Notification Failure Semantics`](backlog/P007-notification-failure-semantics.md)
- [`P008 — Ownership Mapping Fix`](backlog/P008-ownership-mapping-fix.md)
- [`P009 — Reject Sales Document`](backlog/P009-reject-sales-document.md)
- [`P010 — Test Coverage Expansion`](backlog/P010-test-coverage-expansion.md)
- [`P011 — Final Documentation and Delivery`](backlog/P011-final-documentation-and-delivery.md)

## Refinement

Każde zadanie P005–P011 ma osobny refinement:

- [`P005`](refinement/P005-refinement.md)
- [`P006`](refinement/P006-refinement.md)
- [`P007`](refinement/P007-refinement.md)
- [`P008`](refinement/P008-refinement.md)
- [`P009`](refinement/P009-refinement.md)
- [`P010`](refinement/P010-refinement.md)
- [`P011`](refinement/P011-refinement.md)

## Evidence

- [`evidence/README.md`](evidence/README.md) — format dowodów runtime/testowych do zamykania etapów.
- [`evidence/P005-infrastructure-bootstrap.md`](evidence/P005-infrastructure-bootstrap.md)
- [`evidence/P006-application-error-handling.md`](evidence/P006-application-error-handling.md)
- [`evidence/P007-notification-failure-semantics.md`](evidence/P007-notification-failure-semantics.md)
- [`evidence/P008-ownership-mapping-fix.md`](evidence/P008-ownership-mapping-fix.md)
- [`evidence/P009-reject-sales-document.md`](evidence/P009-reject-sales-document.md)
- [`evidence/P010-test-coverage-expansion.md`](evidence/P010-test-coverage-expansion.md)
- [`evidence/P011-final-documentation-and-delivery.md`](evidence/P011-final-documentation-and-delivery.md)

## Aktualny stan prac

Wszystkie etapy zamknięte.

| Etap | Status |
|---|---|
| P005 Infrastructure Bootstrap | `DONE_AND_VERIFIED` |
| P006 Application Error Handling | `DONE_AND_VERIFIED` |
| P007 Notification Failure Semantics | `DONE_AND_VERIFIED` |
| P008 Ownership Mapping Fix | `DONE_AND_VERIFIED` |
| P009 Reject Sales Document | `DONE_AND_VERIFIED` |
| P010 Test Coverage Expansion | `DONE_AND_VERIFIED` |
| P011 Final Documentation and Delivery | `DONE_AND_VERIFIED` |

```text
CS_CHECK=PASS   PHPSTAN=PASS   DEPTRAC=PASS
UNIT=PASS   INTEGRATION=PASS   FUNCTIONAL=PASS   E2E=PASS
TEST=PASS   VERIFY=PASS
```

## Źródła

- [`../TASK.MD`](../TASK.MD) — oryginalna specyfikacja zadania
- [`../README.md`](../README.md) — onboarding i główne informacje repozytorium

## Zasady

- problem statement nie miesza diagnozy z rozwiązaniem,
- decyzje trwałe trafiają do ADR,
- zakres wykonawczy trafia do kart backlogu,
- szczegóły implementacyjne przed kodowaniem trafiają do refinementów,
- `DONE_AND_VERIFIED` wymaga rzeczywistego dowodu,
- dokumentacja nie deklaruje testów/komend jako PASS, dopóki nie zostały wykonane.
