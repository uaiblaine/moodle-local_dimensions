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

use local_dimensions\constants;
use local_dimensions\customfield\lp_handler;
use local_dimensions\helper;

/**
 * Tests for the learning plan template CSV serializer (export + parse round-trip).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\local\template_csv_serializer
 */
final class template_csv_serializer_test extends \advanced_testcase {
    /**
     * Export a template with custom fields and links, then parse it back.
     *
     * @return void
     */
    public function test_export_and_parse_roundtrip(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);

        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework(['shortname' => 'FW', 'idnumber' => 'FW-1']);
        $fwid = (int) $framework->get('id');
        $first = $ccg->create_competency(['competencyframeworkid' => $fwid, 'shortname' => 'First', 'idnumber' => 'C1']);
        $second = $ccg->create_competency(['competencyframeworkid' => $fwid, 'shortname' => 'Second', 'idnumber' => 'C2']);

        $template = $ccg->create_template(['shortname' => 'Nursing', 'description' => 'Plan body']);
        $templateid = (int) $template->get('id');
        $ccg->create_template_competency(['templateid' => $templateid, 'competencyid' => (int) $first->get('id')]);
        $ccg->create_template_competency(['templateid' => $templateid, 'competencyid' => (int) $second->get('id')]);

        // Write real custom-field values through the same path the importer will use.
        $tag1label = explode("\n", get_string('tag1_options', 'local_dimensions'))[1];
        $formdata = (object) (['id' => $templateid] + helper::template_customfields_to_formdata([
            'template_idnumber' => 'TPL-NUR',
            'cf_bgcolor' => 'ff0000',
            'cf_tag1' => $tag1label,
            'cf_displaymode' => (string) constants::DISPLAYMODE_PLAN,
            'cf_subline_source' => constants::SUBLINE_RATING,
            'cf_showrelated' => constants::SHOWRELATED_NO,
            'cf_lockedcardmode' => constants::LOCKEDCARDMODE_LEARNMORE,
        ]));
        lp_handler::create()->instance_form_save($formdata, true);

        $result = template_csv_serializer::export_templates([$templateid], false);
        $this->assertStringEndsWith('.csv', $result['filename']);
        $this->assertNotEmpty($result['content']);
        // The referenced framework is offered as a companion structure download.
        $this->assertCount(1, $result['frameworks']);
        $this->assertSame('FW-1', $result['frameworks'][0]['idnumber']);

        $lines = array_values(array_filter(explode("\n", trim($result['content']))));
        // Header + template row + 2 link rows.
        $this->assertCount(4, $lines);
        $this->assertStringContainsString('rowtype', $lines[0]);
        $this->assertStringContainsString('cf_displaymode', $lines[0]);
        // The cf_customscss column is omitted when the SCSS feature is off.
        $this->assertStringNotContainsString('cf_customscss', $lines[0]);

        $parsed = template_csv_serializer::parse($result['content']);
        $this->assertSame('', $parsed['error']);
        $this->assertFalse($parsed['legacy']);
        $this->assertCount(1, $parsed['templates']);
        $this->assertCount(2, $parsed['links']);

        $tpl = $parsed['templates'][0];
        $this->assertSame('Nursing', $tpl->shortname);
        $this->assertSame('TPL-NUR', $tpl->templateidnumber);
        $this->assertSame('1', $tpl->visible);
        $this->assertSame('system', $tpl->sourcecontext);
        $this->assertSame('ff0000', $tpl->cf['cf_bgcolor']);
        $this->assertSame($tag1label, $tpl->cf['cf_tag1']);
        $this->assertSame((string) constants::DISPLAYMODE_PLAN, $tpl->cf['cf_displaymode']);
        $this->assertSame(constants::SUBLINE_RATING, $tpl->cf['cf_subline_source']);
        $this->assertSame(constants::SHOWRELATED_NO, $tpl->cf['cf_showrelated']);
        $this->assertSame(constants::LOCKEDCARDMODE_LEARNMORE, $tpl->cf['cf_lockedcardmode']);

        // Links carry the parent key, both halves of the competency key, and their order.
        $this->assertSame('TPL-NUR', $parsed['links'][0]->parentidnumber);
        $this->assertSame('FW-1', $parsed['links'][0]->frameworkidnumber);
        $this->assertSame('C1', $parsed['links'][0]->competencyidnumber);
        $this->assertSame('0', $parsed['links'][0]->sortorder);
        $this->assertSame('C2', $parsed['links'][1]->competencyidnumber);
        $this->assertSame('1', $parsed['links'][1]->sortorder);
    }

    /**
     * A template named as a spreadsheet formula is exported inert and read back intact.
     *
     * See framework_csv_serializer_test::test_export_neutralises_a_formula_shortname() - the
     * two formats share the escaper and the same round-trip obligation.
     *
     * @return void
     */
    public function test_export_neutralises_a_formula_shortname(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);

        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $template = $ccg->create_template(['shortname' => '=cmd|calc']);

        $result = template_csv_serializer::export_templates([(int) $template->get('id')], false);

        $this->assertStringContainsString('"\'=cmd|calc"', $result['content']);

        $parsed = template_csv_serializer::parse($result['content']);
        $this->assertSame('=cmd|calc', $parsed['templates'][0]->shortname);
    }

    /**
     * A template whose competencies come from two frameworks exports both, one per link row.
     *
     * @return void
     */
    public function test_export_spans_two_frameworks(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);

        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $one = $ccg->create_framework(['shortname' => 'One', 'idnumber' => 'FW-A']);
        $two = $ccg->create_framework(['shortname' => 'Two', 'idnumber' => 'FW-B']);
        $compa = $ccg->create_competency(['competencyframeworkid' => (int) $one->get('id'), 'idnumber' => 'A1']);
        $compb = $ccg->create_competency(['competencyframeworkid' => (int) $two->get('id'), 'idnumber' => 'B1']);

        $template = $ccg->create_template(['shortname' => 'Mixed']);
        $templateid = (int) $template->get('id');
        $ccg->create_template_competency(['templateid' => $templateid, 'competencyid' => (int) $compa->get('id')]);
        $ccg->create_template_competency(['templateid' => $templateid, 'competencyid' => (int) $compb->get('id')]);

        $result = template_csv_serializer::export_templates([$templateid], false);
        $this->assertCount(2, $result['frameworks']);

        $parsed = template_csv_serializer::parse($result['content']);
        $idnumbers = array_map(static function ($link): string {
            return $link->frameworkidnumber;
        }, $parsed['links']);
        sort($idnumbers);
        $this->assertSame(['FW-A', 'FW-B'], $idnumbers);
    }

    /**
     * A template with no competencies exports one template row and no link rows.
     *
     * @return void
     */
    public function test_export_template_without_competencies(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);

        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $template = $ccg->create_template(['shortname' => 'Empty']);

        $result = template_csv_serializer::export_templates([(int) $template->get('id')], false);
        $this->assertSame([], $result['frameworks']);

        $parsed = template_csv_serializer::parse($result['content']);
        $this->assertSame('', $parsed['error']);
        $this->assertCount(1, $parsed['templates']);
        $this->assertCount(0, $parsed['links']);
        // A link row with no parent is the analyser's problem, not the parser's: none is emitted here.
        $this->assertSame('Empty', $parsed['templates'][0]->shortname);
    }

    /**
     * A link row whose parent key matches no template row still parses, for the analyser to flag.
     *
     * @return void
     */
    public function test_parse_keeps_orphan_link_rows(): void {
        $this->resetAfterTest();

        $csv = '"rowtype","template_idnumber","shortname","competency_idnumber","framework_idnumber","sortorder"' . "\n"
            . '"template","TPL-1","Kept","","",""' . "\n"
            . '"link","TPL-MISSING","","C9","FW-1","0"' . "\n";

        $parsed = template_csv_serializer::parse($csv);
        $this->assertSame('', $parsed['error']);
        $this->assertCount(1, $parsed['templates']);
        $this->assertCount(1, $parsed['links']);
        $this->assertSame('TPL-MISSING', $parsed['links'][0]->parentidnumber);
    }

    /**
     * An absent column is distinguishable from a present-but-empty one.
     *
     * @return void
     */
    public function test_parse_absent_column_is_null_not_empty(): void {
        $this->resetAfterTest();

        $withcolumn = '"rowtype","shortname","description"' . "\n" . '"template","Named",""' . "\n";
        $without = '"rowtype","shortname"' . "\n" . '"template","Named"' . "\n";

        $this->assertSame('', template_csv_serializer::parse($withcolumn)['templates'][0]->description);
        $this->assertNull(template_csv_serializer::parse($without)['templates'][0]->description);
    }

    /**
     * A five-column Learning Plan Template Manager export is read through the legacy shim.
     *
     * @return void
     */
    public function test_parse_legacy_lptmanager_file(): void {
        $this->resetAfterTest();

        $csv = '"Short name","Description","Description format","Competency Framework ID Number",'
            . '"Cross-referenced competency ID numbers"' . "\n"
            . '"Work role","<p>Body</p>","1","NICE","K0001,K0002"' . "\n";

        $parsed = template_csv_serializer::parse($csv);
        $this->assertSame('', $parsed['error']);
        $this->assertTrue($parsed['legacy']);
        $this->assertCount(1, $parsed['templates']);
        $this->assertSame('Work role', $parsed['templates'][0]->shortname);
        $this->assertCount(2, $parsed['links']);
        $this->assertSame('NICE', $parsed['links'][0]->frameworkidnumber);
        $this->assertSame('K0001', $parsed['links'][0]->competencyidnumber);
        $this->assertSame('1', $parsed['links'][1]->sortorder);
        // The legacy format has no parent idnumber, so the parent key is the shortname.
        $this->assertSame('Work role', $parsed['links'][0]->parentshortname);
    }

    /**
     * A framework CSV is refused by name rather than misread as a template file.
     *
     * @return void
     */
    public function test_parse_refuses_a_framework_csv(): void {
        $this->resetAfterTest();

        $csv = '"' . implode('","', framework_csv_serializer::CORE_HEADERS) . '"' . "\n"
            . '"","FW-1","FW","","1","A,B","","","0","null","","","1",""' . "\n";

        $parsed = template_csv_serializer::parse($csv);
        $this->assertSame(
            get_string('central_plans_import_frameworkcsv', 'local_dimensions'),
            $parsed['error']
        );
        $this->assertSame([], $parsed['templates']);
    }

    /**
     * A file with neither a rowtype column nor exactly five columns is refused readably.
     *
     * @return void
     */
    public function test_parse_refuses_an_unknown_format(): void {
        $this->resetAfterTest();

        $csv = '"alpha","beta","gamma"' . "\n" . '"1","2","3"' . "\n";

        $parsed = template_csv_serializer::parse($csv);
        $this->assertSame(
            get_string('central_plans_import_unknownformat', 'local_dimensions'),
            $parsed['error']
        );
    }

    /**
     * The cf_customscss column is emitted only when the custom SCSS feature is enabled.
     *
     * @return void
     */
    public function test_headers_scss_toggle(): void {
        $this->resetAfterTest();
        $this->assertNotContains('cf_customscss', template_csv_serializer::headers(false));
        $this->assertContains('cf_customscss', template_csv_serializer::headers(true));
        $this->assertSame(
            template_csv_serializer::HEADERS_CORE,
            array_slice(template_csv_serializer::headers(true), 0, count(template_csv_serializer::HEADERS_CORE))
        );
    }

    /**
     * Due dates serialise and parse as UTC, so an untouched round trip is byte-identical.
     *
     * @return void
     */
    public function test_duedate_roundtrips_in_utc(): void {
        $this->resetAfterTest();

        $midnight = \DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            '2027-03-01',
            new \DateTimeZone('UTC')
        )->getTimestamp();

        $this->assertSame($midnight, template_csv_serializer::parse_duedate('2027-03-01'));
        $this->assertSame($midnight + 3600 + 1800, template_csv_serializer::parse_duedate('2027-03-01 01:30'));
        $this->assertSame(0, template_csv_serializer::parse_duedate(''));
        $this->assertSame(1767225600, template_csv_serializer::parse_duedate('1767225600'));
        $this->assertNull(template_csv_serializer::parse_duedate('not a date'));
        $this->assertNull(template_csv_serializer::parse_duedate('2027-13-45'));
    }

    /**
     * A template exported from a course category reports the category, not a bare empty cell.
     *
     * @return void
     */
    public function test_export_describes_a_category_context(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);

        $category = $this->getDataGenerator()->create_category(['name' => 'Nursing', 'idnumber' => 'NUR']);
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $template = $ccg->create_template([
            'shortname' => 'Scoped',
            'contextid' => \context_coursecat::instance((int) $category->id)->id,
        ]);

        $parsed = template_csv_serializer::parse(
            template_csv_serializer::export_templates([(int) $template->get('id')], false)['content']
        );
        $this->assertSame('Nursing (NUR)', $parsed['templates'][0]->sourcecontext);
    }

    /**
     * One file carries many templates, each followed by its own link rows.
     *
     * @return void
     */
    public function test_export_many_templates_in_one_file(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);

        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework(['shortname' => 'FW', 'idnumber' => 'FW-1']);
        $competency = $ccg->create_competency([
            'competencyframeworkid' => (int) $framework->get('id'),
            'idnumber' => 'C1',
        ]);
        $alpha = $ccg->create_template(['shortname' => 'Alpha']);
        $beta = $ccg->create_template(['shortname' => 'Beta']);
        $ccg->create_template_competency([
            'templateid' => (int) $beta->get('id'),
            'competencyid' => (int) $competency->get('id'),
        ]);

        $result = template_csv_serializer::export_templates(
            [(int) $alpha->get('id'), (int) $beta->get('id')],
            false
        );
        $this->assertSame('learningplantemplates.csv', $result['filename']);

        $parsed = template_csv_serializer::parse($result['content']);
        $this->assertCount(2, $parsed['templates']);
        $this->assertCount(1, $parsed['links']);
        $this->assertSame('Alpha', $parsed['templates'][0]->shortname);
        $this->assertSame('Beta', $parsed['templates'][1]->shortname);
    }

    /**
     * An over-long shortname survives the parse untruncated, for the analyser to report.
     *
     * @return void
     */
    public function test_parse_does_not_truncate_a_long_shortname(): void {
        $this->resetAfterTest();

        $long = str_repeat('a', 140);
        $csv = '"rowtype","shortname"' . "\n" . '"template","' . $long . '"' . "\n";

        $parsed = template_csv_serializer::parse($csv);
        $this->assertSame(140, \core_text::strlen($parsed['templates'][0]->shortname));
    }
}
