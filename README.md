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
        "@ci:static",
        "@ci:dynamic"
    ],
    "ci:composer:normalize": "@composer normalize --no-check-lock --dry-run",
    "ci:composer:psr-verify": "@composer dumpautoload --optimize --strict-psr",
    "ci:composer:static": [
        "@ci:composer:psr-verify",
        "@ci:composer:normalize"
    ],
    "ci:dynamic": [
        "@ci:tests"
    ],
    "ci:php": [
        "@ci:php:static",
        "@ci:php:dynamic"
    ],
    "ci:php:cs-fixer": "./vendor/bin/php-cs-fixer fix --config .php-cs-fixer.php -v --dry-run --using-cache no --diff src tests",
    "ci:php:dynamic": [
        "@ci:php:tests"
    ],
    "ci:php:lint": "find .*.php *.php src tests -name '*.php' -print0 | xargs -r -0 -n 1 -P 4 php -l",
    "ci:php:rector": "./vendor/bin/rector --dry-run",
    "ci:php:stan": "php -d memory_limit=228M ./vendor/bin/phpstan --no-progress --no-interaction analyse",
    "ci:php:static": [
        "@ci:php:rector",
        "@ci:php:cs-fixer",
        "@ci:php:lint",
        "@ci:php:stan"
    ],
    "ci:php:tests": [
        "@ci:php:tests:unit",
        "@ci:php:tests:integration"
    ],
    "ci:php:tests:integration": "./vendor/bin/phpunit --bootstrap vendor/autoload.php --testdox --colors=always tests/Integration/",
    "ci:php:tests:unit": "./vendor/bin/phpunit --bootstrap vendor/autoload.php --testdox --colors=always tests/Unit/",
    "ci:static": [
        "@ci:composer:static",
        "@ci:php:static"
    ],
    "ci:tests": [
        "@ci:php:tests"
    ],
    "fix": [
        "@fix:composer",
        "@fix:php"
    ],
    "fix:composer": [
        "@fix:composer:normalize"
    ],
    "fix:composer:normalize": "@composer normalize --no-check-lock",
    "fix:php": [
        "@fix:php:rector",
        "@fix:php:cs"
    ],
    "fix:php:cs": "./vendor/bin/php-cs-fixer fix --config .php-cs-fixer.php src tests",
    "fix:php:rector": "./vendor/bin/rector"
},
"scripts-descriptions": {
    "ci": "Runs all dynamic and static code checks.",
    "ci:composer": "Runs all dynamic and static composer checks",
    "ci:composer:normalize": "Checks the composer.json.",
    "ci:composer:psr-verify": "Verifies PSR-4 namespace correctness.",
    "ci:dynamic": "Runs all dynamic tests.",
    "ci:php": "Runs all static checks for the PHP files.",
    "ci:php:cs-fixer": "Checks the code style with the PHP Coding Standards Fixer (PHP-CS-Fixer).",
    "ci:php:dynamic": "Run all PHP tests",
    "ci:php:lint": "Lints the PHP files for syntax errors.",
    "ci:php:rector": "Checks the code style with the TYPO3 rector (typo3-rector).",
    "ci:php:stan": "Checks the PHP types using PHPStan.",
    "ci:php:static": "Runs all static code checks on PHP code.",
    "ci:php:tests": "Run all PHPUnit tests (unit and integration)",
    "ci:php:tests:integration": "Runs all PHPUnit integration tests.",
    "ci:php:tests:unit": "Runs all PHPUnit unit tests.",
    "ci:static": "Runs all static code checks (syntax, style, types).",
    "ci:tests": "Runs all PHPUnit tests (unit and integration).",
    "fix": "Runs all fixers.",
    "fix:composer": "Runs all fixers for the composer.json file.",
    "fix:composer:normalize": "Normalizes composer.json file content.",
    "fix:php": "Runs all fixers for the PHP code.",
    "fix:php:cs": "Fixes the code style with PHP-CS-Fixer.",
    "fix:php:rector": "Fixes code structures with PHP Rector."
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
  code-quality:
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

      - name: Static Code Checks
        run: composer run-script ci:static

      - name: Dynamic Code Checks
        run: composer run-script ci:dynamic
```
