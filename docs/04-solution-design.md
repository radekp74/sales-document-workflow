# Projekt rozwiązania

## Status

**Zrealizowany** — 2026-09-04

Wszystkie elementy projektu zostały wdrożone i zweryfikowane. Wyniki: [`06-implementation-summary.md`](06-implementation-summary.md).

## Cel

Dokument definiuje docelowe rozwiązanie problemów opisanych w `TASK.MD` i potwierdzonych w analizie technicznej.

Nie zastępuje kart backlogu ani refinementów. Jest wspólnym obrazem architektury rozwiązania.

## Zasady nadrzędne

1. Zachowujemy CQRS: `Command -> command.bus -> Handler`.
2. Nie wprowadzamy DDD ani dodatkowych warstw bez potrzeby.
3. Nie aktualizujemy Symfony tylko dlatego, że istnieją nowsze wersje.
4. Nie dokładamy asynchronicznej kolejki dla notyfikacji.
5. Zmiany mają być możliwie małe, czytelne i pokryte regresjami.
6. Istniejące poprawne happy-path assertions pozostają bez zmian.

## P006 — błędy aplikacyjne i HTTP

Wprowadzamy jawne typy błędów dla:

```text
document not found
invalid document state
```

Handler rzuca semantyczny wyjątek. Kontroler mapuje go na HTTP zgodnie z ADR-002.

Kontroler nie interpretuje tekstu wyjątku.

Po dispatchu dokument wynikowy jest pobierany przez `SalesDocumentRepository`.

## P007 — notyfikacje po commicie

Transakcja kończy się przed wysłaniem notyfikacji.

Po commicie każda notyfikacja jest wykonana best-effort:

```text
notify creator   -> catch/log failure
notify contractor -> catch/log failure
```

Failure side-effectu nie zmienia trwałego sukcesu approval.

Nie dodajemy nowej kolejki ani outboxa.

## P008 — ownership mapping

Błąd istnieje wyłącznie w ścieżce HTTP:

```text
contractor_id -> createdBy
created_by    -> contractorId
```

Naprawa przywraca mapowanie 1:1:

```text
contractor_id -> contractorId
created_by    -> createdBy
```

Dodajemy regresję, która odczytuje zapisany dokument i sprawdza obie wartości.

## P009 — reject

Dodajemy:

```text
RejectSalesDocument
RejectSalesDocumentHandler
SalesDocumentStatus::Rejected
```

Minimalna reguła wynikająca z dostarczonego testu:

```text
Draft -> Rejected   allowed
Approved -> Rejected forbidden
```

`rejectedBy` jest częścią kontraktu komendy, dlatego zapis aktora odrzucenia powinien być jawny. Dla spójności audytowej rekomendujemy również `rejectedAt`.

Zrealizowano zgodnie z rekomendacją. Doprecyzowano też przypadek nierozstrzygnięty na etapie projektu: `Rejected -> Rejected` jest niedozwolone, ponieważ odrzucić można wyłącznie dokument w stanie `Draft`. Uzasadnienie w [`backlog/P009-reject-sales-document.md`](backlog/P009-reject-sales-document.md) i w evidence.

To wymaga migracji pól:

```text
rejected_by nullable
rejected_at nullable
```

Nie dodajemy endpointu HTTP reject, ponieważ zadanie dostarcza kontrakt handlera, nie wymóg nowego endpointu.

## P010 — testy regresyjne

Docelowy zestaw testów ma pokryć cztery zgłoszone obszary:

- trwałość approval mimo awarii notyfikacji,
- poprawne 404/409 i brak raw exception,
- poprawne ownership,
- reject i niedozwolone przejścia.

Do tego utrzymujemy istniejące happy paths.

E2E ma być testem realnego HTTP, a nie bezpośrednim wywołaniem handlera.

Zrealizowano w `infrastructure/scripts/e2e-api.sh` (pięć scenariuszy). Odchylenie od pierwotnego założenia: ownership w E2E jest weryfikowany zapytaniem SQL do bazy TEST, ponieważ publiczne API nie udostępnia odczytu dokumentu, a nie tworzymy endpointu wyłącznie na potrzeby testu. Operacja biznesowa nadal wykonywana jest wyłącznie przez HTTP.

## P011 — finalizacja

Przed dostarczeniem:

- pełny `make verify`,
- zielony PHPUnit,
- zielony PHPStan,
- zielony PHP-CS-Fixer check,
- zielony Deptrac,
- README z naturą wszystkich problemów i diagnozą problemu ownership,
- `06-implementation-summary.md` z listą zmian i wynikami,
- bezpieczny eksport źródeł.

## Kolejność implementacji

```text
P005 Infrastructure
      |
      +--> P006 Error handling
      +--> P007 Notification semantics
      +--> P008 Ownership mapping
      +--> P009 Reject
                |
                v
           P010 Tests
                |
                v
           P011 Delivery
```

P006–P009 są logicznie niezależne po zakończeniu P005, ale dla prostszego review rekomendowana kolejność commitów to P006 -> P007 -> P008 -> P009.

## Powiązane ADR

- [`adr/ADR-001-containerized-development-and-test-environment.md`](adr/ADR-001-containerized-development-and-test-environment.md)
- [`adr/ADR-002-application-error-contract.md`](adr/ADR-002-application-error-contract.md)
- [`adr/ADR-003-post-commit-notification-semantics.md`](adr/ADR-003-post-commit-notification-semantics.md)
- [`adr/ADR-004-automated-test-strategy-and-isolation.md`](adr/ADR-004-automated-test-strategy-and-isolation.md)
