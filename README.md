# Code Quality Package

## Installation

```
composer require --dev mediatis/coding-standards
```

## rector.php

```
<?php

use Mediatis\CodingStandards\Php\RectorSetup;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    RectorSetup::setup($rectorConfig, __DIR__);
};
```

## .php-cs-fixer.php

```
<?php

return \Mediatis\CodingStandards\Php\CsFixerSetup::setup();
```

## phpstan.neon

```
includes:
	- vendor/mediatis/coding-standards/phpstan.neon
```

## composer.json

```
"scripts": {
  "ci": [
    "@ci:test",
    "@ci:code-quality"
  ],
  "ci:test": [
    "@ci:php:test"
  ],
  "ci:code-quality": [
    "@ci:php:code-quality"
  ],
  "ci:php": [
    "@ci:php:test",
    "@ci:php:code-quality"
  ],

  "ci:php:test": [
    "@ci:php:test-unit",
    "@ci:php:test-integration"
  ],
  "ci:php:test-unit": "./vendor/bin/phpunit --bootstrap vendor/autoload.php --testdox --colors=always tests/Unit/",
  "ci:php:test-integration": "./vendor/bin/phpunit --bootstrap vendor/autoload.php --testdox --colors=always tests/Integration/",

  "ci:php:code-quality": [
    "@ci:php:rector",
    "@ci:php:cs-fixer",
    "@ci:php:stan"
  ],
  "ci:php:cs-fixer": "./vendor/bin/php-cs-fixer fix --config .php-cs-fixer.php -v --dry-run --using-cache no --diff src tests",
  "ci:php:rector": "./vendor/bin/rector --dry-run",
  "ci:php:stan": "php -d memory_limit=228M ./vendor/bin/phpstan --no-progress --no-interaction analyse",

  "fix": [
    "@fix:php"
  ],
  "fix:php": [
    "@fix:php:rector",
    "@fix:php:cs-fixer"
  ],
  "fix:php:rector": "./vendor/bin/rector",
  "fix:php:cs-fixer": "./vendor/bin/php-cs-fixer fix --config .php-cs-fixer.php src tests"
}
```

## .github/workflows/ci.yml

```
name: Code Check

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  code-check:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php-versions: ['8.1', '8.2']

    steps:
      - uses: actions/checkout@v2

      - name: Setup PHP with composer v2
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php-versions }}
          tools: composer:v2

      - name: Validate composer.json and composer.lock
        run: composer validate --strict

      - name: Cache Composer packages
        id: composer-cache
        uses: actions/cache@v2
        with:
          path: vendor
          key: ${{ runner.os }}-php-${{ hashFiles('**/composer.lock') }}
          restore-keys: |
            ${{ runner.os }}-php-

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress

      - name: Tests
        run: composer run-script ci:test

      - name: Code Quality
        run: composer run-script ci:code-quality
```
