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

namespace local_dimensions;

use local_dimensions\customfield\lp_handler;

/**
 * Tests for the plugin-side data copy behind full template duplication.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\helper::copy_template_plugin_data
 */
final class helper_duplicate_test extends \advanced_testcase {
    /**
     * Custom field rows, embedded files and built-in images are all copied.
     *
     * @return void
     */
    public function test_copies_customfields_embedded_files_and_builtin_images(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enablecustomscss', 1, 'local_dimensions');
        helper::ensure_custom_fields_exist(helper::AREA_LP);

        $lpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $sourceid = (int) $lpg->create_template(['shortname' => 'Source'])->get('id');
        $targetid = (int) $lpg->create_template(['shortname' => 'Copy'])->get('id');

        // Seed one select, one text and the SCSS textarea field on the source.
        $formdata = (object) [
            'id' => $sourceid,
            'customfield_' . constants::CFIELD_DISPLAYMODE => 2,
            'customfield_' . constants::CFIELD_CUSTOMBGCOLOR => '#112233',
            'customfield_' . constants::CFIELD_CUSTOMSCSS . '_editor' => [
                'text' => '.hero { color: red; }',
                'format' => FORMAT_PLAIN,
            ],
        ];
        lp_handler::create()->instance_form_save($formdata, true);

        $fs = get_file_storage();
        $syscontextid = \core\context\system::instance()->id;

        // A built-in card image, keyed by the template id.
        $fs->create_file_from_string([
            'contextid' => $syscontextid,
            'component' => picture_manager::COMPONENT,
            'filearea' => picture_manager::FILEAREA_TEMPLATE_CARD,
            'itemid' => $sourceid,
            'filepath' => '/',
            'filename' => 'card.png',
        ], 'card-bytes');

        // A file embedded in the SCSS textarea data row, keyed by the DATA id.
        $scssfield = $DB->get_record(
            'customfield_field',
            ['shortname' => constants::CFIELD_CUSTOMSCSS],
            '*',
            MUST_EXIST
        );
        $sourcedata = $DB->get_record(
            'customfield_data',
            ['fieldid' => $scssfield->id, 'instanceid' => $sourceid],
            '*',
            MUST_EXIST
        );
        $fs->create_file_from_string([
            'contextid' => $syscontextid,
            'component' => 'customfield_textarea',
            'filearea' => 'value',
            'itemid' => (int) $sourcedata->id,
            'filepath' => '/',
            'filename' => 'embedded.png',
        ], 'embedded-bytes');

        helper::copy_template_plugin_data($sourceid, $targetid);

        // Select value copied (1-based option index in intvalue).
        $modefield = $DB->get_record(
            'customfield_field',
            ['shortname' => constants::CFIELD_DISPLAYMODE],
            '*',
            MUST_EXIST
        );
        $copiedmode = $DB->get_record(
            'customfield_data',
            ['fieldid' => $modefield->id, 'instanceid' => $targetid],
            '*',
            MUST_EXIST
        );
        $this->assertSame(2, (int) $copiedmode->intvalue);

        // Text value copied to both the datafield and the mirror column.
        $colorfield = $DB->get_record(
            'customfield_field',
            ['shortname' => constants::CFIELD_CUSTOMBGCOLOR],
            '*',
            MUST_EXIST
        );
        $copiedcolor = $DB->get_record(
            'customfield_data',
            ['fieldid' => $colorfield->id, 'instanceid' => $targetid],
            '*',
            MUST_EXIST
        );
        $this->assertSame('#112233', $copiedcolor->charvalue);
        $this->assertSame('#112233', $copiedcolor->value);

        // Embedded file re-keyed to the NEW data row id; source untouched.
        $copieddata = $DB->get_record(
            'customfield_data',
            ['fieldid' => $scssfield->id, 'instanceid' => $targetid],
            '*',
            MUST_EXIST
        );
        $this->assertNotEquals((int) $sourcedata->id, (int) $copieddata->id);
        $this->assertSame('.hero { color: red; }', $copieddata->value);
        $copiedfile = $fs->get_file(
            $syscontextid,
            'customfield_textarea',
            'value',
            (int) $copieddata->id,
            '/',
            'embedded.png'
        );
        $this->assertNotEmpty($copiedfile);
        $this->assertSame('embedded-bytes', $copiedfile->get_content());
        $this->assertNotEmpty(
            $fs->get_file($syscontextid, 'customfield_textarea', 'value', (int) $sourcedata->id, '/', 'embedded.png')
        );

        // Built-in card image copied to the new template id.
        $copiedcard = $fs->get_file(
            $syscontextid,
            picture_manager::COMPONENT,
            picture_manager::FILEAREA_TEMPLATE_CARD,
            $targetid,
            '/',
            'card.png'
        );
        $this->assertNotEmpty($copiedcard);
        $this->assertSame('card-bytes', $copiedcard->get_content());
    }

    /**
     * Re-running the copy replaces rows/files instead of colliding or duplicating.
     *
     * @return void
     */
    public function test_copy_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);

        $lpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $sourceid = (int) $lpg->create_template(['shortname' => 'Source'])->get('id');
        $targetid = (int) $lpg->create_template(['shortname' => 'Copy'])->get('id');

        $formdata = (object) [
            'id' => $sourceid,
            'customfield_' . constants::CFIELD_DISPLAYMODE => 2,
        ];
        lp_handler::create()->instance_form_save($formdata, true);

        helper::copy_template_plugin_data($sourceid, $targetid);
        helper::copy_template_plugin_data($sourceid, $targetid);

        $modefield = $DB->get_record(
            'customfield_field',
            ['shortname' => constants::CFIELD_DISPLAYMODE],
            '*',
            MUST_EXIST
        );
        $rows = $DB->get_records(
            'customfield_data',
            ['fieldid' => $modefield->id, 'instanceid' => $targetid]
        );
        $this->assertCount(1, $rows);
        $this->assertSame(2, (int) reset($rows)->intvalue);
    }

    /**
     * A source with no plugin data leaves the target untouched (no rows, no files).
     *
     * @return void
     */
    public function test_source_without_data_is_a_noop(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);

        $lpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $sourceid = (int) $lpg->create_template(['shortname' => 'Bare'])->get('id');
        $targetid = (int) $lpg->create_template(['shortname' => 'Copy'])->get('id');

        helper::copy_template_plugin_data($sourceid, $targetid);

        $this->assertSame(0, $DB->count_records('customfield_data', ['instanceid' => $targetid]));
    }
}
