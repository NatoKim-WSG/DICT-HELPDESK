<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LegacyTicketImportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_command_preserves_source_created_at_and_uses_default_user(): void
    {
        $requester = User::create([
            'name' => 'Legacy Import User',
            'username' => 'legacy.import',
            'email' => 'legacy-import@example.com',
            'department' => 'DICT',
            'phone' => '09171234567',
            'role' => User::ROLE_CLIENT,
            'password' => 'password',
            'is_active' => true,
        ]);
        Category::create([
            'name' => 'Other',
            'description' => 'General import category',
            'color' => '#6B7280',
            'is_active' => true,
        ]);

        $importPath = storage_path('app/private/imports/legacy-created-at.csv');
        File::ensureDirectoryExists(dirname($importPath));
        File::put($importPath, implode(PHP_EOL, [
            'subject,description,created_at,category,priority,status,name,contact_number,email,province,municipality',
            '"DICT CARAGA - Re-activation of Starlink","Imported from legacy sheet","2025-06-24 15:04:43","Other","high","open","Requester Snapshot","09998887777","requester@example.com","Agusan del Norte","Butuan City"',
            '',
        ]));

        $this->artisan('tickets:import-csv', [
            'path' => 'legacy-created-at.csv',
            '--default-user' => (string) $requester->id,
            '--source-timezone' => 'Asia/Manila',
        ])->assertSuccessful();

        $ticket = Ticket::query()->sole();
        $expectedCreatedAt = Carbon::parse('2025-06-24 15:04:43', 'Asia/Manila')->utc();

        $this->assertTrue($ticket->created_at?->equalTo($expectedCreatedAt));
        $this->assertTrue($ticket->updated_at?->equalTo($expectedCreatedAt));
        $this->assertSame($requester->id, $ticket->user_id);
        $this->assertSame('Requester Snapshot', $ticket->name);
        $this->assertSame('DICT CARAGA - Re-activation of Starlink', $ticket->subject);
    }

    public function test_import_command_rejects_files_without_created_at_column(): void
    {
        $requester = User::create([
            'name' => 'Legacy Import User',
            'username' => 'legacy.import',
            'email' => 'legacy-import@example.com',
            'department' => 'DICT',
            'phone' => '09171234567',
            'role' => User::ROLE_CLIENT,
            'password' => 'password',
            'is_active' => true,
        ]);
        Category::create([
            'name' => 'Other',
            'description' => 'General import category',
            'color' => '#6B7280',
            'is_active' => true,
        ]);

        $importPath = storage_path('app/private/imports/missing-created-at.csv');
        File::ensureDirectoryExists(dirname($importPath));
        File::put($importPath, implode(PHP_EOL, [
            'subject,description,category',
            '"Missing date column","This import should fail","Other"',
            '',
        ]));

        $this->artisan('tickets:import-csv', [
            'path' => 'missing-created-at.csv',
            '--default-user' => (string) $requester->id,
        ])
            ->expectsOutput('Import file must include a created_at column so historical ticket dates are preserved.')
            ->assertFailed();

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_import_command_skips_existing_ticket_numbers_without_update_flag(): void
    {
        $requester = User::create([
            'name' => 'Legacy Import User',
            'username' => 'legacy.import',
            'email' => 'legacy-import@example.com',
            'department' => 'DICT',
            'phone' => '09171234567',
            'role' => User::ROLE_CLIENT,
            'password' => 'password',
            'is_active' => true,
        ]);
        $category = Category::create([
            'name' => 'Other',
            'description' => 'General import category',
            'color' => '#6B7280',
            'is_active' => true,
        ]);

        $existingTicket = new Ticket;
        $existingTicket->timestamps = false;
        $existingTicket->forceFill([
            'ticket_number' => 'TK-LEGACY-0001',
            'name' => 'Existing Snapshot',
            'contact_number' => '09170000000',
            'email' => 'existing@example.com',
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'subject' => 'Existing imported ticket',
            'description' => 'Existing description',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $requester->id,
            'category_id' => $category->id,
            'created_at' => Carbon::parse('2025-06-01 08:00:00', 'Asia/Manila')->utc(),
            'updated_at' => Carbon::parse('2025-06-01 08:00:00', 'Asia/Manila')->utc(),
        ]);
        $existingTicket->save();

        $importPath = storage_path('app/private/imports/skip-existing.csv');
        File::ensureDirectoryExists(dirname($importPath));
        File::put($importPath, implode(PHP_EOL, [
            'ticket_number,subject,description,created_at,category',
            '"TK-LEGACY-0001","Updated subject that should be skipped","Updated description","2025-06-24 15:04:43","Other"',
            '',
        ]));

        $this->artisan('tickets:import-csv', [
            'path' => 'skip-existing.csv',
            '--default-user' => (string) $requester->id,
            '--source-timezone' => 'Asia/Manila',
        ])->assertSuccessful();

        $existingTicket->refresh();

        $this->assertSame('Existing imported ticket', $existingTicket->subject);
        $this->assertDatabaseCount('tickets', 1);
    }
}
