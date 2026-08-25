<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Renderer
    |--------------------------------------------------------------------------
    |
    | How the campus .xlsx forms become PDFs. Both options convert the real
    | workbook — neither rebuilds a document from database rows.
    |
    |   auto        LibreOffice when the binary is present, otherwise pure PHP
    |   php         always the pure-PHP writer, which is what shared hosting
    |               without shell access can run
    |   libreoffice only meaningful where the binary exists
    |
    | Set PDF_RENDERER=php locally to see exactly what production produces.
    |
    */

    'renderer' => env('PDF_RENDERER', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | LibreOffice binary
    |--------------------------------------------------------------------------
    |
    | Left blank, the usual install locations are searched. Read through config
    | rather than env() directly so it survives `php artisan config:cache`.
    |
    */

    'libreoffice_path' => env('LIBREOFFICE_PATH'),

];
