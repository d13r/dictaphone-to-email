<?php

return [

    'drive' => env('DRIVE'),

    'folders' => [
        'VOICE/Home' => [
            ['email' => env('MAIL_HOME_ADDRESS'), 'name' => env('MAIL_HOME_NAME')],
        ],
        'VOICE/Work' => [
            ['email' => env('MAIL_WORK_ADDRESS'), 'name' => env('MAIL_WORK_NAME')],
        ],
    ],

];
