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

namespace local_dimensions\output;

use core_competency\plan;
use local_dimensions\chip_filters;
use local_dimensions\constants;
use local_dimensions\customfield\competency_handler;
use local_dimensions\customfield\lp_handler;
use local_dimensions\helper;

/**
 * Admin-set labels on the learner pages reach the learner filtered to one language.
 *
 * A scale item and a select option are stored as the admin typed them. The fixture label is a
 * multilang pair, which only format_string() resolves: the raw value carries both languages and
 * their markup, so a label that skipped formatting cannot pass.
 *
 * The class-level covers tags stay in docblock form on purpose: moodle-cs on the 4.05 leg cannot
 * see PHP attributes, and the plugin still supports 4.5.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\output\view_plan_summary_page
 * @covers     \local_dimensions\chip_filters
 */
final class learner_labels_test extends \advanced_testcase {
    /** @var string A label in two languages, as an admin stores it. */
    private const MULTILANG = '<span lang="en" class="multilang">Leadership</span><span lang="de" class="multilang">Leitung</span>';

    /** @var string What an English reader must see of it. */
    private const ENGLISH = 'Leadership';

    /**
     * Turn the multilang filter on for strings, as a multilingual site does.
     *
     * @return void
     */
    private function enable_multilang(): void {
        \filter_manager::reset_caches();
        filter_set_global_state('multilang', TEXTFILTER_ON);
        filter_set_applies_to_strings('multilang', true);
    }

    /**
     * A plan of one competency, rated on a scale whose top item is the multilang label.
     *
     * @param string $sublinesource One of the constants::SUBLINE_* keys, stored on the template.
     * @return array Keys: plan (plan), user (stdClass), competencyid (int).
     */
    private function create_rated_plan(string $sublinesource): array {
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);

        $generator = $this->getDataGenerator();
        $ccg = $generator->get_plugin_generator('core_competency');
        $scale = $generator->create_scale(['scale' => 'Beginner,' . self::MULTILANG]);
        $framework = $ccg->create_framework(['scaleid' => $scale->id]);
        $competencyid = (int) $ccg->create_competency(['competencyframeworkid' => $framework->get('id')])->get('id');
        $template = $ccg->create_template();
        $templateid = (int) $template->get('id');
        $ccg->create_template_competency(['templateid' => $templateid, 'competencyid' => $competencyid]);

        $keys = array_keys(constants::subline_source_options());
        lp_handler::create()->instance_form_save((object) [
            'id' => $templateid,
            'customfield_' . constants::CFIELD_SUBLINE_SOURCE => array_search($sublinesource, $keys, true) + 1,
        ], true);

        $user = $generator->create_user();
        $plan = $ccg->create_plan([
            'userid' => $user->id,
            'templateid' => $templateid,
            'status' => plan::STATUS_ACTIVE,
        ]);
        // The generator's configuration makes the last item the proficient one.
        $ccg->create_user_competency([
            'userid' => $user->id,
            'competencyid' => $competencyid,
            'grade' => 2,
            'proficiency' => 1,
        ]);

        return ['plan' => $plan, 'user' => $user, 'competencyid' => $competencyid];
    }

    /**
     * Export the plan overview as the plan's own learner, and return its one competency row.
     *
     * @param array $fixture From create_rated_plan().
     * @return array Keys: row (the competency row), data (the whole context).
     */
    private function export_row(array $fixture): array {
        global $PAGE;
        $this->setUser($fixture['user']);
        $data = (new view_plan_summary_page($fixture['plan']))->export_for_template($PAGE->get_renderer('core'));
        $this->assertCount(1, $data['competencies']);

        return ['row' => $data['competencies'][0], 'data' => $data];
    }

    /**
     * The rating, and the rating subline, read the scale item in the learner's language.
     *
     * @return void
     */
    public function test_the_rating_is_filtered_to_one_language(): void {
        $this->resetAfterTest();
        $this->enable_multilang();
        $fixture = $this->create_rated_plan(constants::SUBLINE_RATING);

        $row = $this->export_row($fixture)['row'];

        $this->assertTrue($row['hasrating']);
        $this->assertSame(self::ENGLISH, $row['rating']);
        $this->assertSame(self::ENGLISH, $row['sublinetext']);
    }

    /**
     * A tag subline and the competency chips read the select option in the learner's language.
     *
     * @return void
     */
    public function test_tag_subline_and_competency_chips_are_filtered_to_one_language(): void {
        $this->resetAfterTest();
        $this->enable_multilang();
        $fixture = $this->create_rated_plan(constants::SUBLINE_TAG1);

        $field = helper::find_field_by_shortname(constants::CFIELD_TAG1, helper::AREA_COMPETENCY);
        $config = $field->get('configdata');
        $config['options'] = self::MULTILANG . "\nOther";
        $field->set('configdata', json_encode($config));
        $field->save();
        competency_handler::create()->instance_form_save((object) [
            'id' => $fixture['competencyid'],
            'customfield_' . constants::CFIELD_TAG1 => 1,
        ], true);
        set_config('viewplan_filter_fields', constants::CFIELD_TAG1, 'local_dimensions');

        $export = $this->export_row($fixture);

        $this->assertSame(self::ENGLISH, $export['row']['sublinetext']);
        $this->assertSame(
            [constants::CFIELD_TAG1 => self::ENGLISH],
            json_decode($export['row']['filtervaluesjson'], true)
        );
        $this->assertSame([['value' => self::ENGLISH]], $export['data']['chipfilters']['groups'][0]['values']);
    }

    /**
     * Course chips read every course in one query, resolve select labels and filter them late.
     *
     * The read count for six courses must equal the count for two. The shared cache keeps the
     * stored label, since it serves every language; each reader gets the label filtered for them.
     *
     * @return void
     */
    public function test_course_chips_are_read_in_one_query_and_filtered_per_reader(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->enable_multilang();

        $generator = $this->getDataGenerator();
        $cfg = $generator->get_plugin_generator('core_customfield');
        $category = $cfg->create_category();
        $level = $cfg->create_field(['categoryid' => $category->get('id'), 'type' => 'text', 'shortname' => 'level']);
        $track = $cfg->create_field([
            'categoryid' => $category->get('id'),
            'type' => 'select',
            'shortname' => 'track',
            'configdata' => ['options' => self::MULTILANG . "\nSafety"],
        ]);
        $courseids = [];
        for ($i = 1; $i <= 6; $i++) {
            $courseid = (int) $generator->create_course()->id;
            $courseids[] = $courseid;
            $cfg->add_instance_data($level, $courseid, 'Level ' . $i);
            // Odd courses take the first option; even ones leave the select unset.
            if ($i % 2) {
                $cfg->add_instance_data($track, $courseid, 1);
            }
        }
        $shortnames = ['level', 'track'];
        $cache = \cache::make('local_dimensions', 'course_customfields');

        // Warm the handler's field list and the filter setup, which are read once per request.
        chip_filters::get_course_values([$courseids[0]], $shortnames);

        $cache->purge();
        $before = $DB->perf_get_reads();
        chip_filters::get_course_values(array_slice($courseids, 0, 2), $shortnames);
        $tworeads = $DB->perf_get_reads() - $before;

        $cache->purge();
        $before = $DB->perf_get_reads();
        $values = chip_filters::get_course_values($courseids, $shortnames);
        $sixreads = $DB->perf_get_reads() - $before;

        $this->assertGreaterThan(0, $tworeads);
        $this->assertSame($tworeads, $sixreads);
        foreach ($courseids as $index => $courseid) {
            $this->assertSame(
                ['level' => 'Level ' . ($index + 1), 'track' => ($index % 2) ? '' : self::ENGLISH],
                $values[$courseid]
            );
        }

        // The cache holds the label as stored, and a cached read is filtered all the same.
        $this->assertSame(self::MULTILANG, $cache->get($courseids[0])['track']);
        $this->assertSame(self::ENGLISH, chip_filters::get_course_values([$courseids[0]], $shortnames)[$courseids[0]]['track']);
    }
}
