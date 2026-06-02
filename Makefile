.PHONY: help build up down logs shell test

COMPOSE := $(shell docker compose version >/dev/null 2>&1 && echo "docker compose" || echo "docker-compose")

help: ## List available commands
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-10s\033[0m %s\n", $$1, $$2}'

build: ## Build the image (needs ~/.composer/auth.json for the private Satis)
	$(COMPOSE) build

up: ## Start web + worker (detached)
	$(COMPOSE) up -d

down: ## Stop services
	$(COMPOSE) down

logs: ## Tail logs
	$(COMPOSE) logs -f

shell: ## Shell into the web container
	-$(COMPOSE) exec app bash

test: ## Run phpunit inside the container
	$(COMPOSE) run --rm app composer test
