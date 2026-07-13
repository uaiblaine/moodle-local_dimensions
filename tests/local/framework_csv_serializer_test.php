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
use local_dimensions\customfield\competency_handler;
use local_dimensions\helper;

/**
 * Tests for the framework CSV serializer (export + parse round-trip).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\local\framework_csv_serializer
 */
final class framework_csv_serializer_test extends \advanced_testcase {
    /**
     * Export a framework with custom fields, then parse it back and check the reconstruction.
     *
     * @return void
     */
    public function test_export_and_parse_roundtrip(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);

        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework(['shortname' => 'FW', 'idnumber' => 'FW-1']);
        $fwid = (int) $framework->get('id');
        $root = $ccg->create_competency(['competencyframeworkid' => $fwid, 'shortname' => 'Root', 'idnumber' => 'R1']);
        $child = $ccg->create_competency([
            'competencyframeworkid' => $fwid,
            'parentid' => (int) $root->get('id'),
            'shortname' => 'Child',
            'idnumber' => 'C1',
        ]);
        api::add_related_competency((int) $child->get('id'), (int) $root->get('id'));

        // Give the child real custom-field values through the same write path the importer uses.
        // Select labels come from the lang defaults, so future string edits keep the test passing.
        $tag1label = explode("\n", get_string('tag1_options', 'local_dimensions'))[1];
        $typelabel = explode("\n", get_string('type_options', 'local_dimensions'))[0];
        $formdata = (object) (['id' => (int) $child->get('id')] + helper::customfields_to_formdata([
            'cf_bgcolor' => 'ff0000',
            'cf_tag1' => $tag1label,
            'cf_type' => $typelabel,
        ]));
        competency_handler::create()->instance_form_save($formdata, true);

        $result = framework_csv_serializer::export_framework($fwid, false);
        $this->assertStringEndsWith('.csv', $result['filename']);
        $this->assertNotEmpty($result['content']);
        // Taxonomies must serialise as their comma-joined terms, never the literal "Array".
        $this->assertStringNotContainsString('Array', $result['content']);

        $lines = array_values(array_filter(explode("\n", trim($result['content']))));
        // Header + framework row + 2 competency rows.
        $this->assertCount(4, $lines);
        $this->assertStringContainsString('parentidnumber', $lines[0]);
        $this->assertStringContainsString('cf_tag1', $lines[0]);
        // The cf_customscss column is omitted when the SCSS feature is off.
        $this->assertStringNotContainsString('cf_customscss', $lines[0]);

        $parsed = framework_csv_serializer::parse($result['content']);
        $this->assertSame('', $parsed['error']);
        $this->assertNotNull($parsed['framework']);
        $this->assertSame('FW-1', $parsed['framework']->idnumber);
        $this->assertSame('FW', $parsed['framework']->shortname);
        $this->assertArrayHasKey('R1', $parsed['competencies']);
        $this->assertArrayHasKey('C1', $parsed['competencies']);
        $this->assertSame('R1', $parsed['competencies']['C1']->parentidnumber);
        $this->assertSame('', $parsed['competencies']['R1']->parentidnumber);

        // The child's custom-field tokens survive the round trip (label for selects, hex for colours).
        $cf = $parsed['competencies']['C1']->cf;
        $this->assertSame('ff0000', $cf['cf_bgcolor']);
        $this->assertSame($tag1label, $cf['cf_tag1']);
        $this->assertSame($typelabel, $cf['cf_type']);

        // The related-competency idnumber is carried on the child row.
        $this->assertSame('R1', $parsed['competencies']['C1']->relatedidnumbers);
    }

    /**
     * The cf_customscss column is emitted only when the custom SCSS feature is enabled.
     *
     * @return void
     */
    public function test_headers_scss_toggle(): void {
        $this->resetAfterTest();
        $this->assertNotContains('cf_customscss', framework_csv_serializer::headers(false));
        $this->assertContains('cf_customscss', framework_csv_serializer::headers(true));
        // The 14 core columns always lead, in order.
        $this->assertSame(framework_csv_serializer::CORE_HEADERS, array_slice(framework_csv_serializer::headers(true), 0, 14));
    }
}
