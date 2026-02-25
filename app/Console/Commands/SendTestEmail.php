<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestEmail extends Command
{
    protected $signature = 'mail:test {to : Recipient email address}';

    protected $description = 'Send a test email using the current mail configuration.';

    public function handle(): int
    {
        $to = trim((string) $this->argument('to'));

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address provided.');

            return self::FAILURE;
        }

        try {
            Mail::raw(
                'This is a live SMTP test email from DICT Helpdesk sent at '.now()->toDateTimeString().'.',
                function ($message) use ($to) {
                    $message->to($to)
                        ->subject('DICT Helpdesk SMTP Test Email');
                }
            );
        } catch (\Throwable $exception) {
            $this->error('Email send failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Test email sent successfully to '.$to.'.');

        return self::SUCCESS;
    }
}
