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
 * Guards the Bootstrap 5 markup the plugin emits.
 *
 * phpcs, the Mustache lint and stylelint never read a class name or a data attribute out of a
 * Mustache, JS or PHP file, so a deprecated Bootstrap 4 spelling or a badge without its text
 * colour reaches the browser silently. These scans read the sources instead.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class bootstrap_markup_test extends \basic_testcase {
    /**
     * Saturated background utilities that need an explicit light text colour.
     *
     * Bootstrap 5's .badge defaults to white text, so a light background renders white on
     * near-white (bg-secondary is 1.49:1, against the 4.5:1 AA floor). Stating the text colour on
     * every background, saturated ones included, keeps the pairing in the markup rather than in a
     * default a theme can change.
     *
     * @return array Background utility => the text utility it requires.
     */
    private function badge_text_colours(): array {
        return [
            'bg-success' => 'text-white',
            'bg-primary' => 'text-white',
            'bg-danger' => 'text-white',
            'bg-info' => 'text-white',
            'bg-dark' => 'text-white',
            'bg-secondary' => 'text-dark',
            'bg-warning' => 'text-dark',
        ];
    }

    /**
     * Absolute path to the plugin root.
     *
     * @return string Plugin directory without a trailing separator.
     */
    private function plugin_root(): string {
        return dirname(__DIR__, 2);
    }

    /**
     * Every file whose contents can put a class name in front of a user.
     *
     * Skips amd/build (generated from amd/src) and docs (not shipped, and .gitattributes keeps it
     * out of the release zip).
     *
     * @return array List of absolute file paths.
     */
    private function markup_files(): array {
        $root = $this->plugin_root();
        $dirs = [$root . '/templates', $root . '/amd/src', $root . '/classes'];
        $files = [];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                if (!in_array($file->getExtension(), ['mustache', 'js', 'php'], true)) {
                    continue;
                }
                $files[] = $file->getPathname();
            }
        }
        sort($files);
        return $files;
    }

    /**
     * Whether a line is prose rather than markup.
     *
     * The rules below are about what reaches the browser. A comment that names a class or an
     * attribute in order to explain a rule is not a violation of it.
     *
     * @param string $line One raw source line.
     * @return bool True when the line opens with a PHP, JS or Mustache comment marker.
     */
    private function is_comment_line(string $line): bool {
        $trimmed = ltrim($line);

        return $trimmed === ''
            || str_starts_with($trimmed, '//')
            || str_starts_with($trimmed, '/*')
            || str_starts_with($trimmed, '*')
            || str_starts_with($trimmed, '{{!');
    }

    /**
     * The class string a class token sits in: the text between the nearest quotes around it.
     *
     * One source line can carry two class strings, as a ternary choosing between two badges does,
     * and a text utility in one of them says nothing about the other.
     *
     * @param string $line One raw source line.
     * @param int $offset Byte offset of the token in the line.
     * @param int $length Byte length of the token.
     * @return string The enclosing quoted text, or the whole line when the token is not quoted.
     */
    private function class_string_around(string $line, int $offset, int $length): string {
        $start = 0;
        $end = strlen($line);
        /* The third delimiter is the JS template-literal backtick; Moodle's phpcs forbids it literally. */
        foreach (['"', "'", "\x60"] as $quote) {
            $before = strrpos(substr($line, 0, $offset), $quote);
            if ($before !== false && $before + 1 > $start) {
                $start = $before + 1;
            }
            $after = strpos($line, $quote, $offset + $length);
            if ($after !== false && $after < $end) {
                $end = $after;
            }
        }
        return substr($line, $start, $end - $start);
    }

    /**
     * Every badge background must state its text colour.
     *
     * The text utility must be the one badge_text_colours() pairs with that background, in the
     * same class string: text-dark on bg-success fails the AA floor.
     *
     * Changes that must make it fail: pair bg-success with text-dark in
     * template_import_verdict::verdict_badge(); swap the two text utilities in the source
     * colour ternary of amd/src/setting_iconpicker.js.
     *
     * @return void
     */
    public function test_badges_state_their_text_colour(): void {
        /*
         * Checked on every line carrying a background utility, not only lines that also say
         * "badge": a match arm returning a bare 'bg-success' carries no such word. The exceptions
         * are surfaces whose text colour the plugin's own CSS sets.
         */
        $exceptions = ['hero_header.mustache'];
        $offenders = [];
        foreach ($this->markup_files() as $path) {
            if (in_array(basename($path), $exceptions, true)) {
                continue;
            }
            foreach (file($path) as $number => $line) {
                if ($this->is_comment_line($line)) {
                    continue;
                }
                foreach ($this->badge_text_colours() as $background => $required) {
                    $pattern = '/(?<![\w-])' . preg_quote($background, '/') . '(?![\w-])/';
                    if (!preg_match_all($pattern, $line, $matches, PREG_OFFSET_CAPTURE)) {
                        continue;
                    }
                    foreach ($matches[0] as [$token, $offset]) {
                        $classes = $this->class_string_around($line, $offset, strlen($token));
                        if (!preg_match('/(?<![\w-])' . preg_quote($required, '/') . '(?![\w-])/', $classes)) {
                            $offenders[] = basename($path) . ':' . ($number + 1) . ' needs ' . $required
                                . ' beside ' . $background;
                        }
                    }
                }
            }
        }
        $this->assertSame(
            [],
            $offenders,
            'Bootstrap 5 defaults .badge text to white, so a badge that does not state its own colour '
                . 'fails contrast on a light background: '
                . implode('; ', $offenders)
        );
    }

    /**
     * A component wired through Bootstrap's markup data-API uses only the Bootstrap 5 spelling.
     *
     * Bootstrap 5 listens on data-bs-toggle, data-bs-target, data-bs-dismiss and data-bs-parent
     * only, so the Bootstrap 4 attribute is dead markup: beside the BS5 one it adds nothing, and
     * alone it leaves the component inert.
     *
     * Change that must make it fail: add data-toggle="dropdown" back beside data-bs-toggle in
     * templates/central/plans.mustache.
     *
     * @return void
     */
    public function test_data_api_attributes_use_the_bootstrap5_spelling(): void {
        $offenders = [];
        $spellings = [
            'data-toggle' => 'data-bs-toggle',
            'data-target' => 'data-bs-target',
            'data-dismiss' => 'data-bs-dismiss',
            'data-parent' => 'data-bs-parent',
        ];
        foreach ($this->markup_files() as $path) {
            foreach (file($path) as $number => $line) {
                if ($this->is_comment_line($line)) {
                    continue;
                }
                foreach ($spellings as $bs4 => $bs5) {
                    /* Match the attribute itself, never a longer name that merely starts with it. */
                    if (preg_match('/(?<![-\w])' . preg_quote($bs4, '/') . '(?![-\w])/', $line)) {
                        $offenders[] = basename($path) . ':' . ($number + 1) . ' should use ' . $bs5;
                    }
                }
            }
        }
        $this->assertSame(
            [],
            $offenders,
            'Bootstrap 5 ignores the Bootstrap 4 data-API attributes; use the data-bs- spelling alone: '
                . implode('; ', $offenders)
        );
    }

    /**
     * The plugin must not declare custom properties inside core's design-system namespace.
     *
     * Moodle 5.2 ships theme/boost/scss/design-system/ with $mds-* tokens, so an --mds-*
     * declaration in the plugin's stylesheet squats a namespace core is expanding. The design
     * kit's own --mds-* references document core's palette and are not covered here - only
     * shipped CSS is.
     *
     * @return void
     */
    public function test_stylesheet_declares_no_core_design_system_tokens(): void {
        $root = $this->plugin_root();
        $sheets = array_merge([$root . '/styles.css'], glob($root . '/styles_*.css') ?: []);
        $offenders = [];
        foreach ($sheets as $sheet) {
            foreach (file($sheet) as $number => $line) {
                /* A declaration, not a mention: the property name followed by its colon. */
                if (preg_match('/--mds-[a-z0-9-]+\s*:/i', $line)) {
                    $offenders[] = basename($sheet) . ':' . ($number + 1);
                }
            }
        }
        $this->assertSame(
            [],
            $offenders,
            'These lines declare custom properties in core\'s --mds- namespace; use the plugin\'s own '
                . 'prefix instead: ' . implode(', ', $offenders)
        );
    }

    /**
     * Bootstrap 4 class names that 5.x resolves only through its deprecation layer.
     *
     * These names resolve on 5.x only through theme/boost/scss/moodle/bs4-compat.scss, which wraps
     * each in @include deprecated-styles() (a red outline under behat-site and themedesignermode)
     * and which Moodle 6.0 removes (MDL-84465). Writing "ml-2 ms-2" side by side only adds a
     * deprecation.
     *
     * @return array Regex matching the deprecated class => the Bootstrap 5 spelling to use instead.
     */
    private function deprecated_bs4_utilities(): array {
        return [
            '/\bsr-only\b/' => 'visually-hidden',
            '/\bml-[0-9]\b/' => 'ms-*',
            '/\bmr-[0-9]\b/' => 'me-*',
            '/\bpl-[0-9]\b/' => 'ps-*',
            '/\bpr-[0-9]\b/' => 'pe-*',
            '/\btext-left\b/' => 'text-start',
            '/\btext-right\b/' => 'text-end',
            '/\bfloat-left\b/' => 'float-start',
            '/\bfloat-right\b/' => 'float-end',
            '/\bborder-left\b/' => 'border-start',
            '/\bborder-right\b/' => 'border-end',
            '/\brounded-left\b/' => 'rounded-start',
            '/\brounded-right\b/' => 'rounded-end',
            '/\bno-gutters\b/' => 'g-0',
            '/\bfont-weight-(light|lighter|normal|bold|bolder)\b/' => 'fw-*',
        ];
    }

    /**
     * The plugin emits no Bootstrap 4 class name that 5.x has already deprecated.
     *
     * Change that must make it fail: write font-weight-bold for fw-bold on the card header in
     * templates/course_card.mustache.
     *
     * @return void
     */
    public function test_no_deprecated_bootstrap4_class_names(): void {
        $offenders = [];
        foreach ($this->markup_files() as $path) {
            foreach (file($path) as $number => $line) {
                if ($this->is_comment_line($line)) {
                    continue;
                }
                foreach ($this->deprecated_bs4_utilities() as $pattern => $replacement) {
                    if (!preg_match($pattern, $line)) {
                        continue;
                    }
                    $offenders[] = basename($path) . ':' . ($number + 1) . ' should use ' . $replacement;
                }
            }
        }
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'Moodle 5.x resolves these Bootstrap 4 names only through bs4-compat.scss, which marks each '
                . 'one deprecated and which Moodle 6.0 deletes; use the Bootstrap 5 spelling: '
                . implode('; ', $offenders)
        );
    }
}
