# Laravel PO

[![Tests](https://github.com/webhappens/laravel-po/workflows/Tests/badge.svg)](https://github.com/webhappens/laravel-po/actions)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/webhappens/laravel-po.svg)](https://packagist.org/packages/webhappens/laravel-po)
[![Total Downloads](https://img.shields.io/packagist/dt/webhappens/laravel-po.svg)](https://packagist.org/packages/webhappens/laravel-po)
[![License](https://img.shields.io/packagist/l/webhappens/laravel-po.svg)](https://packagist.org/packages/webhappens/laravel-po)

A Laravel package for synchronizing Laravel PHP translation arrays with PO (Portable Object) files, with optional POEditor integration.

Perfect for teams using translation management services like POEditor, Crowdin, or Lokalise that work with industry-standard gettext PO files.

## Features

- 📤 **Export** Laravel PHP translations to PO files
- 📥 **Import** PO files back to Laravel PHP arrays
- 🔄 **POEditor Integration** - Download translations directly from POEditor API
- 🎯 **Selective Imports** - Filter translations by pattern with `--only` option
- 🔀 **Merge or Replace** - Choose to merge with existing translations or replace them
- 🌐 **Auto-detection** - Automatically detect available languages from your lang directory
- 🧪 **Fully Tested** - Comprehensive test suite with 30+ tests
- ⚙️ **Configurable** - All paths and settings can be customized

## Requirements

- PHP 8.2 or higher
- Laravel 11.x or higher

## Installation

Install the package via Composer:

```bash
composer require webhappens/laravel-po
```

The service provider will be automatically registered via Laravel's package discovery.

### Publish Configuration

Publish the configuration file to customize paths and settings:

```bash
php artisan vendor:publish --tag=po-config
```

This will create a `config/po.php` file where you can configure:

- Export and import directory paths
- Excluded translation groups
- Language definitions
- POEditor API credentials
- Cache clearing callbacks

## Configuration

### Basic Configuration

```php
// config/po.php

return [
    'paths' => [
        'export' => lang_path('export'),  // Where PO files are exported
        'import' => lang_path('import'),  // Where PO files are imported from
        'lang' => lang_path(),            // Root translation directory
    ],

    'excluded_groups' => [
        'auth',
        'pagination',
        'passwords',
        'validation',
    ],

    'languages' => [
        // Leave empty for auto-detection, or define explicitly:
        'en' => ['label' => 'English', 'enabled' => true],
        'fr' => ['label' => 'Français', 'enabled' => true],
        'de' => ['label' => 'Deutsch', 'enabled' => true],
    ],
];
```

### POEditor Integration

If you use POEditor, add these to your `.env` file:

```env
POEDITOR_ENABLED=true
POEDITOR_API_TOKEN=your-api-token-here
POEDITOR_PROJECT_ID=your-project-id-here
```

### Cache Clearing (Optional)

If your application caches compiled translation catalogues, configure a callback to clear the cache after importing:

```php
// config/po.php

'cache' => [
    'clear_callback' => function ($locale) {
        Cache::forget("catalogue:{$locale}");
        File::delete(storage_path("app/cache/catalogue:{$locale}"));
    },
],
```

## Usage

### Export Translations to PO Files

Export all translations for the default language:

```bash
php artisan po:export
```

Export translations for specific languages:

```bash
php artisan po:export fr de es
```

This will create PO files in your configured export directory (default: `lang/export/`):

```
lang/export/
├── fr.po
├── de.po
└── es.po
```

#### Export Options

**Export all enabled languages:**

```bash
php artisan po:export --all
```

**Clear export directory before generating:**

```bash
php artisan po:export --clear
```

### Import PO Files to Laravel

Import all available PO files:

```bash
php artisan po:import
```

Import specific languages:

```bash
php artisan po:import fr de
```

#### Import Options

**Include fuzzy translations:**

```bash
php artisan po:import --fuzzy
```

**Filter to specific translation keys:**

```bash
php artisan po:import --only=actions.*
php artisan po:import --only=messages.* --only=actions.*
```

**Replace existing translations instead of merging:**

```bash
php artisan po:import --replace
```

By default, imports will merge new translations with existing ones. Use `--replace` to completely replace the translation files.

### Download from POEditor

Download PO files from POEditor (requires POEditor configuration):

```bash
# Download all enabled languages
php artisan po:download --all

# Download specific languages
php artisan po:download fr de es
```

Downloaded files will be saved to your import directory. Then run the import command:

```bash
php artisan po:import
```

## Workflow Example

### Working with POEditor

1. **Export your current translations:**
   ```bash
   php artisan po:export
   ```

2. **Upload PO files to POEditor** (manually or via their API)

3. **Translators work on translations in POEditor**

4. **Download updated translations:**
   ```bash
   php artisan po:download --all
   ```

5. **Import the translations:**
   ```bash
   php artisan po:import
   ```

6. **Commit the updated translation files to your repository**

### Working with Translation Agencies

1. **Export translations:**
   ```bash
   php artisan po:export fr de es
   ```

2. **Send PO files** from `lang/export/` to your translation agency

3. **Receive translated PO files** and place them in `lang/import/`

4. **Import the translations:**
   ```bash
   php artisan po:import
   ```

## How It Works

### Placeholder Conversion

The package automatically converts between Laravel and gettext placeholder formats:

**Laravel format (in PHP files):**
```php
'welcome' => 'Hello :name, you have :count messages'
```

**Gettext format (in PO files):**
```
msgid "Hello {name}, you have {count} messages"
```

This conversion happens automatically during export and import.

### Translation Grouping

Laravel translations are organized into groups (files), and the package maintains this structure:

**PHP files:**
```
lang/en/
├── actions.php
├── messages.php
└── errors.php
```

**PO file structure:**
```
msgctxt "actions.save"
msgid "Save"
msgstr "Sauvegarder"

msgctxt "messages.welcome"
msgid "Welcome"
msgstr "Bienvenue"

msgctxt "errors.not_found"
msgid "Not found"
msgstr "Non trouvé"
```

The first part before the dot (e.g., `actions`, `messages`) determines which PHP file the translation belongs to.

### Nested Translations

Nested arrays are automatically flattened to dot notation:

**PHP:**
```php
// lang/en/messages.php
return [
    'user' => [
        'welcome' => 'Welcome :name',
        'goodbye' => 'Goodbye :name',
    ],
];
```

**PO:**
```
msgctxt "messages.user.welcome"
msgid "Welcome {name}"
msgstr "..."

msgctxt "messages.user.goodbye"
msgid "Goodbye {name}"
msgstr "..."
```

## Testing

Run the test suite:

```bash
cd ~/Sites/packages/laravel-po
composer test
```

The package includes comprehensive tests covering:
- Export functionality
- Import functionality with merge/replace modes
- Placeholder conversion
- Fuzzy translation handling
- POEditor API integration (with HTTP mocking - no POEditor account required!)
- Pattern filtering
- Language auto-detection

**Note:** Tests use HTTP mocking for POEditor API calls, so you don't need a POEditor account or project to run the tests.

For detailed testing instructions, see [TESTING.md](TESTING.md).

## Directory Structure

```
your-laravel-app/
└── lang/
    ├── export/          # Generated PO files (add to .gitignore)
    ├── import/          # PO files to be imported (add to .gitignore)
    ├── en/
    │   ├── actions.php
    │   ├── messages.php
    │   └── ...
    ├── fr/
    │   ├── actions.php
    │   ├── messages.php
    │   └── ...
    └── de/
        ├── actions.php
        ├── messages.php
        └── ...
```

**Recommended `.gitignore`:**

```gitignore
/lang/export/*
!/lang/export/.gitignore

/lang/import/*
!/lang/import/.gitignore
```

This keeps the directories tracked while ignoring the PO files themselves.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Security

If you discover any security-related issues, please email hello@webhappens.co.uk instead of using the issue tracker.

## Credits

- [WebHappens](https://webhappens.co.uk)
- Built with [gettext/gettext](https://github.com/php-gettext/Gettext)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
