set positional-arguments := true

export LOCAL_UID := `id -u`
export LOCAL_GID := `id -g`

# List available recipes.
default:
    @just --list

# Build the PHP CLI development image.
build:
    docker compose build php

# Install Composer dependencies in the PHP container.
install:
    docker compose run --build --rm php composer install --no-interaction

# Run all tests, a test file/directory, or additional PHPUnit arguments.
test *args:
    docker compose run --build --rm php vendor/bin/phpunit "$@"

# Run static analysis or pass additional PHPStan arguments.
analyse *args:
    docker compose run --build --rm php vendor/bin/phpstan analyse "$@"

# Fix code style or pass additional PHP-CS-Fixer arguments.
fix *args:
    docker compose run --build --rm php vendor/bin/php-cs-fixer fix "$@"

# Check code style without modifying files, showing required changes.
check-style *args:
    @just fix --dry-run --diff "$@"
