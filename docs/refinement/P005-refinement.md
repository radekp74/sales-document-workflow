# Refinement P005 — Infrastructure Bootstrap

## Status

**Zaakceptowany do implementacji**

## Cel refinementu

Doprecyzować P005 tak, aby implementacja infrastruktury nie wymagała podejmowania niejawnych decyzji podczas kodowania.

## 1. Stan obecny

Projekt posiada:

```text
compose.yaml
```

uruchamiający wyłącznie PostgreSQL.

PHP i Composer nie są obecnie dostarczane przez projekt w Dockerze.

Brakuje:

- kontrolowanej wersji PHP,
- obrazu aplikacji,
- osobnego środowiska TEST,
- Makefile,
- standardowego bootstrapu,
- testowego quality gate,
- eksportu źródeł.

## 2. Stan docelowy

Deweloper po sklonowaniu repozytorium potrzebuje wyłącznie:

```text
Git
Docker Desktop
Docker Compose v2
make
```

Pierwsze uruchomienie:

```bash
make setup
```

Codzienna praca:

```bash
make up
make test
make verify
make down
```

Przekazanie źródeł:

```bash
make export-source
```

## 3. Komponenty DEV

### PHP

- PHP 8.4.x,
- Composer 2.x,
- wymagane rozszerzenia Doctrine/PostgreSQL,
- kod aplikacji jako bind mount.

### PostgreSQL

- PostgreSQL 16,
- named volume,
- healthcheck,
- hostname `postgres`.

## 4. Komponenty TEST

### PHP TEST

Ten sam bazowy obraz PHP, uruchamiany z:

```text
APP_ENV=test
```

### PostgreSQL TEST

- PostgreSQL 16,
- hostname `postgres-test`,
- baza `app_test`,
- storage `tmpfs`,
- niezależny project name Docker Compose.

## 5. Compose projects

DEV:

```text
sales-document-workflow-dev
```

TEST:

```text
sales-document-workflow-test
```

Nazwy zapobiegają kolizjom z innymi projektami na tej samej maszynie.

## 6. Dockerfile

Jeden Dockerfile:

```text
infrastructure/docker/php/Dockerfile
```

Preferowane targety:

```text
base
dev
test
```

Nie tworzymy osobnych, duplikujących się Dockerfile dla DEV i TEST.

## 7. Stary `compose.yaml`

Po wdrożeniu nowego kontraktu rootowy `compose.yaml` nie może pozostać równoległym, oficjalnym sposobem uruchamiania środowiska.

Podczas implementacji P005 należy zdecydować jedno z dwóch:

1. usunąć go i opisać migrację do `infrastructure/compose`,
2. pozostawić tylko jako jawnie deprecated shim, jeśli istnieje realny powód kompatybilności.

Preferowane: **usunąć**, ponieważ repozytorium nie ma zewnętrznego kontraktu wymagającego jego zachowania.

## 8. Makefile

Makefile nie może zawierać ścieżek użytkownika.

Preferowany Docker binary:

```text
/Applications/Docker.app/Contents/Resources/bin/docker
```

Fallback:

```text
docker
```

Publiczne targety dokumentujemy w `make help`.

Wewnętrzne helper targets mogą być prefiksowane `_`.

## 9. Bootstrap Composer

`vendor/` nie jest częścią obrazu źródłowego ani repozytorium.

`composer install` wykonujemy w kontenerze.

Musi respektować:

```text
composer.lock
```

Nie wykonujemy automatycznie:

```text
composer update
```

## 10. Migracje

DEV:

```text
make migrate
```

TEST:

migracje są wykonywane automatycznie podczas przygotowania testowego stacku.

Testy nie zakładają istnienia ręcznie przygotowanej bazy.

## 11. Strategia testów

Zgodnie z ADR-004:

```text
make test-unit
make test-integration
make test-functional
make test-e2e
```

Pełny:

```text
make test
```

Quality gate:

```text
make verify
```

Na początku P005 część kategorii może nie zawierać jeszcze własnych testów.

Target ma wtedy zachowywać poprawną semantykę i nie udawać pokrycia, którego nie ma.

Przykład:

```text
TEST_UNIT=NO_TESTS_PRESENT
```

może być poprawnym wynikiem P005, jeśli projekt nie ma jeszcze testów unit.

## 12. E2E

E2E musi testować prawdziwy HTTP endpoint.

Nie używamy BrowserKit jako substytutu E2E.

Minimalny klient może być wykonany przez:

```text
curl
```

lub niewielki skrypt testowy.

E2E w P005 może początkowo ograniczać się do smoke testu istniejącej aplikacji.

Regresyjne scenariusze biznesowe zostaną rozszerzone w P010.

## 13. `make verify`

Minimalny quality gate:

1. `composer validate`,
2. syntax check PHP,
3. Doctrine schema validation,
4. test-unit,
5. test-integration,
6. test-functional,
7. test-e2e.

Sprawdzenie Git working tree w `make verify` powinno raportować stan, ale nie może uniemożliwiać normalnej pracy nad niezacommitowanymi zmianami.

Twardy clean-tree gate pozostaje wymagany dla:

```text
make export-source-committed
```

> **Korekta.** Pierwotna wersja tego punktu wiązała clean-tree gate z `make export-source`. Zostało to zastąpione decyzją z sekcji 19–20: `make export-source` obsługuje working tree, a rygor `HEAD` przeniesiono do `make export-source-committed`. Obowiązuje wersja z sekcji 20.

Lista quality gate została ponadto rozszerzona w sekcji 18 o `cs-check`, `phpstan` i `deptrac`.

## 14. Kontrakt eksportu

> **Korekta.** Pierwotna wersja tej sekcji opisywała jeden target `make export-source` oparty o `git archive HEAD`, wymagający clean working tree i zapisujący paczkę do `exports/`. Została zastąpiona kontraktem dwutrybowym z sekcji 19–20 oraz katalogiem docelowym `$HOME/Downloads`. Poniżej stan obowiązujący.

### `make export-source` — working tree

- nie wymaga clean working tree,
- źródłem listy plików jest `git ls-files -co --exclude-standard`,
- obejmuje pliki śledzone, zmodyfikowane oraz nowe nieignorowane,
- ZIP, UTC timestamp i short SHA w nazwie, suffix `-working-tree`,
- output: `EXPORT_PATH`, `EXPORT_SHA256`, `EXPORT_BASE_COMMIT`, `EXPORT_DIRTY_ENTRIES`.

### `make export-source-committed` — `HEAD`

- wymaga clean working tree i odmawia pracy przy zmianach (`REFUSED_DIRTY_WORKTREE`, exit code != 0),
- bazuje na `git archive HEAD`,
- ZIP, UTC timestamp i short SHA w nazwie, suffix `-committed`,
- output: `EXPORT_PATH`, `EXPORT_SHA256`, `EXPORT_COMMIT`.

### Wspólne

- katalog docelowy: `$HOME/Downloads`,
- `exports/` pozostaje w `.gitignore`,
- po zbudowaniu archiwum oba tryby weryfikują listę plików i przerywają pracę, jeżeli wykryją zabronioną ścieżkę.

## 15. Ryzyka

### R1 — różnica PHP constraint

`composer.json` deklaruje szerzej PHP niż niektóre dev dependencies.

Mitigacja:

- runtime P005 zamrażamy na PHP 8.4,
- nie zmieniamy jeszcze constraintu bez osobnej potrzeby.

### R2 — bind mount i `vendor`

Jeżeli `vendor` jest generowany wewnątrz bind mountu, właściciel plików na macOS może wymagać uwagi.

Mitigacja:

- zweryfikować realne UID/GID podczas P005,
- nie komplikować obrazu przed wystąpieniem problemu.

### R3 — port hosta

Port 8000 może być zajęty.

Mitigacja:

- port konfigurowalny przez env,
- sensowna wartość domyślna.

### R4 — baseline ma celowy failure

Projekt zawiera test wskazujący istniejący bug.

Mitigacja:

- P005 nie wymaga zielonego istniejącego test suite,
- wymaga wiarygodnego udokumentowania baseline,
- infrastruktura musi poprawnie propagować jego failure.

## 16. Definition of Ready

P005 jest gotowe do implementacji, ponieważ:

- ADR-001 jest zaakceptowany,
- ADR-004 jest zaakceptowany,
- zakres infrastruktury jest określony,
- Makefile contract jest określony,
- test strategy jest określona,
- export-source contract jest określony,
- acceptance criteria są zapisane.

## 17. Kolejny krok

Implementacja P005.

Po jej zakończeniu:

```text
P005 -> DONE_AND_VERIFIED
```

i dopiero wtedy rozpoczynamy refinement zmian biznesowych P006–P009.


## 18. Quality tooling

P005 obejmuje dodatkową warstwę narzędzi jakościowych:

```text
PHPStan 2.2.12
PHP-CS-Fixer 3.95.24
Deptrac 4.7.1
```

Nie dodajemy ich do `composer.json` aplikacji.

Są instalowane w obrazie PHP w osobnym katalogu:

```text
/opt/php-tools
```

Decyzja ogranicza ingerencję w dostarczony `composer.lock` i pozwala traktować tooling jako część infrastruktury developerskiej.

Konfiguracje w repo:

```text
phpstan.neon
.php-cs-fixer.php
deptrac.yaml
```

Targety:

```text
make phpstan
make cs-check
make cs-fix
make deptrac
```

`make verify` uruchamia:

```text
composer validate
PHP syntax
Doctrine schema validation
cs-check
phpstan
deptrac
unit
integration
functional
E2E
```

`make cs-fix` pozostaje świadomą, ręczną operacją modyfikującą źródła.


### Wykluczenia z eksportu

`make export-source` nigdy nie może zawierać:

```text
vendor/
var/
exports/
.git/
.idea/
.DS_Store
```

Wymóg jest egzekwowany przez `.gitattributes` (`export-ignore`) oraz kontrolę gotowego archiwum.


## 19. Dwa tryby eksportu

Przyjmujemy dwa jawne kontrakty:

```text
make export-source
```

dla aktualnego working tree oraz:

```text
make export-source-committed
```

dla czystego, reprodukowalnego `HEAD`.

Tryb working-tree nie wymaga commita, ponieważ służy do przekazywania aktualnego stanu roboczego. Tryb committed zachowuje twardy clean-tree gate.


## 20. Ujednolicenie kontraktu eksportu

Aktualny kontrakt nadrzędny:

```text
make export-source
```

tworzy bezpieczny snapshot **working tree** i może działać przy niezacommitowanych zmianach.

```text
make export-source-committed
```

jest trybem finalnym i wymaga czystego working tree.

Oba tryby wykluczają:

```text
vendor/
var/
exports/
.git/
.idea/
.DS_Store
```

Finalny deliverable P011 musi używać `make export-source-committed`.

## 21. Rozstrzygnięcia podjęte podczas realizacji

Sekcja zapisuje decyzje, które zapadły dopiero w trakcie pełnej weryfikacji runtime P005.

### 21.1. Usunięcie rootowego `compose.yaml`

Zrealizowany został wariant preferowany z sekcji 7: plik został usunięty, a jedynym oficjalnym sposobem uruchamiania środowisk pozostaje `infrastructure/compose` sterowane przez `Makefile`. Nie pozostawiono shimu, ponieważ żaden zewnętrzny kontrakt go nie wymagał.

### 21.2. Odstępstwa stylistyczne PHP-CS-Fixer

Zestaw `@Symfony` nie przechodził na dostarczonym baseline w dwóch regułach czysto stylistycznych. Zamiast przepisywać kod, który nie jest przedmiotem zadania, doprecyzowano konfigurację:

```text
concat_space => ['spacing' => 'one']   (wartość zgodna z @PER-CS2.0)
yoda_style   => false
```

Poza tymi dwiema pozycjami nie stosujemy wyłączeń. Przyjęto natomiast trzy realne poprawki mechaniczne: `declare(strict_types=1)` w `src/Kernel.php` i `tests/bootstrap.php` oraz pre-inkrementację w `InMemoryNotifier`. Są to zmiany bez wpływu na zachowanie.

### 21.3. Wyjątek Deptrac dla atrybutu Doctrine

Jedyne naruszenie warstw wynikało z `#[ORM\Entity(repositoryClass: SalesDocumentRepository::class)]`, czyli z wymogu frameworka, a nie z decyzji projektowej. Reguła „Entity nie zależy od Repository" pozostała aktywna, a wyjątek zawężono przez `skip_violations` do jednej pary klas. Nie stosujemy szerokich wyłączeń ani obniżania rygoru.

### 21.4. PHPStan pozostaje na poziomie 8

`make phpstan` zgłasza 17 błędów w dostarczonym kodzie aplikacji. Poziom nie został obniżony i nie dodano listy ignorowanych błędów. Wynik został zapisany jako `APPLICATION_BASELINE_FAILURE` i należy do zakresu P006–P010.

### 21.5. Zakres smoke testu E2E

Smoke E2E pozostaje infrastrukturalny, ale został wzmocniony tak, aby faktycznie dowodził przejścia przez cały stos. Po utworzeniu dokumentu przez HTTP skrypt potwierdza jego obecność w PostgreSQL TEST zapytaniem SQL. Regresje biznesowe pozostają zakresem P010.

### 21.6. Czystość repozytorium

Ze śledzenia usunięto `src/.DS_Store` oraz katalog `.idea/`, a cache Deptraca skierowano do `var/`. Szczegóły w [`../evidence/P005-infrastructure-bootstrap.md`](../evidence/P005-infrastructure-bootstrap.md).
