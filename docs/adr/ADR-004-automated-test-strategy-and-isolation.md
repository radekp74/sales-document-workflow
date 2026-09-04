# ADR-004 — Strategia testów automatycznych i izolacja środowisk

## Status

**Zaakceptowane**

## Data

2026-09-04

## Kontekst

Projekt posiada istniejące testy funkcjonalne, ale zgłoszone problemy pokazują, że obecne pokrycie nie wystarcza do wykrywania wszystkich regresji.

W szczególności:

- błąd mapowania `contractor_id` / `created_by` nie jest wykrywany przez aktualne testy HTTP,
- zachowanie po awarii notyfikacji wymaga testowania granicy pomiędzy trwałością danych a efektem ubocznym,
- nowa operacja `RejectSalesDocument` wymaga sprawdzenia poprawnych i niedozwolonych przejść stanów,
- testy nie mogą korzystać z danych ani bazy środowiska DEV.

Potrzebujemy jednej, jawnej strategii testowej, która określa poziomy testów, zakres odpowiedzialności każdego poziomu oraz sposób ich uruchamiania.

## Decyzja

Przyjmujemy czteropoziomową strategię testów:

```text
Unit
Integration
Functional
E2E
```

Każdy poziom ma inny cel i nie powinien niepotrzebnie duplikować odpowiedzialności pozostałych warstw.

## 1. Testy jednostkowe

### Cel

Testowanie izolowanej logiki bez uruchamiania Symfony Kernel i bez prawdziwej bazy danych.

### Przykładowy zakres

- reguły przejścia stanów dokumentu,
- małe klasy polityk i mapperów,
- klasyfikacja błędów,
- zachowanie komponentów bez infrastruktury.

### Zasada

Nie tworzymy sztucznych warstw lub klas wyłącznie po to, aby zwiększyć liczbę testów jednostkowych.

Jeżeli logika naturalnie znajduje się w handlerze i wymaga współpracy z repozytorium, może być lepiej pokryta testem integracyjnym.

## 2. Testy integracyjne

### Cel

Weryfikacja współpracy pomiędzy kodem aplikacji a rzeczywistymi komponentami infrastrukturalnymi.

### Środowisko

Testy integracyjne korzystają z:

```text
PHP 8.4
PostgreSQL 16
Doctrine ORM
Symfony Messenger
```

w izolowanym środowisku TEST.

### Przykładowy zakres

- zapis i odczyt encji przez Doctrine,
- działanie repozytorium,
- wykonanie migracji,
- transakcje,
- rollback,
- wykonanie handlerów z prawdziwą bazą,
- poprawne utworzenie powiązanego dokumentu.

## 3. Testy funkcjonalne

### Cel

Weryfikacja zachowania aplikacji na poziomie Symfony Kernel oraz publicznego API.

### Przykładowy zakres

- kontrolery,
- request/response,
- dispatch komend,
- mapowanie wyjątków na HTTP,
- regresje dla błędów zgłoszonych przez support,
- kompletne scenariusze create / approve / reject.

Istniejące testy w:

```text
tests/Functional
```

pozostają częścią tego poziomu.

## 4. Testy E2E

### Cel

Sprawdzenie działającej aplikacji jako czarnej skrzynki przez prawdziwy HTTP endpoint.

### Środowisko

E2E korzysta z uruchomionego stacku testowego:

```text
HTTP client
    ↓
kontener aplikacji
    ↓
Symfony
    ↓
Messenger
    ↓
Doctrine
    ↓
PostgreSQL TEST
```

Test E2E nie powinien bezpośrednio wywoływać handlerów ani korzystać z repozytorium w celu wykonania operacji biznesowej.

### Minimalny zestaw E2E

#### E2E-001 — create → approve

- utworzenie dokumentu przez HTTP,
- zatwierdzenie przez HTTP,
- potwierdzenie poprawnej odpowiedzi.

#### E2E-002 — missing document

- zatwierdzenie nieistniejącego dokumentu,
- oczekiwana odpowiedź 404.

#### E2E-003 — invalid state transition

- utworzenie dokumentu,
- zatwierdzenie,
- próba niedozwolonej operacji,
- kontrolowany błąd biznesowy.

#### E2E-004 — ownership correctness

Dla:

```json
{
  "contractor_id": 77,
  "created_by": 5
}
```

system zachowuje poprawną semantykę danych.

## Izolacja TEST od DEV

Testy nie mogą korzystać z developerskiej bazy danych.

Środowisko TEST posiada własny PostgreSQL:

```text
postgres-test
```

z osobną bazą:

```text
app_test
```

Dane testowe są efemeryczne.

Preferowany storage:

```text
tmpfs
```

## Kontrakt Makefile

Strategia testów jest dostępna przez:

```text
make test-unit
make test-integration
make test-functional
make test-e2e
make test
make verify
```

### `make test`

Uruchamia wszystkie poziomy w kolejności:

```text
unit
  ↓
integration
  ↓
functional
  ↓
e2e
```

Pierwsza porażka kończy target kodem różnym od zera.

### `make verify`

`make verify` jest lokalnym quality gate.

Minimalny zakres:

```text
composer validate
PHP syntax validation
Doctrine schema validation
unit tests
integration tests
functional tests
E2E tests
git working tree validation
```

Nie wymagamy w P005 dodatkowych narzędzi statycznej analizy, których projekt obecnie nie posiada.

Ich ewentualne dodanie wymaga osobnej decyzji. Taka decyzja została podjęta — zapisuje ją sekcja „Statyczna analiza i jakość kodu" na końcu tego dokumentu, która rozszerza `make verify` o PHP-CS-Fixer, PHPStan i Deptrac.

## Baseline

Przed naprawą kodu biznesowego P005 musi uruchomić obecny zestaw testów i zachować wynik jako baseline.

Celowo failing testy nie są naprawiane w P005.

## Konsekwencje

### Korzyści

- regresje są testowane na właściwym poziomie,
- baza testowa jest odseparowana od developmentu,
- możliwe jest odtworzenie problemów transakcyjnych na prawdziwym PostgreSQL,
- publiczne API może być testowane jako black box,
- `make verify` zapewnia jeden powtarzalny quality gate.

### Koszty

- więcej artefaktów testowych do utrzymania,
- E2E będą wolniejsze niż testy jednostkowe,
- pełne `make test` będzie wymagało Dockera.

## Kryteria akceptacji

ADR jest wdrożony, gdy:

1. istnieją osobne targety dla wszystkich czterech poziomów,
2. `make test` uruchamia cały zestaw,
3. `make verify` działa jako quality gate,
4. testy integracyjne i funkcjonalne używają TEST PostgreSQL,
5. E2E wykonują prawdziwe żądania HTTP,
6. TEST nie korzysta z danych DEV,
7. wynik komend jest propagowany przez Makefile.


## Statyczna analiza i jakość kodu

Quality gate obejmuje również:

- PHP-CS-Fixer w trybie check,
- PHPStan,
- Deptrac.

Narzędzia te nie zastępują testów. Są dodatkową warstwą wykrywania:

- problemów typów i przepływu danych,
- niespójności stylu,
- naruszeń zależności architektonicznych.

Publiczne targety:

```text
make cs-check
make cs-fix
make phpstan
make deptrac
```

`make cs-fix` jest operacją modyfikującą kod i nie jest automatycznie wykonywane przez `make verify`.
