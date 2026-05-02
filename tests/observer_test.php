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
 *  - does not throw the core_customfield "id must be set" coding_exception
 *    when competency/template events are dispatched outside a form context;
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
     * Set up: enable competency framework and reset DB after each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    /**
     * Helper: create a framework + competency via the core API (triggers events).
     *
     * @return \core_competency\competency
     */
    private function create_competency(): \core_competency\competency {
        /** @var \core_competency_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $gen->create_framework();
        return $gen->create_competency(['competencyframeworkid' => $framework->get('id')]);
    }

    /**
     * Helper: create a template via the core API (triggers competency_template_created).
     *
     * @return \core_competency\template
     */
    private function create_template(): \core_competency\template {
        /** @var \core_competency_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('core_competency');
        return $gen->create_template();
    }

    /**
     * Creating a competency via the core API must NOT throw the
     * "Caller must ensure that id is already set" coding_exception, even
     * though no form was submitted (regression test for the original bug).
     */
    public function test_competency_created_does_not_throw_without_form(): void {
        $this->expectNotToPerformAssertions();
        // If the observer were to call instance_form_save unguarded, this
        // would throw \coding_exception inside the event dispatch.
        $this->create_competency();
    }

    /**
     * Updating a competency via the core API must not throw.
     */
    public function test_competency_updated_does_not_throw_without_form(): void {
        $competency = $this->create_competency();
        $this->expectNotToPerformAssertions();
        $record = $competency->to_record();
        $record->shortname = 'Updated shortname';
        api::update_competency($record);
    }

    /**
     * Creating/updating a template via the core API must not throw.
     */
    public function test_template_lifecycle_does_not_throw_without_form(): void {
        $this->expectNotToPerformAssertions();
        $template = $this->create_template();
        $record = $template->to_record();
        $record->shortname = 'Updated template';
        api::update_template($record);
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
        // Only insert if we have at least one configured field (skip otherwise).
        $fieldid = $DB->get_field_sql(
            "SELECT f.id
               FROM {customfield_field} f
               JOIN {customfield_category} c ON c.id = f.categoryid
              WHERE c.component = :component AND c.area = :area",
            ['component' => 'local_dimensions', 'area' => 'competency']
        );
        if ($fieldid) {
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
        }

        api::delete_competency($id);

        $this->assertFalse($cache->get($id), 'competency_metadata cache should be invalidated on delete.');
        if ($fieldid) {
            $this->assertEquals(
                0,
                $DB->count_records('customfield_data', ['instanceid' => $id, 'fieldid' => $fieldid]),
                'customfield_data for the deleted competency must be removed.'
            );
        }
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
        $fieldid = $DB->get_field_sql(
            "SELECT f.id
               FROM {customfield_field} f
               JOIN {customfield_category} c ON c.id = f.categoryid
              WHERE c.component = :component AND c.area = :area",
            ['component' => 'local_dimensions', 'area' => 'lp']
        );
        if ($fieldid) {
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
        }

        api::delete_template($id);

        $this->assertFalse($metacache->get($id), 'template_metadata cache should be invalidated on delete.');
        $this->assertFalse($coursecache->get($id), 'template_courses cache should be invalidated on delete.');
        if ($fieldid) {
            $this->assertEquals(
                0,
                $DB->count_records('customfield_data', ['instanceid' => $id, 'fieldid' => $fieldid]),
                'customfield_data for the deleted template must be removed.'
            );
        }
    }
}
