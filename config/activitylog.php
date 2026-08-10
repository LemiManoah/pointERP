<?php

declare(strict_types=1);

use App\Models\AuditActivity;
use Spatie\Activitylog\Actions\CleanActivityLogAction;
use Spatie\Activitylog\Actions\LogActivityAction;

return [
    'enabled' => env('ACTIVITYLOG_ENABLED', true),
    'clean_after_days' => 365,
    'default_log_name' => 'audit',
    'default_auth_driver' => null,
    'include_soft_deleted_subjects' => true,
    'activity_model' => AuditActivity::class,
    'default_except_attributes' => [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ],
    'buffer' => [
        'enabled' => env('ACTIVITYLOG_BUFFER_ENABLED', false),
    ],
    'actions' => [
        'log_activity' => LogActivityAction::class,
        'clean_log' => CleanActivityLogAction::class,
    ],
];
