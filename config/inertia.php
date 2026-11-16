<?php

return [
    'pages' => [
        'ensure_pages_exist' => false,
        // Kapitalisasi harus sama dengan resolver React, termasuk pada filesystem Linux.
        'paths' => [resource_path('js/Pages')],
        'extensions' => ['tsx'],
    ],
];
