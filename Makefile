# Makefile for Laravel project

SAIL := ./vendor/bin/sail
DOCKER_COMPOSE := docker compose
DEV_COMPOSE_FILE := compose.dev.yaml
PROD_COMPOSE_FILE := compose.prod.yaml
DEV_SERVICE := laravel.test
PHP_FPM := $(shell sh -lc "command -v systemctl >/dev/null 2>&1 && systemctl list-units --type=service --state=running 2>/dev/null | grep -oE 'php[0-9.]+-fpm' | head -n 1 || true")

.DEFAULT_GOAL := help

help:
	@echo "Usage: make [target]"
	@echo ""
	@echo "Targets:"
	@echo "  up            Start the development Docker containers"
	@echo "  down          Stop the development Docker containers"
	@echo "  restart       Restart the development Docker containers"
	@echo "  up-dev        Start the development Docker containers"
	@echo "  down-dev      Stop the development Docker containers"
	@echo "  restart-dev   Restart the development Docker containers"
	@echo "  build-dev     Build the development Docker containers"
	@echo "  up-prod       Start the production Docker containers"
	@echo "  down-prod     Stop the production Docker containers"
	@echo "  restart-prod  Restart the production Docker containers"
	@echo "  build-prod    Build the production Docker containers"
	@echo "  cert-prod     Generate a trusted local cert with mkcert for the production domain"
	@echo "  logs-dev      Tail development container logs"
	@echo "  logs-prod     Tail production container logs"
	@echo "  restart-local Restart local Nginx and PHP-FPM daemons"
	@echo "  build         Build the development Docker containers"
	@echo "  setup         Initial project setup (composer, env, key, migrate, npm)"
	@echo "  test          Run PHPUnit tests"
	@echo "  lint          Run Laravel Pint for code formatting"
	@echo "  migrate       Run database migrations"
	@echo "  fresh         Refresh database and run seeds"
	@echo "  tinker        Open Laravel Tinker"
	@echo "  vite          Run Vite development server"
	@echo "  shell         Enter the application container shell"
	@echo "  artisan c=    Run an artisan command"
	@echo "  composer c=   Run a composer command"
	@echo "  npm c=        Run an npm command"

up:
	$(DOCKER_COMPOSE) -f $(DEV_COMPOSE_FILE) up -d

down:
	$(DOCKER_COMPOSE) -f $(DEV_COMPOSE_FILE) down

restart:
	$(DOCKER_COMPOSE) -f $(DEV_COMPOSE_FILE) restart

up-dev:
	$(DOCKER_COMPOSE) -f $(DEV_COMPOSE_FILE) up -d

down-dev:
	$(DOCKER_COMPOSE) -f $(DEV_COMPOSE_FILE) down

restart-dev:
	$(DOCKER_COMPOSE) -f $(DEV_COMPOSE_FILE) restart

build-dev:
	$(DOCKER_COMPOSE) -f $(DEV_COMPOSE_FILE) build --no-cache

up-prod:
	$(DOCKER_COMPOSE) -f $(PROD_COMPOSE_FILE) up -d --build

down-prod:
	$(DOCKER_COMPOSE) -f $(PROD_COMPOSE_FILE) down

restart-prod:
	$(DOCKER_COMPOSE) -f $(PROD_COMPOSE_FILE) restart

build-prod:
	$(DOCKER_COMPOSE) -f $(PROD_COMPOSE_FILE) build --no-cache

cert-prod:
	mkdir -p docker/certs
	mkcert -cert-file docker/certs/$(or $(PROD_DOMAIN),taqsimot.test).pem -key-file docker/certs/$(or $(PROD_DOMAIN),taqsimot.test)-key.pem $(or $(PROD_DOMAIN),taqsimot.test)

logs-dev:
	$(DOCKER_COMPOSE) -f $(DEV_COMPOSE_FILE) logs -f

logs-prod:
	$(DOCKER_COMPOSE) -f $(PROD_COMPOSE_FILE) logs -f

restart-local:
	@echo "Restarting local services..."
	sudo systemctl restart nginx
	@if [ -n "$(PHP_FPM)" ]; then \
		sudo systemctl restart $(PHP_FPM); \
		echo "Restarted Nginx and $(PHP_FPM)"; \
	else \
		echo "Nginx restarted, but could not detect running php-fpm service."; \
	fi

build:
	$(DOCKER_COMPOSE) -f $(DEV_COMPOSE_FILE) build --no-cache

setup:
	composer install
	php -r "file_exists('.env') || copy('.env.example', '.env');"
	php artisan key:generate
	$(DOCKER_COMPOSE) -f $(DEV_COMPOSE_FILE) up -d
	$(SAIL) artisan migrate:fresh --seed
	$(SAIL) npm install
	$(SAIL) npm run build

test:
	$(SAIL) artisan test

lint:
	$(SAIL) bin pint

migrate:
	$(SAIL) artisan migrate

fresh:
	$(SAIL) artisan migrate:fresh --seed

tinker:
	$(SAIL) artisan tinker

vite:
	$(SAIL) npm run dev

shell:
	$(DOCKER_COMPOSE) -f $(DEV_COMPOSE_FILE) exec $(DEV_SERVICE) bash || $(DOCKER_COMPOSE) -f $(DEV_COMPOSE_FILE) exec $(DEV_SERVICE) sh

artisan:
	$(SAIL) artisan $(c)

composer:
	$(SAIL) composer $(c)

npm:
	$(SAIL) npm $(c)

.PHONY: help up down restart up-dev down-dev restart-dev build-dev up-prod down-prod restart-prod build-prod cert-prod logs-dev logs-prod restart-local build setup test lint migrate fresh tinker vite shell artisan composer npm
