.PHONY: help dev php frontend-build frontend-dev mailhog mailhog-check laravel services-up services-down services-logs deploy-check prod-env-check db-baseline test-db deploy-server

HOST ?= 127.0.0.1
PHP_PORT ?= 8080
VITE_PORT ?= 5173
MAILHOG_UI_PORT ?= 8025
MAILHOG_SMTP_PORT ?= 1025
MONGO_PORT ?= 27017
PHP_UPLOAD_MAX ?= 2048M
PHP_POST_MAX ?= 2100M

help:
	@echo ""
	@echo "ProjectWebsite (lokal) — Targets:"
	@echo ""
	@echo "  make dev            Frontend build + PHP-Server (stabil)"
	@echo "  make php            PHP-Server starten (Host/Port siehe Variablen)"
	@echo "  make frontend-build Frontend-Assets bauen (react-dist/)"
	@echo "  make frontend-dev   Vite Dev-Server (HMR) starten"
	@echo "  make mailhog        MailHog via Docker (SMTP+Web UI, einzelner Container)"
	@echo "  make mailhog-check  Prüft SMTP-Port MailHog (127.0.0.1:1025)"
	@echo "  make services-up    MongoDB + MailHog (docker compose up -d)"
	@echo "  make services-down  Docker-Dienste stoppen"
	@echo "  make services-logs  Logs der Compose-Dienste"
	@echo "  make laravel        Laravel Test-Server (Port 8000)"
	@echo "  make deploy-check   Deploy-Status-Prüfungen (CLI)"
	@echo "  make prod-env-check Produktions-Overlay + deploy-check"
	@echo "  make db-baseline    DB-Skripte 17/16/15/14 idempotent (CLI)"
	@echo "  make test-db        DB-Baseline + PHPUnit (wie CI legacy_db)"
	@echo "  make deploy-server  Build + Composer (Server-Update)"
	@echo ""
	@echo "Variablen (optional überschreiben):"
	@echo "  HOST=$(HOST)  PHP_PORT=$(PHP_PORT)  VITE_PORT=$(VITE_PORT)"
	@echo "  MAILHOG_SMTP_PORT=$(MAILHOG_SMTP_PORT)  MAILHOG_UI_PORT=$(MAILHOG_UI_PORT)"
	@echo "  MONGO_PORT=$(MONGO_PORT)"
	@echo ""
	@echo "URLs (Default):"
	@echo "  PHP:     http://$(HOST):$(PHP_PORT)/"
	@echo "  Vite:    http://$(HOST):$(VITE_PORT)/ (nur bei frontend-dev)"
	@echo "  MailHog: http://$(HOST):$(MAILHOG_UI_PORT)/"
	@echo ""

dev: frontend-build php
	@echo ""
	@echo "Mail-Tests: make mailhog-check — Setup: docs/local_mail_setup.md"
	@echo ""

php:
	@echo ""
	@echo "PHP: http://$(HOST):$(PHP_PORT)/"
	@echo "Upload-Limits: upload_max_filesize=$(PHP_UPLOAD_MAX) post_max_size=$(PHP_POST_MAX)"
	@echo ""
	php -d upload_max_filesize=$(PHP_UPLOAD_MAX) -d post_max_size=$(PHP_POST_MAX) -d max_execution_time=600 -d max_input_time=600 -S $(HOST):$(PHP_PORT) -t . router.php

frontend-build:
	cd frontend && npm install && npm run build

frontend-dev:
	@echo ""
	@echo "Vite Dev Server: http://$(HOST):$(VITE_PORT)/"
	@echo ""
	@echo "Hinweis: Für HMR in PHP zusätzlich in .env.local setzen:"
	@echo "  VITE_HMR=1"
	@echo "  VITE_DEV_SERVER_URL=http://$(HOST):$(VITE_PORT)"
	@echo ""
	cd frontend && npm install && npm run dev -- --host $(HOST) --port $(VITE_PORT)

mailhog:
	@echo ""
	@echo "MailHog UI:   http://$(HOST):$(MAILHOG_UI_PORT)/"
	@echo "MailHog SMTP: smtp://$(HOST):$(MAILHOG_SMTP_PORT)"
	@echo ""
	docker run --rm -p $(MAILHOG_SMTP_PORT):1025 -p $(MAILHOG_UI_PORT):8025 mailhog/mailhog

mailhog-check:
	@php -r '$$e=0; $$fp=@fsockopen("$(HOST)", $(MAILHOG_SMTP_PORT), $$e, $$s, 2); if (!$$fp) { fwrite(STDERR, "MailHog SMTP nicht erreichbar auf $(HOST):$(MAILHOG_SMTP_PORT) — make services-up oder make mailhog\n"); exit(1); } fclose($$fp); echo "MailHog SMTP OK ($(HOST):$(MAILHOG_SMTP_PORT))\n"; echo "Web-UI: http://$(HOST):$(MAILHOG_UI_PORT)/\n";'

services-up:
	@echo ""
	@echo "MongoDB: mongodb://$(HOST):$(MONGO_PORT)"
	@echo "MailHog: http://$(HOST):$(MAILHOG_UI_PORT)/  SMTP $(HOST):$(MAILHOG_SMTP_PORT)"
	@echo ""
	MONGO_PORT=$(MONGO_PORT) docker compose up -d

services-down:
	docker compose down

services-logs:
	docker compose logs -f --tail=100

laravel:
	cd laravel && ./serve-test.sh

deploy-check:
	php scripts/deploy-check.php

prod-env-check:
	@bash scripts/prod-env-check.sh

db-baseline:
	php scripts/ensure-db-baseline.php

test-db:
	php scripts/ensure-db-baseline.php && vendor/bin/phpunit -c phpunit.xml.dist

deploy-server: frontend-build
	composer install --no-dev --optimize-autoloader
	cd laravel && composer install --no-dev --optimize-autoloader && php artisan config:cache
	@echo ""
	@echo "Deploy-Build fertig. Danach: make deploy-check, Admin DB-Skripte, docs/deployment.md"

