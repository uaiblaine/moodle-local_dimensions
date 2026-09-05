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
 * 4.5's forward bridge (theme/boost/scss/moodle/bs5-bridge.scss) is 116 lines covering only
 * g-0, btn-close, the ms/me/ps/pe spacers and float/text/border/rounded-start/end, while 5.x's
 * backward bridge runs past a thousand. A BS5 utility outside that short list resolves to
 * nothing on 4.5.
 *
 * This defect class has shipped three times - see CHANGELOG.md and commit f84d30a - and was
 * correctly root-caused and documented each time. It recurred anyway, and a 2026-08-06 sweep
 * still found 90 sites. The reason is enforcement, not diligence: the sibling rule about JS
 * data attributes has held at 100% compliance because the 4.05 Behat leg throws when a dropdown
 * fails to open, while the class rules failed silently with CI fully green. Nothing else in the
 * pipeline can see a class name that resolves to nothing - not phpcs, not the mustache lint, not
 * stylelint, which never reads a Mustache or JS file. This test is that missing observer.
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
     * Bootstrap 4's .badge sets no colour at all, so a saturated badge renders near-black text
     * on a dark fill; Bootstrap 5's .badge defaults to white, so a LIGHT background renders white
     * on near-white. Both directions were measured on the running stacks: bg-success gives 3.07:1
     * on 4.5 and bg-secondary gives 1.49:1 on 5.2, against the 4.5:1 AA floor. The only markup
     * that is correct on both branches states its text colour explicitly.
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
     * Deliberately token-level, not family-level. A family-level check ("is gap-* covered?")
     * passes while gap-2 alone is missing, which is precisely the silent gap this whole test
     * exists to close.
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
         * .btn-close and .modal chrome core emits, and the body gate itself.
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
     * Every badge background must state its text colour, so it reads on both branches.
     *
     * @return void
     */
    public function test_badges_state_their_text_colour(): void {
        /*
         * Checked on every line carrying a background utility, NOT only lines that also say
         * "badge". The first version of this test filtered on that word and stayed green while
         * template_import_verdict.php returned bare 'bg-success' from a match arm - the word
         * "badge" was in the method name, one line up. The exceptions below are the surfaces that
         * legitimately carry a background without a text utility, because the plugin's own CSS
         * sets the colour for them.
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
                    if (!preg_match('/\b' . preg_quote($background, '/') . '\b/', $line)) {
                        continue;
                    }
                    if (!preg_match('/\btext-(white|dark|body|muted)\b/', $line)) {
                        $offenders[] = basename($path) . ':' . ($number + 1) . ' needs ' . $required;
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
     * This rule has never been broken, because the 4.05 Behat leg catches it. It is asserted here
     * so the guarantee survives a change in what Behat covers.
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
     * Moodle 5.2 ships theme/boost/scss/design-system/ with $mds-* tokens and 5.3 LTS brings MDS
     * React, so an --mds-* declaration in the plugin's stylesheet is squatting a namespace core is
     * actively expanding. The design kit's own --mds-* references document core's palette and are
     * not covered here - only shipped CSS is.
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
     * The asymmetry runs both ways, and this is the direction that is easy to miss. These names DO
     * resolve on 5.x - but only through theme/boost/scss/moodle/bs4-compat.scss, which wraps each
     * in an @include deprecated-styles() (a red outline under behat-site and themedesignermode) and
     * which Moodle 6.0 removes entirely, MDL-84465. Their Bootstrap 5 spellings are all inside
     * 4.5's own 116-line forward bridge, so the BS5 name ALONE is correct on both branches: writing
     * "ml-2 ms-2" side by side buys nothing and costs a deprecation.
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
        ];
    }

    /**
     * The plugin emits no Bootstrap 4 class name that 5.x has already deprecated.
     *
     * The companion rule to test_every_bs5_utility_used_is_polyfilled, and the reason the fix for a
     * missing BS5 utility is the polyfill rather than the BS4 name: writing the old name moves the
     * breakage from 4.5 to Moodle 6.0 instead of removing it.
     *
     * Mutations that must redden it: revert one visually-hidden to sr-only; write ml-2 beside ms-2.
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
     * The Bootstrap 4 marker must be added wherever the plugin sets one of its page body classes.
     *
     * The polyfill is gated on that marker, so an entry point that forgets it renders unstyled on
     * 4.5 while every static gate stays green.
     *
     * @return void
     */
    public function test_entry_points_mark_the_bootstrap_version(): void {
        $root = $this->plugin_root();
        $offenders = [];
        foreach (glob($root . '/*.php') ?: [] as $path) {
            $contents = file_get_contents($path);
            if (!preg_match('/add_body_class\(\s*[\'"]local-dimensions-/', $contents)) {
                continue;
            }
            if (!str_contains($contents, 'bootstrap::mark_page()')) {
                $offenders[] = basename($path);
            }
        }
        $this->assertSame(
            [],
            $offenders,
            'These entry points set a plugin body class but never call bootstrap::mark_page(), so the '
                . 'Bootstrap 4 polyfill will not reach them: ' . implode(', ', $offenders)
        );
    }
}
