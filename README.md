# Code Quality Package

## Installation

Make sure that you removed old code quality and pipeline configuration files or folders, e.g. `rector.php`, `.php-cs-fixer.php`, `.phpstan`.

Make sure, your `composer.json` does not have any dev-requirements on explicit code-quality packages (like `phpunit/phpunit`, `rector/rector` and so on).

Make sure your `.gitignore` file includes the folder `vendor` and the file `composer.lock`.

```
vendor
composer.lock
```

Install the coding-standards package.

```
composer require --dev --with-all-dependencies mediatis/coding-standards
```

Run the kickstart script to install configuration files.

```
./vendor/bin/mediatis-coding-standards-setup
```

## Usage - Check

Run all checks:

```
composer run-script ci
```

Run group checks:

```
# all php tests and code quality checks
composer run-script ci:php

# all php tests
composer run-script ci:php:tests

# all php code quality checks
composer run-script ci:php:static

# all composer-related checks
composer run-script ci:composer
```

Run specific checks:

```
composer run-script ci:composer:normalize
composer run-script ci:composer:psr-verify
composer run-script ci:composer:validate
composer run-script ci:php:lint
composer run-script ci:php:rector
composer run-script ci:php:cs-fixer
composer run-script ci:php:stan
composer run-script ci:php:tests:unit
composer run-script ci:php:tests:integration
```

## Usage - Fix

Run all fixes:

```
composer run-script fix
```

Run group fixes:

```
composer run-script fix:php
composer run-script fix:composer
```

Run specific fixes:

```
composer run-script fix:php:rector
composer run-script fix:php:cs
composer run-script fix:composer:normalize
```
