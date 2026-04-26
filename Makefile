.PHONY: help dev php frontend-build frontend-dev mailhog laravel

HOST ?= 127.0.0.1
PHP_PORT ?= 8080
VITE_PORT ?= 5173
MAILHOG_UI_PORT ?= 8025
MAILHOG_SMTP_PORT ?= 1025

help:
	@echo ""
	@echo "ProjectWebsite (lokal) — Targets:"
	@echo ""
	@echo "  make dev            Frontend build + PHP-Server (stabil)"
	@echo "  make php            PHP-Server starten (Host/Port siehe Variablen)"
	@echo "  make frontend-build Frontend-Assets bauen (react-dist/)"
	@echo "  make frontend-dev   Vite Dev-Server (HMR) starten"
	@echo "  make mailhog        MailHog via Docker (SMTP+Web UI)"
	@echo "  make laravel        Laravel Test-Server (Port 8000)"
	@echo ""
	@echo "Variablen (optional überschreiben):"
	@echo "  HOST=$(HOST)  PHP_PORT=$(PHP_PORT)  VITE_PORT=$(VITE_PORT)"
	@echo "  MAILHOG_SMTP_PORT=$(MAILHOG_SMTP_PORT)  MAILHOG_UI_PORT=$(MAILHOG_UI_PORT)"
	@echo ""
	@echo "URLs (Default):"
	@echo "  PHP:     http://$(HOST):$(PHP_PORT)/"
	@echo "  Vite:    http://$(HOST):$(VITE_PORT)/ (nur bei frontend-dev)"
	@echo "  MailHog: http://$(HOST):$(MAILHOG_UI_PORT)/"
	@echo ""

dev: frontend-build php

php:
	@echo ""
	@echo "PHP: http://$(HOST):$(PHP_PORT)/"
	@echo ""
	php -S $(HOST):$(PHP_PORT) -t .

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

laravel:
	cd laravel && ./serve-test.sh

