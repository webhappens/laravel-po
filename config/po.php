<?php

return [

    /*
    |--------------------------------------------------------------------------
    | File Paths
    |--------------------------------------------------------------------------
    |
    | Configure the paths used for exporting and importing PO files.
    | These paths are fully configurable to support testing and custom setups.
    |
    */

    'paths' => [
        // Directory where exported PO files will be saved
        'export' => lang_path('export'),

        // Directory where PO files to be imported are located
        'import' => lang_path('import'),

        // Root directory containing your Laravel translation files
        'lang' => lang_path(),
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Translation Groups
    |--------------------------------------------------------------------------
    |
    | Translation groups to exclude from export. Typically these are Laravel
    | framework translations (auth, pagination, validation) that are managed
    | by the laravel-lang packages and don't need to be translated separately.
    |
    */

    'excluded_groups' => [
        'auth',
        'pagination',
        'passwords',
        'validation',
    ],

    /*
    |--------------------------------------------------------------------------
    | Language Configuration
    |--------------------------------------------------------------------------
    |
    | Define the languages supported by your application. If left empty or null,
    | languages will be auto-detected by scanning directories in your lang path.
    |
    | Format:
    | 'locale' => [
    |     'label' => 'Display Name',
    |     'enabled' => true|false,
    | ],
    |
    */

    'languages' => [
        // Leave empty for auto-detection, or define explicitly:
        // 'en' => ['label' => 'English', 'enabled' => true],
        // 'fr' => ['label' => 'Français', 'enabled' => true],
        // 'de' => ['label' => 'Deutsch', 'enabled' => true],
    ],

    /*
    |--------------------------------------------------------------------------
    | POEditor Integration
    |--------------------------------------------------------------------------
    |
    | Configure POEditor API integration for downloading translations.
    | The po:download command will use these credentials.
    |
    */

    'poeditor' => [
        'enabled' => env('POEDITOR_ENABLED', false),
        'api_token' => env('POEDITOR_API_TOKEN'),
        'project_id' => env('POEDITOR_PROJECT_ID'),
    ],

];
