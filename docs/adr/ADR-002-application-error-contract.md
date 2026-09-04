# ADR-002 — Kontrakt błędów aplikacyjnych i mapowanie HTTP

## Status

**Zaakceptowane**

## Data

2026-09-04

## Kontekst

Aktualny `SalesDocumentController::approve()` przechwytuje każde `Throwable` i zwraca HTTP 500 z surową treścią wyjątku.

W praktyce trzy różne klasy sytuacji są dziś traktowane identycznie:

- dokument nie istnieje,
- dokument istnieje, ale operacja jest niedozwolona w jego aktualnym stanie,
- wystąpił nieoczekiwany błąd techniczny.

Dodatkowo handler używa `RuntimeException` zarówno dla braku dokumentu, jak i dla błędu przejścia stanu. Warstwa HTTP nie ma więc stabilnej informacji, na podstawie której może dobrać poprawny kod odpowiedzi.

## Decyzja

Wprowadzamy jawne, minimalne typy błędów aplikacyjnych zamiast interpretować tekst komunikatu wyjątku.

Minimalny kontrakt:

```text
SalesDocumentNotFound
InvalidSalesDocumentState
```

Nazwy klas mogą zostać doprecyzowane podczas implementacji, ale semantyka pozostaje stała.

Mapowanie HTTP:

| Sytuacja | HTTP |
|---|---:|
| Niepoprawne wejście HTTP | 400 Bad Request |
| Dokument nie istnieje | 404 Not Found |
| Operacja niedozwolona w aktualnym stanie | 409 Conflict |
| Nieoczekiwany błąd techniczny | 500 Internal Server Error |

API nie może zwracać klientowi surowych komunikatów technicznych dla nieoczekiwanych wyjątków.

## Granice decyzji

ADR nie wprowadza:

- globalnego frameworka wyjątków,
- DDD,
- osobnej warstwy Application/Domain/Infrastructure,
- rozbudowanego formatu Problem Details,
- mapowania po treści `RuntimeException`.

Rozwiązanie ma pozostać proporcjonalne do małego projektu i zadania CQRS.

## Kontroler a repozytorium

Po poprawnym dispatchu kontroler powinien pobierać dokument przez istniejący `SalesDocumentRepository`, a nie wykonywać surowy SQL przez `EntityManagerInterface::getConnection()`.

## Konsekwencje

Pozytywne:

- stabilny kontrakt HTTP,
- brak zależności od tekstu wyjątku,
- brak wycieku informacji technicznych,
- kontroler przestaje znać strukturę tabeli,
- testy mogą jednoznacznie rozróżniać 404, 409 i 500.

Koszt:

- kilka małych klas wyjątków,
- aktualizacja testów kontrolera i handlerów.

## Powiązania

- [`../02-technical-analysis.md`](../02-technical-analysis.md)
- [`../backlog/P006-application-error-handling.md`](../backlog/P006-application-error-handling.md)
- [`../refinement/P006-refinement.md`](../refinement/P006-refinement.md)
