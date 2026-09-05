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
 * Behat step definitions for local_dimensions.
 *
 * @package    local_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.
require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException;

/**
 * Step definitions for local_dimensions Behat features.
 *
 * @package    local_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_dimensions extends behat_base {
    /** @var string|null The page background colour remembered by the colour-mode steps. */
    protected $rememberedbackground = null;

    /**
     * Creates a site-wide grading scale with the given comma-separated values.
     *
     * The competency edit modal offers every site scale in its "Scale" dropdown, so a
     * deterministic scale is needed to exercise the "Configure scale" dialogue.
     *
     * @Given /^a competency scale "(?P<name_string>(?:[^"]|\\")*)" with values "(?P<values_string>(?:[^"]|\\")*)" exists$/
     * @param string $name Scale name shown in the dropdown.
     * @param string $values Comma-separated scale values (e.g. "Bad,Good").
     */
    public function a_competency_scale_with_values_exists(string $name, string $values): void {
        \testing_util::get_data_generator()->create_scale([
            'name' => $name,
            'scale' => $values,
        ]);
    }

    /**
     * The background colour the page paints right now.
     *
     * Read from body, falling back to the html element when body paints nothing: a transparent body
     * shows the html element's colour, and comparing a plugin surface against "transparent" would
     * be comparing it against nothing at all.
     *
     * @return string The computed background colour, in the browser's own rgb() spelling.
     */
    protected function page_background_colour(): string {
        return trim((string) $this->getSession()->evaluateScript(
            'return (function() {'
                . ' var ground = window.getComputedStyle(document.body).backgroundColor;'
                . ' if (!ground || ground === "transparent" || ground === "rgba(0, 0, 0, 0)") {'
                . '     ground = window.getComputedStyle(document.documentElement).backgroundColor;'
                . ' }'
                . ' return ground;'
                . '})();'
        ));
    }

    /**
     * The computed background colour of the first element matching a CSS selector.
     *
     * @param string $selector A CSS selector.
     * @return string The computed background colour, in the browser's own rgb() spelling.
     */
    protected function element_background_colour(string $selector): string {
        $escaped = addcslashes($selector, "'\\");

        return trim((string) $this->getSession()->evaluateScript(
            'return (function() {'
                . ' var el = document.querySelector(\'' . $escaped . '\');'
                . ' if (!el) { return "NO SUCH ELEMENT"; }'
                . ' return window.getComputedStyle(el).backgroundColor;'
                . '})();'
        ));
    }

    /**
     * Puts the page into the host's colour mode.
     *
     * Sets Bootstrap's own colour-mode attribute on the document element - exactly what Moodle
     * 5.3's theme_boost writes from the before_html_attributes hook and from its own colourmode AMD
     * module, and exactly what Bootstrap 5.3's compiled token block reads. A step is needed only
     * because no shipped theme in the 405-502 range turns it on yet, and the attribute is set from
     * HERE rather than from plugin code on purpose: whether a page is dark is the host's decision,
     * and colour_tokens_test::test_plugin_never_writes_the_host_signal fails the build if any
     * shipped file of this plugin writes it. executeScript for a DOM attribute follows core's own
     * idiom in lib/tests/behat/behat_navigation.php.
     *
     * @Given /^the page colour mode is "(?P<mode_string>light|dark)"$/
     * @param string $mode The colour mode to force.
     * @return void
     */
    public function the_page_colour_mode_is(string $mode): void {
        $this->require_javascript();
        $attribute = \local_dimensions\local\colour_mode::HOST_ATTRIBUTE;
        $value = $mode === \local_dimensions\local\colour_mode::DARK
            ? \local_dimensions\local\colour_mode::DARK
            : \local_dimensions\local\colour_mode::LIGHT;
        $this->getSession()->executeScript(
            'document.documentElement.setAttribute("' . $attribute . '", "' . $value . '");'
        );
    }

    /**
     * Records the page's own background colour so a later step can compare against it.
     *
     * @Given /^I remember the page background colour$/
     * @return void
     */
    public function i_remember_the_page_background_colour(): void {
        $this->require_javascript();
        $this->rememberedbackground = $this->page_background_colour();
    }

    /**
     * Asserts the plugin surface tracks the page, whichever way the page went.
     *
     * This is the design's first decision written as an executable biconditional, and it needs no
     * branch tag: the element must equal the page's CURRENT background, so if the page moved when
     * the attribute was set the element must have moved with it, and if the page did not move
     * (Moodle 4.5, which ships no dark palette at all) the element must not have moved either.
     * Correct on every supported branch, including one whose compiled sheet nobody has measured.
     *
     * @Then /^the "(?P<selector_string>[^"]*)" element background should still match the page$/
     * @param string $selector A CSS selector for the element under test.
     * @return void
     */
    public function the_element_background_should_still_match_the_page(string $selector): void {
        $this->require_javascript();
        if ($this->rememberedbackground === null) {
            throw new ExpectationException(
                'Nothing has been remembered yet: put "I remember the page background colour" before this step.',
                $this->getSession()
            );
        }
        /*
         * Poll until the colours settle. The cards carry a 0.12s background transition, so a read
         * taken in the same tick as the attribute write returns an interpolated frame - measured,
         * rgb(218, 219, 219) part way from white to the dark page - which is neither colour and is
         * a false failure. Waiting cannot turn a genuinely wrong colour into a right one, so the
         * assertion is unweakened; it just stops racing the compositor.
         */
        $page = $this->page_background_colour();
        $element = $this->element_background_colour($selector);
        for ($attempt = 0; $attempt < 30 && $element !== $page; $attempt++) {
            usleep(100000);
            $page = $this->page_background_colour();
            $element = $this->element_background_colour($selector);
        }
        if ($element !== $page) {
            throw new ExpectationException(
                'The "' . $selector . '" element paints ' . $element . ' while the page paints ' . $page
                    . ' (it was ' . $this->rememberedbackground . ' before). A plugin surface follows the '
                    . 'host page and nothing else.',
                $this->getSession()
            );
        }
        if ($page !== $this->rememberedbackground && $element === $this->rememberedbackground) {
            throw new ExpectationException(
                'The page moved from ' . $this->rememberedbackground . ' to ' . $page
                    . ' and the "' . $selector . '" element did not move with it.',
                $this->getSession()
            );
        }
    }

    /**
     * Asserts a resolved colour token, so an assertion names a token rather than a pixel.
     *
     * A custom property's computed value is the token stream as written, not a resolved colour, so
     * "#fd7e14" comes back as "#fd7e14" - unlike a real property, where getComputedStyle would
     * return rgb(253, 126, 20).
     *
     * @Then /^the "(?P<token_string>[^"]*)" colour token should resolve to "(?P<value_string>[^"]*)"$/
     * @param string $token Token name without the leading dashes.
     * @param string $value Expected resolved value, normalised (lower-case, whitespace-collapsed).
     * @return void
     */
    public function the_colour_token_should_resolve_to(string $token, string $value): void {
        $this->require_javascript();
        $escaped = addcslashes($token, "'\\");
        $actual = (string) $this->getSession()->evaluateScript(
            'return window.getComputedStyle(document.documentElement).getPropertyValue(\'--'
                . $escaped . '\');'
        );
        $normalise = static function (string $raw): string {
            return strtolower(trim(preg_replace('/\s+/', ' ', $raw)));
        };
        if ($normalise($actual) !== $normalise($value)) {
            throw new ExpectationException(
                'The --' . $token . ' token resolves to "' . trim($actual) . '" and not to "' . $value . '".',
                $this->getSession()
            );
        }
    }

    /**
     * Skips the scenario on a branch whose core ships no dark palette.
     *
     * Detected at RUNTIME, not from $CFG->branch: the step sets the attribute, reads --bs-body-bg
     * back, restores the previous state, and skips if the value did not move. A branch-number guard
     * would be an assumption where a measurement is available, and the plugin's supported range
     * reaches branches whose compiled sheet this design never measured.
     *
     * @Given /^the site has a host colour mode$/
     * @throws \Moodle\BehatExtension\Exception\SkippedException
     * @return void
     */
    public function the_site_has_a_host_colour_mode(): void {
        $this->require_javascript();
        $attribute = \local_dimensions\local\colour_mode::HOST_ATTRIBUTE;
        $dark = \local_dimensions\local\colour_mode::DARK;
        $measured = (string) $this->getSession()->evaluateScript(
            'return (function() {'
                . ' var root = document.documentElement;'
                . ' var prior = root.getAttribute("' . $attribute . '");'
                . ' var before = window.getComputedStyle(root).getPropertyValue("--bs-body-bg").trim();'
                . ' root.setAttribute("' . $attribute . '", "' . $dark . '");'
                . ' var after = window.getComputedStyle(root).getPropertyValue("--bs-body-bg").trim();'
                . ' if (prior === null) { root.removeAttribute("' . $attribute . '"); }'
                . ' else { root.setAttribute("' . $attribute . '", prior); }'
                . ' return before + "|" + after;'
                . '})();'
        );
        [$before, $after] = array_pad(explode('|', $measured, 2), 2, '');
        if ($before === $after) {
            throw new \Moodle\BehatExtension\Exception\SkippedException(
                'This Moodle does not ship a host colour mode: --bs-body-bg stayed at "' . $before
                    . '" with the colour-mode attribute set, so there is no dark palette for the '
                    . 'plugin to follow. The relative invariant scenarios still run on this branch.'
            );
        }
    }
}
