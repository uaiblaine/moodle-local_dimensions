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

use local_dimensions\customfield\lp_handler;
use local_dimensions\helper;

/**
 * Tests for the learning plan import analyser, starting with its zero-writes guarantee.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\local\template_import_analyser
 */
final class template_import_analyser_test extends \advanced_testcase {
    /**
     * Analysing a file that exercises every verdict writes nothing at all.
     *
     * customfield_field and customfield_category are in the snapshot precisely because
     * provisioning was deliberately removed from the analyser: ensure_custom_fields_exist()
     * inserts into both, so a stray call would show up here rather than in production.
     *
     * @return void
     */
    public function test_analyse_writes_nothing(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        helper::ensure_custom_fields_exist(helper::AREA_LP);

        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework(['shortname' => 'FW', 'idnumber' => 'FW-1']);
        $competency = $ccg->create_competency([
            'competencyframeworkid' => (int) $framework->get('id'),
            'shortname' => 'First',
            'idnumber' => 'C1',
        ]);
        $existing = $ccg->create_template(['shortname' => 'Existing']);
        $this->set_template_idnumber((int) $existing->get('id'), 'TPL-1');
        $ccg->create_template(['shortname' => 'Taken']);

        // The fields must already exist: the analyser reports a missing field, it never creates one.
        $this->assertNotNull(helper::find_field_by_shortname(
            \local_dimensions\constants::CFIELD_TEMPLATE_IDNUMBER,
            helper::AREA_LP
        ));

        $csv = $this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-1', 'shortname' => 'Existing renamed'],
            ['rowtype' => 'link', 'template_idnumber' => 'TPL-1', 'framework_idnumber' => 'FW-1',
             'competency_idnumber' => 'C1', 'sortorder' => '0'],
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-2', 'shortname' => 'Brand new'],
            ['rowtype' => 'link', 'template_idnumber' => 'TPL-2', 'framework_idnumber' => 'GONE',
             'competency_idnumber' => 'X9', 'sortorder' => '0'],
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-3', 'shortname' => 'Taken'],
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-4', 'shortname' => 'Late',
             'duedate' => '2001-01-01'],
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-5', 'shortname' => ''],
            ['rowtype' => 'link', 'template_idnumber' => 'NOBODY', 'framework_idnumber' => 'FW-1',
             'competency_idnumber' => 'C1', 'sortorder' => '0'],
        ]);

        $before = $this->snapshot();
        $plan = $this->analyse($csv, \context_system::instance(), true);
        $after = $this->snapshot();

        $this->assertSame($before['counts'], $after['counts']);
        $this->assertEquals($before['templates'], $after['templates']);

        // And the fixture really did exercise the range, so the proof is not vacuous.
        $verdicts = [];
        foreach ($plan->get_items() as $item) {
            $verdicts[$item['verdict']] = true;
        }
        $this->assertArrayHasKey(template_import_verdict::VERDICT_UPDATE, $verdicts);
        $this->assertArrayHasKey(template_import_verdict::VERDICT_BLOCKED, $verdicts);
        $this->assertArrayHasKey(template_import_verdict::VERDICT_CONFLICT, $verdicts);
        $this->assertArrayHasKey(template_import_verdict::VERDICT_ORPHANLINK, $verdicts);
        $matchedlinks = $plan->get_item('t0')['links'];
        $matchedlink = reset($matchedlinks);
        $this->assertSame((int) $competency->get('id'), $matchedlink['competencyid']);
    }

    /**
     * A row matching nothing on the site is a create, ticked by default.
     *
     * @return void
     */
    public function test_an_unmatched_row_is_a_create(): void {
        $this->prepare_site();
        $csv = $this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-NEW', 'shortname' => 'Brand new'],
        ]);

        $item = $this->first_item($this->analyse($csv, \context_system::instance(), false));

        $this->assertSame(template_import_verdict::VERDICT_CREATE, $item['verdict']);
        $this->assertSame(0, $item['matchedid']);
        $this->assertTrue($item['selectable']);
        $this->assertTrue($item['preselected']);
    }

    /**
     * A plan whose competencies are all missing is blocked: importing it would create an empty
     * template that looks like the real thing.
     *
     * @return void
     */
    public function test_a_plan_with_no_structure_here_is_blocked(): void {
        $this->prepare_site();
        $csv = $this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-NEW', 'shortname' => 'Needs structure'],
            ['rowtype' => 'link', 'template_idnumber' => 'TPL-NEW', 'framework_idnumber' => 'ABSENT',
             'framework_shortname' => 'Absent structure', 'competency_idnumber' => 'C9', 'sortorder' => '0'],
        ]);

        $plan = $this->analyse($csv, \context_system::instance(), false);
        $item = $this->first_item($plan);

        $this->assertSame(template_import_verdict::VERDICT_BLOCKED, $item['verdict']);
        $this->assertSame(template_import_verdict::REASON_STRUCTUREMISSING, $item['reason']);
        $this->assertFalse($item['selectable']);
        $this->assertSame(
            [['idnumber' => 'ABSENT', 'shortname' => 'Absent structure']],
            $plan->get_missing_structures()
        );
    }

    /**
     * A template with no competency rows at all is a legitimate export and is NOT blocked:
     * the roll-up only fires when competencies were named and none of them resolved.
     *
     * @return void
     */
    public function test_a_template_with_no_links_is_not_blocked(): void {
        $this->prepare_site();
        $csv = $this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-EMPTY', 'shortname' => 'No competencies'],
        ]);

        $item = $this->first_item($this->analyse($csv, \context_system::instance(), false));

        $this->assertSame(template_import_verdict::VERDICT_CREATE, $item['verdict']);
        $this->assertSame(0, $item['linkstotal']);
    }

    /**
     * A competency that moved to another structure is reported as a hint, never matched across
     * structures: the same ID number in another structure is a different competency.
     *
     * @return void
     */
    public function test_a_competency_in_another_structure_is_only_a_hint(): void {
        $site = $this->prepare_site();
        $other = $site['generator']->create_framework(['shortname' => 'Other', 'idnumber' => 'FW-2']);
        $site['generator']->create_competency([
            'competencyframeworkid' => (int) $other->get('id'),
            'shortname' => 'Moved',
            'idnumber' => 'C-MOVED',
        ]);

        $csv = $this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-NEW', 'shortname' => 'Wants moved'],
            ['rowtype' => 'link', 'template_idnumber' => 'TPL-NEW', 'framework_idnumber' => 'FW-1',
             'competency_idnumber' => 'C-MOVED', 'sortorder' => '0'],
        ]);

        $item = $this->first_item($this->analyse($csv, \context_system::instance(), false));
        $link = reset($item['links']);

        $this->assertSame(template_import_verdict::LINK_MISSINGCOMPETENCY, $link['status']);
        $this->assertSame(0, $link['competencyid']);
        $this->assertStringContainsString('Other', $link['detail']);
    }

    /**
     * A file ID number that matches nothing here, against a context that already holds a
     * template with the same name, is a conflict offering both ways out.
     *
     * @return void
     */
    public function test_a_taken_shortname_is_a_conflict_offering_adopt(): void {
        $site = $this->prepare_site();
        $site['generator']->create_template(['shortname' => 'Induction']);

        $csv = $this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-IND', 'shortname' => 'Induction'],
        ]);

        $item = $this->first_item($this->analyse($csv, \context_system::instance(), false));
        $remedies = array_column($item['remedies'], 'remedy');

        $this->assertSame(template_import_verdict::VERDICT_CONFLICT, $item['verdict']);
        $this->assertSame(template_import_verdict::REASON_SHORTNAMETAKEN, $item['reason']);
        $this->assertContains(template_import_verdict::REMEDY_ADOPT, $remedies);
        $this->assertContains(template_import_verdict::REMEDY_CREATEHERE, $remedies);
        $this->assertTrue($item['selectable']);
        $this->assertFalse($item['preselected']);
    }

    /**
     * The same ID number in another context is a conflict offering a separate copy here, and
     * the other context is named so the operator can tell what happened.
     *
     * @return void
     */
    public function test_an_idnumber_in_another_context_offers_createhere(): void {
        $site = $this->prepare_site();
        $category = $this->getDataGenerator()->create_category(['name' => 'Nursing school']);
        $categorycontext = \context_coursecat::instance((int) $category->id);
        $elsewhere = $site['generator']->create_template([
            'shortname' => 'Elsewhere',
            'contextid' => $categorycontext->id,
        ]);
        $this->set_template_idnumber((int) $elsewhere->get('id'), 'TPL-ELSE');

        $csv = $this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-ELSE', 'shortname' => 'Elsewhere'],
        ]);

        $item = $this->first_item($this->analyse($csv, \context_system::instance(), false));

        $this->assertSame(template_import_verdict::VERDICT_CONFLICT, $item['verdict']);
        $this->assertSame(template_import_verdict::REASON_CONTEXTMISMATCH, $item['reason']);
        $this->assertSame(
            [template_import_verdict::REMEDY_CREATEHERE],
            array_column($item['remedies'], 'remedy')
        );
        $this->assertStringContainsString('Nursing school', $item['detail']);
    }

    /**
     * A due date in the past blocks, with clearing it pre-selected and shifting it available.
     * keepduedate is not offered on a create: there is no stored value to keep.
     *
     * @return void
     */
    public function test_a_past_duedate_blocks_with_clearduedate_preselected(): void {
        $this->prepare_site();
        $csv = $this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-OLD', 'shortname' => 'Old deadline',
             'duedate' => '2001-06-30'],
        ]);

        $item = $this->first_item($this->analyse($csv, \context_system::instance(), false));
        $selected = [];
        foreach ($item['remedies'] as $remedy) {
            if ($remedy['selected']) {
                $selected[] = $remedy['remedy'];
            }
        }

        $this->assertSame(template_import_verdict::VERDICT_BLOCKED, $item['verdict']);
        $this->assertSame(template_import_verdict::REASON_DUEDATEPAST, $item['reason']);
        $this->assertSame([template_import_verdict::REMEDY_CLEARDUEDATE], $selected);
        $this->assertSame(
            [template_import_verdict::REMEDY_CLEARDUEDATE, template_import_verdict::REMEDY_SHIFTDUEDATE],
            array_column($item['remedies'], 'remedy')
        );
        $this->assertTrue($item['selectable']);
    }

    /**
     * An unreadable due date blocks on its own reason rather than being silently dropped.
     *
     * @return void
     */
    public function test_an_unreadable_duedate_blocks(): void {
        $this->prepare_site();
        $csv = $this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-BAD', 'shortname' => 'Bad date',
             'duedate' => 'next tuesday'],
        ]);

        $item = $this->first_item($this->analyse($csv, \context_system::instance(), false));

        $this->assertSame(template_import_verdict::VERDICT_BLOCKED, $item['verdict']);
        $this->assertSame(template_import_verdict::REASON_DUEDATEUNPARSEABLE, $item['reason']);
    }

    /**
     * An empty template ID number is not a key. Tier 1 is skipped entirely, so a row with an
     * empty ID number cannot match a stored template whose ID number is also empty.
     *
     * @return void
     */
    public function test_an_empty_template_idnumber_skips_the_idnumber_tier(): void {
        $site = $this->prepare_site();
        $stored = $site['generator']->create_template(['shortname' => 'Stored']);
        $this->set_template_idnumber((int) $stored->get('id'), '');

        $csv = $this->csv([
            ['rowtype' => 'template', 'template_idnumber' => '', 'shortname' => 'Something else'],
        ]);

        $item = $this->first_item($this->analyse($csv, \context_system::instance(), false));

        $this->assertSame(template_import_verdict::VERDICT_CREATE, $item['verdict']);
        $this->assertSame(0, $item['matchedid']);
    }

    /**
     * With "update existing" off a matched row is a skip that still shows its diff; with it on
     * the same row is an update.
     *
     * @return void
     */
    public function test_a_matched_row_is_skipped_or_updated_by_the_flag(): void {
        $site = $this->prepare_site();
        $stored = $site['generator']->create_template(['shortname' => 'Original']);
        $this->set_template_idnumber((int) $stored->get('id'), 'TPL-M');

        $csv = $this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-M', 'shortname' => 'Renamed'],
        ]);

        $skipped = $this->first_item($this->analyse($csv, \context_system::instance(), false));
        $this->assertSame(template_import_verdict::VERDICT_SKIP, $skipped['verdict']);
        $this->assertSame(template_import_verdict::REASON_UPDATEEXISTINGOFF, $skipped['reason']);
        $this->assertFalse($skipped['preselected']);
        $this->assertTrue($skipped['selectable']);
        $this->assertSame(
            [['field' => 'shortname', 'from' => 'Original', 'to' => 'Renamed']],
            $skipped['diff']
        );

        $updated = $this->first_item($this->analyse($csv, \context_system::instance(), true));
        $this->assertSame(template_import_verdict::VERDICT_UPDATE, $updated['verdict']);
        $this->assertTrue($updated['preselected']);
        $this->assertSame((int) $stored->get('id'), $updated['matchedid']);
    }

    /**
     * A row that changes nothing is in sync, and is left unticked: applying it still moves two
     * timemodified columns and re-arms the cohort sync.
     *
     * @return void
     */
    public function test_an_unchanged_row_is_insync_and_unticked(): void {
        $site = $this->prepare_site();
        $stored = $site['generator']->create_template(['shortname' => 'Unchanged']);
        $this->set_template_idnumber((int) $stored->get('id'), 'TPL-S');

        $csv = $this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-S', 'shortname' => 'Unchanged'],
        ]);

        $item = $this->first_item($this->analyse($csv, \context_system::instance(), true));

        $this->assertSame(template_import_verdict::VERDICT_INSYNC, $item['verdict']);
        $this->assertSame([], $item['diff']);
        $this->assertTrue($item['selectable']);
        $this->assertFalse($item['preselected']);
    }

    /**
     * A competency row whose parent key names no template row in the file is its own item.
     *
     * @return void
     */
    public function test_a_link_with_no_parent_is_an_orphan(): void {
        $this->prepare_site();
        $csv = $this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-A', 'shortname' => 'A'],
            ['rowtype' => 'link', 'template_idnumber' => 'TPL-ZZZ', 'framework_idnumber' => 'FW-1',
             'competency_idnumber' => 'C1', 'sortorder' => '0'],
        ]);

        $plan = $this->analyse($csv, \context_system::instance(), false);
        $items = array_values($plan->get_items());

        $this->assertCount(2, $items);
        $this->assertSame(template_import_verdict::VERDICT_ORPHANLINK, $items[1]['verdict']);
        $this->assertSame(template_import_verdict::REASON_NOPARENT, $items[1]['reason']);
        $this->assertFalse($items[1]['selectable']);
    }

    /**
     * A resolved link that is already on the matched template is reported, not re-added.
     *
     * @return void
     */
    public function test_an_existing_link_is_reported_as_already_linked(): void {
        $site = $this->prepare_site();
        $stored = $site['generator']->create_template(['shortname' => 'Has one']);
        $this->set_template_idnumber((int) $stored->get('id'), 'TPL-L');
        $site['generator']->create_template_competency([
            'templateid' => (int) $stored->get('id'),
            'competencyid' => (int) $site['competency']->get('id'),
        ]);

        $csv = $this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-L', 'shortname' => 'Has one'],
            ['rowtype' => 'link', 'template_idnumber' => 'TPL-L', 'framework_idnumber' => 'FW-1',
             'competency_idnumber' => 'C1', 'sortorder' => '0'],
        ]);

        $item = $this->first_item($this->analyse($csv, \context_system::instance(), true));
        $link = reset($item['links']);

        $this->assertSame(template_import_verdict::LINK_ALREADYLINKED, $link['status']);
        $this->assertFalse($link['selectable']);
        $this->assertSame(template_import_verdict::VERDICT_INSYNC, $item['verdict']);
    }

    /**
     * A competency matched by name rather than by ID number is still ticked, but says so.
     *
     * @return void
     */
    public function test_a_name_matched_competency_is_badged_as_a_fallback(): void {
        $this->prepare_site();
        $csv = $this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-N', 'shortname' => 'By name'],
            ['rowtype' => 'link', 'template_idnumber' => 'TPL-N', 'framework_idnumber' => 'FW-1',
             'competency_idnumber' => '', 'competency_shortname' => 'First', 'sortorder' => '0'],
        ]);

        $item = $this->first_item($this->analyse($csv, \context_system::instance(), false));
        $link = reset($item['links']);

        $this->assertSame(template_import_verdict::LINK_MATCHEDFALLBACK, $link['status']);
        $this->assertSame(template_import_verdict::CONFIDENCE_COMPETENCYSHORTNAME, $link['confidence']);
        $this->assertTrue($link['preselected']);
        $this->assertSame('bg-warning text-dark', $link['statusbadge']);
    }

    /**
     * Every item carries a fingerprint, and it moves when the projected result moves.
     *
     * @return void
     */
    public function test_the_fingerprint_moves_with_the_projection(): void {
        $site = $this->prepare_site();
        $stored = $site['generator']->create_template(['shortname' => 'Fingerprinted']);
        $this->set_template_idnumber((int) $stored->get('id'), 'TPL-F');

        $csv = $this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-F', 'shortname' => 'Fingerprinted'],
        ]);
        $before = $this->first_item($this->analyse($csv, \context_system::instance(), true))['fingerprint'];

        $renamed = $this->csv([
            ['rowtype' => 'template', 'template_idnumber' => 'TPL-F', 'shortname' => 'Renamed'],
        ]);
        $after = $this->first_item($this->analyse($renamed, \context_system::instance(), true))['fingerprint'];

        $this->assertNotEmpty($before);
        $this->assertNotSame($before, $after);
    }

    /**
     * Seed a site with the plugin fields, one structure and one competency.
     *
     * @return array The generator, the framework and the competency.
     */
    private function prepare_site(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        helper::ensure_custom_fields_exist(helper::AREA_LP);

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework(['shortname' => 'FW', 'idnumber' => 'FW-1']);
        $competency = $generator->create_competency([
            'competencyframeworkid' => (int) $framework->get('id'),
            'shortname' => 'First',
            'idnumber' => 'C1',
        ]);
        return ['generator' => $generator, 'framework' => $framework, 'competency' => $competency];
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
        lp_handler::create()->instance_form_save($formdata, true);
    }

    /**
     * Parse a CSV and analyse it against a context.
     *
     * @param string $csv The CSV text.
     * @param \context $target The target context.
     * @param bool $updateexisting Whether matched templates are updated.
     * @return template_import_plan
     */
    private function analyse(string $csv, \context $target, bool $updateexisting): template_import_plan {
        $parsed = template_csv_serializer::parse($csv);
        $this->assertSame('', $parsed['error']);
        return (new template_import_analyser($parsed, $target, $updateexisting))->analyse();
    }

    /**
     * The first item of a plan.
     *
     * @param template_import_plan $plan The plan.
     * @return array
     */
    private function first_item(template_import_plan $plan): array {
        $items = $plan->get_items();
        $this->assertNotEmpty($items);
        return reset($items);
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

    /**
     * Row counts of every table an import could touch, plus the whole template table.
     *
     * @return array
     */
    private function snapshot(): array {
        global $DB;

        $counts = [];
        foreach ([
            'competency_template', 'competency_templatecomp', 'competency_templatecohort',
            'competency_plan', 'customfield_data', 'customfield_field', 'customfield_category', 'files',
        ] as $table) {
            $counts[$table] = $DB->count_records($table);
        }
        return ['counts' => $counts, 'templates' => $DB->get_records('competency_template', null, 'id')];
    }
}
