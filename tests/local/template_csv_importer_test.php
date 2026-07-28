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

namespace local_dimensions\local;

use core_competency\template;
use core_competency\template_competency;
use local_dimensions\helper;

/**
 * Tests for the learning plan CSV importer: the only write path of the transfer.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\local\template_csv_importer
 */
final class template_csv_importer_test extends \advanced_testcase {
    /**
     * Only the ticked rows are written, and the untouched ones leave no trace.
     *
     * @return void
     */
    public function test_only_the_ticked_rows_are_written(): void {
        global $DB;

        $this->prepare_site();
        $csv = $this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-A', 'shortname' => 'Wanted'],
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-B', 'shortname' => 'Unwanted'],
        ]);

        $results = $this->apply($csv, false, $this->tick($csv, false, ['t0']));

        $this->assertSame(template_import_verdict::OUTCOME_CREATED, $results[0]['outcome']);
        $this->assertSame(1, $DB->count_records('competency_template'));
        $this->assertNotFalse(template::get_record(['shortname' => 'Wanted']));
        $this->assertFalse(template::get_record(['shortname' => 'Unwanted']));
    }

    /**
     * The identity column is back-filled on create, so importing the same file twice is an
     * update rather than a duplicate. This is the whole point of promoting it to a column.
     *
     * @return void
     */
    public function test_the_second_import_of_a_file_is_not_a_duplicate(): void {
        global $DB;

        $this->prepare_site();
        $csv = $this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-A', 'shortname' => 'Once'],
        ]);

        $this->apply($csv, false, $this->tick($csv, false, ['t0']));
        $this->assertSame(1, $DB->count_records('competency_template'));

        /* Second run: the row now matches the template it created and changes nothing, so it is
           in sync — selectable, unticked, and an update rather than a second template. */
        $second = $this->apply($csv, false, $this->tick($csv, false, ['t0']));
        $this->assertSame(
            template_import_verdict::OUTCOME_UPDATED,
            $second[0]['outcome'],
            'second run: ' . json_encode($second[0])
        );
        $this->assertSame(1, $DB->count_records('competency_template'));
    }

    /**
     * A projection that moved between the preview and the apply is refused, and nothing is
     * written for it.
     *
     * @return void
     */
    public function test_a_stale_fingerprint_is_refused(): void {
        global $DB;

        $this->prepare_site();
        $csv = $this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-A', 'shortname' => 'Fresh'],
        ]);
        $selections = $this->tick($csv, false, ['t0']);
        $selections[0]['fingerprint'] = 'staleaaaa0000';

        $before = $DB->count_records('competency_template');
        $results = $this->apply($csv, false, $selections);

        $this->assertSame(template_import_verdict::OUTCOME_CHANGED, $results[0]['outcome']);
        $this->assertSame($before, $DB->count_records('competency_template'));
    }

    /**
     * A selection whose row is no longer in the file is reported as gone, not as an error.
     *
     * @return void
     */
    public function test_a_vanished_row_is_gone(): void {
        $this->prepare_site();
        $csv = $this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-A', 'shortname' => 'Only row'],
        ]);

        $results = $this->apply($csv, false, [[
            'itemkey' => 't7',
            'verdict' => template_import_verdict::VERDICT_CREATE,
            'fingerprint' => 'whatever',
            'remedy' => 'none',
            'links' => [],
        ]]);

        $this->assertSame(template_import_verdict::OUTCOME_GONE, $results[0]['outcome']);
    }

    /**
     * A refused item does not abort the run and leaves no transaction open — the selection after
     * it must still be written in the same request.
     *
     * @return void
     */
    public function test_a_refused_item_does_not_abort_the_run(): void {
        global $DB;

        $this->prepare_site();
        $csv = $this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-A', 'shortname' => 'First'],
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-B', 'shortname' => 'Second'],
        ]);
        $selections = $this->tick($csv, false, ['t0', 't1']);
        // Point the first selection at a remedy the item does not offer: it is refused, and the
        // second selection must still be written.
        $selections[0]['remedy'] = template_import_verdict::REMEDY_ADOPT;

        $results = $this->apply($csv, false, $selections);

        $this->assertSame(template_import_verdict::OUTCOME_CHANGED, $results[0]['outcome']);
        $this->assertSame(template_import_verdict::OUTCOME_CREATED, $results[1]['outcome']);
        $this->assertNotFalse(template::get_record(['shortname' => 'Second']));

        /* The property that matters is that no transaction was left open and none was force-rolled
           back, either of which poisons every later write in the request. is_transaction_started()
           cannot express it: advanced_testcase::setUp() wraps each test in its own delegated
           transaction, so it is ALWAYS true here. Writing again after the run is what proves it. */
        $after = $this->getDataGenerator()->get_plugin_generator('core_competency')
            ->create_template(['shortname' => 'Written after the run']);
        $this->assertNotFalse(template::get_record(['id' => (int) $after->get('id')]));
        // Two, not three: the refused row wrote nothing, which is what this test is about.
        $this->assertSame(2, $DB->count_records('competency_template'));
    }

    /**
     * Competency links land in file order, and an update that keeps extra links renumbers the
     * whole final set rather than colliding with the retained rows' own sortorder.
     *
     * @return void
     */
    public function test_link_order_survives_an_update_that_keeps_extras(): void {
        $site = $this->prepare_site();
        $extra = $site['generator']->create_competency([
            'competencyframeworkid' => (int) $site['framework']->get('id'),
            'shortname' => 'Kept',
            'idnumber' => 'C-KEPT',
        ]);
        $stored = $site['generator']->create_template(['shortname' => 'Has extras', 'description' => '']);
        $this->set_template_idnumber((int) $stored->get('id'), 'TPL-K');
        $site['generator']->create_template_competency([
            'templateid' => (int) $stored->get('id'),
            'competencyid' => (int) $extra->get('id'),
        ]);

        $csv = $this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-K', 'shortname' => 'Has extras'],
            ['rowtype' => 'link', 'template_idnumber' => 'TPL-K', 'framework_idnumber' => 'FW-1',
             'competency_idnumber' => 'C2', 'sortorder' => '0'],
            ['rowtype' => 'link', 'template_idnumber' => 'TPL-K', 'framework_idnumber' => 'FW-1',
             'competency_idnumber' => 'C1', 'sortorder' => '1'],
        ]);

        $applied = $this->apply($csv, true, $this->tick($csv, true, ['t0']));

        $ordered = [];
        foreach (template_competency::list_competencies((int) $stored->get('id')) as $competency) {
            $ordered[] = (string) $competency->get('idnumber');
        }
        // The file's own order first, then the link that was kept because nothing is ever removed.
        $this->assertSame(['C2', 'C1', 'C-KEPT'], $ordered, 'apply result: ' . json_encode($applied));
    }

    /**
     * The past-due-date remedies do what they say: clearing drops the date, and the row is
     * written rather than blocked.
     *
     * @return void
     */
    public function test_the_clearduedate_remedy_writes_the_row(): void {
        $this->prepare_site();
        $csv = $this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-D', 'shortname' => 'Old deadline',
             'duedate' => '2001-06-30'],
        ]);
        $selections = $this->tick($csv, false, ['t0']);
        $selections[0]['remedy'] = template_import_verdict::REMEDY_CLEARDUEDATE;

        $results = $this->apply($csv, false, $selections);
        $written = template::get_record(['shortname' => 'Old deadline']);

        $this->assertSame(template_import_verdict::OUTCOME_CREATED, $results[0]['outcome']);
        $this->assertNotFalse($written);
        $this->assertSame(0, (int) $written->get('duedate'));
    }

    /**
     * Custom fields land through the handler, so the audit event fires and the values are
     * readable back through the same encoder the export uses.
     *
     * @return void
     */
    public function test_custom_fields_land_through_the_handler(): void {
        $this->prepare_site();
        $csv = $this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-C', 'shortname' => 'With fields',
             'cf_bgcolor' => '#ff0000'],
        ]);

        $sink = $this->redirectEvents();
        $this->apply($csv, false, $this->tick($csv, false, ['t0']));
        $events = $sink->get_events();
        $sink->close();

        $written = template::get_record(['shortname' => 'With fields']);
        $this->assertNotFalse($written);
        $stored = helper::export_template_customfields((int) $written->get('id'));
        $this->assertSame('#ff0000', $stored['cf_bgcolor']);
        $this->assertSame('TPL-C', $stored['template_idnumber']);

        $names = array_map(static function ($event): string {
            return get_class($event);
        }, $events);
        $this->assertContains(\local_dimensions\event\template_imported::class, $names);
        $this->assertContains(\local_dimensions\event\template_customfields_updated::class, $names);
    }

    /**
     * Unticking every resolvable competency of a row whose others are missing is refused: it
     * would write the empty template the structure roll-up exists to prevent.
     *
     * @return void
     */
    public function test_deselecting_every_link_re_runs_the_rollup(): void {
        global $DB;

        $this->prepare_site();
        $csv = $this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-P', 'shortname' => 'Partial'],
            ['rowtype' => 'link', 'template_idnumber' => 'TPL-P', 'framework_idnumber' => 'FW-1',
             'competency_idnumber' => 'C1', 'sortorder' => '0'],
            ['rowtype' => 'link', 'template_idnumber' => 'TPL-P', 'framework_idnumber' => 'FW-1',
             'competency_idnumber' => 'NOPE', 'sortorder' => '1'],
        ]);
        $selections = $this->tick($csv, false, ['t0']);
        $selections[0]['links'] = [];

        $before = $DB->count_records('competency_template');
        $results = $this->apply($csv, false, $selections);

        $this->assertSame(template_import_verdict::OUTCOME_SKIPPED, $results[0]['outcome']);
        $this->assertSame($before, $DB->count_records('competency_template'));
    }

    /**
     * Seed the plugin fields, one structure and two competencies.
     *
     * @return array The generator and the framework.
     */
    private function prepare_site(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        helper::ensure_custom_fields_exist(helper::AREA_LP);

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework(['shortname' => 'FW', 'idnumber' => 'FW-1']);
        $generator->create_competency([
            'competencyframeworkid' => (int) $framework->get('id'),
            'shortname' => 'First',
            'idnumber' => 'C1',
        ]);
        $generator->create_competency([
            'competencyframeworkid' => (int) $framework->get('id'),
            'shortname' => 'Second',
            'idnumber' => 'C2',
        ]);
        return ['generator' => $generator, 'framework' => $framework];
    }

    /**
     * Write a template ID number through the same handler the importer uses.
     *
     * @param int $templateid The template id.
     * @param string $idnumber The ID number to store.
     * @return void
     */
    private function set_template_idnumber(int $templateid, string $idnumber): void {
        $formdata = (object) (['id' => $templateid] + helper::template_customfields_to_formdata([
            'template_idnumber' => $idnumber,
        ]));
        \local_dimensions\customfield\lp_handler::create()->instance_form_save($formdata, true);
    }

    /**
     * Build the selections a preview of this file would produce for the named items, with every
     * offered remedy and every resolvable link ticked.
     *
     * @param string $csv The CSV text.
     * @param bool $updateexisting Whether matched templates are updated.
     * @param array $itemkeys The item keys to tick.
     * @return array
     */
    private function tick(string $csv, bool $updateexisting, array $itemkeys): array {
        $plan = (new template_import_analyser(
            template_csv_serializer::parse($csv),
            \context_system::instance(),
            $updateexisting
        ))->analyse();

        $selections = [];
        foreach ($itemkeys as $itemkey) {
            $item = $plan->get_item($itemkey);
            $this->assertNotNull($item, $itemkey . ' is not in the projection');
            $links = [];
            foreach ($item['links'] as $linkkey => $link) {
                if (!empty($link['preselected'])) {
                    $links[] = $linkkey;
                }
            }
            $remedy = 'none';
            foreach ($item['remedies'] as $offered) {
                if (!empty($offered['selected'])) {
                    $remedy = $offered['remedy'];
                }
            }
            $selections[] = [
                'itemkey' => $itemkey,
                'verdict' => $item['verdict'],
                'fingerprint' => $item['fingerprint'],
                'remedy' => $remedy,
                'links' => $links,
            ];
        }
        return $selections;
    }

    /**
     * Run the importer over a CSV.
     *
     * @param string $csv The CSV text.
     * @param bool $updateexisting Whether matched templates are updated.
     * @param array $selections The selections to apply.
     * @return array The per-item results.
     */
    private function apply(string $csv, bool $updateexisting, array $selections): array {
        $importer = new template_csv_importer(
            template_csv_serializer::parse($csv),
            \context_system::instance(),
            $updateexisting
        );
        return $importer->apply($selections);
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
