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

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * The plan view's course card, as the course card contract (moodle-dev/docs/course-card/CONTRACT.md) draws it.
 *
 * The card is written by amd/src/accordion.js and painted by styles.css, and nothing in the pipeline reads the two
 * together, so these tests read the files. course_card_contract_test holds the fleet's shared arms; these hold what
 * is specific to this plugin's card. Each test names the change that must make it fail.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class course_card_rules_test extends \basic_testcase {
    /** @var string The card root. */
    private const CARD = '.local-dimensions-course-card-lg';

    /**
     * The stylesheet, comments removed and every run of whitespace one space, so a selector may wrap.
     *
     * @return string
     */
    private function css(): string {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(dirname(__DIR__, 2) . '/styles.css'));

        return (string) preg_replace('/\s+/', ' ', $css);
    }

    /**
     * The accordion's source, comments removed.
     *
     * @return string
     */
    private function js(): string {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/amd/src/accordion.js');
        $js = (string) preg_replace('#/\*.*?\*/#s', '', $js);

        return (string) preg_replace('#^\s*//[^\n]*$#m', '', $js);
    }

    /**
     * The body of a named function declaration of the accordion, up to its closing brace at the same indent.
     *
     * @param string $name The function name.
     * @return string
     */
    private function function_body(string $name): string {
        $pattern = '/^( *)function ' . preg_quote($name, '/') . '\([^)]*\) \{\n(.*?)\n\1\}$/ms';
        $this->assertSame(1, preg_match($pattern, $this->js(), $match), "function {$name} not found");

        return $match[2];
    }

    /**
     * The declarations of the rule with exactly this selector.
     *
     * @param string $selector The selector, as the stylesheet writes it.
     * @return string The rule's body.
     */
    private function rule(string $selector): string {
        $pattern = '~(?:^|\})\s*' . preg_quote($selector, '~') . '\s*\{([^{}]*)\}~';
        $this->assertSame(1, preg_match($pattern, $this->css(), $match), "No rule {$selector}.");

        return $match[1];
    }

    /**
     * A card's title keeps no underline at rest inside #region-main, and the hover and the keyboard's focus underline it.
     *
     * Core's dark mode underlines every link of #region-main at (1,6,1), read here from core's own stylesheet, and a
     * plugin's sheet is compiled before the theme's, so the card's two rules must outweigh it outright. The plan view
     * lives in #region-main. The rules name no colour mode: colour_tokens_test allows the mode attribute on the token
     * activation pair only, and the title rests without an underline in both modes.
     *
     * Changes that must make it fail: the rest rule dropped or made lighter; the hover or focus twin dropped.
     *
     * @return void
     */
    public function test_the_card_title_outranks_cores_dark_underline(): void {
        global $CFG;

        $core = (string) file_get_contents($CFG->dirroot . '/theme/boost/scss/moodle/dark.scss');
        $link = 'a:not(.btn):not(.nav-link):not(.dropdown-item):not(.list-group-item):not(.page-link)';
        $this->assertStringContainsString(
            $link . " {\n            text-decoration: underline;",
            $core,
            "Precondition: core's rule, as read on 5.3."
        );
        $this->assertStringContainsString('#region-main,', $core, "Precondition: core's rule covers #region-main.");
        $cores = self::specificity('[data-bs-theme="dark"] #region-main ' . $link);
        $this->assertSame([1, 6, 1], $cores, 'The helper reads core\'s weight as a browser does.');

        $scope = '#region-main .local-dimensions-courses-section .local-dimensions-courses-scroll ';
        $title = ' .local-dimensions-course-name-lg a.local-dimensions-course-link[href]:not(.btn)';
        $rest = $scope . self::CARD . $title;
        $hover = $scope . self::CARD . ':hover' . $title;
        $focus = $scope . self::CARD . $title . ':focus-visible';
        $q = static fn(string $s): string => preg_quote($s, '~');
        $this->assertMatchesRegularExpression(
            '~(?:^|\}) ' . $q($rest) . ' \{\s*text-decoration: none;\s*\}\s*'
                . $q($hover) . ',\s*' . $q($focus) . ' \{\s*text-decoration: underline;\s*\}~m',
            $this->css()
        );
        $atrest = self::specificity($rest);
        $this->assertGreaterThan(0, $atrest <=> $cores, 'The title at rest must outweigh core: ' . implode(',', $atrest));
        foreach ([$hover, $focus] as $state) {
            $weight = self::specificity($state);
            $this->assertGreaterThan(0, $weight <=> $atrest, 'Hover and focus must outweigh the rest: ' . implode(',', $weight));
        }
    }

    /**
     * The card is one target: the title's link stretches over the card, whose ring it draws, and the controls of the
     * card's foot sit above it.
     *
     * Changes that must make it fail: the stretched area dropped; the ring taken off the stretched link or drawn as
     * a box-shadow alone; the drawer or a compact card's button left under the stretched area; the card's
     * transitions left running under reduced motion.
     *
     * @return void
     */
    public function test_the_card_is_one_target_with_its_ring_on_the_stretched_link(): void {
        $after = $this->rule(self::CARD . ' .local-dimensions-course-link::after');
        $this->assertMatchesRegularExpression('~position: absolute;~', $after);
        $this->assertMatchesRegularExpression('~z-index: 1;~', $after);
        $this->assertMatchesRegularExpression('~inset: 0;~', $after);
        $this->assertMatchesRegularExpression('~content: "";~', $after);

        $ring = $this->rule(self::CARD . ' .local-dimensions-course-link:focus-visible::after');
        $this->assertMatchesRegularExpression(
            '~outline: var\(--local-dimensions-card-focus-width\) solid var\(--local-dimensions-card-focus-ring\);~',
            $ring,
            'the card\'s ring is not its stretched link\'s outline'
        );
        $this->assertMatchesRegularExpression(
            '~outline: var\(--local-dimensions-card-focus-width\) solid transparent;~',
            $this->rule(self::CARD . ' .local-dimensions-course-link:focus-visible'),
            'the link\'s own outline is not kept, transparent, for forced colours'
        );

        $foot = $this->rule(
            self::CARD . ' .local-dimensions-course-acts-toggle, ' . self::CARD . ' .local-dimensions-course-acts'
        );
        $this->assertMatchesRegularExpression(
            '~position: relative;\s*z-index: 2;~',
            $foot,
            'the drawer is under the stretched link'
        );
        $this->assertMatchesRegularExpression(
            '~position: relative;\s*z-index: 2;~',
            $this->rule(self::CARD . ' .local-dimensions-course-actions'),
            'a compact card\'s button is under the stretched link'
        );

        $this->assertMatchesRegularExpression(
            '~@media \(prefers-reduced-motion: reduce\) \{\s*' . preg_quote(self::CARD, '~') . ',\s*'
                . preg_quote(self::CARD, '~') . ' \.local-dimensions-course-link \{\s*transition: none;~',
            $this->css(),
            'the card\'s transitions run under reduced motion'
        );
    }

    /**
     * The card's own rule makes it the stretched link's containing block, and no access means no hover.
     *
     * Changes that must make it fail: the card's position dropped (the stretched area would cover the carousel);
     * the blocked card's hover shadow dropped; its cover no longer receding.
     *
     * @return void
     */
    public function test_a_card_without_access_recedes_and_does_not_respond_to_hover(): void {
        preg_match_all('~(?:^|(?<=\}))\s*' . preg_quote(self::CARD, '~') . '\s*\{([^{}]*)\}~', $this->css(), $roots);
        $this->assertCount(2, $roots[1], 'the token rule and the layout rule');
        $layout = implode("\n", $roots[1]);
        $this->assertMatchesRegularExpression('~\bposition: relative;~', $layout, 'the card is not the containing block');

        $blocked = self::CARD . '.local-dimensions-course-card-blocked';
        $this->assertMatchesRegularExpression(
            '~box-shadow: var\(--local-dimensions-card-shadow\);~',
            $this->rule($blocked . ':hover'),
            'a card the viewer cannot open still responds to hover'
        );
        $cover = $this->rule('.local-dimensions-course-card-blocked .local-dimensions-course-img');
        $this->assertMatchesRegularExpression('~filter: grayscale\(55%\);~', $cover);
        $this->assertMatchesRegularExpression('~opacity: var\(--local-dimensions-card-locked-opacity\);~', $cover);
    }

    /**
     * The cards of one carousel share a height (contract, section 3): the carousel no longer keeps their natural one.
     *
     * Change that must make it fail: align-items: flex-start back on the carousel.
     *
     * @return void
     */
    public function test_the_carousel_draws_cards_of_one_height(): void {
        $track = $this->rule('.local-dimensions-courses-scroll');
        $this->assertStringContainsString('display: flex;', $track, 'precondition: the carousel is a flex row');
        $this->assertDoesNotMatchRegularExpression('~align-items\s*:~', $track);
        $this->assertMatchesRegularExpression('~flex: 1;~', $this->rule(self::CARD . ' .local-dimensions-course-body'));
    }

    /**
     * A completed course draws the green seal and base; a coverless card draws the top edge, green once complete.
     *
     * Changes that must make it fail: the base, the coverless edge or its green dropped.
     *
     * @return void
     */
    public function test_the_completed_and_coverless_edges(): void {
        $this->assertMatchesRegularExpression(
            '~border-bottom: var\(--local-dimensions-card-done-edge\) solid var\(--local-dimensions-card-done\);~',
            $this->rule('.local-dimensions-course-card-done .local-dimensions-course-img')
        );
        $this->assertMatchesRegularExpression(
            '~border-top: var\(--local-dimensions-card-done-edge\) solid var\(--local-dimensions-card-cover-fallback\);~',
            $this->rule(self::CARD . '.local-dimensions-course-card-nocover')
        );
        $this->assertMatchesRegularExpression(
            '~border-top-color: var\(--local-dimensions-card-done\);~',
            $this->rule(self::CARD . '.local-dimensions-course-card-nocover.local-dimensions-course-card-done')
        );
        $seal = $this->rule(self::CARD . ' .local-dimensions-course-seal');
        $this->assertMatchesRegularExpression('~background: var\(--local-dimensions-card-done-bg\);~', $seal);
        $this->assertMatchesRegularExpression('~color: var\(--local-dimensions-card-done-ink\);~', $seal);
    }

    /**
     * The server's flags decide the card: the cover only with showcover, the progress row only with showprogress and
     * completion tracking, the seal on a completed course whatever showprogress says (D13), and the section's ring is
     * a percentage too.
     *
     * Changes that must make it fail: the cover drawn whatever showcover says; the progress row drawn whatever
     * showprogress or hascompletion say; the seal tied to showprogress; the coverless or done class not written;
     * the ring drawn with the progress off.
     *
     * @return void
     */
    public function test_the_flags_decide_the_card(): void {
        $cards = $this->function_body('renderCourseCardsScrollable');
        $this->assertStringContainsString('const showCover = course.showcover !== false;', $cards);
        $this->assertStringContainsString('const showProgress = course.showprogress !== false;', $cards);
        $this->assertStringContainsString(
            'const isDone = isReachable && !!course.hascompletion && (Number.parseInt(course.progress, 10) || 0) >= 100;',
            $cards
        );
        $this->assertMatchesRegularExpression('~if \(showCover\) \{\s*html \+= renderCourseCover\(course\);\s*\}~', $cards);
        $this->assertStringContainsString("(isDone ? ' local-dimensions-course-card-done' : '')", $cards);
        $this->assertStringContainsString("(showCover ? '' : ' local-dimensions-course-card-nocover')", $cards);
        $this->assertStringContainsString('renderCourseProgress(course, strMap, showProgress, isDone)', $cards);
        $this->assertStringContainsString('renderCourseShape(course, strMap, showProgress)', $cards);

        $progress = $this->function_body('renderCourseProgress');
        $seal = strpos($progress, 'if (isDone) {');
        $gate = strpos($progress, 'if (!course.hascompletion || !showProgress) {');
        $this->assertNotFalse($seal, 'the seal is not drawn on a completed course');
        $this->assertNotFalse($gate, 'the progress row is not gated on tracking and the setting');
        $this->assertLessThan($gate, $seal, 'the seal must not depend on the progress setting (D13)');
        $this->assertStringContainsString('role="progressbar" aria-valuenow="', $progress);
        $this->assertStringContainsString('strMap.courseCardProgress', $progress);

        $shape = $this->function_body('renderCourseShape');
        $this->assertStringContainsString('if (course.section.tracked && showProgress) {', $shape);
    }

    /**
     * A card exposes one link, named by the course: no button on an open timeline card, a button on a compact or a
     * blocked one, and no capital letters or Font Awesome 4 names in the card's own markup.
     *
     * Changes that must make it fail: a second link written in the card loop; the compact or blocked action not a
     * Bootstrap button; a version 4 icon name in the plugin slot or the seal.
     *
     * @return void
     */
    public function test_a_card_exposes_one_link_named_by_the_course(): void {
        $cards = $this->function_body('renderCourseCardsScrollable');
        $start = strpos($cards, 'courses.forEach(function(course) {');
        $end = strpos($cards, "html += '</div>'; // End local-dimensions-course-card-lg.");
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $card = substr($cards, $start, $end - $start);
        $this->assertSame(1, substr_count($card, "'<a "), 'the card loop writes one link: the title');
        $this->assertStringContainsString('class="local-dimensions-course-link">\' +' . "\n", $card);

        $this->assertStringContainsString(
            'class="btn btn-primary btn-sm local-dimensions-course-go"',
            $this->function_body('renderCourseGoLink')
        );
        $this->assertStringContainsString(
            'class="btn btn-primary btn-sm local-dimensions-course-cta"',
            $this->function_body('renderLockedState')
        );

        $outcome = $this->function_body('outcomeOf');
        $this->assertStringContainsString("icon: 'fa-solid fa-trophy'", $outcome);
        $this->assertStringContainsString("icon: 'fa-regular fa-paper-plane'", $outcome);
        foreach (['outcomeOf', 'renderCourseExtra', 'renderCourseProgress', 'renderCourseCover'] as $name) {
            $this->assertStringNotContainsString("'fa ", $this->function_body($name), $name . ' writes a Font Awesome 4 class');
            $this->assertStringNotContainsString('"fa ', $this->function_body($name), $name . ' writes a Font Awesome 4 class');
        }
    }

    /**
     * A selector's specificity, as a browser counts it: ids, classes with attributes and pseudo-classes, types.
     *
     * Enough for the selectors this test reads: a :not() counts its argument, a pseudo-element is not written.
     *
     * @param string $selector One selector, no comma.
     * @return array The three counts, ids first.
     */
    private static function specificity(string $selector): array {
        $total = [0, 0, 0];
        while (preg_match('~:not\(([^()]*)\)~', $selector, $not)) {
            $inner = self::specificity($not[1]);
            foreach ($inner as $i => $count) {
                $total[$i] += $count;
            }
            $selector = str_replace($not[0], ' ', $selector);
        }
        $selector = (string) preg_replace('~\[[^\]]*\]~', ' .attr ', $selector);
        $total[0] += preg_match_all('~#[\w-]+~', $selector);
        $total[1] += preg_match_all('~\.[\w-]+|:(?!:)[\w-]+~', $selector);
        $total[2] += preg_match_all('/(?:^|[\s>+~])([a-z][\w-]*)/i', $selector);

        return $total;
    }
}
