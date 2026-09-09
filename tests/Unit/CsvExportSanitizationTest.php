<?php

declare(strict_types=1);

namespace emWooTeamManage\Tests\Unit;

use Brain\Monkey;
use emWooTeamManage\init_plugin\Classes\TeamAjaxHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CsvExportSanitizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    #[Test]
    public function prefixes_formula_like_cells_without_changing_ordinary_values(): void
    {
        $this->assertSame("'=SUM(1+1)", TeamAjaxHandler::sanitize_csv_cell('=SUM(1+1)'));
        $this->assertSame("'+CMD(...)", TeamAjaxHandler::sanitize_csv_cell('+CMD(...)'));
        $this->assertSame("'-1+2", TeamAjaxHandler::sanitize_csv_cell('-1+2'));
        $this->assertSame("'@something", TeamAjaxHandler::sanitize_csv_cell('@something'));
        $this->assertSame('Normal Name', TeamAjaxHandler::sanitize_csv_cell('Normal Name'));
        $this->assertSame('person@example.com', TeamAjaxHandler::sanitize_csv_cell('person@example.com'));
    }
}