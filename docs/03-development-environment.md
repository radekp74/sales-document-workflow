# Instalacja i uruchamianie środowiska developerskiego i testowego

## 1. Cel dokumentu

Dokument opisuje docelowy sposób instalacji i obsługi lokalnego środowiska projektu.

Decyzja architektoniczna znajduje się w [`adr/ADR-001-containerized-development-and-test-environment.md`](adr/ADR-001-containerized-development-and-test-environment.md).

Na tym etapie dokument definiuje kontrakt, który zostanie zaimplementowany w kolejnym kroku.

## 2. Wymagania na komputerze dewelopera

Wymagane będą wyłącznie:

- Git,
- Docker Desktop,
- Docker Compose v2,
- GNU/BSD Make.

Nie wymagamy lokalnej instalacji PHP, Composera, PostgreSQL, Redisa ani Symfony CLI.

## 3. Docelowa struktura infrastruktury

```text
.
├── Makefile
├── infrastructure/
│   ├── compose/
│   │   ├── dev/
│   │   │   └── docker-compose.dev.yml
│   │   └── test/
│   │       └── docker-compose.test.yml
│   ├── docker/
│   │   └── php/
│   │       ├── Dockerfile
│   │       ├── php.dev.ini
│   │       └── php.test.ini
│   └── env/
│       ├── dev.env
│       └── test.env
└── ...
```

## 4. Środowisko DEV

DEV będzie zawierał serwisy `php` i `postgres`.

`php`: PHP 8.4.x, Composer, kod aplikacji jako bind mount, port HTTP na hosta, połączenie do bazy po nazwie `postgres`.

`postgres`: PostgreSQL 16, trwały named volume i healthcheck.

## 5. Środowisko TEST

TEST będzie zawierał `php-test` i `postgres-test`.

`postgres-test` będzie używał `tmpfs`, aby dane nie przetrwały między uruchomieniami.

## 6. Makefile jako oficjalny interfejs

Nie wymagamy zapamiętywania długich poleceń `docker compose`.

```bash
make help
make build
make up
make down
make ps
make logs
make shell
make composer-install
make migrate
make test
make test-functional
make test-shell
make test-down
make export-source
```

## 7. Pierwsze uruchomienie

Po implementacji infrastruktury onboarding będzie wyglądał następująco:

```bash
git clone <repository>
cd sales-document-workflow

make build
make up
make composer-install
make migrate
make test
```

Docelowo możliwe jest dodanie `make setup` wykonującego bezpieczny bootstrap pierwszego uruchomienia.

## 8. Kontrakt Docker Compose DEV

Projekt Compose: `sales-document-workflow-dev`.

Plik: `infrastructure/compose/dev/docker-compose.dev.yml`.

Env: `infrastructure/env/dev.env`.

## 9. Kontrakt Docker Compose TEST

Projekt Compose: `sales-document-workflow-test`.

Plik: `infrastructure/compose/test/docker-compose.test.yml`.

Env: `infrastructure/env/test.env`.

Testy nie będą korzystać z uruchomionej bazy DEV.

## 10. Dostęp do Dockera z Makefile

Na macOS preferowana będzie binarka Docker Desktop:

```text
/Applications/Docker.app/Contents/Resources/bin/docker
```

z fallbackiem do `docker`.

```make
DOCKER_BIN ?= /Applications/Docker.app/Contents/Resources/bin/docker

ifeq (,$(wildcard $(DOCKER_BIN)))
  DOCKER_BIN := docker
endif
```

## 11. Redis

Redis nie będzie instalowany ani uruchamiany. Obecna aplikacja nie posiada zależności, które uzasadniałyby jego obecność.

## 12. P005 — implementacja infrastruktury developerskiej i testowej

P005 jest osobnym etapem implementacyjnym. Jego celem jest dostarczenie kompletnego, powtarzalnego środowiska uruchomieniowego przed rozpoczęciem zmian w kodzie biznesowym.

P005 **nie zmienia logiki domenowej aplikacji**. Dotyczy wyłącznie środowiska, automatyzacji developerskiej, uruchamiania testów oraz eksportu źródeł.

### 12.1. Zakres P005

P005 musi dostarczyć:

1. `infrastructure/docker/php/Dockerfile`,
2. konfigurację PHP dla DEV,
3. konfigurację PHP dla TEST,
4. `infrastructure/compose/dev/docker-compose.dev.yml`,
5. `infrastructure/compose/test/docker-compose.test.yml`,
6. `infrastructure/env/dev.env`,
7. `infrastructure/env/test.env`,
8. rootowy `Makefile`,
9. aktualizację rootowego `README.md`,
10. aktualizację `docs/README.md`,
11. automatyczny bootstrap zależności Composer,
12. automatyczne migracje DEV,
13. izolowaną bazę TEST,
14. pełne uruchamianie PHPUnit w Dockerze,
15. eksport źródeł przez `make export-source`,
16. walidację wersji runtime,
17. walidację zdrowia kontenerów,
18. dokumentację komend developerskich.

### 12.2. Struktura dostarczana przez P005

```text
.
├── Makefile
├── infrastructure/
│   ├── compose/
│   │   ├── dev/
│   │   │   └── docker-compose.dev.yml
│   │   └── test/
│   │       └── docker-compose.test.yml
│   ├── docker/
│   │   └── php/
│   │       ├── Dockerfile
│   │       ├── php.dev.ini
│   │       └── php.test.ini
│   └── env/
│       ├── dev.env
│       └── test.env
└── ...
```

### 12.3. Obowiązkowy kontrakt `Makefile`

P005 musi udostępnić co najmniej następujące targety:

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
```

Targety mają być idempotentne tam, gdzie jest to praktycznie możliwe.

### 12.4. `make setup`

`make setup` ma być komendą pierwszego uruchomienia projektu.

Oczekiwany przepływ:

```text
make setup
  ↓
walidacja dostępności Docker Desktop
  ↓
build obrazu PHP
  ↓
uruchomienie PostgreSQL DEV
  ↓
uruchomienie PHP DEV
  ↓
composer install
  ↓
oczekiwanie na zdrowy PostgreSQL
  ↓
migracje
  ↓
gotowe środowisko DEV
```

Komenda ma zakończyć się kodem różnym od zera przy błędzie któregokolwiek krytycznego kroku.

### 12.5. `make up`

`make up` uruchamia środowisko DEV.

Minimalne serwisy:

```text
php
postgres
```

Po zakończeniu:

- kontener PHP ma działać,
- PostgreSQL ma być healthy,
- aplikacja ma być dostępna na uzgodnionym porcie hosta.

### 12.6. `make down`

`make down` zatrzymuje środowisko DEV bez kasowania trwałych danych developerskich.

Kasowanie danych wymaga osobnej, jawnej operacji.

### 12.7. `make db-reset`

`make db-reset` może usunąć i odtworzyć developerską bazę danych.

Ponieważ operacja jest destrukcyjna, target powinien być jednoznacznie opisany w `make help`.

Nie może wpływać na środowisko TEST ani na inne projekty Docker Compose.

### 12.8. `make test`

`make test` ma uruchamiać kompletny test suite w **izolowanym środowisku TEST**.

Nie może korzystać z bazy DEV.

Oczekiwany przepływ:

```text
make test
  ↓
build / reuse obrazu testowego
  ↓
start postgres-test
  ↓
healthcheck
  ↓
utworzenie / migracja bazy testowej
  ↓
PHPUnit
  ↓
zachowanie kodu wyjścia PHPUnit
  ↓
sprzątnięcie środowiska testowego
```

Baza testowa powinna być efemeryczna.

Preferowane:

```text
tmpfs
```

dla:

```text
/var/lib/postgresql/data
```

### 12.9. `make test-functional`

Target uruchamia wyłącznie testy funkcjonalne, np.:

```text
tests/Functional
```

i korzysta z tego samego izolowanego kontraktu TEST co `make test`.

### 12.10. `make export-source`

P005 musi dostarczyć oficjalny mechanizm eksportu źródeł projektu:

```bash
make export-source
```

Eksport nie może być tworzony przez przypadkowe rekurencyjne pakowanie katalogu roboczego.

#### Źródło eksportu

Źródłem paczki jest aktualny commit:

```text
HEAD
```

Do archiwum trafiają wyłącznie pliki śledzone przez Git.

Preferowany mechanizm:

```bash
git archive
```

Dzięki temu eksport automatycznie pomija m.in.:

- `.git/`,
- `vendor/`, jeżeli nie jest śledzony,
- lokalne logi,
- katalogi runtime,
- tymczasowe pliki IDE,
- poprzednie eksporty,
- przypadkowe pliki nieśledzone.

#### Warunek czystego repozytorium

Domyślnie `make export-source` ma wymagać:

```text
git status --porcelain == empty
```

Jeżeli working tree jest brudny, eksport kończy się błędem i informacją, że należy najpierw zatwierdzić lub odłożyć zmiany.

Zapobiega to sytuacji, w której wysłana paczka nie odpowiada stanowi widocznemu w repozytorium.

#### Nazwa paczki

Format:

```text
sales-document-workflow-source-<UTC_TIMESTAMP>-<SHORT_SHA>.zip
```

Przykład:

```text
sales-document-workflow-source-20260904T113700Z-3fdbfd6.zip
```

Timestamp musi być generowany w UTC.

#### Lokalizacja

Eksporty trafiają do katalogu:

```text
exports/
```

Katalog:

```text
exports/
```

musi być wpisany do `.gitignore`.

#### SHA-256

Po utworzeniu paczki target ma wypisać:

```text
EXPORT_PATH=<ścieżka>
EXPORT_SHA256=<sha256>
EXPORT_COMMIT=<pełny SHA HEAD>
```

Przykład:

```text
EXPORT_PATH=exports/sales-document-workflow-source-20260904T113700Z-3fdbfd6.zip
EXPORT_SHA256=...
EXPORT_COMMIT=3fdbfd6...
```

Na macOS SHA-256 może być wyliczany przez:

```text
shasum -a 256
```

#### Powtarzalność i identyfikowalność

Każda paczka źródłowa musi jednoznacznie wskazywać commit, z którego została wygenerowana.

Nazwa pliku zawiera skrócony SHA, a output targetu pełny SHA.

Nie eksportujemy niezacommitowanych zmian.

### 12.11. `make help`

`make help` musi być wystarczające do odkrycia podstawowych operacji bez czytania implementacji Makefile.

Każdy publiczny target powinien posiadać krótki opis.

### 12.12. Dostęp do Docker Desktop

Makefile ma preferować:

```text
/Applications/Docker.app/Contents/Resources/bin/docker
```

na macOS.

Jeśli binarka nie istnieje, używany jest:

```text
docker
```

z `PATH`.

Nie zapisujemy ścieżek specyficznych dla konkretnego użytkownika.

### 12.13. Wersje weryfikowane w P005

Po uruchomieniu środowiska należy potwierdzić i zapisać w wyniku weryfikacji:

```text
PHP 8.4.x
Composer 2.x
PostgreSQL 16.x
Symfony 7.4.x
```

### 12.14. Baseline testów

P005 kończy się uruchomieniem istniejącego test suite bez zmian w kodzie biznesowym.

Wynik jest naszym baseline'em przed właściwą implementacją zadania.

Jeżeli testy nie przechodzą, dokumentujemy:

- liczbę testów,
- liczbę błędów,
- dokładne failing testy,
- czy failure odpowiada problemom celowo zaszytym w zadaniu.

Nie naprawiamy ich w P005.

### 12.15. Kryteria akceptacji P005

P005 jest zakończone dopiero wtedy, gdy:

1. `make help` działa,
2. `make build` działa,
3. `make setup` potrafi wykonać bootstrap od czystego środowiska,
4. `make up` uruchamia DEV,
5. PostgreSQL DEV zgłasza stan healthy,
6. PHP działa w wersji 8.4.x,
7. Composer działa w kontenerze,
8. aplikacja może połączyć się z PostgreSQL 16,
9. `make migrate` działa,
10. `make shell` otwiera shell w kontenerze PHP,
11. `make test` używa wyłącznie TEST stacku,
12. baza TEST jest odseparowana od DEV,
13. TEST nie pozostawia trwałych danych,
14. `make test-functional` działa,
15. kod wyjścia PHPUnit jest propagowany przez `make`,
16. `make down` zatrzymuje DEV bez kasowania danych,
17. `make export-source` działa na czystym repozytorium,
18. `make export-source` odmawia eksportu z brudnego working tree,
19. eksport zawiera wyłącznie pliki śledzone przez Git,
20. nazwa eksportu zawiera UTC timestamp i short SHA,
21. po eksporcie wypisywany jest SHA-256 oraz pełny commit SHA,
22. `exports/` nie jest śledzony przez Git,
23. README dokumentuje onboarding i wszystkie podstawowe komendy,
24. istniejący kod biznesowy pozostaje niezmieniony.

### 12.16. Artefakty dowodowe P005

Po implementacji P005 należy zebrać co najmniej:

```text
docker compose config
docker compose ps
php -v
composer --version
php bin/console about
php bin/console doctrine:migrations:status
php bin/phpunit
git status --short
make export-source
```

Wyniki stanowią podstawę do zamknięcia P005 i przejścia do projektowania zmian biznesowych.

## 13. Miejsce P005 w planie prac

P005 dostarcza powtarzalne środowisko i zmierzony baseline testów. Dopiero na tej podstawie realizowane są zmiany biznesowe P006–P009, a następnie P010 i P011.

Projekt rozwiązania tych zmian opisuje [`04-solution-design.md`](04-solution-design.md).

## Artefakty infrastrukturalne P005

```text
Makefile
infrastructure/compose/dev/docker-compose.dev.yml
infrastructure/compose/test/docker-compose.test.yml
infrastructure/docker/php/Dockerfile
infrastructure/docker/php/php.dev.ini
infrastructure/docker/php/php.test.ini
infrastructure/env/dev.env
infrastructure/env/test.env
infrastructure/scripts/e2e-api.sh
phpstan.neon
.php-cs-fixer.php
deptrac.yaml
```

Status `DONE_AND_VERIFIED` został nadany dopiero po wykonaniu realnych komend na Docker Desktop. Zebrane wyniki znajdują się w [`evidence/P005-infrastructure-bootstrap.md`](evidence/P005-infrastructure-bootstrap.md).

## Korekta P005.1 — Composer stage w Dockerfile

Podczas pierwszego realnego `make build` wykryto błąd składni BuildKit:

```text
failed to parse stage name "composer:${COMPOSER_VERSION}": invalid reference format
```

Przyczyną było użycie zmiennej `ARG` bezpośrednio w `COPY --from=...`.

Poprawiony kontrakt używa jawnego stage:

```dockerfile
FROM composer:2.8.12 AS composer-bin
...
COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer
```

Zmiana dotyczy wyłącznie infrastruktury builda i nie zmienia kodu aplikacji.


## Quality tooling

Środowisko PHP zawiera również przypięte narzędzia jakościowe niezależne od `composer.lock` aplikacji:

```text
PHPStan          2.2.12
PHP-CS-Fixer     3.95.24
Deptrac          4.7.1
```

Narzędzia są instalowane w obrazie do:

```text
/opt/php-tools
```

i dostępne przez `PATH`.

Dzięki temu nie zmieniamy zależności aplikacji wyłącznie w celu uruchamiania narzędzi developerskich.

Publiczny kontrakt Makefile:

```bash
make phpstan
make cs-check
make cs-fix
make deptrac
```

### PHPStan

Konfiguracja:

```text
phpstan.neon
```

Początkowy poziom:

```text
level: 8
```

### PHP-CS-Fixer

Konfiguracja:

```text
.php-cs-fixer.php
```

Bazą jest `@PER-CS2.0` + `@Symfony`. Świadomie odstępujemy od dwóch reguł zestawu `@Symfony`, aby nie przepisywać stylu dostarczonego baseline'u:

| Reguła | Ustawienie | Powód |
|---|---|---|
| `concat_space` | `['spacing' => 'one']` | wartość zgodna z `@PER-CS2.0`; baseline używa spacji wokół `.` |
| `yoda_style` | `false` | baseline i nowy kod używają naturalnej kolejności porównań |

Nie stosujemy globalnych wyłączeń poza tymi dwiema pozycjami.

Tryb kontroli bez modyfikacji:

```bash
make cs-check
```

Automatyczna poprawa:

```bash
make cs-fix
```

### Deptrac

Konfiguracja:

```text
deptrac.yaml
```

Deptrac kontroluje zależności pomiędzy istniejącymi warstwami katalogowymi projektu bez wprowadzania DDD.

Warstwy odpowiadają realnym katalogom `src/`: `Controller`, `Message`, `MessageHandler`, `Repository`, `Entity`, `Notification`, `Enum`.

Reguła „Entity nie zależy od Repository" pozostaje aktywna. Jedynym wyjątkiem jest wpis `skip_violations` zawężony do pary:

```text
App\Entity\SalesDocument -> App\Repository\SalesDocumentRepository
```

Zależność ta wynika wyłącznie z atrybutu `#[ORM\Entity(repositoryClass: ...)]` wymaganego przez Doctrine, a nie z decyzji projektowej. Nie używamy szerokich `skip_violations`.

Cache Deptraca jest kierowany do `var/deptrac/deptrac.cache`, aby nie zaśmiecać katalogu głównego repozytorium.

### Integracja z quality gate

`make verify` uruchamia kolejno również:

```text
cs-check
phpstan
deptrac
```

przed pełnym zestawem testów.

Pierwsze uruchomienie tych narzędzi stanowi baseline. Jeżeli istniejący kod nie przejdzie któregoś checka, P005 dokumentuje wynik zamiast maskować problem przez automatyczne modyfikowanie kodu biznesowego.


## Korekta P005.3 — twarda polityka zawartości eksportu

Eksport źródeł nie może zawierać katalogów runtime ani zależności.

Jawnie wykluczamy:

```text
vendor/
var/
exports/
.git/
.idea/
.DS_Store
```

Mechanizm ma dwie warstwy:

1. `.gitattributes` używa `export-ignore`,
2. `make export-source` po utworzeniu ZIP-a sprawdza listę plików i odrzuca eksport, jeżeli wykryje zabronioną ścieżkę.

Dzięki temu nie polegamy wyłącznie na założeniu, że katalog jest nieśledzony przez Git.


## Korekta P005.4 — dwa tryby eksportu źródeł

W praktycznym workflow potrzebne są dwa różne przypadki eksportu.

### `make export-source`

Eksportuje **aktualny working tree**, dzięki czemu może być używany podczas pracy przed commitem.

Uwzględnia:

- pliki śledzone przez Git,
- zmodyfikowane pliki,
- nowe pliki nieśledzone, o ile nie są ignorowane.

Nie uwzględnia:

```text
vendor/
var/
exports/
.git/
.idea/
.DS_Store
```

Paczka trafia do:

```text
$HOME/Downloads
```

i zawiera suffix:

```text
-working-tree.zip
```

Output zawiera również liczbę wpisów w `git status`:

```text
EXPORT_DIRTY_ENTRIES=<n>
```

### `make export-source-committed`

Jest trybem rygorystycznym.

- wymaga czystego working tree,
- eksportuje dokładnie `HEAD`,
- używa `git archive`,
- trafia do `$HOME/Downloads`,
- kończy się błędem przy niezacommitowanych zmianach.

Dzięki temu rozdzielamy dwa zastosowania:

```text
bieżące przekazanie stanu pracy  -> make export-source
reprodukowany artefakt commita   -> make export-source-committed
```


## Korekta P005.5 — czystość repozytorium i konfiguracja quality tooling

Pełna weryfikacja runtime P005 ujawniła trzy realne defekty infrastruktury, naprawione przed zamknięciem etapu.

### 1. `src/.DS_Store` był śledzony przez Git

Objaw: `make export-source-committed` odrzuciłby paczkę, ponieważ walidacja zabronionych ścieżek wykrywa `.DS_Store` w archiwum `git archive`.

Przyczyna: plik metadanych macOS trafił do commita bazowego zadania. Wpis `export-ignore` w `.gitattributes` obejmował wyłącznie ścieżkę główną.

Naprawa: plik usunięty ze śledzenia i dodany do `.gitignore` jako wzorzec globalny.

### 2. Katalog `.idea/` był śledzony przez Git

Objaw: metadane IDE w repozytorium przekazywanym do review.

Naprawa: katalog usunięty ze śledzenia i dodany do `.gitignore`. `.gitattributes` nadal chroni eksport `HEAD`.

### 3. `.deptrac.cache` trafiał do eksportu working tree

Objaw: `make export-source` zawierał plik cache narzędzia jakościowego.

Naprawa: cache Deptraca skierowany do `var/deptrac/deptrac.cache` (katalog już ignorowany), a wzorzec `/.deptrac.cache` dodany do `.gitignore` dla starszych uruchomień.

### Wynik weryfikacji baseline

Infrastruktura przechodzi w całości. Czerwone pozostają wyłącznie checki dotyczące kodu biznesowego, który jest przedmiotem P006–P009:

```text
BUILD=PASS
SETUP=PASS
DEPTRAC=PASS
CS_CHECK=PASS
PHPSTAN=FAIL   (17 błędów w kodzie baseline — zakres P006–P010)
TEST_FUNCTIONAL=FAIL (2 errors, 1 failure — celowo niedokończone zadanie)
TEST_E2E=PASS
```

To rozróżnienie jest świadome: `make verify` propaguje kod wyjścia, a P005 nie maskuje błędów aplikacyjnych.

## Powiązane dokumenty

- [`04-solution-design.md`](04-solution-design.md)
- [`05-test-plan.md`](05-test-plan.md)
- [`backlog/BACKLOG.md`](backlog/BACKLOG.md)
