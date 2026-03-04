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

    public function test_modal_views_keep_shared_modal_structure_classes(): void
    {
        $modalViewFiles = [
            'admin/reports/index.blade.php',
            'admin/tickets/index.blade.php',
            'admin/tickets/show.blade.php',
            'admin/users/index.blade.php',
            'admin/users/show.blade.php',
            'client/tickets/show.blade.php',
            'legal/modal.blade.php',
        ];

        foreach ($modalViewFiles as $modalViewFile) {
            $contents = File::get(resource_path('views/'.$modalViewFile));

            $this->assertStringContainsString(
                'app-modal-root',
                $contents,
                "Expected {$modalViewFile} to define at least one modal root."
            );
            $this->assertStringContainsString(
                'app-modal-overlay',
                $contents,
                "Expected {$modalViewFile} to define a modal overlay."
            );
            $this->assertStringContainsString(
                'app-modal-panel',
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
