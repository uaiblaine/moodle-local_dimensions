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
        /* Read at body, which is where the token block is declared. Custom properties inherit
           DOWNWARDS only, so reading at documentElement returns the empty string for every one
           of them. body is also correct against the older contract, when the block sat on
           :root: the values were visible there by inheritance. */
        $actual = (string) $this->getSession()->evaluateScript(
            'return window.getComputedStyle(document.body).getPropertyValue(\'--'
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

    /**
     * Convert page names to URLs for steps like 'When I am on the "[identifier]" "[page type]" page'.
     *
     * Recognised page names are:
     * | Page type       | Identifier meaning                               | Description            |
     * | View plan       | Learning plan name                               | The plan overview      |
     * | View competency | Learning plan name > competency idnumber         | The competency tracker |
     *
     * @param string $type Identifies which type of page this is.
     * @param string $identifier Identifies the particular page.
     * @return moodle_url The corresponding URL.
     * @throws Exception With a meaningful error message if the specified page cannot be found.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        switch (strtolower($type)) {
            case 'view plan':
                return new moodle_url('/local/dimensions/view-plan.php', [
                    'id' => $this->get_plan_id_by_name($identifier),
                ]);
            case 'view competency':
                [$planname, $idnumber] = array_map('trim', array_pad(explode('>', $identifier, 2), 2, ''));
                return new moodle_url('/local/dimensions/view-competency.php', [
                    'id' => $this->get_plan_id_by_name($planname),
                    'competencyid' => $this->get_competency_id_by_idnumber($idnumber),
                ]);
            default:
                throw new Exception('Unrecognised local_dimensions page type "' . $type . '."');
        }
    }

    /**
     * Asserts how many times a plan's view was logged.
     *
     * @Then the view of plan :planname should be logged :count time(s)
     * @param string $planname The learning plan name.
     * @param int $count The expected number of log rows.
     * @return void
     */
    public function the_view_of_plan_should_be_logged(string $planname, int $count): void {
        $this->assert_view_log_count('\core\event\competency_plan_viewed', $planname, null, $count);
    }

    /**
     * Asserts how many times a competency's view in a plan that is not complete was logged.
     *
     * @Then the view of competency :idnumber in plan :planname should be logged :count time(s)
     * @param string $idnumber The competency idnumber.
     * @param string $planname The learning plan name.
     * @param int $count The expected number of log rows.
     * @return void
     */
    public function the_view_of_competency_in_plan_should_be_logged(string $idnumber, string $planname, int $count): void {
        $this->assert_view_log_count('\core\event\competency_user_competency_viewed_in_plan', $planname, $idnumber, $count);
    }

    /**
     * Asserts how many times a competency's view in a completed plan was logged.
     *
     * @Then the completed-plan view of competency :idnumber in plan :planname should be logged :count time(s)
     * @param string $idnumber The competency idnumber.
     * @param string $planname The learning plan name.
     * @param int $count The expected number of log rows.
     * @return void
     */
    public function the_completed_plan_view_of_competency_should_be_logged(string $idnumber, string $planname, int $count): void {
        $this->assert_view_log_count('\core\event\competency_user_competency_plan_viewed', $planname, $idnumber, $count);
    }

    /**
     * Asserts how many requests the current page has sent to one web service.
     *
     * Read from the browser's resource timing entries. core/ajax names the methods of a request in its
     * URL (info=...) while the arguments travel in the body, so this counts calls to a method, not calls
     * about one competency. Polled, because an entry lands only once its response has arrived.
     *
     * The browser keeps 250 entries by default and silently drops the rest, which would undercount. The
     * first use of this step on a page enlarges the buffer; if the page had already filled it by then,
     * requests may be missing and the step fails, because no count read from it can be trusted.
     *
     * @Then the page should have called the :methodname web service :count time(s)
     * @param string $methodname The external function name.
     * @param int $count The expected number of requests.
     * @return void
     */
    public function the_page_should_have_called_the_web_service(string $methodname, int $count): void {
        $filled = (int) $this->getSession()->evaluateScript(
            'return (function() {'
                . ' if (window.localDimensionsTimingBuffer) { return 0; }'
                . ' var held = performance.getEntriesByType("resource").length;'
                . ' performance.setResourceTimingBufferSize(10000);'
                . ' window.localDimensionsTimingBuffer = true;'
                . ' return held >= 250 ? held : 0;'
                . '})();'
        );
        if ($filled > 0) {
            throw new ExpectationException(
                'The resource timing buffer was already full (' . $filled . ' entries), so requests may be missing from it.',
                $this->getSession()
            );
        }

        $script = 'return (function(name) {'
            . ' return performance.getEntriesByType("resource").filter(function(entry) {'
            . '     var match = entry.name.match(/[?&]info=([^&]*)/);'
            . '     return match !== null && decodeURIComponent(match[1]).split(",").indexOf(name) !== -1;'
            . ' }).length;'
            . '})(' . json_encode($methodname) . ');';

        $this->spin(
            function () use ($script, $methodname, $count): bool {
                $actual = (int) $this->getSession()->evaluateScript($script);
                if ($actual !== $count) {
                    throw new ExpectationException(
                        'Expected ' . $count . ' requests to ' . $methodname . ', found ' . $actual . '.',
                        $this->getSession()
                    );
                }
                return true;
            },
            [],
            behat_base::get_extended_timeout()
        );
    }

    /**
     * Polls the standard log store until one view event has left exactly the expected number of rows.
     *
     * Polled, not read once. The accordion logs a competency view from a fire-and-forget request sent
     * after the detail renders; core/ajax registers no pending-JS token for it, so Behat's own wait
     * does not cover it, and the log store only writes at the end of that request.
     *
     * @param string $eventname The fully qualified event class name.
     * @param string $planname The learning plan name.
     * @param string|null $idnumber The competency idnumber, or null for a plan-level event.
     * @param int $expected The expected number of log rows.
     * @return void
     */
    protected function assert_view_log_count(string $eventname, string $planname, ?string $idnumber, int $expected): void {
        $planid = $this->get_plan_id_by_name($planname);
        $competencyid = $idnumber === null ? null : $this->get_competency_id_by_idnumber($idnumber);

        $this->spin(
            function () use ($eventname, $planid, $competencyid, $expected): bool {
                global $DB;

                $actual = 0;
                foreach ($DB->get_records('logstore_standard_log', ['eventname' => $eventname]) as $row) {
                    $other = (array) json_decode((string) $row->other, true);
                    $rowplanid = $competencyid === null ? (int) $row->objectid : (int) ($other['planid'] ?? 0);
                    $rowcompetencyid = $competencyid === null ? null : (int) ($other['competencyid'] ?? 0);
                    if ($rowplanid === $planid && $rowcompetencyid === $competencyid) {
                        $actual++;
                    }
                }
                if ($actual !== $expected) {
                    throw new ExpectationException(
                        'Expected ' . $expected . ' "' . $eventname . '" log rows, found ' . $actual . '.',
                        $this->getSession()
                    );
                }
                return true;
            },
            [],
            behat_base::get_extended_timeout()
        );
    }

    /**
     * Moves a competency under a parent in the same framework.
     *
     * Core's generator takes a parent only as an id, which a feature cannot know. Updating the
     * persistent recomputes the path and leaves the parent's rule alone, so a rule set by the
     * generator survives the move.
     *
     * @Given the competency :childidnumber is a child of :parentidnumber
     * @param string $childidnumber The idnumber of the competency to move.
     * @param string $parentidnumber The idnumber of its new parent.
     * @return void
     */
    public function the_competency_is_a_child_of(string $childidnumber, string $parentidnumber): void {
        $child = new \core_competency\competency($this->get_competency_id_by_idnumber($childidnumber));
        $child->set('parentid', $this->get_competency_id_by_idnumber($parentidnumber));
        $child->update();
    }

    /**
     * Records a logged evidence row, carrying a note, on a user's competency.
     *
     * @Given :username has an evidence note :note on competency :idnumber
     * @param string $username The learner.
     * @param string $note The note the evidence carries, which makes its row open a detail dialogue.
     * @param string $idnumber The competency idnumber.
     * @return void
     */
    public function has_an_evidence_note_on_competency(string $username, string $note, string $idnumber): void {
        $this->get_competency_generator()->create_evidence([
            'usercompetencyid' => $this->get_user_competency_id($username, $idnumber),
            'action' => \core_competency\evidence::ACTION_LOG,
            'descidentifier' => 'evidence_manualoverride',
            'note' => $note,
        ]);
    }

    /**
     * Records a rule completion on a user's competency without rating it.
     *
     * The rating stays unset, which is the stale state the progress tab answers with a review request.
     *
     * @Given the rule of competency :idnumber was met by :username
     * @param string $idnumber The competency idnumber.
     * @param string $username The learner.
     * @return void
     */
    public function the_rule_of_competency_was_met_by(string $idnumber, string $username): void {
        $this->get_competency_generator()->create_evidence([
            'usercompetencyid' => $this->get_user_competency_id($username, $idnumber),
            'action' => \core_competency\evidence::ACTION_COMPLETE,
            'grade' => 1,
            'descidentifier' => 'evidence_competencyrule',
        ]);
    }

    /**
     * The core_competency data generator.
     *
     * @return \core_competency_generator
     */
    protected function get_competency_generator(): \core_competency_generator {
        return \testing_util::get_data_generator()->get_plugin_generator('core_competency');
    }

    /**
     * The id of a user's competency record, created when the user has none yet.
     *
     * @param string $username The learner.
     * @param string $idnumber The competency idnumber.
     * @return int
     */
    protected function get_user_competency_id(string $username, string $idnumber): int {
        global $DB;

        $userid = (int) $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $competencyid = $this->get_competency_id_by_idnumber($idnumber);
        $existing = \core_competency\user_competency::get_record(['userid' => $userid, 'competencyid' => $competencyid]);
        if ($existing) {
            return (int) $existing->get('id');
        }

        return (int) $this->get_competency_generator()->create_user_competency([
            'userid' => $userid,
            'competencyid' => $competencyid,
        ])->get('id');
    }

    /**
     * The id of a learning plan, looked up by name.
     *
     * @param string $name The learning plan name.
     * @return int
     */
    protected function get_plan_id_by_name(string $name): int {
        global $DB;

        return (int) $DB->get_field('competency_plan', 'id', ['name' => $name], MUST_EXIST);
    }

    /**
     * The id of a competency, looked up by idnumber.
     *
     * @param string $idnumber The competency idnumber.
     * @return int
     */
    protected function get_competency_id_by_idnumber(string $idnumber): int {
        global $DB;

        return (int) $DB->get_field('competency', 'id', ['idnumber' => $idnumber], MUST_EXIST);
    }
}
