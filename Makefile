# Makefile for Laravel project

SAIL := ./vendor/bin/sail
PHP_FPM := $(shell systemctl list-units --type=service --state=running | grep -oE 'php[0-9.]+-fpm' | head -n 1)

.DEFAULT_GOAL := help

help:
	@echo "Usage: make [target]"
	@echo ""
	@echo "Targets:"
	@echo "  up            Start the Docker containers (Sail)"
	@echo "  down          Stop the Docker containers (Sail)"
	@echo "  restart       Restart the Docker containers (Sail)"
	@echo "  restart-local Restart local Nginx and PHP-FPM daemons"
	@echo "  build         Build the Docker containers"
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
	$(SAIL) up -d

down:
	$(SAIL) down

restart:
	$(SAIL) restart

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
	$(SAIL) build --no-cache

setup:
	composer install
	php -r "file_exists('.env') || copy('.env.example', '.env');"
	php artisan key:generate
	$(SAIL) up -d
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
	$(SAIL) shell

artisan:
	$(SAIL) artisan $(c)

composer:
	$(SAIL) composer $(c)

npm:
	$(SAIL) npm $(c)

.PHONY: help up down restart restart-local build setup test lint migrate fresh tinker vite shell artisan composer npm
