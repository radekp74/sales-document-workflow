# P005 — Infrastructure Bootstrap

## Status

**DONE_AND_VERIFIED** — 2026-09-04

Dowód: [`../evidence/P005-infrastructure-bootstrap.md`](../evidence/P005-infrastructure-bootstrap.md)

## Priorytet

**P0**

## Cel

Zbudować kompletne, powtarzalne i izolowane środowisko developerskie oraz testowe przed rozpoczęciem zmian w kodzie biznesowym.

## Źródła decyzji

- [`../adr/ADR-001-containerized-development-and-test-environment.md`](../adr/ADR-001-containerized-development-and-test-environment.md)
- [`../adr/ADR-004-automated-test-strategy-and-isolation.md`](../adr/ADR-004-automated-test-strategy-and-isolation.md)
- [`../03-development-environment.md`](../03-development-environment.md)

## Scope

P005 obejmuje:

- `infrastructure/compose`,
- `infrastructure/docker`,
- `infrastructure/env`,
- rootowy `Makefile`,
- PHP 8.4,
- Composer 2,
- PostgreSQL 16 DEV,
- PostgreSQL 16 TEST,
- osobny runtime testowy,
- healthchecki,
- migracje,
- wszystkie targety testowe,
- `make verify`,
- `make export-source`,
- `make export-source-committed`,
- aktualizację README,
- baseline istniejących testów.

## Out of scope

P005 nie zmienia:

- handlerów biznesowych,
- kontrolerów,
- encji poza zmianami wymaganymi wyłącznie do bootstrapu infrastruktury,
- semantyki approve,
- mapowania ownership,
- obsługi błędów HTTP,
- implementacji reject.

## Publiczny kontrakt Makefile

P005 musi udostępnić:

```text
make help
make setup
make build
make up
make down
make restart
make ps
make logs
make shell
make composer-install
make migrate
make db-reset
make cs-check
make cs-fix
make phpstan
make deptrac
make test-unit
make test-integration
make test-functional
make test-e2e
make test
make verify
make test-shell
make test-down
make clean
make export-source
make export-source-committed
```

## Acceptance Criteria

1. pełny runtime aplikacji działa bez lokalnego PHP,
2. Composer działa w Dockerze,
3. DEV używa PostgreSQL 16,
4. TEST używa osobnego PostgreSQL 16,
5. TEST nie korzysta z trwałych danych DEV,
6. `make setup` działa od czystego środowiska,
7. `make up` uruchamia zdrowy stack DEV,
8. `make down` zachowuje dane DEV,
9. `make migrate` działa,
10. `make test-*` działa zgodnie z ADR-004,
11. `make test` propaguje failure,
12. `make verify` jest pełnym lokalnym quality gate,
13. `make export-source` eksportuje bezpieczny snapshot aktualnego working tree,
14. `make export-source-committed` wymaga clean working tree i eksportuje wyłącznie `HEAD`,
15. oba tryby wypisują SHA-256 oraz commit bazowy/finalny,
16. eksport nie zawiera `vendor/`, `var/`, `exports/`, `.git/`, `.idea/` ani `.DS_Store`,
17. README dokumentuje onboarding,
18. istniejący kod biznesowy pozostaje bez zmian,
19. baseline PHPUnit jest zapisany przed P006,
20. `make cs-check` działa,
21. `make cs-fix` działa,
22. `make phpstan` działa,
23. `make deptrac` działa,
24. `make verify` zawiera wszystkie trzy niemodyfikujące quality checks.

## Definition of Done

P005 osiąga `DONE_AND_VERIFIED`, gdy:

- wszystkie acceptance criteria mają dowód,
- komendy bootstrapu zostały uruchomione na realnym Docker Desktop,
- wynik baseline testów jest udokumentowany,
- Git working tree jest czysty po commicie,
- `make export-source` tworzy poprawny working-tree snapshot,
- `make export-source-committed` tworzy reprodukowalny finalny artefakt po commicie.


## Dodatkowe kryterium eksportu

`make export-source` musi gwarantować brak `vendor/` i `var/` w archiwum oraz odrzucić eksport, jeśli zabroniona ścieżka pojawi się w ZIP-ie.


## Potwierdzenie acceptance criteria

Wszystkie 24 kryteria zostały potwierdzone realnym uruchomieniem komend, nie inspekcją kodu. Pełny zapis: [`../evidence/P005-infrastructure-bootstrap.md`](../evidence/P005-infrastructure-bootstrap.md).

| Kryteria | Dowód |
|---|---|
| 1–2 | `make setup` — PHP 8.4.25 i Composer 2.8.12 wyłącznie w kontenerze |
| 3–5 | `make runtime-info` (PostgreSQL 16.14 DEV) oraz osobny projekt Compose TEST z bazą `app_test` na `tmpfs` |
| 6–9 | `make setup`, `make up`, `make ps`, `make down`, `make migrate` |
| 10–11 | `make test-unit`/`test-integration` → `NO_TESTS_PRESENT`, `make test-functional` i `make test` zwracają kod 2 |
| 12 | `make verify` — `composer validate`, `PHP_SYNTAX`, `doctrine:schema:validate`, `cs-check`, `phpstan`, `deptrac`, testy |
| 13–16 | `make export-source` (`EXPORT_DIRTY_ENTRIES=42`), `make export-source-committed` odmawia pracy na brudnym drzewie, kontrola listy plików ZIP |
| 17 | sekcja „Uruchomienie lokalne" w [`../../README.md`](../../README.md) |
| 18 | zmiany w `src/` ograniczone do trzech poprawek stylistycznych bez wpływu na zachowanie |
| 19 | baseline PHPUnit i PHPStan zapisany w evidence |
| 20–24 | `make cs-check` (0), `make cs-fix` (0), `make phpstan` (uruchamia się, baseline 17 błędów), `make deptrac` (0), wszystkie trzy w `make verify` |

## Zakres zmian w `src/` wykonanych w P005

Zgodnie z sekcją „Out of scope" logika biznesowa nie została zmieniona. Wykonano wyłącznie poprawki stylistyczne wymuszone przez `make cs-check`:

```text
src/Kernel.php                          + declare(strict_types=1)
src/Notification/InMemoryNotifier.php   $this->calls++ -> ++$this->calls
```

Obie zmiany są neutralne dla zachowania aplikacji. Problemy z `TASK.MD` pozostają nienaruszone i są przedmiotem P006–P009.
