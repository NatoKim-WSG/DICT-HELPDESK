<?php

namespace App\Providers;

use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Policies\AttachmentPolicy;
use App\Policies\TicketPolicy;
use App\Policies\TicketReplyPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Attachment::class, AttachmentPolicy::class);
        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(TicketReply::class, TicketReplyPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        $this->deleteStaleViteHotFile();
    }

    private function deleteStaleViteHotFile(): void
    {
        $hotFile = public_path('hot');

        if (! is_file($hotFile)) {
            return;
        }

        $hotUrl = trim((string) @file_get_contents($hotFile));
        if ($hotUrl === '') {
            @unlink($hotFile);

            return;
        }

        $parsedUrl = parse_url($hotUrl);
        if (! is_array($parsedUrl) || empty($parsedUrl['host'])) {
            @unlink($hotFile);

            return;
        }

        $scheme = $parsedUrl['scheme'] ?? 'http';
        $host = (string) $parsedUrl['host'];
        $port = (int) ($parsedUrl['port'] ?? ($scheme === 'https' ? 443 : 80));

        $socket = @fsockopen($host, $port, $errno, $errstr, 0.25);
        if ($socket === false) {
            @unlink($hotFile);

            return;
        }

        fclose($socket);
    }
}
