# Testing Guide

## Running the Tests

### Requirements

- PHP 8.2+
- Composer dependencies installed

### Installation

If you haven't already, install the package dependencies:

```bash
cd ~/Sites/packages/laravel-syncpo
composer install
```

### Running All Tests

```bash
cd ~/Sites/packages/laravel-syncpo
composer test
```

Or directly with PHPUnit:

```bash
vendor/bin/phpunit
```

### Running Specific Test Files

```bash
vendor/bin/phpunit tests/Feature/ExportCommandTest.php
vendor/bin/phpunit tests/Feature/ImportCommandTest.php
vendor/bin/phpunit tests/Feature/PoeditorDownloadCommandTest.php
```

### Running Individual Tests

```bash
vendor/bin/phpunit --filter testMethodName
```

For example:

```bash
vendor/bin/phpunit --filter it_exports_translations_to_po_file
```

## Do I Need a POEditor Project?

**No!** All POEditor tests use HTTP mocking via Laravel's `Http::fake()` facade. This means:

- No real API calls are made
- No POEditor account or project required
- Tests run completely offline
- API responses are mocked in the test files

The `PoeditorDownloadCommandTest.php` file simulates all POEditor API interactions without connecting to the actual service.

## Test Structure

```
tests/
├── Feature/
│   ├── ExportCommandTest.php       # 9 tests for export functionality
│   ├── ImportCommandTest.php       # 15 tests for import functionality
│   └── PoeditorDownloadCommandTest.php  # 11 tests for POEditor download
├── Fixtures/
│   ├── translations/   # Sample PHP translation files
│   │   ├── actions.php
│   │   └── messages.php
│   └── po-files/       # Sample PO files
│       ├── fr.po
│       └── de.po
└── TestCase.php        # Base test class
```

## What the Tests Cover

### ExportCommandTest
- Basic export functionality
- Placeholder conversion (`:placeholder` → `{placeholder}`)
- Excluded translation groups
- Nested translations
- Specific language export
- Auto-detection of languages
- Empty values handling

### ImportCommandTest
- Basic import functionality
- Placeholder conversion (`{placeholder}` → `:placeholder`)
- Translation grouping by file
- Merge vs replace modes
- Fuzzy translation handling
- Pattern filtering (`--only` option)
- Cache clearing callbacks
- Directory creation

### PoeditorDownloadCommandTest
- POEditor integration enabled/disabled checks
- API credential validation
- File downloading (mocked)
- Multiple language downloads
- Error handling
- HTTP request failures

## Current Test Status

✅ **All 32 tests passing!**

Latest test run results:
- **32 tests total**
- **100 assertions**
- **0 failures**
- **0 errors**

The package has comprehensive test coverage with all core functionality verified.

## Continuous Testing

While developing, you can use PHPUnit's watch mode (requires `phpunit-watcher`):

```bash
composer require --dev spatie/phpunit-watcher
vendor/bin/phpunit-watcher watch
```

This will automatically re-run tests when files change.

## Debugging Tests

To see more verbose output:

```bash
vendor/bin/phpunit --verbose
```

To stop on first failure:

```bash
vendor/bin/phpunit --stop-on-failure
```

## Writing New Tests

1. Create test files in `tests/Feature/`
2. Extend `WebHappens\LaravelPoSync\Tests\TestCase`
3. Use helper methods:
   - `createTranslationFile($locale, $group, $translations)`
   - `createPoFile($locale, $content)`
   - `getExportedPoFile($locale)`
   - `getGeneratedTranslations($locale, $group)`
4. Test commands with: `$this->artisan('po-sync:command')`

Example:

```php
<?php

namespace WebHappens\LaravelPoSync\Tests\Feature;

use WebHappens\LaravelPoSync\Tests\TestCase;

class MyNewTest extends TestCase
{
    /** @test */
    public function it_does_something()
    {
        $this->createTranslationFile('en', 'test', ['hello' => 'world']);

        $this->artisan('po-sync:export')->assertSuccessful();

        $content = $this->getExportedPoFile('en');
        $this->assertStringContainsString('hello', $content);
    }
}
```
