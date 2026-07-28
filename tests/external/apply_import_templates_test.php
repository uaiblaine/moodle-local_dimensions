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

use core_competency\template;
use core_external\external_api;
use local_dimensions\helper;
use local_dimensions\local\template_csv_serializer;
use local_dimensions\local\template_import_analyser;
use local_dimensions\local\template_import_verdict;

/**
 * Tests for the learning plan CSV apply web service.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\apply_import_templates
 */
final class apply_import_templates_test extends \advanced_testcase {
    /**
     * A ticked row is written, and the response comes back through clean_returnvalue() with a
     * repainted row carrying its outcome.
     *
     * @return void
     */
    public function test_apply_writes_the_ticked_row(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->prepare_site();

        $csv = $this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-1', 'shortname' => 'Induction'],
            ['rowtype' => 'link', 'template_idnumber' => 'TPL-1', 'framework_idnumber' => 'FW-1',
             'competency_idnumber' => 'C1', 'sortorder' => '0'],
        ]);
        $draftitemid = $this->upload($csv);
        $contextid = (int) \context_system::instance()->id;

        $result = apply_import_templates::execute(
            $draftitemid,
            $contextid,
            'comma',
            'UTF-8',
            false,
            $this->tick($csv, ['t0'])
        );
        $result = external_api::clean_returnvalue(apply_import_templates::execute_returns(), $result);

        $this->assertSame(1, $result['counts']['created']);
        $this->assertSame(0, $result['counts']['failed']);
        $this->assertCount(1, $result['results']);
        $this->assertSame(template_import_verdict::OUTCOME_CREATED, $result['results'][0]['outcome']);
        $this->assertStringContainsString('data-itemkey="t0"', $result['results'][0]['html']);
        $this->assertNotFalse(template::get_record(['shortname' => 'Induction']));
    }

    /**
     * The run is logged once, with its per-outcome tally.
     *
     * @return void
     */
    public function test_the_run_is_logged(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->prepare_site();

        $csv = $this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-2', 'shortname' => 'Logged'],
        ]);
        $draftitemid = $this->upload($csv);

        $sink = $this->redirectEvents();
        apply_import_templates::execute(
            $draftitemid,
            (int) \context_system::instance()->id,
            'comma',
            'UTF-8',
            false,
            $this->tick($csv, ['t0'])
        );
        $events = $sink->get_events();
        $sink->close();

        $runs = array_filter($events, static function ($event): bool {
            return $event instanceof \local_dimensions\event\templates_imported;
        });
        $this->assertCount(1, $runs);
        $run = reset($runs);
        $this->assertSame(1, $run->other['created']);
        $this->assertNotEmpty($run->get_description());
    }

    /**
     * A user without templatemanage cannot apply an import.
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
        apply_import_templates::execute($draftitemid, (int) \context_system::instance()->id, 'comma', 'UTF-8', false, []);
    }

    /**
     * Applying nothing is legal and writes nothing.
     *
     * @return void
     */
    public function test_an_empty_selection_writes_nothing(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->prepare_site();
        $draftitemid = $this->upload($this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-4', 'shortname' => 'Untouched'],
        ]));

        $before = $DB->count_records('competency_template');
        $result = apply_import_templates::execute(
            $draftitemid,
            (int) \context_system::instance()->id,
            'comma',
            'UTF-8',
            false,
            []
        );
        $result = external_api::clean_returnvalue(apply_import_templates::execute_returns(), $result);

        $this->assertSame([], $result['results']);
        $this->assertSame(0, $result['counts']['created']);
        $this->assertSame($before, $DB->count_records('competency_template'));
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
     * Build the selections a preview of this file would produce for the named items.
     *
     * @param string $csv The CSV text.
     * @param array $itemkeys The item keys to tick.
     * @return array
     */
    private function tick(string $csv, array $itemkeys): array {
        $plan = (new template_import_analyser(
            template_csv_serializer::parse($csv),
            \context_system::instance(),
            false
        ))->analyse();

        $selections = [];
        foreach ($itemkeys as $itemkey) {
            $item = $plan->get_item($itemkey);
            $links = [];
            foreach ($item['links'] as $linkkey => $link) {
                if (!empty($link['preselected'])) {
                    $links[] = $linkkey;
                }
            }
            $selections[] = [
                'itemkey' => $itemkey,
                'verdict' => $item['verdict'],
                'fingerprint' => $item['fingerprint'],
                'remedy' => 'none',
                'links' => $links,
            ];
        }
        return $selections;
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
