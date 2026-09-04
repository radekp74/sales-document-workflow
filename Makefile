SHELL := /bin/bash
.DEFAULT_GOAL := help

DOCKER_BIN ?= /Applications/Docker.app/Contents/Resources/bin/docker
ifeq (,$(wildcard $(DOCKER_BIN)))
  DOCKER_BIN := docker
endif

DEV_PROJECT := sales-document-workflow-dev
TEST_PROJECT := sales-document-workflow-test

DEV_COMPOSE := $(DOCKER_BIN) compose \
	-p $(DEV_PROJECT) \
	--env-file infrastructure/env/dev.env \
	-f infrastructure/compose/dev/docker-compose.dev.yml

TEST_COMPOSE := $(DOCKER_BIN) compose \
	-p $(TEST_PROJECT) \
	--env-file infrastructure/env/test.env \
	-f infrastructure/compose/test/docker-compose.test.yml

PHP_DEV := $(DEV_COMPOSE) exec -T php
PHP_DEV_TTY := $(DEV_COMPOSE) exec php
PHP_TEST_RUN := $(TEST_COMPOSE) run --rm php-test

.PHONY: help
help: ## Pokaż wspierane komendy developerskie
	@awk 'BEGIN {FS = ":.*##"; printf "\nSales Document Workflow — developer commands\n\n"} /^[a-zA-Z0-9_.%+\/-]+:.*##/ {printf "  %-24s %s\n", $$1, $$2}' $(MAKEFILE_LIST)
	@printf "\nStart:\n  make setup\n\n"

.PHONY: docker-check
docker-check: ## Sprawdź dostępność Docker Desktop / Docker Engine
	@$(DOCKER_BIN) version >/dev/null
	@$(DOCKER_BIN) compose version
	@echo "DOCKER_CHECK=PASS"

.PHONY: setup
setup: docker-check build up composer-install migrate ## Pierwsze uruchomienie środowiska DEV
	@echo "SETUP=PASS"
	@echo "APP_URL=http://localhost:$$(grep '^APP_PORT=' infrastructure/env/dev.env | cut -d= -f2)"

.PHONY: build
build: docker-check ## Zbuduj obrazy DEV i TEST
	$(DEV_COMPOSE) build php
	$(TEST_COMPOSE) build php-test app-test
	@echo "BUILD=PASS"

.PHONY: up
up: docker-check ## Uruchom stack DEV
	$(DEV_COMPOSE) up -d postgres php
	@$(MAKE) _wait-dev-db
	@echo "DEV_UP=PASS"

.PHONY: down
down: ## Zatrzymaj DEV bez usuwania danych
	$(DEV_COMPOSE) down --remove-orphans

.PHONY: restart
restart: down up ## Zrestartuj stack DEV

.PHONY: ps
ps: ## Pokaż status DEV
	$(DEV_COMPOSE) ps -a

.PHONY: logs
logs: ## Śledź logi DEV
	$(DEV_COMPOSE) logs -f

.PHONY: shell
shell: ## Otwórz shell w kontenerze PHP DEV
	$(PHP_DEV_TTY) bash

.PHONY: composer-install
composer-install: ## Zainstaluj zależności dokładnie z composer.lock
	$(DEV_COMPOSE) run --rm php composer install --no-interaction --prefer-dist
	@echo "COMPOSER_INSTALL=PASS"

.PHONY: migrate
migrate: ## Uruchom migracje DEV
	@$(MAKE) _wait-dev-db
	$(PHP_DEV) php bin/console doctrine:migrations:migrate --no-interaction
	@echo "DEV_MIGRATE=PASS"

.PHONY: db-reset
db-reset: ## DESTRUKCYJNE: odtwórz bazę DEV od zera
	@echo "WARNING: resetting DEV database"
	$(DEV_COMPOSE) down -v --remove-orphans
	$(DEV_COMPOSE) up -d postgres php
	@$(MAKE) _wait-dev-db
	$(PHP_DEV) php bin/console doctrine:migrations:migrate --no-interaction
	@echo "DEV_DB_RESET=PASS"

.PHONY: test-unit
test-unit: ## Uruchom testy jednostkowe
	@if [ ! -d tests/Unit ] || ! find tests/Unit -type f -name '*Test.php' -print -quit 2>/dev/null | grep -q .; then \
		echo "TEST_UNIT=NO_TESTS_PRESENT"; \
	else \
		$(MAKE) _test-prepare >/dev/null && \
		set +e; $(PHP_TEST_RUN) php bin/phpunit tests/Unit; rc=$$?; $(MAKE) test-down >/dev/null; exit $$rc; \
	fi

.PHONY: test-integration
test-integration: ## Uruchom testy integracyjne
	@if [ ! -d tests/Integration ] || ! find tests/Integration -type f -name '*Test.php' -print -quit 2>/dev/null | grep -q .; then \
		echo "TEST_INTEGRATION=NO_TESTS_PRESENT"; \
	else \
		$(MAKE) _test-prepare >/dev/null && \
		set +e; $(PHP_TEST_RUN) php bin/phpunit tests/Integration; rc=$$?; $(MAKE) test-down >/dev/null; exit $$rc; \
	fi

.PHONY: test-functional
test-functional: ## Uruchom istniejące testy funkcjonalne w izolowanym TEST
	@$(MAKE) _test-prepare
	@set +e; \
	$(PHP_TEST_RUN) php bin/phpunit tests/Functional; rc=$$?; \
	$(MAKE) test-down >/dev/null; \
	exit $$rc

.PHONY: test-e2e
test-e2e: ## Uruchom HTTP smoke E2E na stacku TEST
	@$(MAKE) _test-prepare
	$(TEST_COMPOSE) up -d app-test
	@set +e; \
	$(PHP_TEST_RUN) sh infrastructure/scripts/e2e-api.sh; rc=$$?; \
	$(MAKE) test-down >/dev/null; \
	exit $$rc

.PHONY: test
test: ## Uruchom pełną strategię testów w kolejności
	$(MAKE) test-unit
	$(MAKE) test-integration
	$(MAKE) test-functional
	$(MAKE) test-e2e

.PHONY: phpstan
phpstan: ## Uruchom PHPStan
	@$(MAKE) up >/dev/null
	$(PHP_DEV) phpstan analyse --configuration=phpstan.neon --no-progress
	@echo "PHPSTAN=PASS"

.PHONY: cs-check
cs-check: ## Sprawdź styl kodu przez PHP-CS-Fixer bez modyfikacji
	@$(MAKE) up >/dev/null
	$(PHP_DEV) php-cs-fixer fix --config=.php-cs-fixer.php --dry-run --diff --using-cache=no
	@echo "CS_CHECK=PASS"

.PHONY: cs-fix
cs-fix: ## Napraw styl kodu przez PHP-CS-Fixer
	@$(MAKE) up >/dev/null
	$(PHP_DEV) php-cs-fixer fix --config=.php-cs-fixer.php --using-cache=no
	@echo "CS_FIX=PASS"

.PHONY: deptrac
deptrac: ## Sprawdź zależności architektoniczne przez Deptrac
	@$(MAKE) up >/dev/null
	$(PHP_DEV) deptrac analyse --config-file=deptrac.yaml --cache-file=var/deptrac/deptrac.cache --no-progress
	@echo "DEPTRAC=PASS"

.PHONY: verify
verify: ## Lokalny quality gate
	@$(MAKE) _test-prepare
	$(PHP_TEST_RUN) composer validate --no-check-publish
	$(PHP_TEST_RUN) sh -lc 'find src tests migrations config public bin -type f -name "*.php" -print0 | xargs -0 -n1 php -l >/dev/null && echo PHP_SYNTAX=PASS'
	$(PHP_TEST_RUN) php bin/console doctrine:schema:validate --skip-sync
	@$(MAKE) test-down >/dev/null
	$(MAKE) cs-check
	$(MAKE) phpstan
	$(MAKE) deptrac
	$(MAKE) test
	@echo "VERIFY_GIT_STATUS_BEGIN"
	@git status --short
	@echo "VERIFY_GIT_STATUS_END"
	@echo "VERIFY=PASS"

.PHONY: test-shell
test-shell: ## Otwórz shell w jednorazowym kontenerze TEST
	@$(MAKE) _test-prepare >/dev/null
	$(TEST_COMPOSE) run --rm php-test bash

.PHONY: test-down
test-down: ## Usuń stack TEST i jego efemeryczne zasoby
	$(TEST_COMPOSE) down --remove-orphans

.PHONY: clean
clean: ## Usuń stacki DEV/TEST, DEV volume i lokalne cache runtime
	$(TEST_COMPOSE) down --remove-orphans
	$(DEV_COMPOSE) down -v --remove-orphans
	rm -rf var/cache/* .phpunit.cache
	@echo "CLEAN=PASS"

.PHONY: runtime-info
runtime-info: ## Pokaż wersje runtime w kontenerach
	@$(MAKE) up >/dev/null
	@$(PHP_DEV) php -v | head -1
	@$(PHP_DEV) composer --version
	@$(PHP_DEV) php bin/console about | sed -n '1,80p'
	@$(DEV_COMPOSE) exec -T postgres postgres --version

.PHONY: export-source
export-source: ## Eksportuj bezpieczny snapshot aktualnego working tree do ~/Downloads
	@set -euo pipefail; \
	ts="$$(date -u +%Y%m%dT%H%M%SZ)"; \
	short="$$(git rev-parse --short=8 HEAD)"; \
	full="$$(git rev-parse HEAD)"; \
	out="$$HOME/Downloads/sales-document-workflow-source-$${ts}-$${short}-working-tree.zip"; \
	tmp="$$(mktemp -d)"; \
	trap 'rm -rf "$$tmp"' EXIT; \
	stage="$$tmp/sales-document-workflow"; \
	mkdir -p "$$stage"; \
	while IFS= read -r -d '' path; do \
		case "$$path" in \
			vendor/*|var/*|exports/*|.git/*|.idea/*|*/.DS_Store|.DS_Store) continue ;; \
		esac; \
		if [ -f "$$path" ]; then \
			mkdir -p "$$stage/$$(dirname "$$path")"; \
			cp -p "$$path" "$$stage/$$path"; \
		elif [ -L "$$path" ]; then \
			mkdir -p "$$stage/$$(dirname "$$path")"; \
			cp -P "$$path" "$$stage/$$path"; \
		fi; \
	done < <(git ls-files -co --exclude-standard -z); \
	(cd "$$tmp" && zip -qry "$$out" sales-document-workflow); \
	if unzip -Z1 "$$out" | grep -Eq '^sales-document-workflow/(vendor|var|exports|\.git|\.idea)(/|$$)|(^|/)\.DS_Store$$'; then \
		echo "EXPORT_SOURCE=FAIL_FORBIDDEN_PATH_PRESENT" >&2; \
		rm -f "$$out"; \
		exit 1; \
	fi; \
	sha="$$(shasum -a 256 "$$out" | awk '{print $$1}')"; \
	dirty="$$(git status --porcelain | wc -l | tr -d ' ')"; \
	echo "EXPORT_SOURCE=PASS"; \
	echo "EXPORT_MODE=WORKING_TREE"; \
	echo "EXPORT_PATH=$$out"; \
	echo "EXPORT_SHA256=$$sha"; \
	echo "EXPORT_BASE_COMMIT=$$full"; \
	echo "EXPORT_DIRTY_ENTRIES=$$dirty"

.PHONY: export-source-committed
export-source-committed: ## Eksportuj wyłącznie czysty HEAD do ~/Downloads
	@set -euo pipefail; \
	if [ -n "$$(git status --porcelain)" ]; then \
		echo "EXPORT_SOURCE_COMMITTED=REFUSED_DIRTY_WORKTREE" >&2; \
		git status --short >&2; \
		exit 1; \
	fi; \
	ts="$$(date -u +%Y%m%dT%H%M%SZ)"; \
	short="$$(git rev-parse --short=8 HEAD)"; \
	full="$$(git rev-parse HEAD)"; \
	out="$$HOME/Downloads/sales-document-workflow-source-$${ts}-$${short}-committed.zip"; \
	git archive --format=zip --prefix=sales-document-workflow/ -o "$$out" HEAD; \
	if unzip -Z1 "$$out" | grep -Eq '^sales-document-workflow/(vendor|var|exports|\.git|\.idea)(/|$$)|(^|/)\.DS_Store$$'; then \
		echo "EXPORT_SOURCE_COMMITTED=FAIL_FORBIDDEN_PATH_PRESENT" >&2; \
		rm -f "$$out"; \
		exit 1; \
	fi; \
	sha="$$(shasum -a 256 "$$out" | awk '{print $$1}')"; \
	echo "EXPORT_SOURCE_COMMITTED=PASS"; \
	echo "EXPORT_MODE=COMMITTED_HEAD"; \
	echo "EXPORT_PATH=$$out"; \
	echo "EXPORT_SHA256=$$sha"; \
	echo "EXPORT_COMMIT=$$full"
.PHONY: _wait-dev-db
_wait-dev-db:
	@for i in $$(seq 1 45); do \
		if $(DEV_COMPOSE) exec -T postgres pg_isready -U app -d app >/dev/null 2>&1; then \
			echo "DEV_DB_READY=PASS"; exit 0; \
		fi; \
		sleep 1; \
	done; \
	echo "DEV_DB_READY=FAIL" >&2; exit 1

.PHONY: _test-prepare
_test-prepare: docker-check
	$(TEST_COMPOSE) build php-test app-test
	$(TEST_COMPOSE) up -d postgres-test
	@for i in $$(seq 1 45); do \
		if $(TEST_COMPOSE) exec -T postgres-test pg_isready -U app -d app_test >/dev/null 2>&1; then \
			echo "TEST_DB_READY=PASS"; break; \
		fi; \
		if [ "$$i" -eq 45 ]; then echo "TEST_DB_READY=FAIL" >&2; exit 1; fi; \
		sleep 1; \
	done
	$(PHP_TEST_RUN) composer install --no-interaction --prefer-dist
	$(PHP_TEST_RUN) php bin/console doctrine:migrations:migrate --no-interaction
	@echo "TEST_PREPARE=PASS"
