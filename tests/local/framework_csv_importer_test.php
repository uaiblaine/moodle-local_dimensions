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

/**
 * Tests for the context-aware framework CSV importer.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\local\framework_csv_importer
 */
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
            'cf_tag1' => '1st Year',
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
        $this->assertSame('1st Year', $cf['cf_tag1']);
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
}
