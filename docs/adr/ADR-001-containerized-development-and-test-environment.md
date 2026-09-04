# ADR-001 — Konteneryzowane środowisko developerskie i testowe

## Status

**Zaakceptowane**

## Data

2026-09-04

## Kontekst

Projekt `sales-document-workflow` został przekazany jako niewielka aplikacja Symfony z istniejącym `compose.yaml`, który uruchamia wyłącznie PostgreSQL.

Na potrzeby dalszej pracy potrzebujemy powtarzalnego, izolowanego środowiska developerskiego i testowego, które:

- nie zależy od lokalnej wersji PHP zainstalowanej na macOS,
- używa wersji PHP zgodnej z rzeczywistymi wymaganiami `composer.lock`,
- używa tej samej głównej wersji PostgreSQL co projekt,
- umożliwia uruchamianie aplikacji, migracji i testów przez jednolity `Makefile`,
- oddziela środowisko developerskie od testowego,
- minimalizuje zależności instalowane bezpośrednio na komputerze dewelopera,
- pozostaje proporcjonalne do rozmiaru zadania.

Obecny projekt korzysta z PHP, Symfony 7.4, Doctrine ORM / Doctrine Migrations, PostgreSQL 16, Symfony Messenger w trybie synchronicznym i PHPUnit. Projekt nie wymaga obecnie Redisa, RabbitMQ, Kafki ani osobnego workera.

## Decyzja

Przyjmujemy architekturę, w której **runtime aplikacji oraz bazy danych działa w Dockerze**.

Na hoście wymagane są tylko:

- Docker Desktop,
- Docker Compose v2,
- `make`,
- Git.

PHP, Composer i PostgreSQL nie będą wymagane jako lokalne zależności systemowe.

### Docelowa struktura

```text
infrastructure/
├── compose/
│   ├── dev/
│   │   └── docker-compose.dev.yml
│   └── test/
│       └── docker-compose.test.yml
├── docker/
│   └── php/
│       ├── Dockerfile
│       ├── php.dev.ini
│       └── php.test.ini
└── env/
    ├── dev.env
    └── test.env
```

W głównym katalogu repozytorium powstanie `Makefile`, który stanie się oficjalnym interfejsem do środowiska lokalnego.

## Wersje

### PHP

Docelowo: `PHP 8.4.x`.

`composer.json` deklaruje PHP `>=8.2`, ale wersje zależności zapisane w `composer.lock`, w szczególności PHPUnit 13.x, wymagają nowszego środowiska. Dla powtarzalności przyjmujemy PHP 8.4 jako oficjalny runtime developerski i testowy.

### PostgreSQL

Docelowo: `PostgreSQL 16`.

Istniejący projekt używa obrazu `postgres:16-alpine` oraz konfiguracji Doctrine z `serverVersion=16`.

### Symfony

Pozostaje `Symfony 7.4.*`. Aktualizacja frameworka nie jest potrzebna do rozwiązania zadania i zwiększałaby zakres zmian.

### Composer

Composer będzie dostarczany wewnątrz obrazu PHP. Nie wymagamy lokalnej instalacji Composera.

## Architektura DEV

```text
┌──────────────────────────────────────────────┐
│               Docker Compose DEV             │
│                                              │
│  ┌──────────────────────┐                    │
│  │ php                  │                    │
│  │ PHP 8.4              │                    │
│  │ Composer             │                    │
│  │ Symfony application  │                    │
│  │ port 8000            │                    │
│  └──────────┬───────────┘                    │
│             │                                │
│             │ postgresql://postgres:5432     │
│             ▼                                │
│  ┌──────────────────────┐                    │
│  │ postgres             │                    │
│  │ PostgreSQL 16        │                    │
│  │ persistent volume    │                    │
│  └──────────────────────┘                    │
└──────────────────────────────────────────────┘
```

Aplikacja developerska będzie wystawiona przez prosty serwer HTTP uruchomiony w kontenerze PHP. Dla tego zadania nie wprowadzamy dodatkowego Nginx ani reverse proxy, ponieważ nie jest to wymagane przez zadanie i zwiększałoby liczbę komponentów bez wartości dla bieżącego celu.

## Architektura TEST

Środowisko testowe będzie odseparowane od DEV.

```text
┌──────────────────────────────────────────────┐
│              Docker Compose TEST             │
│                                              │
│  ┌──────────────────────┐                    │
│  │ php-test             │                    │
│  │ PHP 8.4              │                    │
│  │ PHPUnit              │                    │
│  └──────────┬───────────┘                    │
│             │                                │
│             ▼                                │
│  ┌──────────────────────┐                    │
│  │ postgres-test        │                    │
│  │ PostgreSQL 16        │                    │
│  │ tmpfs / ephemeral    │                    │
│  └──────────────────────┘                    │
└──────────────────────────────────────────────┘
```

Baza testowa nie powinna korzystać z trwałego wolumenu. Preferowane jest `tmpfs` dla katalogu danych PostgreSQL, dzięki czemu testy nie zależą od poprzednich uruchomień i nie ingerują w bazę developerską.

## Symfony Messenger

Aktualna konfiguracja command busa jest synchroniczna. Nie dodajemy Redisa, RabbitMQ, Kafki ani asynchronicznych workerów.

## Redis

Redis **nie jest częścią środowiska**. Nie ma obecnie technicznego wymagania, które uzasadniałoby jego uruchomienie.

## Dockerfile PHP

Powstanie jeden obraz PHP z co najmniej targetami `base`, `dev`, `test`.

Warstwa `base` będzie zawierała:

- PHP 8.4,
- Composer,
- rozszerzenia wymagane przez aplikację i Doctrine,
- narzędzia systemowe wymagane do `composer install`.

Minimalny zestaw rozszerzeń: `pdo`, `pdo_pgsql`, `intl`, `zip`, `opcache`, a także wbudowane/wymagane `ctype`, `iconv`.

## Makefile

Rootowy `Makefile` będzie oficjalnym API developerskim.

Planowane podstawowe targety:

```text
make help
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
make test
make test-unit
make test-functional
make test-shell
make test-down
make clean
make export-source
```

Wszystkie komendy PHP i Composer będą wykonywane w kontenerze.

Przykładowy kontrakt:

```make
DOCKER_BIN ?= /Applications/Docker.app/Contents/Resources/bin/docker

ifeq (,$(wildcard $(DOCKER_BIN)))
  DOCKER_BIN := docker
endif

DEV_COMPOSE := $(DOCKER_BIN) compose \
  -p sales-document-workflow-dev \
  --env-file infrastructure/env/dev.env \
  -f infrastructure/compose/dev/docker-compose.dev.yml

TEST_COMPOSE := $(DOCKER_BIN) compose \
  -p sales-document-workflow-test \
  --env-file infrastructure/env/test.env \
  -f infrastructure/compose/test/docker-compose.test.yml

PHP_DEV := $(DEV_COMPOSE) exec -T php
PHP_TEST := $(TEST_COMPOSE) run --rm php-test
```

## Pliki środowiskowe

`infrastructure/env/dev.env` i `infrastructure/env/test.env` będą zawierać wyłącznie wartości bezpieczne dla lokalnego developmentu i testów. Nie będą przechowywać sekretów produkcyjnych.

## Eksport źródeł

Oficjalnym mechanizmem przygotowania paczki źródłowej będzie:

```bash
make export-source
```

Eksport będzie tworzony z aktualnego `HEAD` przez `git archive`, a nie przez rekurencyjne pakowanie katalogu roboczego.

Wymagania:

- clean working tree,
- wyłącznie pliki śledzone przez Git,
- nazwa zawierająca UTC timestamp oraz short SHA,
- output do ignorowanego katalogu `exports/`,
- SHA-256 paczki wypisywany po eksporcie,
- pełny SHA commita wypisywany po eksporcie.

Takie podejście zapewnia identyfikowalność paczki i eliminuje przypadkowe dołączenie lokalnych lub wygenerowanych plików.

### Aktualizacja z 2026-09-04 — kontrakt eksportu zastąpiony

Powyższy jednotrybowy kontrakt został podczas realizacji P005 zastąpiony kontraktem dwutrybowym. Powód: pojedynczy target wymagający clean working tree uniemożliwiał przekazanie aktualnego stanu pracy przed commitem, co jest realną potrzebą w trakcie etapu.

Obowiązuje:

| Target | Wymaga clean tree | Źródło plików |
|---|---|---|
| `make export-source` | nie | `git ls-files -co --exclude-standard` (working tree) |
| `make export-source-committed` | tak | `git archive HEAD` |

Katalog docelowy to `$HOME/Downloads`, a nie `exports/` (`exports/` pozostaje w `.gitignore`). Oba tryby nadal wypisują SHA-256 oraz commit i dodatkowo weryfikują listę plików gotowego archiwum, odrzucając paczkę zawierającą `vendor/`, `var/`, `exports/`, `.git/`, `.idea/` lub `.DS_Store`.

Pozostałe decyzje ADR-001 pozostają bez zmian. Szczegóły: [`../refinement/P005-refinement.md`](../refinement/P005-refinement.md) sekcje 14 i 19–20 oraz [`../evidence/P005-infrastructure-bootstrap.md`](../evidence/P005-infrastructure-bootstrap.md).

## Konsekwencje

### Korzyści

- identyczna wersja PHP dla wszystkich deweloperów,
- brak zależności od lokalnego PHP i PostgreSQL,
- powtarzalne uruchamianie testów,
- oddzielenie DEV od TEST,
- prosty onboarding przez `make`,
- możliwość późniejszego uruchomienia CI na tych samych kontraktach.

### Koszty

- pierwsze uruchomienie wymaga zbudowania obrazu,
- Docker Desktop staje się wymaganiem developerskim,
- bind mounty mogą być wolniejsze niż natywne pliki na macOS,
- pojawia się dodatkowy kod infrastrukturalny do utrzymania.

## Odrzucone alternatywy

### PHP lokalnie + PostgreSQL w Dockerze

Odrzucone, ponieważ lokalna wersja PHP i rozszerzeń staje się dodatkową zmienną środowiskową.

### Pełny stack z Redisem

Odrzucony, ponieważ aplikacja obecnie go nie wymaga.

### PHP-FPM + Nginx

Odrzucone na obecnym etapie jako niepotrzebne dla zadania. Może być dodane później, jeśli pojawi się realna potrzeba.

### Jeden wspólny PostgreSQL dla DEV i TEST

Odrzucone, ponieważ testy powinny być izolowane od danych developerskich.

## Kryteria akceptacji decyzji

Decyzję uznajemy za wdrożoną, gdy:

1. `make build` buduje obraz PHP 8.4,
2. `make up` uruchamia aplikację i PostgreSQL DEV,
3. aplikacja odpowiada przez HTTP,
4. `make migrate` wykonuje migracje w DEV,
5. `make test` uruchamia testy wyłącznie w środowisku TEST,
6. TEST korzysta z osobnego PostgreSQL 16,
7. testowa baza jest efemeryczna,
8. lokalna instalacja PHP, PostgreSQL i Composera nie jest potrzebna,
9. Redis nie jest uruchamiany,
10. `make help` dokumentuje oficjalne komendy developerskie.

## Wynik wdrożenia

Wszystkie 10 kryteriów zostało potwierdzonych realnym uruchomieniem w P005 (2026-09-04). Dowód: [`../evidence/P005-infrastructure-bootstrap.md`](../evidence/P005-infrastructure-bootstrap.md).

Potwierdzony runtime: PHP 8.4.25, Composer 2.8.12, Symfony 7.4.16, PostgreSQL 16.14. Redis nie jest uruchamiany, transport Messengera pozostaje `sync://`.
