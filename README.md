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

Then generate the configuration files (`.php-cs-fixer.php`, `rector.php`, `phpstan.neon`, `.gitlab-ci.yml`, `.github/workflows/ci.yml`, the `composer ci` / `composer fix` scripts and the `src` / `tests` folders) with the **`mediatis-php-coding-standards`** Claude Code skill.

In Claude Code, ask something like *"set up mediatis coding standards for this package"*. The skill can:

- **scaffold** a brand-new package from scratch,
- **add** the config to an existing package (merging into `composer.json` without clobbering your keys), or
- **reset** the generated files (overwrite drifted CI/config), the `composer.json` scripts being merged rather than replaced.

These are the same jobs the removed `mediatis-coding-standards-setup` binary used to do.

## Usage - Check

Run all checks:

```
composer ci
```

Run group checks:

```
# all php tests and code quality checks
composer ci:php

# all php tests
composer ci:php:tests

# all php code quality checks
composer ci:php:static

# all composer-related checks
composer ci:composer
```

Run specific checks:

```
composer ci:composer:normalize
composer ci:composer:psr-verify
composer ci:composer:validate
composer ci:php:lint
composer ci:php:rector
composer ci:php:cs-fixer
composer ci:php:stan
composer ci:php:tests:unit
composer ci:php:tests:integration
```

## Usage - Fix

Run all fixes:

```
composer fix
```

Run group fixes:

```
composer fix:php
composer fix:composer
```

Run specific fixes:

```
composer fix:php:rector
composer fix:php:cs
composer fix:composer:normalize
```
