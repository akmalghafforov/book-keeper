# Makefile for Laravel Sail project

SAIL := ./vendor/bin/sail

.DEFAULT_GOAL := help

help:
	@echo "Usage: make [target]"
	@echo ""
	@echo "Targets:"
	@echo "  up          Start the Docker containers"
	@echo "  down        Stop the Docker containers"
	@echo "  build       Build the Docker containers"
	@echo "  setup       Initial project setup (composer, env, key, migrate, npm)"
	@echo "  test        Run PHPUnit tests"
	@echo "  lint        Run Laravel Pint for code formatting"
	@echo "  migrate     Run database migrations"
	@echo "  fresh       Refresh database and run seeds"
	@echo "  tinker      Open Laravel Tinker"
	@echo "  vite        Run Vite development server"
	@echo "  shell       Enter the application container shell"
	@echo "  artisan c=  Run an artisan command (e.g., make artisan c='make:model Client')"
	@echo "  composer c= Run a composer command (e.g., make composer c='require laravel/breeze')"
	@echo "  npm c=      Run an npm command (e.g., make npm c='install')"

up:
	$(SAIL) up -d

down:
	$(SAIL) down

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

.PHONY: help up down build setup test lint migrate fresh tinker vite shell artisan composer npm
