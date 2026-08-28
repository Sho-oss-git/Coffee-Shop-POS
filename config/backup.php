<?php

use App\Services\BackupService;

return [
    /*
    |--------------------------------------------------------------------------
    | Backup Storage
    |--------------------------------------------------------------------------
    |
    | Backups are stored outside the web root, inside the "local" disk, so they
    | are never served directly by the web server. Downloads go through an
    | authenticated controller.
    |
    */
    'disk' => 'local',

    'path' => 'backups',

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | When the number of backups exceeds this value, the oldest backups are
    | deleted automatically. Set to 0 to disable automatic cleanup. Manual
    | deletion is always available regardless of this setting.
    |
    */
    'retention' => 10,

    /*
    |--------------------------------------------------------------------------
    | Database Dump / Restore Binaries
    |--------------------------------------------------------------------------
    |
    | The backup system uses the MySQL client tools (mysqldump / mysql) that
    | ship with XAMPP (and any standard MySQL/MariaDB install). Paths are
    | resolved automatically and safely:
    |
    |   1. Explicit override via config or environment variable.
    |   2. Common XAMPP / system locations.
    |   3. The system PATH (via `where` / `which`).
    |
    | No database credentials are read from here — they are always taken from
    | the Laravel database configuration so they never get hard-coded.
    |
    */
    'mysqldump_path' => env('MYSQLDUMP_PATH'),
    'mysql_path' => env('MYSQL_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Application Files Included In A Backup
    |--------------------------------------------------------------------------
    |
    | These map backup-internal folders to real application directories. Only
    | files inside these directories are ever restored, preventing path
    | traversal or arbitrary file writes.
    |
    */
    'restore_directories' => [
        'storage' => 'app/public',
        'public' => '',
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Metadata
    |--------------------------------------------------------------------------
    */
    'application_name' => 'JC66 Coffee Shop Management System',
    'format' => 'jc66-backup',
    'version' => '1.0',
];
