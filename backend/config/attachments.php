<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage disk
    |--------------------------------------------------------------------------
    |
    | Defaults to the private "local" disk. To use S3, first install the driver:
    |
    |     composer require league/flysystem-aws-s3-v3
    |
    | then set ATTACHMENTS_DISK=s3 and the AWS_* credentials in .env. The s3
    | disk is present in config/filesystems.php but will fail at runtime until
    | that package is installed.
    |
    */
    'disk' => env('ATTACHMENTS_DISK', 'local'),

    /** Maximum upload size per file, in kilobytes. */
    'max_size_kb' => (int) env('ATTACHMENTS_MAX_SIZE_KB', 10240),

    /*
    | Allowed extensions. Deliberately excludes executable and script types;
    | uploads are validated against this list rather than trusting mime alone.
    */
    'allowed_extensions' => [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'txt', 'csv', 'md', 'zip',
    ],

];
