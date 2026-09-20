<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Inertia Page Components
    |--------------------------------------------------------------------------
    |
    | Proyek menyimpan page React pada resources/js/Pages. Kapitalisasi ini
    | harus dikonfigurasi secara eksplisit karena runner Linux bersifat
    | case-sensitive, sedangkan default paket Inertia adalah resources/js/pages.
    |
    */
    'pages' => [
        'ensure_pages_exist' => false,
        'paths' => [
            resource_path('js/Pages'),
        ],
        'extensions' => [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ],
    ],
];
