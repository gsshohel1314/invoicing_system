<?php

return [
    // Default mPDF configuration
    // Character encoding (must for Bengali/Unicode)
    'mode' => 'utf-8',

    // Page size
    'format' => 'A4',

    // Default text size in points
    'default_font_size' => 12,

    // Default font for whole document
    'default_font' => 'solaimanlipi',

    // Folder for temporary files (font cache, etc.)
    'tempDir' => storage_path('tmp'),

    // Folders where mPDF looks for font files
    'fontDir' => [
        base_path('vendor/mpdf/mpdf/ttfonts'), // built-in fonts
        resource_path('fonts'), // your custom fonts
    ],

    // Register custom font
    'fontdata' => [
        'solaimanlipi' => [
            'R' => 'SolaimanLipi.ttf', // Regular font file
            'useOTL' => 0xFF, // Enable full OpenType Layout (fixes Bengali conjuncts)
            'useKashida' => 75, // Improves Bengali/Arabic text justification
        ],
    ],

    // Replace missing characters with similar ones
    'useSubstitutions' => true,

    // Better support for Asian scripts
    'useAdobeCJK' => true,

    // Auto detect script (Bengali, Latin, etc.)
    'autoScriptToLang' => true,

    // Auto choose font based on language/script
    'autoLangToFont' => true,

    // Prevent page breaks inside tables/paragraphs
    'use_kwt' => true,

    // Shrink large tables to fit page width
    'shrink_tables_to_fit' => 1,

    // Page margins in mm
    'margin_left'       => 10,
    'margin_right'      => 10,
    'margin_top'        => 15,
    'margin_bottom'     => 30,
];