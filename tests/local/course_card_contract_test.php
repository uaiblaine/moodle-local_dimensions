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
 * Tests for the course card contract shared by the Dimensions family of plugins.
 *
 * @package    local_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins the shared course card contract in this plugin's files.
 *
 * theme_dimensions, block_compass and local_dimensions each draw course cards with their own markup and
 * stylesheet, and none depends on another to draw one. The contract keeps the cards alike: each plugin
 * declares the card tokens under its own prefix and reads the family token (--dimensions-card-*) first, so
 * the theme can restyle every card at once; only the theme declares the family tokens; a card rule paints
 * through tokens, sets no label in capitals and moves nothing on hover; and the plugin reads Boost Union's
 * course overview settings and its README names them.
 *
 * The class is copied between plugins: only the namespace, the package and copyright tags and the
 * constants above the first method change. It reads files only, so it runs wherever the PHPUnit suite
 * runs. A card rule is an innermost CSS block whose selector names CARD_ROOT; in a nested SCSS source,
 * name the root in each card rule's own selector, or the rule is not seen.
 *
 * @package    local_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class course_card_contract_test extends \basic_testcase {
    /** @var string The plugin's own prefix for the card tokens, for example '--block_compass-card-'. */
    private const TOKEN_PREFIX = '--local-dimensions-card-';

    /** @var string The card root class as it appears in a selector, for example '.compass-card'. */
    private const CARD_ROOT = '.local-dimensions-course-card-lg';

    /** @var array The stylesheets holding the card rules, relative to the plugin root. */
    private const STYLESHEETS = ['styles.css'];

    /** @var array The files that read Boost Union's settings, relative to the plugin root. */
    private const SOURCES = ['classes/local/card_settings.php'];

    /** @var bool True in theme_dimensions only: the one plugin that declares --dimensions-card-* names. */
    private const PROVIDER = false;

    /** @var array Every token suffix of the card contract. */
    private const SUFFIXES = [
        'radius', 'border-width', 'border', 'surface', 'ink', 'ink-muted', 'title-hover', 'cover-height',
        'cover-fallback', 'cover-overlay', 'chip-bg', 'chip-ink', 'extra-bg', 'extra-icon', 'track', 'fill',
        'track-height', 'done', 'done-bg', 'done-ink', 'done-edge', 'shadow', 'shadow-hover', 'focus-ring',
        'focus-width', 'locked-opacity', 'close-bg', 'close-bg-hover', 'close-edge', 'transition',
    ];

    /** @var array Boost Union's settings the card contract reads. */
    private const BOOST_UNION_SETTINGS = [
        'courseoverviewshowcourseprogress',
        'courseoverviewimagesource',
        'courseoverviewshowcourseimages',
    ];

    /**
     * A file of the plugin, read whole.
     *
     * @param string $path Relative to the plugin root.
     * @return string The contents.
     */
    private function read(string $path): string {
        $file = dirname(__DIR__, 2) . '/' . $path;
        $this->assertFileIsReadable($file);
        return (string) file_get_contents($file);
    }

    /**
     * The stylesheets, block comments and SCSS line comments removed.
     *
     * A line comment starts a line or follows a space, so a URL's "//" is kept.
     *
     * @return string The CSS.
     */
    private function css(): string {
        $css = '';
        foreach (self::STYLESHEETS as $path) {
            $css .= $this->read($path) . "\n";
        }
        $css = (string) preg_replace('~/\*.*?\*/~s', '', $css);
        return (string) preg_replace('~(^|\s)//[^\n]*~m', '$1', $css);
    }

    /**
     * The card rules: innermost blocks whose selector names the card root.
     *
     * @return array List of [selector, body] pairs; never empty, or the other arms prove nothing.
     */
    private function card_rules(): array {
        preg_match_all('~([^{}]+)\{([^{}]*)\}~', $this->css(), $matches, PREG_SET_ORDER);
        $rules = [];
        foreach ($matches as $match) {
            if (str_contains($match[1], self::CARD_ROOT)) {
                $rules[] = [trim($match[1]), $match[2]];
            }
        }
        $this->assertNotEmpty($rules, 'No card rule names ' . self::CARD_ROOT . ': check the constants.');
        return $rules;
    }

    /**
     * Every token of the contract is declared under the plugin's prefix and reads the family token first.
     *
     * @return void
     */
    public function test_every_contract_token_reads_the_family_token_first(): void {
        $css = $this->css();
        foreach (self::SUFFIXES as $suffix) {
            $pattern = '~' . preg_quote(self::TOKEN_PREFIX . $suffix, '~') . '\s*:\s*([^;]+);~';
            preg_match_all($pattern, $css, $values);
            $this->assertNotEmpty($values[1], self::TOKEN_PREFIX . $suffix . ' is not declared.');
            foreach ($values[1] as $value) {
                $this->assertMatchesRegularExpression(
                    '~^var\(--dimensions-card-' . preg_quote($suffix, '~') . '\s*[,)]~',
                    trim($value),
                    self::TOKEN_PREFIX . $suffix . ' must read --dimensions-card-' . $suffix . ' first.'
                );
            }
        }
    }

    /**
     * Only the theme declares the family's card tokens.
     *
     * @return void
     */
    public function test_only_the_theme_declares_family_card_tokens(): void {
        if (self::PROVIDER) {
            $this->assertMatchesRegularExpression('~--dimensions-card-[a-z-]+\s*:~', $this->css());
            return;
        }
        $this->assertDoesNotMatchRegularExpression('~--dimensions-card-[a-z-]+\s*:~', $this->css());
    }

    /**
     * No card label is set in capital letters.
     *
     * @return void
     */
    public function test_no_card_rule_sets_uppercase(): void {
        foreach ($this->card_rules() as [$selector, $body]) {
            $this->assertDoesNotMatchRegularExpression('~text-transform\s*:\s*uppercase~i', $body, $selector);
        }
    }

    /**
     * A hover changes the shadow and the title's underline, and moves nothing.
     *
     * @return void
     */
    public function test_no_card_hover_moves_anything(): void {
        $hovers = 0;
        foreach ($this->card_rules() as [$selector, $body]) {
            if (!str_contains($selector, ':hover')) {
                continue;
            }
            $hovers++;
            $this->assertDoesNotMatchRegularExpression('~(^|[;\s])(transform|translate|scale)\s*:~', $body, $selector);
        }
        $this->assertGreaterThan(0, $hovers, 'No card :hover rule: the card has lost its hover.');
    }

    /**
     * A card rule paints through tokens: a colour literal lives only in a custom property's declaration.
     *
     * @return void
     */
    public function test_card_rules_have_no_colour_literal(): void {
        foreach ($this->card_rules() as [$selector, $body]) {
            $plain = (string) preg_replace('~--[a-zA-Z0-9_-]+\s*:[^;]*;?~', '', $body);
            $this->assertDoesNotMatchRegularExpression('~#[0-9a-fA-F]{3,8}\b|\b(rgba?|hsla?)\(~', $plain, $selector);
        }
    }

    /**
     * The plugin reads Boost Union's card settings on the server.
     *
     * @return void
     */
    public function test_sources_read_boost_union_settings(): void {
        $sources = '';
        foreach (self::SOURCES as $path) {
            $sources .= $this->read($path);
        }
        foreach (self::BOOST_UNION_SETTINGS as $setting) {
            $this->assertStringContainsString("'" . $setting . "'", $sources, $setting . ' is never read.');
        }
    }

    /**
     * The README tells an administrator which Boost Union settings change the cards.
     *
     * @return void
     */
    public function test_readme_names_boost_union_settings(): void {
        $readme = $this->read('README.md');
        foreach (self::BOOST_UNION_SETTINGS as $setting) {
            $this->assertStringContainsString($setting, $readme, 'README.md does not explain ' . $setting . '.');
        }
    }
}
