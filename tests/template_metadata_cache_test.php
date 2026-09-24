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
 * Tests that a template's metadata has one shape whether it was read from the cache or built.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\template_metadata_cache
 */
final class template_metadata_cache_test extends \advanced_testcase {
    /** @var int A timemodified no template created by the test can carry. */
    private const TOUCHED = 12345;

    /** @var int Template that sets every option explicitly. */
    protected $explicitid;

    /** @var int Template that sets nothing, so both options inherit the site settings. */
    protected $inheritid;

    /**
     * Two templates and site settings that differ from both the plugin defaults and the explicit template.
     *
     * The explicit template says "enrolled" and "no" while the site says "active" and yes, so
     * every resolved value asserted below can only come from one of the two sources.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        helper::ensure_all_fields();
        set_config('enrollmentfilter', constants::ENROLLMENTFILTER_ACTIVE, 'local_dimensions');
        set_config('singlecourseredirect', 1, 'local_dimensions');

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $this->explicitid = (int) $generator->create_template()->get('id');
        $this->inheritid = (int) $generator->create_template()->get('id');

        $formdata = (object) (['id' => $this->explicitid] + helper::template_customfields_to_formdata([
            'cf_displaymode' => (string) constants::DISPLAYMODE_PLAN,
            'cf_enrollmentfilter' => constants::ENROLLMENTFILTER_ENROLLED,
            'cf_singlecourseredirect' => constants::SINGLECOURSEREDIRECT_NO,
        ]));
        lp_handler::create()->instance_form_save($formdata, true);

        template_metadata_cache::purge_all();
    }

    /**
     * A batch read built from the database returns what the same batch returns from the cache.
     *
     * @return void
     */
    public function test_get_metadata_for_many_cold_read_equals_warm_read(): void {
        $ids = [$this->explicitid, $this->inheritid];
        $this->assert_not_cached($ids);

        $cold = template_metadata_cache::get_metadata_for_many($ids);
        $this->touch_templates($ids);
        $warm = template_metadata_cache::get_metadata_for_many($ids);

        $this->assert_served_from_cache($cold, $warm, $ids);
        $this->assertSame($warm, $cold);
        $this->assert_resolved($cold[$this->explicitid], $cold[$this->inheritid]);
    }

    /**
     * A batch mixing a cached template with an uncached one returns both in one shape.
     *
     * @return void
     */
    public function test_get_metadata_for_many_mixed_batch_returns_one_shape(): void {
        template_metadata_cache::get_metadata_for_many([$this->explicitid]);
        $this->assert_not_cached([$this->inheritid]);

        $mixed = template_metadata_cache::get_metadata_for_many([$this->explicitid, $this->inheritid]);

        $this->assertSame(array_keys($mixed[$this->explicitid]), array_keys($mixed[$this->inheritid]));
        $this->assert_resolved($mixed[$this->explicitid], $mixed[$this->inheritid]);
    }

    /**
     * A single read built from the database returns what the same read returns from the cache.
     *
     * @return void
     */
    public function test_get_template_metadata_cold_read_equals_warm_read(): void {
        $ids = [$this->explicitid, $this->inheritid];
        $this->assert_not_cached($ids);

        $cold = [];
        foreach ($ids as $id) {
            $cold[$id] = template_metadata_cache::get_template_metadata($id);
        }
        $this->touch_templates($ids);
        $warm = [];
        foreach ($ids as $id) {
            $warm[$id] = template_metadata_cache::get_template_metadata($id);
        }

        $this->assert_served_from_cache($cold, $warm, $ids);
        $this->assertSame($warm, $cold);
        $this->assert_resolved($cold[$this->explicitid], $cold[$this->inheritid]);
    }

    /**
     * The single and the batch reader build the same payload, since callers merge their results into one map.
     *
     * @return void
     */
    public function test_single_and_batch_readers_agree(): void {
        $ids = [$this->explicitid, $this->inheritid];

        $single = [];
        foreach ($ids as $id) {
            $single[$id] = template_metadata_cache::get_template_metadata($id);
        }
        template_metadata_cache::purge_all();
        $batch = template_metadata_cache::get_metadata_for_many($ids);

        $this->assertSame($single, $batch);
    }

    /**
     * A cached template follows a change to the site settings, because the cache holds the unresolved keys.
     *
     * @return void
     */
    public function test_warm_read_follows_the_site_settings(): void {
        $ids = [$this->explicitid, $this->inheritid];
        template_metadata_cache::get_metadata_for_many($ids);

        set_config('enrollmentfilter', constants::ENROLLMENTFILTER_ENROLLEDORSELF, 'local_dimensions');
        set_config('singlecourseredirect', 0, 'local_dimensions');
        $warm = template_metadata_cache::get_metadata_for_many($ids);
        $single = template_metadata_cache::get_template_metadata($this->inheritid);

        $this->assertSame(constants::ENROLLMENTFILTER_ENROLLEDORSELF, $warm[$this->inheritid]['enrollmentfilter']);
        $this->assertFalse($warm[$this->inheritid]['singlecourseredirect']);
        $this->assertSame($warm[$this->inheritid], $single);
        $this->assertSame(constants::ENROLLMENTFILTER_ENROLLED, $warm[$this->explicitid]['enrollmentfilter']);
    }

    /**
     * A field whose options are spelt "key|label", as older releases provisioned them, decodes by the same positions.
     *
     * @return void
     */
    public function test_key_label_options_decode_by_position(): void {
        global $DB;

        $fields = [
            constants::CFIELD_ENROLLMENTFILTER => constants::enrollmentfilter_options(),
            constants::CFIELD_SINGLECOURSEREDIRECT => constants::singlecourseredirect_options(),
        ];
        foreach ($fields as $shortname => $options) {
            $fieldid = (int) helper::find_field_by_shortname($shortname, helper::AREA_LP)->get('id');
            $config = json_decode((string) $DB->get_field('customfield_field', 'configdata', ['id' => $fieldid]), true);
            $lines = [];
            foreach ($options as $key => $label) {
                $lines[] = $key . '|' . $label;
            }
            $config['options'] = implode("\n", $lines);
            $DB->set_field('customfield_field', 'configdata', json_encode($config), ['id' => $fieldid]);
        }

        $metadata = template_metadata_cache::get_metadata_for_many([$this->explicitid])[$this->explicitid];

        $this->assertSame(constants::ENROLLMENTFILTER_ENROLLED, $metadata['enrollmentfilter_raw']);
        $this->assertSame(constants::SINGLECOURSEREDIRECT_NO, $metadata['singlecourseredirect_raw']);
    }

    /**
     * Asserts the templates have no cache entry, so the next read is built from the database.
     *
     * @param array $ids Template ids.
     * @return void
     */
    private function assert_not_cached(array $ids): void {
        $cache = \cache::make('local_dimensions', 'template_metadata');
        foreach ($ids as $id) {
            $this->assertFalse($cache->get($id), "Template $id is already cached.");
        }
    }

    /**
     * Moves each template's timemodified in the database without invalidating the cache.
     *
     * @param array $ids Template ids.
     * @return void
     */
    private function touch_templates(array $ids): void {
        global $DB;

        foreach ($ids as $id) {
            $DB->set_field('competency_template', 'timemodified', self::TOUCHED, ['id' => $id]);
        }
    }

    /**
     * Asserts the warm read came from the cache: it still carries the timemodified the cold read saw.
     *
     * Without this, both reads could have been built from the database and compared nothing.
     *
     * @param array $cold Payloads of the first read, keyed by template id.
     * @param array $warm Payloads of the second read, keyed by template id.
     * @param array $ids Template ids.
     * @return void
     */
    private function assert_served_from_cache(array $cold, array $warm, array $ids): void {
        foreach ($ids as $id) {
            $this->assertNotSame(self::TOUCHED, $cold[$id]['timemodified']);
            $this->assertSame($cold[$id]['timemodified'], $warm[$id]['timemodified']);
        }
    }

    /**
     * Asserts the resolved option keys and the display mode type of both templates.
     *
     * @param array $explicit Payload of the template setting every option.
     * @param array $inherit Payload of the template inheriting the site settings.
     * @return void
     */
    private function assert_resolved(array $explicit, array $inherit): void {
        $this->assertSame(constants::DISPLAYMODE_PLAN, $explicit['displaymode']);
        $this->assertSame(constants::ENROLLMENTFILTER_ENROLLED, $explicit['enrollmentfilter_raw']);
        $this->assertSame(constants::ENROLLMENTFILTER_ENROLLED, $explicit['enrollmentfilter']);
        $this->assertSame(constants::SINGLECOURSEREDIRECT_NO, $explicit['singlecourseredirect_raw']);
        $this->assertFalse($explicit['singlecourseredirect']);

        $this->assertSame(constants::DISPLAYMODE_COMPETENCIES, $inherit['displaymode']);
        $this->assertSame(constants::ENROLLMENTFILTER_INHERIT, $inherit['enrollmentfilter_raw']);
        $this->assertSame(constants::ENROLLMENTFILTER_ACTIVE, $inherit['enrollmentfilter']);
        $this->assertSame(constants::SINGLECOURSEREDIRECT_INHERIT, $inherit['singlecourseredirect_raw']);
        $this->assertTrue($inherit['singlecourseredirect']);
    }
}
