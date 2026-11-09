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

use advanced_testcase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The theme's image for a plan card whose course has none of its own (the course card contract, section 8).
 *
 * Every test stands in for core's renderer or for Boost Union's settings, so none needs Boost Union installed.
 *
 * @package    local_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(course_card::class)]
final class course_card_test extends advanced_testcase {
    /**
     * A renderer stand-in that counts its calls and answers one value.
     *
     * @param string $answer What every call answers.
     * @return object With calls (int), answer (string) and get_generated_image_for_id().
     */
    private function renderer(string $answer): object {
        return new class ($answer) {
            /** @var int The calls so far. */
            public int $calls = 0;

            /**
             * The stand-in.
             *
             * @param string $answer What every call answers.
             */
            public function __construct(
                /** @var string What every call answers. */
                public string $answer,
            ) {
            }

            /**
             * The theme's image for a course.
             *
             * @param int $courseid The course.
             * @return string
             */
            public function get_generated_image_for_id(int $courseid): string {
                $this->calls++;
                return $this->answer;
            }
        };
    }

    /**
     * Only a base64 SVG data URI or this site's pluginfile.php passes; every other shape is refused.
     *
     * Changes that must make it fail: the data URI's end anchor dropped; anything accepted after the pluginfile.php
     * prefix; the host check dropped; PARAM_URL dropped from the local URL's check; the quote check dropped.
     *
     * @return void
     */
    public function test_a_generated_image_is_a_data_uri_or_a_local_file(): void {
        global $CFG;

        $pattern = (new \core_geopattern())->datauri();
        $this->assertTrue(course_card::is_generated_image($pattern), 'core\'s pattern');
        $this->assertTrue(course_card::is_generated_image('data:image/svg+xml;base64,PHN2Zy8+'));
        $this->assertTrue(course_card::is_generated_image($CFG->wwwroot . '/pluginfile.php/1/theme_boost_union/fallback/0/a.png'));
        $this->assertTrue(course_card::is_generated_image($CFG->wwwroot . '/pluginfile.php?file=%2F1%2Ftheme_boost_union%2Fa.png'));

        $refused = [
            '',
            'data:image/png;base64,iVBORw0KGgo=',
            'data:text/html;base64,PHNjcmlwdD4=',
            "data:image/svg+xml;base64,PHN2Zy8+\n",
            'data:image/svg+xml,<svg onload="x"/>',
            'javascript:alert(1)',
            'https://elsewhere.example/pluginfile.php/1/a.png',
            $CFG->wwwroot . '/course/view.php?id=1',
            $CFG->wwwroot . '/pluginfile.phpx/1/a.png',
            $CFG->wwwroot . '/pluginfile.php/1/a b.png',
            $CFG->wwwroot . '/pluginfile.php/1/a".png',
            // PARAM_URL keeps a single quote; the check refuses it.
            $CFG->wwwroot . "/pluginfile.php/1/a'b.png",
            // PARAM_URL refuses a bar; nothing else here does.
            $CFG->wwwroot . '/pluginfile.php/1/a|b.png',
            // Another host whose path has a slash where this site's pluginfile.php ends.
            'https://elsewhere.example/' . str_repeat('a', strlen($CFG->wwwroot . '/pluginfile.php') - 26) . '/1/a.png',
        ];
        foreach ($refused as $image) {
            $this->assertFalse(course_card::is_generated_image($image), "accepted: {$image}");
        }
    }

    /**
     * The theme is asked only when a course of the answer has no image, for those courses only.
     *
     * The closure stands in for the theme's renderer: an answer whose every course has its image never calls it, so
     * core's renderer is never created; an answer with courses without calls it once, with exactly those courses,
     * and its answer becomes their image. An answer of another shape is dropped, and so is one for a course that has
     * its own image.
     *
     * Changes that must make it fail: the closure called whatever the images; the answer taken unchecked; the answer
     * allowed to replace a course's own image.
     *
     * @return void
     */
    public function test_the_theme_is_asked_only_when_a_course_has_no_image(): void {
        $this->resetAfterTest();
        $own = 'https://www.example.com/moodle/pluginfile.php/5/course/overviewfiles/a.png';
        $pattern = 'data:image/svg+xml;base64,PHN2Zy8+';
        $asked = [];
        $generated = static function (array $courseids) use (&$asked, $pattern): array {
            $asked[] = $courseids;
            return array_fill_keys($courseids, $pattern) + [1 => $pattern];
        };

        $this->assertSame([1 => $own, 2 => $own], course_card::with_generated([1 => $own, 2 => $own], $generated));
        $this->assertSame([], $asked, 'every course has its image: the theme is not asked');

        $images = course_card::with_generated([1 => $own, 2 => '', 3 => ''], $generated);
        $this->assertSame([[2, 3]], $asked, 'asked once, for the two courses without an image');
        $this->assertSame([1 => $own, 2 => $pattern, 3 => $pattern], $images, 'a course keeps its own image');

        $bad = static fn(array $courseids): array => array_fill_keys($courseids, 'https://elsewhere.example/a.png');
        $this->assertSame([7 => ''], course_card::with_generated([7 => ''], $bad), 'an answer of another shape is dropped');
    }

    /**
     * Under another theme the closure answers nothing and touches no renderer.
     *
     * The stand-in renderer counts its calls; the control is the same closure with the settings active.
     *
     * Change that must make it fail: the renderer asked whatever the theme.
     *
     * @return void
     */
    public function test_the_generated_images_closure_answers_nothing_under_another_theme(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->assertFalse(card_settings::applies($PAGE->theme, true), 'precondition: the request\'s theme is not Boost Union');
        $this->assertSame([], (course_card::generated_images())([1, 2]), 'the request\'s own settings');

        $renderer = $this->renderer('data:image/svg+xml;base64,PHN2Zy8+');
        $this->assertSame([], (course_card::generated_images(new card_settings(false), $renderer))([1, 2]));
        $this->assertSame(0, $renderer->calls, 'no renderer is asked under another theme');

        // Control: under Boost Union the same closure asks the renderer for each course.
        $images = (course_card::generated_images(new card_settings(true), $renderer))([1, 2]);
        $this->assertSame([1, 2], array_keys($images));
        $this->assertSame(2, $renderer->calls);
    }

    /**
     * Under Boost Union with no stand-in, the closure asks core's general renderer, which answers core's pattern.
     *
     * The general target, never the request's: the test proves the closure reaches a renderer that implements
     * get_generated_image_for_id() and that its answer passes the shape check.
     *
     * @return void
     */
    public function test_the_closure_asks_the_general_renderer(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $images = (course_card::generated_images(new card_settings(true)))([(int) $course->id]);
        $this->assertArrayHasKey((int) $course->id, $images);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $images[(int) $course->id]);
        $this->assertTrue(course_card::is_generated_image($images[(int) $course->id]));
    }

    /**
     * While Boost Union's fallback image is chosen, the renderer is asked once per answer.
     *
     * Its answer is one file for every course, and asking again would read the file area once per course. A data
     * URI answer (the pattern) is asked per course, as the control.
     *
     * Change that must make it fail: the fallback image asked once per course.
     *
     * @return void
     */
    public function test_the_fallback_image_is_asked_once_per_answer(): void {
        global $CFG;

        $this->resetAfterTest();
        $settings = new card_settings(true);
        $renderer = $this->renderer($CFG->wwwroot . '/pluginfile.php/1/theme_boost_union/courseoverviewimagefallback/0/f.png');

        set_config(card_settings::SETTING_IMAGESOURCE, card_settings::SOURCE_FALLBACK, card_settings::COMPONENT);
        $images = (course_card::generated_images($settings, $renderer))([11, 12, 13]);
        $this->assertSame(1, $renderer->calls, 'the fallback image was asked for once per course');
        $this->assertSame([11, 12, 13], array_keys($images));
        $this->assertSame([$renderer->answer], array_values(array_unique($images)));

        set_config(card_settings::SETTING_IMAGESOURCE, card_settings::SOURCE_PATTERN, card_settings::COMPONENT);
        $renderer->calls = 0;
        $renderer->answer = 'data:image/svg+xml;base64,PHN2Zy8+';
        (course_card::generated_images($settings, $renderer))([11, 12, 13]);
        $this->assertSame(3, $renderer->calls, 'control: the pattern is asked per course');
    }
}
