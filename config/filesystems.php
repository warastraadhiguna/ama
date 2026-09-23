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

        // Same bucket/credentials as 's3', but with a browser-reachable
        // endpoint — for generating temporaryUrl()s for the Web Admin
        // evidence viewer when a deployment DOES use real S3/R2 and needs
        // the Docker-internal-hostname split described below. Not
        // currently referenced by app code (Web Admin now calls
        // Storage::temporaryUrl() on the *default* disk, disk-agnostic —
        // see ActivityMonitoringController); kept configured for whenever
        // a deployment adds real object storage. In local dev, the app
        // container talks to MinIO over the Docker-internal hostname
        // (AWS_ENDPOINT=http://minio:9000), which a presigned URL opened in
        // the developer's own browser can't resolve; AWS_ENDPOINT_PUBLIC
        // points at the same MinIO reachable from the host instead. In
        // staging/production (real S3/R2), both endpoints are the same
        // public DNS name, so this distinction is a no-op — set
        // AWS_ENDPOINT_PUBLIC = AWS_ENDPOINT.
        //
        // Honest gap as of 2026-09-23: MinIO's standalone server/client
        // *binary* downloads were discontinued/archived upstream (both
        // dl.min.io and GitHub Releases now 404/410) — breaks a native
        // (non-Docker) install exactly like this. Local dev is unaffected:
        // its Docker image (quay.io/minio/minio) is a separate
        // distribution channel and still pulls/runs fine.
        's3_public' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT_PUBLIC', env('AWS_ENDPOINT')),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
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
