# Refinement P006 — Application Error Handling

## Status

**Gotowy do implementacji**

## Cel

Usunąć niejednoznaczność błędów aplikacyjnych i SQL z kontrolera bez budowania nadmiarowej infrastruktury.

## Pliki wejściowe

- `src/Controller/SalesDocumentController.php`
- `src/MessageHandler/ApproveSalesDocumentHandler.php`
- `src/Repository/SalesDocumentRepository.php`
- `tests/Functional/SalesDocumentControllerTest.php`

## Decyzje

### 1. Typy błędów

Dodajemy małe wyjątki semantyczne, np.:

```text
SalesDocumentNotFound
InvalidSalesDocumentState
```

Nie mapujemy po `$e->getMessage()`.

### 2. Miejsce rzucania

Handler rzuca semantyczny wyjątek przy:

- braku dokumentu,
- niedozwolonym stanie.

### 3. Miejsce mapowania HTTP

Dla rozmiaru projektu dopuszczalne jest jawne mapowanie w kontrolerze.

Nie wprowadzamy globalnego listenera tylko po to, aby obsłużyć dwa przypadki.

### 4. HTTP

```text
not found      -> 404
invalid state  -> 409
unexpected     -> 500
```

### 5. Payload 500

Nie zwracamy raw `$e->getMessage()`.

Dopuszczalny stabilny payload:

```json
{"error":"Internal server error"}
```

### 6. Raw SQL

Usuwamy `EntityManagerInterface` z kontrolera, jeśli po zmianie nie jest już potrzebny.

Wstrzykujemy `SalesDocumentRepository`.

### 7. Missing result after successful command

Jeżeli handler zwróci ID, którego repozytorium nie może odczytać, traktujemy to jako niespójność techniczną, nie 404 użytkownika.

## Testy

- rename/update obecnego testu missing document -> 404,
- dodać invalid state -> 409,
- dodać test braku wycieku raw technical error tylko jeśli da się go wiarygodnie wywołać bez sztucznego hackowania kontenera,
- happy path unchanged.

## Zakaz scope creep

Nie dodajemy:

- DTO response hierarchy,
- exception bundle,
- RFC7807 dependency,
- API Platform.

## Done

P006 może być zamknięte dopiero po zielonych testach i zgodności z ADR-002.
