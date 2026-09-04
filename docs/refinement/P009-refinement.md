# Refinement P009 — Reject Sales Document

## Status

**Gotowy do implementacji**

## Cel

Wyprowadzić kontrakt brakującej operacji z dostarczonego testu i zaimplementować ją w tym samym CQRS style.

## Wymagany command

```text
App\Message\Command\RejectSalesDocument
```

Pola:

```text
documentId: int
rejectedBy: int
```

## Wymagany handler

```text
App\MessageHandler\RejectSalesDocumentHandler
```

Atrybut:

```text
#[AsMessageHandler(bus: 'command.bus')]
```

## Status

Do enumu dodajemy:

```text
Rejected
```

Wartość DB powinna być spójna ze stylem obecnego enumu.

## Reguły przejścia

Minimalnie:

```text
Draft -> Rejected      allowed
Approved -> Rejected   forbidden
Rejected -> Rejected   forbidden
```

Nie projektujemy kompletnej maszyny stanów.

## Audit fields

Ponieważ command przenosi `rejectedBy`, nie ignorujemy tej informacji.

Dodajemy do encji:

```text
rejectedBy: ?int
rejectedAt: ?DateTimeImmutable
```

oraz migrację nullable.

Nie dodajemy `rejectionReason`, bo test/specyfikacja go nie wymaga.

## Błąd invalid state

Jeśli P006 jest już wykonane, używamy tego samego typu semantycznego błędu invalid state.

Dostarczony test oczekuje `RuntimeException`; własny wyjątek może rozszerzać `RuntimeException`, zachowując kompatybilność.

## Return value

Dostarczony test nie wymaga wyniku. Preferowany prosty kontrakt:

```text
void
```

Nie wymyślamy ID/status response, jeśli caller go nie potrzebuje.

## Transakcja

Jedna encja i pojedynczy flush nie wymagają dodatkowej złożonej infrastruktury. Można zachować spójny styl z istniejącymi handlerami.

## Testy

- dostarczony draft -> rejected,
- dostarczony approved -> exception,
- rejectedBy,
- rejectedAt,
- optional rejected -> reject again invalid.

## Done

Dostarczony test pozostaje semantycznie niezmieniony i jest zielony; schema/quality checks przechodzą.
