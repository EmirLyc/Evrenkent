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
    | Kapak Görselleri Disk'i
    |--------------------------------------------------------------------------
    |
    | Kitap/dergi sayısı kapak görselleri hep bu disk'e yazılır/okunur (kod
    | tarafında hiçbir yerde 'public' sabit yazılmaz, hep bu config okunur).
    | Yerelde varsayılan 'public' (yerel disk). Railway'de container diski her
    | deploy'da sıfırlandığı için COVERS_DISK=bucket_public — dosyalar Railway
    | Bucket'ında, tarayıcıya /media/... üzerinden (MediaController) veriliyor,
    | çünkü Railway Bucket'ları herkese açık olamıyor. Bkz. DEPLOYMENT.md.
    |
    */

    'covers_disk' => env('COVERS_DISK', 'public'),

    // Metne gömülü belgeler (Faz F2) — herkese açık OLMAYAN disk: dosyalar sadece
    // DocumentController üzerinden, içeriği okuyabilen kişiye gösteriliyor.
    // Railway'de DOCUMENTS_DISK=bucket_private (aynı bucket, ayrı klasör, /media'dan verilmez).
    'documents_disk' => env('DOCUMENTS_DISK', 'local'),

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
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        // Railway Bucket (S3 uyumlu, herkese kapalı) — tek bucket, iki klasör.
        // Kapaklar ve defter görselleri: url() uygulamanın /media adresini veriyor.
        'bucket_public' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'auto'),
            'bucket' => env('AWS_BUCKET'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'root' => 'public',
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/media',
            'throw' => false,
            'report' => false,
        ],

        // Gömülü belgeler: sadece DocumentController üzerinden, yetkili okura.
        'bucket_private' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'auto'),
            'bucket' => env('AWS_BUCKET'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'root' => 'private',
            'throw' => false,
            'report' => false,
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
