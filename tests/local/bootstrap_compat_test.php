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

/**
 * Guards the plugin's Bootstrap 4 / Bootstrap 5 contract.
 *
 * Moodle 4.5 ships Bootstrap 4 and 5.0+ ship Bootstrap 5, and the bridging is asymmetric:
 * 4.5's forward bridge (theme/boost/scss/moodle/bs5-bridge.scss) covers only g-0, btn-close,
 * the ms/me/ps/pe spacers and float/text/border/rounded-start/end, while 5.x's backward bridge
 * (bs4-compat.scss) is far broader. A BS5 utility outside that short list resolves to nothing
 * on 4.5, silently: phpcs, the Mustache lint and stylelint never read a class name out of a
 * Mustache or JS file.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\local\bootstrap
 */
final class bootstrap_compat_test extends \basic_testcase {
    /**
     * Bootstrap 5 utilities that do not exist on Moodle 4.5.
     *
     * Each entry maps a regular expression matching the class in a class attribute or a JS class
     * string to the human-readable family name reported when it is found unpolyfilled. Source of
     * truth for what 4.5 does bridge: theme/boost/scss/moodle/bs5-bridge.scss.
     *
     * @return array Regex => family label.
     */
    private function bs5_only_utilities(): array {
        return [
            '/\bvisually-hidden\b/' => 'visually-hidden',
            '/\bform-select(-sm)?\b/' => 'form-select',
            '/\bgap-[0-9]\b/' => 'gap-*',
            '/\bfw-(bold|medium|normal|semibold|light)\b/' => 'fw-*',
            '/\bfont-monospace\b/' => 'font-monospace',
            '/\bform-switch\b/' => 'form-switch',
            '/\bform-label\b/' => 'form-label',
        ];
    }

    /**
     * Saturated background utilities that need an explicit light text colour.
     *
     * Bootstrap 4's .badge sets no colour, so a saturated badge renders near-black text on a dark
     * fill; Bootstrap 5's .badge defaults to white, so a light background renders white on
     * near-white (bg-success is 3.07:1 on 4.5 and bg-secondary 1.49:1 on 5.2, against the 4.5:1
     * AA floor). Only markup that states its text colour is correct on both branches.
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
     * The rules below are about what reaches the browser. A comment that names an attribute in
     * order to explain the rule - as amd/src/central/context.js does - is not a violation of it.
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
     * The exact class tokens the polyfill block defines behind the Bootstrap 4 gate.
     *
     * Token-level on purpose: a family-level check ("is gap-* covered?") would pass while gap-2
     * alone is missing.
     *
     * @return array List of class tokens, e.g. gap-2, without the leading dot.
     */
    private function polyfilled_tokens(): array {
        $css = file_get_contents($this->plugin_root() . '/styles.css');
        /* Strip comments first: the block's own prose names files and classes it does not define. */
        $css = preg_replace('~/\*.*?\*/~s', '', $css);
        $gate = preg_quote(bootstrap::BODY_CLASS_BS4, '/');
        $tokens = [];
        foreach (explode('}', $css) as $block) {
            $selector = explode('{', $block)[0];
            if (!preg_match('/body\.' . $gate . '\b/', $selector)) {
                continue;
            }
            preg_match_all('/\.([a-z][a-z0-9-]*)/', $selector, $matches);
            foreach ($matches[1] as $token) {
                $tokens[] = $token;
            }
        }
        return array_values(array_unique($tokens));
    }

    /**
     * The exact Bootstrap 5 class tokens the plugin emits that 4.5 does not define.
     *
     * @return array Token => list of file basenames using it.
     */
    private function used_bs5_tokens(): array {
        $used = [];
        foreach ($this->markup_files() as $path) {
            foreach (file($path) as $line) {
                if ($this->is_comment_line($line)) {
                    continue;
                }
                foreach ($this->bs5_only_utilities() as $pattern => $unusedlabel) {
                    if (!preg_match_all($pattern, $line, $matches)) {
                        continue;
                    }
                    foreach ($matches[0] as $token) {
                        $used[$token][basename($path)] = true;
                    }
                }
            }
        }
        return array_map('array_keys', $used);
    }

    /**
     * Every Bootstrap 5 class the plugin emits must be defined by the polyfill for 4.5.
     *
     * @return void
     */
    public function test_every_bs5_utility_used_is_polyfilled(): void {
        $polyfilled = $this->polyfilled_tokens();
        $missing = [];
        foreach ($this->used_bs5_tokens() as $token => $files) {
            if (!in_array($token, $polyfilled, true)) {
                $missing[] = $token . ' (used in ' . implode(', ', array_slice($files, 0, 3)) . ')';
            }
        }
        sort($missing);
        $this->assertSame(
            [],
            $missing,
            'These Bootstrap 5 classes are used but resolve to nothing on Moodle 4.5. Either add them '
                . 'to the Bootstrap 4 utility polyfill at the tail of styles.css, or stop using them: '
                . implode('; ', $missing)
        );
    }

    /**
     * The polyfill must not grow rules for classes the plugin no longer uses.
     *
     * A compatibility layer that outlives its callers is how a temporary shim becomes permanent.
     *
     * @return void
     */
    public function test_polyfill_carries_nothing_unused(): void {
        $used = array_keys($this->used_bs5_tokens());
        /*
         * Structural helpers the polyfill needs but no markup names on its own: the .form-check
         * parent it keys off, the .form-check-input and .form-check-label it repositions, the
         * .btn-close and .modal chrome core emits, the hub's page body class, and the body gate
         * itself.
         */
        $structural = [
            bootstrap::BODY_CLASS_BS4,
            'local-dimensions-central-page',
            'form-check',
            'form-check-input',
            'form-check-label',
            'btn-close',
            'modal',
            'modal-header',
            'modal-body',
            'modal-form-dialogue',
        ];
        $unused = array_values(array_diff($this->polyfilled_tokens(), $used, $structural));
        sort($unused);
        $this->assertSame(
            [],
            $unused,
            'The Bootstrap 4 polyfill defines classes nothing uses any more; delete them: '
                . implode(', ', $unused)
        );
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
     * Every badge background must state its text colour, so it reads on both branches.
     *
     * The text utility must be the one badge_text_colours() pairs with that background, in the
     * same class string: text-dark on bg-success fails the AA floor on both branches.
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
            'Bootstrap 4 gives .badge no text colour and Bootstrap 5 defaults it to white, so a badge '
                . 'that does not state its own colour fails contrast on one branch or the other: '
                . implode('; ', $offenders)
        );
    }

    /**
     * A component wired through Bootstrap's markup data-API must carry both attribute spellings.
     *
     * Behat on Moodle 4.5 catches a missing data-toggle only where a scenario opens that component;
     * this check covers every file.
     *
     * @return void
     */
    public function test_data_api_attributes_are_paired(): void {
        $offenders = [];
        $pairs = [
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
                foreach ($pairs as $bs4 => $bs5) {
                    /* Match the attribute itself, never a longer name that merely starts with it. */
                    $hasbs4 = preg_match('/(?<![-\w])' . preg_quote($bs4, '/') . '(?![-\w])/', $line);
                    $hasbs5 = preg_match('/(?<![-\w])' . preg_quote($bs5, '/') . '(?![-\w])/', $line);
                    if ($hasbs4 !== $hasbs5) {
                        $offenders[] = basename($path) . ':' . ($number + 1) . ' has only ' . ($hasbs4 ? $bs4 : $bs5);
                    }
                }
            }
        }
        $this->assertSame(
            [],
            $offenders,
            'Bootstrap 4 listens on data-toggle and Bootstrap 5 on data-bs-toggle, so markup-wired '
                . 'components need both spellings side by side: ' . implode('; ', $offenders)
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
     * and which Moodle 6.0 removes (MDL-84465). Their Bootstrap 5 spellings are in 4.5's forward
     * bridge, or for visually-hidden in the plugin's polyfill, so the BS5 name alone is correct on
     * both branches: writing "ml-2 ms-2" side by side only adds a deprecation.
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
     * The companion rule to test_every_bs5_utility_used_is_polyfilled, and the reason the fix for a
     * missing BS5 utility is the polyfill rather than the BS4 name: writing the old name moves the
     * breakage from 4.5 to Moodle 6.0 instead of removing it.
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
                . 'one deprecated and which Moodle 6.0 deletes; the Bootstrap 5 spelling alone is correct '
                . 'on both branches: ' . implode('; ', $offenders)
        );
    }

    /**
     * PHP source with its comments removed, so a mention in prose is not read as a call.
     *
     * @param string $contents The contents of a PHP file.
     * @return string The same source without comment and docblock tokens.
     */
    private function php_code_only(string $contents): string {
        $code = '';
        foreach (token_get_all($contents) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $code .= is_array($token) ? $token[1] : $token;
        }
        return $code;
    }

    /**
     * The Bootstrap 4 marker must be added wherever the plugin sets one of its page body classes.
     *
     * The polyfill is gated on that marker, so an entry point that forgets it renders unstyled on
     * 4.5, with no error anywhere. Both the body class and the call are read from code, not from
     * comments.
     *
     * Change that must make it fail: turn the mark_page() call in view-plan.php into a comment.
     *
     * @return void
     */
    public function test_entry_points_mark_the_bootstrap_version(): void {
        $root = $this->plugin_root();
        $offenders = [];
        $checked = 0;
        foreach (glob($root . '/*.php') ?: [] as $path) {
            $code = $this->php_code_only(file_get_contents($path));
            if (!preg_match('/add_body_class\(\s*[\'"]local-dimensions-/', $code)) {
                continue;
            }
            $checked++;
            if (!preg_match('/\bbootstrap::mark_page\(\s*\)\s*;/', $code)) {
                $offenders[] = basename($path);
            }
        }
        $this->assertGreaterThan(
            0,
            $checked,
            'No entry point sets a local-dimensions- body class, so this test would pass over an empty list.'
        );
        $this->assertSame(
            [],
            $offenders,
            'These entry points set a plugin body class but never call bootstrap::mark_page(), so the '
                . 'Bootstrap 4 polyfill will not reach them: ' . implode(', ', $offenders)
        );
    }
}
