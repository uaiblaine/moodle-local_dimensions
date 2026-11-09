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
 * Boost Union's three course overview settings, as the plan view's course cards read them (the course card contract,
 * section 8).
 *
 * The settings are written with set_config() under Boost Union's component, which needs no Boost Union installed: the
 * reader's decision whether they apply is a constructor argument, so every setting is read both ways on any site, and
 * the theme rule (applies()) takes the installed check as an argument too. Only of_request() asks the site, and its
 * test states which answer it expects from whether Boost Union is there.
 *
 * @package    local_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(card_settings::class)]
final class card_settings_test extends advanced_testcase {
    /**
     * Each of Boost Union's three settings unset, as on a site where Boost Union never saved its page.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        foreach (['courseoverviewshowcourseprogress', 'courseoverviewimagesource', 'courseoverviewshowcourseimages'] as $name) {
            unset_config($name, card_settings::COMPONENT);
        }
    }

    /**
     * The settings apply under Boost Union and under a theme that names it among its parents, and only while it is installed.
     *
     * Changes that must make it fail: the parents not read; Boost Union matched without being installed; any theme
     * taken for Boost Union.
     *
     * @return void
     */
    public function test_the_settings_apply_under_boost_union_or_a_child_only_while_it_is_installed(): void {
        $boostunion = (object) ['name' => 'boost_union', 'parents' => ['boost']];
        $child = (object) ['name' => 'dimensions', 'parents' => ['boost_union', 'boost']];
        $boost = (object) ['name' => 'boost', 'parents' => []];
        $moove = (object) ['name' => 'moove', 'parents' => ['boost']];

        $this->assertTrue(card_settings::applies($boostunion, true));
        $this->assertTrue(card_settings::applies($child, true), 'a child theme names Boost Union among its parents');
        $this->assertFalse(card_settings::applies($boost, true));
        $this->assertFalse(card_settings::applies($moove, true));
        $this->assertFalse(card_settings::applies($boostunion, false), 'Boost Union is not installed');
        $this->assertFalse(card_settings::applies($child, false), 'a child left without Boost Union');
        $this->assertFalse(card_settings::applies((object) [], true), 'a theme without a name');
    }

    /**
     * The request's theme decides: Boost's never applies, Boost Union's applies exactly when Boost Union is installed.
     *
     * @return void
     */
    public function test_of_request_reads_the_theme_and_whether_boost_union_is_installed(): void {
        $installed = \core_component::get_component_directory(card_settings::COMPONENT) !== null;

        $this->assertFalse(card_settings::of_request((object) ['name' => 'boost', 'parents' => []])->active());
        $this->assertSame(
            $installed,
            card_settings::of_request((object) ['name' => 'boost_union', 'parents' => ['boost']])->active()
        );
    }

    /**
     * Show course progress: missing or "yes" draws the row, "no" does not.
     *
     * @return void
     */
    public function test_the_progress_setting_both_ways(): void {
        $settings = new card_settings(true);
        $this->assertTrue($settings->show_progress(), 'missing reads as Boost Union\'s default, yes');

        set_config('courseoverviewshowcourseprogress', 'no', card_settings::COMPONENT);
        $this->assertFalse($settings->show_progress());
        $this->assertFalse($settings->flags()['progress']);

        set_config('courseoverviewshowcourseprogress', 'yes', card_settings::COMPONENT);
        $this->assertTrue($settings->show_progress());
        $this->assertTrue($settings->flags()['progress']);
    }

    /**
     * Course image source: the fallback image only when chosen, the pattern otherwise.
     *
     * @return void
     */
    public function test_the_image_source_setting_both_ways(): void {
        $settings = new card_settings(true);
        $this->assertSame(card_settings::SOURCE_PATTERN, $settings->image_source(), 'missing reads as the pattern');

        set_config('courseoverviewimagesource', 'courseplusfallback', card_settings::COMPONENT);
        $this->assertSame(card_settings::SOURCE_FALLBACK, $settings->image_source());

        set_config('courseoverviewimagesource', 'coursepluspattern', card_settings::COMPONENT);
        $this->assertSame(card_settings::SOURCE_PATTERN, $settings->image_source());

        set_config('courseoverviewimagesource', 'somethingelse', card_settings::COMPONENT);
        $this->assertSame(card_settings::SOURCE_PATTERN, $settings->image_source(), 'a value Boost Union does not know');
    }

    /**
     * Show course images: each view checked or not, missing as all three, empty as none; summary changes nothing here.
     *
     * Changes that must make it fail: the card view read from the wrong word; missing read as none; empty read as
     * all; generated asked with no cover left to draw it on.
     *
     * @return void
     */
    public function test_the_images_setting_both_ways_for_each_view(): void {
        $settings = new card_settings(true);
        $this->assertSame(
            ['progress' => true, 'cover' => true, 'generated' => true],
            $settings->flags(),
            'missing: every view draws its image'
        );

        set_config('courseoverviewshowcourseimages', 'list,summary', card_settings::COMPONENT);
        $this->assertFalse($settings->show_images(card_settings::VIEW_CARD));
        $this->assertTrue($settings->show_images(card_settings::VIEW_LIST));
        $this->assertSame(
            ['progress' => true, 'cover' => false, 'generated' => false],
            $settings->flags(),
            'the plan\'s cards are a card view: no cover, so no generated image is asked for'
        );

        set_config('courseoverviewshowcourseimages', 'card, summary', card_settings::COMPONENT);
        $this->assertSame(['progress' => true, 'cover' => true, 'generated' => true], $settings->flags());

        set_config('courseoverviewshowcourseimages', 'card', card_settings::COMPONENT);
        $this->assertSame(['progress' => true, 'cover' => true, 'generated' => true], $settings->flags());

        set_config('courseoverviewshowcourseimages', '', card_settings::COMPONENT);
        $this->assertFalse($settings->show_images(card_settings::VIEW_CARD), 'every box unchecked');
        $this->assertFalse($settings->show_images(card_settings::VIEW_LIST));

        $this->expectException(\coding_exception::class);
        $settings->show_images('grid');
    }

    /**
     * Under another theme every answer is the plugin's own rule, whatever Boost Union's settings say.
     *
     * The control is the same settings under Boost Union, which they turn off.
     *
     * @return void
     */
    public function test_another_theme_keeps_the_plugin_rule(): void {
        set_config('courseoverviewshowcourseprogress', 'no', card_settings::COMPONENT);
        set_config('courseoverviewimagesource', 'courseplusfallback', card_settings::COMPONENT);
        set_config('courseoverviewshowcourseimages', '', card_settings::COMPONENT);

        $other = new card_settings(false);
        $this->assertFalse($other->active());
        $this->assertSame(['progress' => true, 'cover' => true, 'generated' => false], $other->flags());
        $this->assertSame(card_settings::SOURCE_PATTERN, $other->image_source());

        // Control: under Boost Union the same values take the row and the cover away.
        $this->assertSame(['progress' => false, 'cover' => false, 'generated' => false], (new card_settings(true))->flags());
        $this->assertSame(card_settings::SOURCE_FALLBACK, (new card_settings(true))->image_source());
    }
}
