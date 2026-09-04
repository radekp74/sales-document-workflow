# Backlog projektu

## Cel

Ten dokument jest nadrzędnym źródłem prawdy dla zakresu, kolejności, zależności i statusu prac.

Szczegółowe wymagania znajdują się w kartach `docs/backlog/Pxxx-*.md`, a decyzje wykonawcze w odpowiadających im plikach `docs/refinement/Pxxx-refinement.md`.

## Statusy

```text
PLANNED
READY
IN_PROGRESS
BLOCKED
DONE
DONE_AND_VERIFIED
```

`BLOCKED` oznacza, że refinement jest gotowy, ale nie została jeszcze spełniona zależność wykonawcza.

## Backlog

| ID | Nazwa | Priorytet | Status | Refinement | Zależności |
|---|---|---:|---|---|---|
| P005 | Infrastructure Bootstrap | P0 | DONE_AND_VERIFIED | Gotowy | ADR-001, ADR-004 |
| P006 | Application Error Handling | P0 | DONE_AND_VERIFIED | Gotowy | P005 (spełniona), ADR-002 |
| P007 | Notification Failure Semantics | P0 | DONE_AND_VERIFIED | Gotowy | P005 (spełniona), ADR-003 |
| P008 | Ownership Mapping Fix | P0 | DONE_AND_VERIFIED | Gotowy | P005 (spełniona) |
| P009 | Reject Sales Document | P0 | DONE_AND_VERIFIED | Gotowy | P005 (spełniona); preferowane P006 |
| P010 | Test Coverage Expansion | P1 | DONE_AND_VERIFIED | Gotowy | P006–P009 (spełnione), ADR-004 |
| P011 | Final Documentation and Delivery | P1 | DONE_AND_VERIFIED | Gotowy | P005–P010 (spełnione) |

Wszystkie etapy zostały zamknięte 2026-09-04. Każdy status `DONE_AND_VERIFIED` ma odpowiadający dowód runtime w [`../evidence/`](../evidence/README.md).

Kolejność realizacji i commity podsumowuje [`../06-implementation-summary.md`](../06-implementation-summary.md).

## Krytyczna ścieżka

```text
P005
 ├─ P006
 ├─ P007
 ├─ P008
 └─ P009
      ↓
     P010
      ↓
     P011
```

P006–P009 mogą technicznie być realizowane niezależnie po P005. Dla prostego review rekomendujemy osobne, małe commity w kolejności:

```text
P006 -> P007 -> P008 -> P009
```

## Karty i refinementy

### P005 — Infrastructure Bootstrap

- [karta](P005-infrastructure-bootstrap.md)
- [refinement](../refinement/P005-refinement.md)

### P006 — Application Error Handling

- [karta](P006-application-error-handling.md)
- [refinement](../refinement/P006-refinement.md)
- [ADR-002](../adr/ADR-002-application-error-contract.md)

### P007 — Notification Failure Semantics

- [karta](P007-notification-failure-semantics.md)
- [refinement](../refinement/P007-refinement.md)
- [ADR-003](../adr/ADR-003-post-commit-notification-semantics.md)

### P008 — Ownership Mapping Fix

- [karta](P008-ownership-mapping-fix.md)
- [refinement](../refinement/P008-refinement.md)

### P009 — Reject Sales Document

- [karta](P009-reject-sales-document.md)
- [refinement](../refinement/P009-refinement.md)

### P010 — Test Coverage Expansion

- [karta](P010-test-coverage-expansion.md)
- [refinement](../refinement/P010-refinement.md)
- [test plan](../05-test-plan.md)

### P011 — Final Documentation and Delivery

- [karta](P011-final-documentation-and-delivery.md)
- [refinement](../refinement/P011-refinement.md)
- [implementation summary](../06-implementation-summary.md)

## Zasada zamykania

Status `DONE_AND_VERIFIED` wymaga dowodu runtime/testowego. Sam commit lub self-test paczki nie wystarcza.
