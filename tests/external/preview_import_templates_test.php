<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_dimensions\external;

use core_external\external_api;
use local_dimensions\helper;
use local_dimensions\local\template_csv_serializer;

/**
 * Tests for the learning plan CSV import preview web service.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\preview_import_templates
 */
final class preview_import_templates_test extends \advanced_testcase {
    /**
     * The preview comes back through clean_returnvalue() with every count declared, and it
     * writes nothing.
     *
     * @return void
     */
    public function test_preview_returns_the_declared_structure(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->prepare_site();

        $draftitemid = $this->upload($this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-1', 'shortname' => 'Induction'],
            ['rowtype' => 'link', 'template_idnumber' => 'TPL-1', 'framework_idnumber' => 'FW-1',
             'competency_idnumber' => 'C1', 'sortorder' => '0'],
        ]));

        $templatesbefore = $DB->count_records('competency_template');
        $result = preview_import_templates::execute($draftitemid, (int) \context_system::instance()->id);
        $result = external_api::clean_returnvalue(preview_import_templates::execute_returns(), $result);

        $this->assertSame($templatesbefore, $DB->count_records('competency_template'));
        $this->assertSame(
            ['total', 'create', 'update', 'insync', 'skip', 'conflict', 'blocked', 'orphanlink',
                'selectable', 'preselected', 'links', 'linksmatched', 'linksunresolved'],
            array_keys($result['counts'])
        );
        $this->assertSame(1, $result['counts']['total']);
        $this->assertSame(1, $result['counts']['create']);
        $this->assertSame(1, $result['counts']['linksmatched']);
        $this->assertTrue($result['canapply']);
        $this->assertSame([], $result['missingframeworks']);
        // The row markup carries the contract the apply step reads back.
        $this->assertStringContainsString('data-itemkey="t0"', $result['html']);
        $this->assertStringContainsString('data-fingerprint="', $result['html']);
        $this->assertStringContainsString('data-verdict="create"', $result['html']);
    }

    /**
     * A structure the file needs and the site does not have is named in the payload, and the
     * plan that needs it cannot be applied.
     *
     * @return void
     */
    public function test_a_missing_structure_is_reported_and_blocks_apply(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->prepare_site();

        $draftitemid = $this->upload($this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-2', 'shortname' => 'Needs structure'],
            ['rowtype' => 'link', 'template_idnumber' => 'TPL-2', 'framework_idnumber' => 'ABSENT',
             'framework_shortname' => 'Absent', 'competency_idnumber' => 'C9', 'sortorder' => '0'],
        ]));

        $result = preview_import_templates::execute($draftitemid, (int) \context_system::instance()->id);
        $result = external_api::clean_returnvalue(preview_import_templates::execute_returns(), $result);

        $this->assertSame(1, $result['counts']['blocked']);
        $this->assertFalse($result['canapply']);
        $this->assertSame([['idnumber' => 'ABSENT', 'shortname' => 'Absent']], $result['missingframeworks']);
    }

    /**
     * A user without templatemanage in the target context cannot preview an import there.
     *
     * @return void
     */
    public function test_a_plain_user_is_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->prepare_site();
        $draftitemid = $this->upload($this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-3', 'shortname' => 'Anything'],
        ]));

        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\required_capability_exception::class);
        preview_import_templates::execute($draftitemid, (int) \context_system::instance()->id);
    }

    /**
     * A context id that names nothing comes back as a readable error rather than an unhandled
     * exception from deep inside the context API.
     *
     * @return void
     */
    public function test_a_bogus_contextid_is_a_readable_error(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->prepare_site();
        $draftitemid = $this->upload($this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-4', 'shortname' => 'Anything'],
        ]));

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/context/i');
        preview_import_templates::execute($draftitemid, -99);
    }

    /**
     * A draft area that no longer holds the file gets its own message rather than an empty
     * preview that looks like "this file does nothing".
     *
     * @return void
     */
    public function test_a_missing_draft_file_is_reported(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->prepare_site();

        $this->expectException(\moodle_exception::class);
        preview_import_templates::execute(file_get_unused_draft_itemid(), (int) \context_system::instance()->id);
    }

    /**
     * Seed the plugin fields, one structure and one competency.
     *
     * @return void
     */
    private function prepare_site(): void {
        set_config('enabled', 1, 'core_competency');
        helper::ensure_custom_fields_exist(helper::AREA_LP);

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework(['shortname' => 'FW', 'idnumber' => 'FW-1']);
        $generator->create_competency([
            'competencyframeworkid' => (int) $framework->get('id'),
            'shortname' => 'First',
            'idnumber' => 'C1',
        ]);
    }

    /**
     * Put a CSV into the current user's draft area, as the upload form would.
     *
     * @param string $content The CSV text.
     * @return int The draft item id.
     */
    private function upload(string $content): int {
        global $USER;

        $draftitemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => 'templates.csv',
        ], $content);
        return $draftitemid;
    }

    /**
     * Build a CSV whose header is the real export header, from partial row maps.
     *
     * @param array $rows Each an array of column token => cell value; absent tokens are empty.
     * @return string
     */
    private function csv(array $rows): string {
        $headers = template_csv_serializer::headers(false);
        $lines = [$this->encode($headers)];
        foreach ($rows as $row) {
            $cells = [];
            foreach ($headers as $header) {
                $cells[] = (string) ($row[$header] ?? '');
            }
            $lines[] = $this->encode($cells);
        }
        return implode("\n", $lines) . "\n";
    }

    /**
     * Encode one CSV row, quoting every cell.
     *
     * @param array $cells The cell values.
     * @return string
     */
    private function encode(array $cells): string {
        return implode(',', array_map(static function ($cell): string {
            return '"' . str_replace('"', '""', (string) $cell) . '"';
        }, $cells));
    }
}
