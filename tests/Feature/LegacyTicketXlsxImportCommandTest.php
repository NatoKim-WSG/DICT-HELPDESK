<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class LegacyTicketXlsxImportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_xlsx_import_command_preserves_excel_date_and_time_columns_without_1899_artifacts(): void
    {
        $requester = User::create([
            'name' => 'Legacy Import User',
            'username' => 'legacy.import.xlsx',
            'email' => 'legacy-import-xlsx@example.com',
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

        $importPath = storage_path('app/private/imports/legacy-tracker.xlsx');
        File::ensureDirectoryExists(dirname($importPath));
        $this->writeMinimalTrackerWorkbook($importPath);

        $this->artisan('tickets:import-xlsx', [
            'paths' => ['legacy-tracker.xlsx'],
            '--default-user' => (string) $requester->id,
            '--source-timezone' => 'Asia/Manila',
        ])->assertSuccessful();

        $ticket = Ticket::query()->sole();

        $this->assertSame('Kawasaki Client - Set up Access point for starlink', $ticket->subject);
        $this->assertSame('closed', $ticket->status);
        $this->assertTrue($ticket->created_at?->equalTo(Carbon::parse('2026-03-17 14:00:00', 'Asia/Manila')->utc()));
        $this->assertTrue($ticket->resolved_at?->equalTo(Carbon::parse('2026-03-17 17:00:00', 'Asia/Manila')->utc()));
        $this->assertTrue($ticket->closed_at?->equalTo(Carbon::parse('2026-03-17 17:00:00', 'Asia/Manila')->utc()));
        $this->assertStringContainsString('Date Received: 03/17/2026', (string) $ticket->description);
        $this->assertStringContainsString('Time Received: 2:00:00 PM', (string) $ticket->description);
        $this->assertStringContainsString('Completed At: 03/17/2026 5:00:00 PM', (string) $ticket->description);
        $this->assertStringNotContainsString('1899', (string) $ticket->description);
    }

    public function test_xlsx_import_command_preserves_duplicate_source_rows_when_not_updating_existing(): void
    {
        $requester = User::create([
            'name' => 'Legacy Import User',
            'username' => 'legacy.import.xlsx.dup',
            'email' => 'legacy-import-xlsx-dup@example.com',
            'department' => 'DICT',
            'phone' => '09171234568',
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

        $importPath = storage_path('app/private/imports/legacy-tracker-dup.xlsx');
        File::ensureDirectoryExists(dirname($importPath));
        $this->writeMinimalTrackerWorkbook($importPath);

        $this->artisan('tickets:import-xlsx', [
            'paths' => ['legacy-tracker-dup.xlsx'],
            '--default-user' => (string) $requester->id,
            '--source-timezone' => 'Asia/Manila',
        ])->assertSuccessful();

        $this->artisan('tickets:import-xlsx', [
            'paths' => ['legacy-tracker-dup.xlsx'],
            '--default-user' => (string) $requester->id,
            '--source-timezone' => 'Asia/Manila',
        ])->assertSuccessful();

        $this->assertSame(2, Ticket::query()->count());
    }

    private function writeMinimalTrackerWorkbook(string $path): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->fail('Unable to create XLSX fixture.');
        }

        try {
            $zip->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
  <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
</Types>
XML);

            $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML);

            $zip->addFromString('xl/workbook.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="March 2026" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>
XML);

            $zip->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>
XML);

            $zip->addFromString('xl/styles.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <numFmts count="2">
    <numFmt numFmtId="164" formatCode="mm/dd/yyyy"/>
    <numFmt numFmtId="165" formatCode="[$-409]h:mm:ss\ am/pm"/>
  </numFmts>
  <fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>
  <fills count="1"><fill><patternFill patternType="none"/></fill></fills>
  <borders count="1"><border/></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="3">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>
    <xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>
  </cellXfs>
</styleSheet>
XML);

            $zip->addFromString('xl/sharedStrings.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="14" uniqueCount="14">
  <si><t>Date Received</t></si>
  <si><t>Time Received</t></si>
  <si><t>Issue / Request Reported via</t></si>
  <si><t>Name of Project</t></si>
  <si><t>Issue / Request Description</t></si>
  <si><t>Date Resolved</t></si>
  <si><t>Time Resolved</t></si>
  <si><t>Issue / Request Resolution</t></si>
  <si><t>Attended by</t></si>
  <si><t>via viber call</t></si>
  <si><t>Kawasaki Client</t></si>
  <si><t>Set up Access point for starlink</t></si>
  <si><t>Assist personel for troubleshooting</t></si>
  <si><t>John Arnold Carrasco</t></si>
</sst>
XML);

            $zip->addFromString('xl/worksheets/sheet1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>
    <row r="1">
      <c r="B1" t="s"><v>0</v></c>
      <c r="C1" t="s"><v>1</v></c>
      <c r="D1" t="s"><v>2</v></c>
      <c r="E1" t="s"><v>3</v></c>
      <c r="F1" t="s"><v>4</v></c>
      <c r="G1" t="s"><v>5</v></c>
      <c r="H1" t="s"><v>6</v></c>
      <c r="I1" t="s"><v>7</v></c>
      <c r="J1" t="s"><v>8</v></c>
    </row>
    <row r="2">
      <c r="B2" s="1"><v>46098</v></c>
      <c r="C2" s="2"><v>0.5833333333</v></c>
      <c r="D2" t="s"><v>9</v></c>
      <c r="E2" t="s"><v>10</v></c>
      <c r="F2" t="s"><v>11</v></c>
      <c r="G2" s="1"><v>46098</v></c>
      <c r="H2" s="2"><v>0.7083333333</v></c>
      <c r="I2" t="s"><v>12</v></c>
      <c r="J2" t="s"><v>13</v></c>
    </row>
  </sheetData>
</worksheet>
XML);
        } finally {
            $zip->close();
        }
    }
}
