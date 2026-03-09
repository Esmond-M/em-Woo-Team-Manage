<?php

declare(strict_types=1);

namespace emWooTeamManage\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use emWooTeamManage\init_plugin\Classes\TeamUserImporter;

/**
 * Tests for TeamUserImporter::readCSV().
 *
 * readCSV() is pure PHP (fopen/fgetcsv) with zero WordPress dependencies,
 * so these tests run without any WP environment or mocking.
 */
class ReadCsvTest extends TestCase
{
    private TeamUserImporter $importer;

    protected function setUp(): void
    {
        $this->importer = new TeamUserImporter();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeTempCsv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'emwtm_csv_test_');
        file_put_contents($path, $contents);
        return $path;
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    #[Test]
    public function returns_false_for_nonexistent_file(): void
    {
        $result = $this->importer->readCSV('/does/not/exist_emwtm.csv');

        $this->assertFalse($result);
    }

    #[Test]
    public function yields_header_and_data_rows_from_valid_csv(): void
    {
        $csv = $this->makeTempCsv(
            "email_address,first_name,last_name\n" .
            "john@example.com,John,Doe\n" .
            "jane@example.com,Jane,Smith\n"
        );

        $rows = iterator_to_array($this->importer->readCSV($csv));

        $this->assertCount(3, $rows);
        $this->assertSame(['email_address', 'first_name', 'last_name'], $rows[0]);
        $this->assertSame(['john@example.com', 'John', 'Doe'], $rows[1]);
        $this->assertSame(['jane@example.com', 'Jane', 'Smith'], $rows[2]);

        unlink($csv);
    }

    #[Test]
    public function yields_nothing_for_empty_file(): void
    {
        $csv = $this->makeTempCsv('');

        $rows = iterator_to_array($this->importer->readCSV($csv));

        $this->assertEmpty($rows);

        unlink($csv);
    }

    #[Test]
    public function works_with_custom_delimiter(): void
    {
        $csv = $this->makeTempCsv(
            "email_address;first_name;last_name\n" .
            "john@example.com;John;Doe\n"
        );

        $rows = iterator_to_array($this->importer->readCSV($csv, ';'));

        $this->assertCount(2, $rows);
        $this->assertSame(['email_address', 'first_name', 'last_name'], $rows[0]);
        $this->assertSame(['john@example.com', 'John', 'Doe'], $rows[1]);

        unlink($csv);
    }

    #[Test]
    public function data_rows_with_missing_fields_are_yielded_with_empty_values(): void
    {
        // The importer yields ALL rows; validation/skipping happens in user_import_submission().
        // This characterises what the importer hands to the import loop.
        $csv = $this->makeTempCsv(
            "email_address,first_name,last_name\n" .
            "test@example.com,,\n" .   // first_name and last_name empty
            ",Jane,Smith\n"            // email empty
        );

        $rows = iterator_to_array($this->importer->readCSV($csv));

        $this->assertCount(3, $rows);
        $this->assertSame('', $rows[1][1]); // first_name is empty → import loop rejects
        $this->assertSame('', $rows[2][0]); // email is empty → import loop rejects

        unlink($csv);
    }

    #[Test]
    public function yields_exactly_fifty_rows_when_csv_has_fifty_data_rows(): void
    {
        // Build a CSV with header + exactly 50 data rows to verify no off-by-one
        // in the generator itself (row limit is enforced by user_import_submission, not readCSV).
        $lines = ['email_address,first_name,last_name'];
        for ($i = 1; $i <= 50; $i++) {
            $lines[] = "user{$i}@example.com,First{$i},Last{$i}";
        }
        $csv = $this->makeTempCsv(implode("\n", $lines) . "\n");

        $rows = iterator_to_array($this->importer->readCSV($csv));

        // header + 50 data rows = 51
        $this->assertCount(51, $rows);

        unlink($csv);
    }
}
