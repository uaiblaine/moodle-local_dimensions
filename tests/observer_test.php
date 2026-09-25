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

/**
 * Tests for local_dimensions event observer.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions;

use core_competency\api;
use local_dimensions\customfield\competency_handler;
use local_dimensions\customfield\lp_handler;

/**
 * Observer test class.
 *
 * Verifies that the observer:
 *  - saves the custom fields a form posts, under the id the event carries;
 *  - ignores a POST without a valid sesskey, quietly, and still invalidates caches;
 *  - invalidates the relevant MUC caches on create/update/delete events;
 *  - cleans up custom field data when a competency or template is deleted.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_dimensions\observer
 */
final class observer_test extends \advanced_testcase {
    /**
     * Reset the database after each test and act as the admin user.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    /**
     * Helper: create a framework, then a competency through the core API.
     *
     * The generator writes the persistent directly and fires no event; the API fires
     * competency_created, which is what reaches the observer.
     *
     * @return \core_competency\competency
     */
    private function create_competency(): \core_competency\competency {
        /** @var \core_competency_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $gen->create_framework();
        return api::create_competency((object) [
            'shortname' => 'Observed competency',
            'idnumber' => 'observed' . random_string(8),
            'competencyframeworkid' => $framework->get('id'),
        ]);
    }

    /**
     * Helper: create a template through the core API, which fires competency_template_created.
     *
     * @return \core_competency\template
     */
    private function create_template(): \core_competency\template {
        return api::create_template((object) [
            'shortname' => 'Observed template',
            'contextid' => \context_system::instance()->id,
        ]);
    }

    /**
     * Post a form submission for the observer to read.
     *
     * @param string $value Value of the custom background colour field, a text field in both areas.
     * @param string|null $sesskey The sesskey to post, or null to post none.
     * @return void
     */
    private function post_customfield(string $value, ?string $sesskey): void {
        $_POST = ['customfield_' . constants::CFIELD_CUSTOMBGCOLOR => $value];
        if ($sesskey !== null) {
            $_POST['sesskey'] = $sesskey;
        }
    }

    /**
     * The stored value of the custom background colour field for one instance, or null when none.
     *
     * @param string $area The customfield area (competency or lp).
     * @param int $instanceid The competency or template id.
     * @return string|null
     */
    private function stored_bgcolor(string $area, int $instanceid): ?string {
        global $DB;

        $value = $DB->get_field_sql(
            "SELECT d.value
               FROM {customfield_data} d
               JOIN {customfield_field} f ON f.id = d.fieldid
               JOIN {customfield_category} c ON c.id = f.categoryid
              WHERE c.component = :component AND c.area = :area
                AND f.shortname = :shortname AND d.instanceid = :instanceid",
            [
                'component' => 'local_dimensions',
                'area' => $area,
                'shortname' => constants::CFIELD_CUSTOMBGCOLOR,
                'instanceid' => $instanceid,
            ]
        );
        return $value === false ? null : (string) $value;
    }

    /**
     * Creating a competency with no form submitted dispatches to the observer and saves nothing.
     */
    public function test_competency_created_without_form_saves_nothing(): void {
        $competency = $this->create_competency();

        $this->assertNull($this->stored_bgcolor('competency', (int) $competency->get('id')));
    }

    /**
     * A form posting a custom field saves it under the id the created event carries.
     *
     * The POST has no id at all, which is what a create form sends: the value can only land on
     * the new competency if the observer takes the id from the event.
     */
    public function test_competency_created_saves_the_posted_customfield(): void {
        $this->post_customfield('#123456', sesskey());

        $competency = $this->create_competency();

        $this->assertSame('#123456', $this->stored_bgcolor('competency', (int) $competency->get('id')));
    }

    /**
     * The template area goes through the same path, from the template_created event.
     */
    public function test_template_created_saves_the_posted_customfield(): void {
        $this->post_customfield('#654321', sesskey());

        $template = $this->create_template();

        $this->assertSame('#654321', $this->stored_bgcolor('lp', (int) $template->get('id')));
    }

    /**
     * A POST carrying custom fields but no sesskey, as a web service call sends, is ignored quietly.
     *
     * The observer used to call confirm_sesskey() without an argument, which throws on a missing
     * sesskey; the event dispatcher reported it through debugging() and the cache invalidation
     * after it never ran.
     */
    public function test_a_post_without_sesskey_is_ignored_and_caches_are_still_invalidated(): void {
        $competency = $this->create_competency();
        $id = (int) $competency->get('id');
        $cache = \cache::make('local_dimensions', 'competency_metadata');
        $cache->set($id, ['sentinel' => true]);

        $this->post_customfield('#abcdef', null);
        $record = $competency->to_record();
        $record->shortname = 'Updated shortname';
        api::update_competency($record);

        $this->assertDebuggingNotCalled();
        $this->assertFalse($cache->get($id), 'competency_metadata cache should be invalidated on update.');
        $this->assertNull($this->stored_bgcolor('competency', $id));
    }

    /**
     * A POST with a wrong sesskey saves nothing, while the same POST with the right one saves.
     */
    public function test_a_wrong_sesskey_saves_nothing(): void {
        $competency = $this->create_competency();
        $id = (int) $competency->get('id');
        $record = $competency->to_record();

        $this->post_customfield('#abcdef', 'not-the-sesskey');
        api::update_competency($record);
        $this->assertNull($this->stored_bgcolor('competency', $id));

        $this->post_customfield('#abcdef', sesskey());
        api::update_competency($record);
        $this->assertSame('#abcdef', $this->stored_bgcolor('competency', $id));
    }

    /**
     * Updating a template with no form submitted must not throw.
     */
    public function test_template_updated_without_form_saves_nothing(): void {
        $template = $this->create_template();
        $record = $template->to_record();
        $record->shortname = 'Updated template';
        api::update_template($record);

        $this->assertNull($this->stored_bgcolor('lp', (int) $template->get('id')));
    }

    /**
     * After a competency_updated event, the metadata cache for that competency
     * must be empty (i.e. it was invalidated by the observer).
     */
    public function test_competency_updated_invalidates_metadata_cache(): void {
        $competency = $this->create_competency();
        $id = (int) $competency->get('id');

        // Prime the cache.
        $cache = \cache::make('local_dimensions', 'competency_metadata');
        $cache->set($id, ['sentinel' => true]);
        $this->assertNotFalse($cache->get($id));

        // Trigger update event.
        $record = $competency->to_record();
        $record->shortname = 'New name';
        api::update_competency($record);

        $this->assertFalse($cache->get($id), 'competency_metadata cache should be invalidated on update.');
    }

    /**
     * After a competency_deleted event, the metadata cache must be cleared
     * and the custom field data instance must be deleted.
     */
    public function test_competency_deleted_clears_caches_and_customfield_data(): void {
        global $DB;

        $competency = $this->create_competency();
        $id = (int) $competency->get('id');

        // Prime the cache.
        $cache = \cache::make('local_dimensions', 'competency_metadata');
        $cache->set($id, ['sentinel' => true]);

        // Insert a fake customfield_data row tied to this competency to verify cleanup.
        $handler = competency_handler::create();
        $contextid = $handler->get_instance_context($id)->id;
        // The area has several configured fields; any one is enough for the
        // cleanup assertion, so pick the lowest id and ignore the rest.
        $fieldid = $DB->get_field_sql(
            "SELECT f.id
               FROM {customfield_field} f
               JOIN {customfield_category} c ON c.id = f.categoryid
              WHERE c.component = :component AND c.area = :area
           ORDER BY f.id",
            ['component' => 'local_dimensions', 'area' => 'competency'],
            IGNORE_MULTIPLE
        );
        $this->assertNotEmpty($fieldid, 'local_dimensions must provision at least one competency customfield.');
        $DB->insert_record('customfield_data', (object) [
            'fieldid' => $fieldid,
            'instanceid' => $id,
            'intvalue' => 0,
            'decvalue' => null,
            'shortcharvalue' => null,
            'charvalue' => null,
            'value' => '',
            'valueformat' => FORMAT_PLAIN,
            'timecreated' => time(),
            'timemodified' => time(),
            'contextid' => $contextid,
        ]);

        api::delete_competency($id);

        $this->assertFalse($cache->get($id), 'competency_metadata cache should be invalidated on delete.');
        $this->assertEquals(
            0,
            $DB->count_records('customfield_data', ['instanceid' => $id, 'fieldid' => $fieldid]),
            'customfield_data for the deleted competency must be removed.'
        );
    }

    /**
     * A value saved through the handler is deleted through the handler, embedded files included.
     *
     * The row carries what the handler writes (component, area and itemid on Moodle 5.1+), so
     * delete_instance() matches it. The file is what tells that path from the fallback sweep:
     * only data_controller::delete() removes the files a textarea value embeds.
     */
    public function test_competency_deleted_removes_handler_saved_data_and_its_files(): void {
        global $DB;

        set_config('enablecustomscss', 1, 'local_dimensions');
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);
        $handler = competency_handler::create();
        $handler->reset_configuration_cache();

        $competency = $this->create_competency();
        $id = (int) $competency->get('id');
        $handler->instance_form_save((object) [
            'id' => $id,
            'customfield_' . constants::CFIELD_CUSTOMSCSS . '_editor' => [
                'text' => 'a { color: red; }',
                'format' => FORMAT_PLAIN,
            ],
        ]);
        $data = $DB->get_record_sql(
            "SELECT d.id, d.contextid
               FROM {customfield_data} d
               JOIN {customfield_field} f ON f.id = d.fieldid
               JOIN {customfield_category} c ON c.id = f.categoryid
              WHERE c.component = :component AND c.area = :area
                AND f.shortname = :shortname AND d.instanceid = :instanceid",
            [
                'component' => 'local_dimensions',
                'area' => 'competency',
                'shortname' => constants::CFIELD_CUSTOMSCSS,
                'instanceid' => $id,
            ],
            MUST_EXIST
        );
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => (int) $data->contextid,
            'component' => 'customfield_textarea',
            'filearea' => 'value',
            'itemid' => (int) $data->id,
            'filepath' => '/',
            'filename' => 'embedded.txt',
        ], 'embedded');
        $this->assertFalse($fs->is_area_empty((int) $data->contextid, 'customfield_textarea', 'value', (int) $data->id));

        api::delete_competency($id);

        $this->assertFalse($DB->record_exists('customfield_data', ['id' => $data->id]));
        $this->assertTrue($fs->is_area_empty((int) $data->contextid, 'customfield_textarea', 'value', (int) $data->id));
    }

    /**
     * After a template_updated event, the template metadata cache must be cleared.
     */
    public function test_template_updated_invalidates_metadata_cache(): void {
        $template = $this->create_template();
        $id = (int) $template->get('id');

        $cache = \cache::make('local_dimensions', 'template_metadata');
        $cache->set($id, ['sentinel' => true]);

        $record = $template->to_record();
        $record->shortname = 'New template name';
        api::update_template($record);

        $this->assertFalse($cache->get($id), 'template_metadata cache should be invalidated on update.');
    }

    /**
     * After a template_deleted event, both template_metadata and template_courses
     * caches must be cleared and customfield_data must be removed.
     */
    public function test_template_deleted_clears_all_caches(): void {
        global $DB;

        $template = $this->create_template();
        $id = (int) $template->get('id');

        $metacache = \cache::make('local_dimensions', 'template_metadata');
        $coursecache = \cache::make('local_dimensions', 'template_courses');
        $metacache->set($id, ['sentinel' => true]);
        $coursecache->set($id, [42]);

        $handler = lp_handler::create();
        $contextid = $handler->get_instance_context($id)->id;
        // The area has several configured fields; any one is enough for the
        // cleanup assertion, so pick the lowest id and ignore the rest.
        $fieldid = $DB->get_field_sql(
            "SELECT f.id
               FROM {customfield_field} f
               JOIN {customfield_category} c ON c.id = f.categoryid
              WHERE c.component = :component AND c.area = :area
           ORDER BY f.id",
            ['component' => 'local_dimensions', 'area' => 'lp'],
            IGNORE_MULTIPLE
        );
        $this->assertNotEmpty($fieldid, 'local_dimensions must provision at least one lp customfield.');
        $DB->insert_record('customfield_data', (object) [
            'fieldid' => $fieldid,
            'instanceid' => $id,
            'intvalue' => 0,
            'decvalue' => null,
            'shortcharvalue' => null,
            'charvalue' => null,
            'value' => '',
            'valueformat' => FORMAT_PLAIN,
            'timecreated' => time(),
            'timemodified' => time(),
            'contextid' => $contextid,
        ]);

        api::delete_template($id);

        $this->assertFalse($metacache->get($id), 'template_metadata cache should be invalidated on delete.');
        $this->assertFalse($coursecache->get($id), 'template_courses cache should be invalidated on delete.');
        $this->assertEquals(
            0,
            $DB->count_records('customfield_data', ['instanceid' => $id, 'fieldid' => $fieldid]),
            'customfield_data for the deleted template must be removed.'
        );
    }
}
