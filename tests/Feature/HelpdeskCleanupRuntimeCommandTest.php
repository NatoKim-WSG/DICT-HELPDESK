<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Category;
use App\Models\CredentialHandoff;
use App\Models\SystemLog;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HelpdeskCleanupRuntimeCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_runtime_removes_transient_artifacts_prunes_stale_handoffs_and_deletes_zero_byte_orphans(): void
    {
        config()->set('observability.system_logs.retention_days', 30);
        config()->set('helpdesk.cleanup.backup_retention_days', 30);
        config()->set('helpdesk.cleanup.seeded_client_credentials_retention_days', 14);
        config()->set('helpdesk.cleanup.import_retention_days', 7);

        File::put(base_path('.phpunit.result.cache'), 'temp-cache');
        File::ensureDirectoryExists(storage_path('phpstan/cache'));
        File::put(storage_path('phpstan/cache/stale.php'), '<?php return [];');
        File::ensureDirectoryExists(storage_path('framework/testing/disks/demo'));
        File::put(storage_path('framework/testing/disks/demo/stale.txt'), 'stale');

        config()->set('helpdesk.attachments_disk', 'cleanup-local');
        config()->set('filesystems.disks.cleanup-local', [
            'driver' => 'local',
            'root' => storage_path('app/cleanup-local'),
            'throw' => false,
        ]);

        Storage::disk('cleanup-local')->put('attachments/orphan-zero.txt', '');
        Storage::disk('cleanup-local')->put('attachments/keep-zero.txt', '');

        $user = User::create([
            'name' => 'Cleanup User',
            'email' => 'cleanup-user@example.com',
            'phone' => '09170000000',
            'department' => 'iOne',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Cleanup Category',
            'description' => 'Cleanup category',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'name' => 'Cleanup Ticket',
            'contact_number' => '09175550001',
            'email' => 'cleanup-ticket@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Cleanup ticket',
            'description' => 'Cleanup ticket body',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);

        $reply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => 'Attachment anchor',
            'is_internal' => false,
        ]);

        Attachment::create([
            'filename' => 'keep-zero.txt',
            'original_filename' => 'keep-zero.txt',
            'file_path' => 'attachments/keep-zero.txt',
            'mime_type' => 'text/plain',
            'file_size' => 0,
            'attachable_type' => TicketReply::class,
            'attachable_id' => $reply->id,
        ]);

        $staleHandoff = CredentialHandoff::create([
            'target_user_id' => $user->id,
            'issued_by_user_id' => $user->id,
            'temporary_password' => 'stale-token',
            'expires_at' => Carbon::now()->subMinute(),
            'revealed_at' => null,
            'consumed_at' => null,
        ]);

        Cache::put('managed-user-password-handoff:stale-token', 'secret', now()->addMinutes(10));

        $oldSystemLog = SystemLog::create([
            'category' => 'cleanup',
            'event_type' => 'cleanup.old',
            'description' => 'Old cleanup log',
            'metadata' => ['age' => 'old'],
            'occurred_at' => now()->subDays(45),
        ]);

        $recentSystemLog = SystemLog::create([
            'category' => 'cleanup',
            'event_type' => 'cleanup.recent',
            'description' => 'Recent cleanup log',
            'metadata' => ['age' => 'recent'],
            'occurred_at' => now()->subDays(5),
        ]);

        Storage::disk('local')->put('backups/old-backup.json', '{}');
        Storage::disk('local')->put('backups/recent-backup.json', '{}');
        Storage::disk('local')->put('seeded-client-credentials/old-export.csv', 'email,password');
        Storage::disk('local')->put('seeded-client-credentials/recent-export.csv', 'email,password');
        Storage::disk('local')->put('imports/old-import.csv', 'subject,description');
        Storage::disk('local')->put('imports/recent-import.csv', 'subject,description');

        touch(Storage::disk('local')->path('backups/old-backup.json'), now()->subDays(45)->getTimestamp());
        touch(Storage::disk('local')->path('backups/recent-backup.json'), now()->subDays(5)->getTimestamp());
        touch(Storage::disk('local')->path('seeded-client-credentials/old-export.csv'), now()->subDays(20)->getTimestamp());
        touch(Storage::disk('local')->path('seeded-client-credentials/recent-export.csv'), now()->subDays(2)->getTimestamp());
        touch(Storage::disk('local')->path('imports/old-import.csv'), now()->subDays(10)->getTimestamp());
        touch(Storage::disk('local')->path('imports/recent-import.csv'), now()->subDays(2)->getTimestamp());

        $this->artisan('helpdesk:cleanup-runtime')
            ->expectsOutput('Helpdesk runtime cleanup complete.')
            ->assertSuccessful();

        $this->assertFileDoesNotExist(base_path('.phpunit.result.cache'));
        $this->assertFileDoesNotExist(storage_path('phpstan/cache/stale.php'));
        $this->assertFileDoesNotExist(storage_path('framework/testing/disks/demo/stale.txt'));
        $this->assertDatabaseMissing('credential_handoffs', [
            'id' => $staleHandoff->id,
        ]);
        $this->assertFalse(Cache::has('managed-user-password-handoff:stale-token'));
        $this->assertFalse(Storage::disk('cleanup-local')->exists('attachments/orphan-zero.txt'));
        $this->assertTrue(Storage::disk('cleanup-local')->exists('attachments/keep-zero.txt'));
        $this->assertDatabaseMissing('system_logs', [
            'id' => $oldSystemLog->id,
        ]);
        $this->assertDatabaseHas('system_logs', [
            'id' => $recentSystemLog->id,
        ]);
        $this->assertFalse(Storage::disk('local')->exists('backups/old-backup.json'));
        $this->assertTrue(Storage::disk('local')->exists('backups/recent-backup.json'));
        $this->assertFalse(Storage::disk('local')->exists('seeded-client-credentials/old-export.csv'));
        $this->assertTrue(Storage::disk('local')->exists('seeded-client-credentials/recent-export.csv'));
        $this->assertFalse(Storage::disk('local')->exists('imports/old-import.csv'));
        $this->assertTrue(Storage::disk('local')->exists('imports/recent-import.csv'));
    }
}
