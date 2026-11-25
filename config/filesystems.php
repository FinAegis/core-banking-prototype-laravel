<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app'),
            'throw'  => false,
        ],

        'public' => [
            'driver'     => 'local',
            'root'       => storage_path('app/public'),
            'url'        => env('APP_URL') . '/storage',
            'visibility' => 'public',
            'throw'      => false,
        ],

        /*
        | Off-server store for the nightly database backups (BACKUP_DISK=backups).
        | Any S3-compatible service works (Cloudflare R2, Hetzner, Backblaze B2,
        | AWS S3). It has its own credentials, so a token limited to the backup
        | bucket never doubles as the app's file storage key. Write errors are
        | thrown, so a failed upload fails backup:run instead of passing silently.
        */
        'backups' => [
            'driver'                  => 's3',
            'key'                     => env('BACKUP_S3_ACCESS_KEY_ID'),
            'secret'                  => env('BACKUP_S3_SECRET_ACCESS_KEY'),
            'region'                  => env('BACKUP_S3_REGION', 'auto'),
            'bucket'                  => env('BACKUP_S3_BUCKET'),
            'endpoint'                => env('BACKUP_S3_ENDPOINT'),
            'use_path_style_endpoint' => (bool) env('BACKUP_S3_USE_PATH_STYLE_ENDPOINT', true),
            'root'                    => env('BACKUP_S3_ROOT', ''),
            'visibility'              => 'private',
            'throw'                   => true,
        ],

        's3' => [
            'driver'                  => 's3',
            'key'                     => env('AWS_ACCESS_KEY_ID'),
            'secret'                  => env('AWS_SECRET_ACCESS_KEY'),
            'region'                  => env('AWS_DEFAULT_REGION'),
            'bucket'                  => env('AWS_BUCKET'),
            'url'                     => env('AWS_URL'),
            'endpoint'                => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw'                   => false,
        ],

        /*
        |----------------------------------------------------------------------
        | Regional S3 Disks (v3.5.0 — Multi-Region Data Residency)
        |----------------------------------------------------------------------
        |
        | Region-specific S3 buckets for data residency compliance.
        | Each region stores data in the corresponding AWS region.
        |
        */

        's3-eu' => [
            'driver'                  => 's3',
            'key'                     => env('AWS_ACCESS_KEY_ID'),
            'secret'                  => env('AWS_SECRET_ACCESS_KEY'),
            'region'                  => env('AWS_S3_EU_REGION', 'eu-west-1'),
            'bucket'                  => env('AWS_S3_EU_BUCKET', 'finaegis-eu'),
            'url'                     => env('AWS_S3_EU_URL'),
            'endpoint'                => env('AWS_S3_EU_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw'                   => false,
        ],

        's3-us' => [
            'driver'                  => 's3',
            'key'                     => env('AWS_ACCESS_KEY_ID'),
            'secret'                  => env('AWS_SECRET_ACCESS_KEY'),
            'region'                  => env('AWS_S3_US_REGION', 'us-east-1'),
            'bucket'                  => env('AWS_S3_US_BUCKET', 'finaegis-us'),
            'url'                     => env('AWS_S3_US_URL'),
            'endpoint'                => env('AWS_S3_US_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw'                   => false,
        ],

        's3-apac' => [
            'driver'                  => 's3',
            'key'                     => env('AWS_ACCESS_KEY_ID'),
            'secret'                  => env('AWS_SECRET_ACCESS_KEY'),
            'region'                  => env('AWS_S3_APAC_REGION', 'ap-southeast-1'),
            'bucket'                  => env('AWS_S3_APAC_BUCKET', 'finaegis-apac'),
            'url'                     => env('AWS_S3_APAC_URL'),
            'endpoint'                => env('AWS_S3_APAC_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw'                   => false,
        ],

        's3-uk' => [
            'driver'                  => 's3',
            'key'                     => env('AWS_ACCESS_KEY_ID'),
            'secret'                  => env('AWS_SECRET_ACCESS_KEY'),
            'region'                  => env('AWS_S3_UK_REGION', 'eu-west-2'),
            'bucket'                  => env('AWS_S3_UK_BUCKET', 'finaegis-uk'),
            'url'                     => env('AWS_S3_UK_URL'),
            'endpoint'                => env('AWS_S3_UK_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw'                   => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
