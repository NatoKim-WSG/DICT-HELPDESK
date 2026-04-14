<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class UiConsistencyGuardTest extends TestCase
{
    public function test_theme_initializer_is_shared_across_layouts(): void
    {
        $layoutFiles = [
            'layouts/app.blade.php',
            'auth/login.blade.php',
            'legal/public-layout.blade.php',
        ];

        foreach ($layoutFiles as $layoutFile) {
            $contents = File::get(resource_path('views/'.$layoutFile));

            $this->assertStringContainsString(
                "@include('partials.theme-initializer')",
                $contents,
                "Expected {$layoutFile} to use the shared theme initializer partial."
            );
        }
    }

    public function test_theme_initializer_partial_uses_external_script_asset(): void
    {
        $contents = File::get(resource_path('views/partials/theme-initializer.blade.php'));

        $this->assertStringContainsString(
            "asset('js/theme-initializer.js')",
            $contents,
            'Expected the shared theme initializer partial to use the CSP-safe external script asset.'
        );
        $this->assertStringNotContainsString("window.localStorage.getItem('ione_theme')", $contents);
    }

    public function test_modal_views_keep_shared_modal_structure_classes(): void
    {
        $modalViewFiles = [
            'admin/reports/partials/shell/volume-chart-modal.blade.php',
            'admin/tickets/index.blade.php',
            'admin/tickets/partials/show-modals.blade.php',
            'admin/users/index.blade.php',
            'admin/users/show.blade.php',
            'client/tickets/partials/show-resolve-modal.blade.php',
            'client/tickets/partials/show-attachment-modal.blade.php',
            'client/tickets/partials/show-delete-reply-modal.blade.php',
            'legal/modal.blade.php',
        ];

        foreach ($modalViewFiles as $modalViewFile) {
            $contents = File::get(resource_path('views/'.$modalViewFile));
            $usesSharedUserModals = str_contains($contents, "@include('admin.users.partials.account-modals'");
            $expectedModalRoot = $usesSharedUserModals ? "@include('admin.users.partials.account-modals'" : 'app-modal-root';
            $expectedModalOverlay = $usesSharedUserModals ? "@include('admin.users.partials.account-modals'" : 'app-modal-overlay';
            $expectedModalPanel = $usesSharedUserModals ? "@include('admin.users.partials.account-modals'" : 'app-modal-panel';

            $this->assertStringContainsString(
                $expectedModalRoot,
                $contents,
                "Expected {$modalViewFile} to define at least one modal root."
            );
            $this->assertStringContainsString(
                $expectedModalOverlay,
                $contents,
                "Expected {$modalViewFile} to define a modal overlay."
            );
            $this->assertStringContainsString(
                $expectedModalPanel,
                $contents,
                "Expected {$modalViewFile} to define a modal panel."
            );
        }
    }

    public function test_reports_page_uses_modalkit_binding_for_volume_modal(): void
    {
        $contents = File::get(resource_path('js/pages/admin-reports-page.js'));

        $this->assertStringContainsString('window.ModalKit.bind(modal', $contents);
        $this->assertStringNotContainsString('fallbackOpen', $contents);
        $this->assertStringNotContainsString('fallbackClose', $contents);
    }
}
