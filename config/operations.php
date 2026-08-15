<?php

declare(strict_types=1);

return [
    'notifications' => [
        'email_enabled' => (bool) env('OPERATIONAL_EMAIL_NOTIFICATIONS', false),
    ],
];
