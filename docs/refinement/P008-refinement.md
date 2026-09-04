# Refinement P008 — Ownership Mapping Fix

## Status

**Gotowy do implementacji**

## Cel

Naprawić zdiagnozowaną zamianę ownership na ścieżce HTTP oraz uczynić błąd niewidoczny wcześniej dla testów niemożliwym do przeoczenia.

## Root cause

Aktualne:

```php
return [
    'contractorId' => (int) $payload['created_by'],
    'createdBy' => (int) $payload['contractor_id'],
];
```

Poprawne:

```php
return [
    'contractorId' => (int) $payload['contractor_id'],
    'createdBy' => (int) $payload['created_by'],
];
```

## Dlaczego „nie za każdym razem”

Błąd dotyczy tylko adaptera HTTP.

Direct CQRS command:

```php
new CreateSalesDocument(contractorId: 77, createdBy: 5)
```

już posiada poprawną semantykę.

Dlatego dokumenty tworzone inną ścieżką niż controller nie muszą mieć odwróconych wartości.

## Implementacja

Preferowana jest minimalna poprawka w `resolveDocumentOwnership()`.

Nie usuwamy helpera tylko dlatego, że ma dwie linie, jeśli jego nazwa pomaga odseparować semantykę mapowania wejścia.

## Test regresyjny

Po POST:

```json
{
  "contractor_id": 77,
  "created_by": 5
}
```

test musi pobrać dokument z repozytorium i asertywnie sprawdzić:

```text
getContractorId() === 77
getCreatedBy() === 5
```

Samo sprawdzenie statusu 201 jest niewystarczające.

## Snapshot

Po approve należy potwierdzić, że `sellerSnapshot.contractor_id` bazuje już na poprawionym `contractorId`.

Nie zmieniamy nazwy `sellerSnapshot` bez dodatkowego wymagania.

## Debugger

TASK sugeruje Xdebug jako możliwy trop. Finalny README powinien opisać rzeczywistą ścieżkę diagnozy: porównanie HTTP payload -> mapper -> command -> persisted entity i zestawienie z direct-command path.

Nie wolno twierdzić, że Xdebug został użyty, jeżeli nie został faktycznie użyty.

## Done

Minimalna poprawka + regresja ownership + prawdziwy opis diagnozy.
