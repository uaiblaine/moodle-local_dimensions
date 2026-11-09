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

namespace local_dimensions\external;

use core_external\external_api;
use local_dimensions\constants;
use local_dimensions\helper;
use local_dimensions\local\card_settings;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * What get_competency_courses tells the plan's course cards (the course card contract, sections 3 and 8).
 *
 * Boost Union's settings and the theme's renderer are stood in for through the service's two seams, so every test
 * runs without Boost Union installed; each payload is cleaned through execute_returns(), so a flag the allowlist
 * drops fails here.
 *
 * @package    local_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(get_competency_courses::class)]
final class competency_courses_card_test extends \advanced_testcase {
    /** @var array Course id lists the stand-in theme was asked for, one entry per call. */
    private array $asked = [];

    /**
     * The service with Boost Union's settings, and optionally the theme's image, stood in for.
     *
     * @param card_settings $settings The settings the request reads.
     * @param bool $standin Whether the theme's image comes from a stand-in that records its calls and answers a
     *     pattern; false leaves the service's own closure.
     * @return string The class to call execute() on.
     */
    private function service(card_settings $settings, bool $standin = true): string {
        $this->asked = [];
        $asked = &$this->asked;
        $service = new class extends get_competency_courses {
            /** @var card_settings|null The settings the request reads. */
            public static ?card_settings $settings = null;

            /** @var \Closure|null The theme's image, null for the service's own closure. */
            public static ?\Closure $generated = null;

            /**
             * The stood-in settings.
             *
             * @return card_settings
             */
            #[\Override]
            protected static function card_settings(): card_settings {
                return self::$settings ?? parent::card_settings();
            }

            /**
             * The stood-in theme image, else the service's own.
             *
             * @param card_settings $settings This request's settings.
             * @return callable
             */
            #[\Override]
            protected static function generated_images(card_settings $settings): callable {
                return self::$generated ?? parent::generated_images($settings);
            }
        };
        $service::$settings = $settings;
        $service::$generated = $standin
            ? static function (array $courseids) use (&$asked): array {
                $asked[] = $courseids;
                return array_fill_keys($courseids, 'data:image/svg+xml;base64,PHN2Zy8+');
            }
            : null;

        return $service::class;
    }

    /**
     * A competency in a plan of a new learner, linked to the given courses, the learner enrolled in each.
     *
     * @param array $courses The courses (stdClass).
     * @return array [competency id, plan id, the learner].
     */
    private function plan_with(array $courses): array {
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);
        set_config('enrollmentfilter', constants::ENROLLMENTFILTER_ALL, 'local_dimensions');

        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework(['visible' => 1]);
        $competencyid = (int) $ccg->create_competency(['competencyframeworkid' => $framework->get('id')])->get('id');
        $user = $this->getDataGenerator()->create_user();
        foreach ($courses as $course) {
            \core_competency\api::add_competency_to_course((int) $course->id, $competencyid);
            $this->getDataGenerator()->enrol_user((int) $user->id, (int) $course->id, 'student');
        }
        $planid = (int) $ccg->create_plan(['userid' => $user->id, 'status' => \core_competency\plan::STATUS_ACTIVE])->get('id');
        $ccg->create_plan_competency(['planid' => $planid, 'competencyid' => $competencyid]);

        return [$competencyid, $planid, $user];
    }

    /**
     * Give a course an image of its own.
     *
     * @param \stdClass $course The course.
     * @return void
     */
    private function add_image(\stdClass $course): void {
        global $CFG;

        get_file_storage()->create_file_from_pathname([
            'contextid' => \core\context\course::instance((int) $course->id)->id,
            'component' => 'course',
            'filearea' => 'overviewfiles',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'cover.png',
        ], $CFG->dirroot . '/lib/tests/fixtures/gd-logo.png');
    }

    /**
     * Run the service as the learner and clean the payload.
     *
     * @param string $service The class to call.
     * @param int $competencyid The competency.
     * @param int $planid The plan.
     * @param \stdClass $user The learner.
     * @return array The cleaned rows, keyed by course id.
     */
    private function rows(string $service, int $competencyid, int $planid, \stdClass $user): array {
        $this->setUser($user);
        $result = external_api::clean_returnvalue(
            get_competency_courses::execute_returns(),
            $service::execute($competencyid, $planid)
        );

        return array_column($result, null, 'id');
    }

    /**
     * Boost Union's progress and image settings reach every row, and under another theme the rows are drawn whole.
     *
     * With the cover gone no theme image is asked for. The control is the same site with the settings not applying.
     *
     * Changes that must make it fail: a flag not sent, or sent from the wrong setting; the theme asked with no cover.
     *
     * @return void
     */
    public function test_the_flags_reach_every_row(): void {
        $this->resetAfterTest();
        $courses = [$this->getDataGenerator()->create_course(), $this->getDataGenerator()->create_course()];
        [$competencyid, $planid, $user] = $this->plan_with($courses);
        set_config(card_settings::SETTING_PROGRESS, 'no', card_settings::COMPONENT);
        set_config(card_settings::SETTING_IMAGES, 'list,summary', card_settings::COMPONENT);

        $rows = $this->rows($this->service(new card_settings(true)), $competencyid, $planid, $user);
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertFalse($row['showprogress']);
            $this->assertFalse($row['showcover']);
            $this->assertSame('', $row['courseimage']);
        }
        $this->assertSame([], $this->asked, 'no cover is drawn, so the theme is not asked');

        // Control: under another theme the same settings change nothing.
        $rows = $this->rows($this->service(new card_settings(false)), $competencyid, $planid, $user);
        foreach ($rows as $row) {
            $this->assertTrue($row['showprogress']);
            $this->assertTrue($row['showcover']);
        }
        $this->assertSame([], $this->asked, 'nor under another theme');
    }

    /**
     * A course without an image gets the theme's, asked once for the courses without one; a course keeps its own.
     *
     * Changes that must make it fail: the theme asked under every theme, or for every course; its answer lost on the
     * way to the client (PARAM_URL refuses a data URI).
     *
     * @return void
     */
    public function test_a_course_without_an_image_gets_the_themes(): void {
        $this->resetAfterTest();
        $with = $this->getDataGenerator()->create_course(['fullname' => 'A with an image']);
        $without = $this->getDataGenerator()->create_course(['fullname' => 'B without an image']);
        $this->add_image($with);
        [$competencyid, $planid, $user] = $this->plan_with([$with, $without]);

        $rows = $this->rows($this->service(new card_settings(true)), $competencyid, $planid, $user);
        $this->assertSame([[(int) $without->id]], $this->asked, 'asked once, for the course without an image');
        $this->assertSame('data:image/svg+xml;base64,PHN2Zy8+', $rows[$without->id]['courseimage']);
        $this->assertStringContainsString('/pluginfile.php/', $rows[$with->id]['courseimage']);
        $this->assertStringEndsWith('/cover.png', $rows[$with->id]['courseimage']);

        // Control: under another theme the course without an image stays without one.
        $rows = $this->rows($this->service(new card_settings(false)), $competencyid, $planid, $user);
        $this->assertSame([], $this->asked);
        $this->assertSame('', $rows[$without->id]['courseimage']);
    }

    /**
     * An answer whose every course has its image asks the theme nothing, so core's renderer is never created.
     *
     * @return void
     */
    public function test_every_course_with_an_image_asks_the_theme_nothing(): void {
        $this->resetAfterTest();
        $courses = [$this->getDataGenerator()->create_course(), $this->getDataGenerator()->create_course()];
        foreach ($courses as $course) {
            $this->add_image($course);
        }
        [$competencyid, $planid, $user] = $this->plan_with($courses);

        $rows = $this->rows($this->service(new card_settings(true)), $competencyid, $planid, $user);
        $this->assertSame([], $this->asked);
        foreach ($rows as $row) {
            $this->assertStringEndsWith('/cover.png', $row['courseimage']);
        }
    }

    /**
     * The service's own closure reaches core's general renderer under Boost Union's settings, and its pattern
     * passes the shape check on its way to the client.
     *
     * @return void
     */
    public function test_the_services_own_closure_answers_cores_pattern(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        [$competencyid, $planid, $user] = $this->plan_with([$course]);

        $rows = $this->rows($this->service(new card_settings(true), false), $competencyid, $planid, $user);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $rows[$course->id]['courseimage']);
    }

    /**
     * A course with completion tracking says so; one without does not, so its card draws no "0 %" row.
     *
     * Changes that must make it fail: hascompletion sent true whatever the course; the owner's tracking not read.
     *
     * @return void
     */
    public function test_a_course_without_completion_tracking_says_so(): void {
        global $CFG;

        $this->resetAfterTest();
        $CFG->enablecompletion = 1;
        $tracked = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $untracked = $this->getDataGenerator()->create_course(['enablecompletion' => 0]);
        [$competencyid, $planid, $user] = $this->plan_with([$tracked, $untracked]);

        $rows = $this->rows($this->service(new card_settings(false)), $competencyid, $planid, $user);
        $this->assertTrue($rows[$tracked->id]['hascompletion']);
        $this->assertFalse($rows[$untracked->id]['hascompletion']);
    }
}
