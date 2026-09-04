# Refinement P010 — Test Coverage Expansion

## Status

**Gotowy, blokowany przez P006–P009**

## Cel

Domknąć testy na właściwych poziomach bez duplikowania całej aplikacji na każdym poziomie.

## Źródło prawdy

[`../05-test-plan.md`](../05-test-plan.md)

## Unit

Dodajemy tylko naturalne testy małych klas/reguł, jeśli takie powstaną.

Brak testów Unit może być poprawnym wynikiem, jeżeli cała nowa logika jest sensowniej testowana przez Kernel/DB.

## Integration

Priorytet:

- migration/schema,
- audit fields reject,
- repository persistence,
- transaction durability.

## Functional

Priorytet:

- missing 404,
- invalid state 409,
- ownership fields,
- notification failure,
- reject transitions.

## E2E

Rozszerzamy obecny smoke do biznesowych scenariuszy:

1. create -> approve,
2. missing approve -> 404,
3. invalid transition -> 409,
4. ownership correctness.

Jeżeli brak publicznego GET uniemożliwia odczyt ownership wyłącznie przez API, nie tworzymy nowego endpointu tylko dla testu. Wtedy ownership pozostaje functional/integration, a E2E dokumentuje N/A dla T016.

## Izolacja

Każdy test korzystający z DB musi działać na `postgres-test`, nie na DEV.

Test nie może zależeć od danych pozostawionych przez poprzedni test.

## Naming

Nazwy testów opisują zachowanie docelowe, nie historyczny bug.

Przykład:

```text
testApprovingMissingDocumentReturns404
```

zamiast:

```text
testApprovingMissingDocumentCurrentlyReturns500
```

## Done

Macierz T001–T016 ma status PASS albo uzasadnione N/A, a `make test` jest zielone.
