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

use core_competency\api;
use core_competency\competency;
use core_competency\competency_framework;
use local_dimensions\customfield\competency_handler;
use local_dimensions\helper;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the context-aware framework CSV importer.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_dimensions\local\framework_csv_importer::class)]
final class framework_csv_importer_test extends \advanced_testcase {
    /**
     * Build a source framework with a two-level tree and one custom-field value, export it, then
     * delete it (framework idnumbers are globally unique) so the CSV can be re-imported cleanly.
     *
     * @return string The exported CSV.
     */
    private function source_csv(): string {
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework(['shortname' => 'Skills', 'idnumber' => 'SKILL']);
        $fwid = (int) $framework->get('id');
        $root = $ccg->create_competency(['competencyframeworkid' => $fwid, 'shortname' => 'Comms', 'idnumber' => 'CM']);
        $child = $ccg->create_competency([
            'competencyframeworkid' => $fwid,
            'parentid' => (int) $root->get('id'),
            'shortname' => 'Writing',
            'idnumber' => 'CM-W',
        ]);
        $formdata = (object) (['id' => (int) $child->get('id')] + helper::customfields_to_formdata([
            'cf_tag1' => explode("\n", get_string('tag1_options', 'local_dimensions'))[0],
            'cf_textcolor' => '112233',
        ]));
        competency_handler::create()->instance_form_save($formdata, true);

        $csv = framework_csv_serializer::export_framework($fwid, false)['content'];
        api::delete_framework($fwid);
        return $csv;
    }

    /**
     * Import into a course-category context (which the core tool cannot do) and check the result.
     *
     * @return void
     */
    public function test_import_into_coursecat(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);

        $csv = $this->source_csv();
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \context_coursecat::instance($category->id);

        $parsed = framework_csv_serializer::parse($csv);
        $result = (new framework_csv_importer($parsed, $catcontext, false))->import();

        $this->assertSame(2, $result['competencycount']);
        $framework = new competency_framework($result['frameworkid']);
        $this->assertSame((int) $catcontext->id, (int) $framework->get('contextid'));
        $this->assertSame('SKILL', $framework->get('idnumber'));
        $this->assertGreaterThan(0, (int) $framework->get('scaleid'));

        // Tree and idnumbers reconstructed.
        $competencies = competency::get_records(['competencyframeworkid' => $result['frameworkid']], 'path, sortorder');
        $byidnumber = [];
        foreach ($competencies as $competency) {
            $byidnumber[$competency->get('idnumber')] = $competency;
        }
        $this->assertArrayHasKey('CM', $byidnumber);
        $this->assertArrayHasKey('CM-W', $byidnumber);
        $this->assertSame((int) $byidnumber['CM']->get('id'), (int) $byidnumber['CM-W']->get('parentid'));

        // Custom fields round-tripped onto the new competency.
        $cf = helper::export_competency_customfields((int) $byidnumber['CM-W']->get('id'));
        $this->assertSame(explode("\n", get_string('tag1_options', 'local_dimensions'))[0], $cf['cf_tag1']);
        $this->assertSame('112233', $cf['cf_textcolor']);
    }

    /**
     * Re-importing with updateexisting matches the framework + competencies by idnumber, no duplicates.
     *
     * @return void
     */
    public function test_updateexisting_no_duplicates(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);

        $csv = $this->source_csv();
        $context = \context_system::instance();

        $first = (new framework_csv_importer(framework_csv_serializer::parse($csv), $context, false))->import();
        $second = (new framework_csv_importer(framework_csv_serializer::parse($csv), $context, true))->import();

        // Same framework updated in place; only one framework with this idnumber in the context.
        $this->assertSame($first['frameworkid'], $second['frameworkid']);
        $frameworks = competency_framework::get_records(['idnumber' => 'SKILL', 'contextid' => $context->id]);
        $this->assertCount(1, $frameworks);
        $this->assertCount(2, competency::get_records(['competencyframeworkid' => $first['frameworkid']]));
    }

    /**
     * A plain core-tool CSV (only the 14 columns, no cf_* extension) imports fine, leaving
     * the custom fields unset — proving the format is a backward-compatible superset.
     *
     * @return void
     */
    public function test_import_core_csv_without_customfields(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);

        // Build a core-format CSV (14 columns only). Quote every field so the embedded JSON and the
        // comma inside the scale values survive; the reader unquotes them.
        $encode = static function (array $row): string {
            return implode(',', array_map(static function ($cell): string {
                return '"' . str_replace('"', '""', (string) $cell) . '"';
            }, $row));
        };
        $csv = $encode(framework_csv_serializer::CORE_HEADERS) . "\n"
            . $encode([
                '', 'COREFW', 'Core FW', '', '1', 'Not good,Good',
                '[{"scaleid":"0"},{"id":2,"scaledefault":1,"proficient":1}]',
                '', '', '', '', '', '1', 'competency',
            ]) . "\n"
            . $encode(['', 'ROOTC', 'Root C', '', '1', '', '', '', '0', '', '', '', '', '']) . "\n";

        $parsed = framework_csv_serializer::parse($csv);
        $this->assertNotNull($parsed['framework']);
        $this->assertArrayHasKey('ROOTC', $parsed['competencies']);
        $this->assertSame([], $parsed['competencies']['ROOTC']->cf);

        $result = (new framework_csv_importer($parsed, \context_system::instance(), false))->import();
        $this->assertSame(1, $result['competencycount']);
        $competencies = competency::get_records(['competencyframeworkid' => $result['frameworkid']]);
        $competency = reset($competencies);
        $cf = helper::export_competency_customfields((int) $competency->get('id'));
        $this->assertSame('', $cf['cf_tag1']);
        $this->assertSame('', $cf['cf_textcolor']);
    }

    /**
     * Build a parsed CSV with a framework scale and one competency carrying its own scale.
     *
     * @param string $fwscale Framework scale values.
     * @param string $compscale Competency scale values ('' inherits the framework's).
     * @param string $compshortname Competency shortname ('' makes the importer skip the row).
     * @return array The parsed CSV.
     */
    private function parsed_with_scales(string $fwscale, string $compscale, string $compshortname = 'Scale C'): array {
        $encode = static function (array $row): string {
            return implode(',', array_map(static function ($cell): string {
                return '"' . str_replace('"', '""', (string) $cell) . '"';
            }, $row));
        };
        $config = '[{"scaleid":"0"},{"id":2,"scaledefault":1,"proficient":1}]';
        $csv = $encode(framework_csv_serializer::CORE_HEADERS) . "\n"
            . $encode(['', 'SCFW', 'Scale FW', '', '1', $fwscale, $config, '', '', '', '', '', '1', 'competency']) . "\n"
            . $encode([
                '', 'SCC', $compshortname, '', '1', $compscale, $compscale === '' ? '' : $config, '', '0', '', '', '', '', '',
            ]) . "\n";
        return framework_csv_serializer::parse($csv);
    }

    /**
     * A user who manages competencies in one category only.
     *
     * @param \context $context The category context.
     * @return void
     */
    private function become_category_competency_manager(\context $context): void {
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/competency:competencymanage', CAP_ALLOW, $roleid, $context->id, true);
        $user = $this->getDataGenerator()->create_user();
        role_assign($roleid, $user->id, $context->id);
        $this->setUser($user);
    }

    /**
     * Row counts that a refused import must leave unchanged.
     *
     * @return int[]
     */
    private function write_counts(): array {
        global $DB;
        return [
            $DB->count_records('competency_framework'),
            $DB->count_records('competency'),
            $DB->count_records('scale'),
        ];
    }

    /**
     * An imported scale item is stored as plain text, and an admin still creates the scale.
     *
     * @return void
     */
    public function test_a_new_global_scale_is_created_from_cleaned_items(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $parsed = $this->parsed_with_scales('Low,<img src=x onerror=alert(1)>High', '');
        $result = (new framework_csv_importer($parsed, \context_system::instance(), false))->import();

        $scaleid = (int) competency_framework::get_record(['id' => $result['frameworkid']])->get('scaleid');
        $this->assertSame('Low,High', $DB->get_field('scale', 'scale', ['id' => $scaleid]));
        $this->assertSame('0', (string) $DB->get_field('scale', 'courseid', ['id' => $scaleid]));
    }

    /**
     * A category manager without site scale management cannot make the import create a site scale,
     * and nothing is written, not even the rows imported before the scale was needed.
     *
     * @return void
     */
    public function test_a_category_manager_cannot_create_a_global_scale(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);
        $this->getDataGenerator()->create_scale(['scale' => 'Not yet,Competent']);
        $category = $this->getDataGenerator()->create_category();
        $context = \context_coursecat::instance($category->id);
        $this->become_category_competency_manager($context);

        // The framework reuses the existing scale; the competency then needs a new one.
        $parsed = $this->parsed_with_scales('Not yet,Competent', 'Brand,New');
        $before = $this->write_counts();
        try {
            (new framework_csv_importer($parsed, $context, false))->import();
            $this->fail('A new site scale must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('central_frameworks_import_scalenotallowed', $e->errorcode);
            $this->assertStringContainsString('Brand,New', $e->getMessage());
        }
        $this->assertSame($before, $this->write_counts());
    }

    /**
     * A row the importer never creates does not make a category manager need its scale.
     *
     * @return void
     */
    public function test_a_row_that_is_not_imported_does_not_need_its_scale(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);
        $this->getDataGenerator()->create_scale(['scale' => 'Not yet,Competent']);
        $category = $this->getDataGenerator()->create_category();
        $context = \context_coursecat::instance($category->id);
        $this->become_category_competency_manager($context);

        // The only row with an unknown scale has no shortname, so create_competency() skips it.
        $parsed = $this->parsed_with_scales('Not yet,Competent', 'Brand,New', '');
        $before = $this->write_counts();
        $result = (new framework_csv_importer($parsed, $context, false))->import();

        $this->assertSame(0, $result['competencycount']);
        $this->assertSame($before[2], $this->write_counts()[2]);
    }

    /**
     * The same manager can import when a matching site scale already exists.
     *
     * @return void
     */
    public function test_a_category_manager_reuses_an_existing_global_scale(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);
        $scale = $this->getDataGenerator()->create_scale(['scale' => 'Not yet,Competent']);
        $category = $this->getDataGenerator()->create_category();
        $context = \context_coursecat::instance($category->id);
        $this->become_category_competency_manager($context);

        $before = $this->write_counts();
        $result = (new framework_csv_importer($this->parsed_with_scales('Not yet,Competent', ''), $context, false))->import();

        $this->assertSame(1, $result['competencycount']);
        $framework = competency_framework::get_record(['id' => $result['frameworkid']]);
        $this->assertSame((int) $scale->id, (int) $framework->get('scaleid'));
        $this->assertSame($before[2], $this->write_counts()[2]);
    }

    /**
     * A framework row without scale values is refused rather than given an empty scale.
     *
     * @return void
     */
    public function test_an_empty_framework_scale_is_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $before = $this->write_counts();
        try {
            (new framework_csv_importer($this->parsed_with_scales('', ''), \context_system::instance(), false))->import();
            $this->fail('An empty framework scale must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('central_frameworks_import_emptyscale', $e->errorcode);
        }
        $this->assertSame($before, $this->write_counts());
    }
}
