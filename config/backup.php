<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Passphrase
    |--------------------------------------------------------------------------
    |
    | The archive is AES-256 encrypted with this. Set BACKUP_PASSWORD to
    | something long and keep it somewhere other than the server — an encrypted
    | archive whose password sits in the .env next to it protects nothing if the
    | server itself is what was lost.
    |
    | Falling back to APP_KEY means a backup taken without configuring anything
    | is still encrypted. It also means restoring needs the APP_KEY from the
    | time the backup was taken, so set BACKUP_PASSWORD before you rely on it.
    |
    */
    'passphrase' => env('BACKUP_PASSWORD') ?: env('APP_KEY'),

    /*
    |--------------------------------------------------------------------------
    | How many to keep
    |--------------------------------------------------------------------------
    |
    | Older archives are deleted after a successful run, locally and remotely.
    |
    */
    'keep' => (int) env('BACKUP_KEEP', 7),

    /*
    |--------------------------------------------------------------------------
    | Off-site copy
    |--------------------------------------------------------------------------
    |
    | A backup on the same disk as the thing it backs up is not much of a
    | backup. Set BACKUP_DISK to a configured filesystem — 's3' pointed at
    | Cloudflare R2 or Backblaze B2 — and each archive is copied there.
    |
    | This needs the S3 adapter, which is not installed by default because it
    | pulls in the whole AWS SDK:
    |
    |     composer require league/flysystem-aws-s3-v3
    |
    | Leave it null and archives stay local; the command says so rather than
    | letting you believe there is a copy somewhere that there is not.
    |
    */
    'disk' => env('BACKUP_DISK'),

    /*
    |--------------------------------------------------------------------------
    | What goes in
    |--------------------------------------------------------------------------
    |
    | Folders under storage/app worth keeping. Everything else there is either
    | derived (the PDF cache), scratch (temp) or framework bookkeeping, and
    | rebuilds itself.
    |
    */
    'include' => [
        'private',
        'public',
    ],

];
