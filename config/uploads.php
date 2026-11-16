<?php

return [
    'default_max_kb' => env('SAKIP_UPLOAD_DEFAULT_MAX_KB', 10240),
    'allowed_extensions' => env('SAKIP_UPLOAD_ALLOWED_EXTENSIONS', 'pdf,docx,xlsx,jpg,jpeg,png'),
    'max_kb' => env('SAKIP_UPLOAD_MAX_KB', 102400),
];
