.PHONY: install test test-static test-modx up down logs

install:
	composer install

test:
	vendor/bin/phpunit

test-static:
	composer verify-static-contract
	composer verify-generated-model

test-modx:
	chmod +x scripts/test-modx.sh
	./scripts/test-modx.sh

up:
	docker compose up -d --build

down:
	docker compose down -v

logs:
	docker compose logs -f
