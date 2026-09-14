<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class EmailErrorReport
{
    public function handle(Throwable $exception): void
    {
        $recipient = config('services.error_notification.email');

        if (config('services.error_notification.enabled') !== true
            || ! is_string($recipient)
            || trim($recipient) === ''
            || ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500)) {
            return;
        }

        $body = implode("\n", [
            'Application: '.config('app.name'),
            'Environment: '.config('app.env'),
            'Time: '.now()->toDateTimeString(),
            'Error: '.$exception::class,
            'Message: '.$exception->getMessage(),
        ]);

        try {
            Mail::raw($body, function ($message) use ($recipient, $exception): void {
                $message->to($recipient)
                    ->subject(sprintf('[%s] Application error: %s', config('app.name'), $exception::class));
            });
        } catch (Throwable $mailException) {
            Log::error('Unable to send application error email.', [
                'mail_error' => $mailException->getMessage(),
                'original_error' => $exception::class,
            ]);
        }
    }
}