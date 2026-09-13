#!/usr/bin/env bash
# Cloud-friendly Symfony unit grind (bounded memory). See grind-phpunit-components.php.
# Agents: fix all failures/errors in scope before returning (skips OK); prefer --filter per bundle.
set -euo pipefail
cd "$(dirname "$0")/.."

if [[ ! -f vendor/autoload.php ]]; then
  composer install --no-interaction
fi
if [[ ! -d .phpunit ]]; then
  ./phpunit install
fi

exec php .github/grind-phpunit-components.php "$@"
