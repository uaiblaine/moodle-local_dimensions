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
use local_dimensions\picture_manager;

/**
 * The hero image URL can never close the CSS url('...') it is written into.
 *
 * hero_header.mustache writes the URL into a style attribute, and the browser decodes the
 * attribute's entities before the CSS parser reads it, so Mustache escaping does not protect it.
 * clean_param(PARAM_URL) would not either: it admits quotes and parentheses. What does is
 * moodle_url, which percent-encodes every path segment and query value, so a stored filename
 * holding a parenthesis, a space or an accent reaches the page encoded. These tests pin that.
 *
 * The class-level covers tags stay in docblock form on purpose: moodle-cs on the 4.05 leg cannot
 * see PHP attributes, and the plugin still supports 4.5.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\picture_manager
 * @covers     \local_dimensions\output\customfield_reader
 */
final class hero_image_url_test extends \advanced_testcase {
    /** @var string A filename PARAM_FILE accepts, holding every character CSS or a URL cares about. */
    private const FILENAME = 'hero (1) café.png';

    /**
     * Store a template background image the way the built-in image handler does.
     *
     * @param int $templateid The template the image belongs to.
     * @return void
     */
    private function store_template_image(int $templateid): void {
        get_file_storage()->create_file_from_string([
            'contextid' => \core\context\system::instance()->id,
            'component' => picture_manager::COMPONENT,
            'filearea' => picture_manager::get_filearea('lp', 'bgimage'),
            'itemid' => $templateid,
            'filepath' => '/',
            'filename' => self::FILENAME,
        ], 'not really an image');
    }

    /**
     * Assert that a URL holds nothing that could end a quoted CSS url() or start a new token.
     *
     * @param string $url The URL as the template receives it.
     * @return void
     */
    private function assert_css_safe(string $url): void {
        $this->assertDoesNotMatchRegularExpression('/[\'"()\\\\\s<>]/', $url);
        // Control: the name did reach the URL, encoded.
        $this->assertStringContainsString('hero%20%281%29%20caf%C3%A9.png', $url);
    }

    /**
     * The built-in handler's URL and the plan hero both carry the image encoded.
     *
     * @return void
     */
    public function test_the_hero_image_url_is_encoded(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $templateid = (int) $ccg->create_template()->get('id');
        $this->store_template_image($templateid);

        $url = picture_manager::get_image_url('lp', $templateid);
        $this->assert_css_safe((string) $url);

        $plan = $ccg->create_plan([
            'userid' => $this->getDataGenerator()->create_user()->id,
            'templateid' => $templateid,
            'status' => plan::STATUS_ACTIVE,
        ]);
        $hero = (new view_plan_summary_page($plan))->export_for_template($PAGE->get_renderer('core'))['hero'];
        $this->assertTrue($hero['hasbgimage']);
        $this->assertSame($url, $hero['bgimage']);
    }

    /**
     * Without slash arguments the path travels as a query value, encoded all the same.
     *
     * @return void
     */
    public function test_the_hero_image_url_is_encoded_without_slash_arguments(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('slasharguments', 0);
        $templateid = (int) $this->getDataGenerator()->get_plugin_generator('core_competency')->create_template()->get('id');
        $this->store_template_image($templateid);

        $url = (string) picture_manager::get_image_url('lp', $templateid);

        $this->assertStringContainsString('?file=', $url);
        $this->assert_css_safe($url);
    }
}
