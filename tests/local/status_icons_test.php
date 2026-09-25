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
 * The status icons are masks painted by colour tokens, so they follow the theme and the colour mode.
 *
 * An SVG shown through an img element keeps the colours written in the file: a fill or stroke
 * rule on the img reaches nothing inside it, which is how the check, lock, circle and info icons
 * and the grade badge icons stayed light-mode green and grey on a dark page while the stylesheet
 * appeared to colour them. Each icon is now an empty span whose background is a token and whose
 * mask is the pix/status SVG. Nothing in the pipeline reads a template and the stylesheet
 * together, so these tests do. Each test names the change that must make it fail.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class status_icons_test extends \basic_testcase {
    /**
     * @var array Icon class => the class every icon of its family carries, the pix image its mask
     *            reads, and whether it draws a glyph (the white mark the SVG paints inside its
     *            shape, which an alpha mask cannot keep).
     */
    private const ICONS = [
        'local-dimensions-icon-check' => [
            'base' => 'local-dimensions-icon',
            'mask' => 'status/check-circle-fill',
            'glyph' => true,
        ],
        'local-dimensions-icon-lock' => [
            'base' => 'local-dimensions-icon',
            'mask' => 'status/lock',
            'glyph' => false,
        ],
        'local-dimensions-icon-lock-sm' => [
            'base' => 'local-dimensions-icon',
            'mask' => 'status/lock',
            'glyph' => false,
        ],
        'local-dimensions-icon-circle' => [
            'base' => 'local-dimensions-icon',
            'mask' => 'status/circle-outline',
            'glyph' => false,
        ],
        'local-dimensions-icon-info' => [
            'base' => 'local-dimensions-icon',
            'mask' => 'status/info-circle',
            'glyph' => false,
        ],
        'local-dimensions-grade-badge-icon-proficient' => [
            'base' => 'local-dimensions-grade-badge-icon',
            'mask' => 'status/check-circle-fill',
            'glyph' => true,
        ],
        'local-dimensions-grade-badge-icon-warning' => [
            'base' => 'local-dimensions-grade-badge-icon',
            'mask' => 'status/warning-triangle-fill',
            'glyph' => true,
        ],
    ];

    /** @var string The custom property a glyph is painted in. */
    private const GLYPH = '--local-dimensions-icon-glyph';

    /**
     * Absolute path to the plugin root.
     *
     * @return string Plugin directory without a trailing separator.
     */
    private function plugin_root(): string {
        return dirname(__DIR__, 2);
    }

    /**
     * Every template of the plugin.
     *
     * @return array Absolute path => contents with Mustache comments removed.
     */
    private function templates(): array {
        $templates = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->plugin_root() . '/templates')
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'mustache') {
                continue;
            }
            /* The docblocks carry example markup; only rendered markup counts. */
            $templates[$file->getPathname()] = preg_replace('/\{\{!.*?\}\}/s', '', file_get_contents($file->getPathname()));
        }
        ksort($templates);
        return $templates;
    }

    /**
     * Every AMD source module of the plugin.
     *
     * @return array Absolute path => contents.
     */
    private function scripts(): array {
        $scripts = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->plugin_root() . '/amd/src')
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'js') {
                $scripts[$file->getPathname()] = file_get_contents($file->getPathname());
            }
        }
        ksort($scripts);
        return $scripts;
    }

    /**
     * The rules of styles.css, with comments removed and at-rule wrappers unwrapped.
     *
     * @return array List of arrays with keys selector (whitespace collapsed), body and at (the
     *               enclosing at-rule preludes, whitespace collapsed; '' at the top level).
     */
    private function rules(): array {
        $css = preg_replace('~/\*.*?\*/~s', '', file_get_contents($this->plugin_root() . '/styles.css'));
        $rules = [];
        $stack = [];
        $start = 0;
        $length = strlen($css);
        for ($i = 0; $i < $length; $i++) {
            if ($css[$i] === '{') {
                $stack[] = [trim(preg_replace('/\s+/', ' ', substr($css, $start, $i - $start))), $i];
                $start = $i + 1;
            } else if ($css[$i] === '}') {
                if ($stack) {
                    [$selector, $open] = array_pop($stack);
                    $body = substr($css, $open + 1, $i - $open - 1);
                    if (!str_contains($body, '{')) {
                        $rules[] = [
                            'selector' => $selector,
                            'body' => $body,
                            'at' => implode(' ', array_column($stack, 0)),
                        ];
                    }
                }
                $start = $i + 1;
            }
        }
        return $rules;
    }

    /**
     * The declarations of one rule body.
     *
     * @param string $body The text between a rule's braces.
     * @return array Lower-case property => value with whitespace collapsed.
     */
    private function declarations(string $body): array {
        $found = [];
        foreach (explode(';', $body) as $declaration) {
            if (!str_contains($declaration, ':')) {
                continue;
            }
            [$property, $value] = explode(':', $declaration, 2);
            $found[strtolower(trim($property))] = trim(preg_replace('/\s+/', ' ', $value));
        }
        return $found;
    }

    /**
     * The merged declarations of every top-level rule whose selector list names one selector.
     *
     * @param string $selector A selector, such as .local-dimensions-icon-check.
     * @param string $at The enclosing at-rule prelude to look in; '' for the top level.
     * @return array Lower-case property => value, later rules winning.
     */
    private function declared_for(string $selector, string $at = ''): array {
        $found = [];
        foreach ($this->rules() as $rule) {
            if ($rule['at'] !== $at) {
                continue;
            }
            $parts = array_map('trim', explode(',', $rule['selector']));
            if (in_array($selector, $parts, true)) {
                $found = array_merge($found, $this->declarations($rule['body']));
            }
        }
        return $found;
    }

    /**
     * The colour tokens the stylesheet declares on its bare body block.
     *
     * @return array List of token names, each starting with --local-dimensions-.
     */
    private function tokens(): array {
        $tokens = [];
        foreach ($this->rules() as $rule) {
            if ($rule['at'] === '' && $rule['selector'] === 'body') {
                foreach (array_keys($this->declarations($rule['body'])) as $property) {
                    if (str_starts_with($property, '--local-dimensions-')) {
                        $tokens[] = $property;
                    }
                }
            }
        }
        return $tokens;
    }

    /**
     * Every opening tag in a template.
     *
     * @param string $markup Template markup.
     * @return array List of arrays with keys tag (lower-case name), attributes (raw text) and
     *               after (the markup that follows the tag).
     */
    private function tags(string $markup): array {
        preg_match_all(
            '/<([a-zA-Z][a-zA-Z0-9]*)\b((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>/s',
            $markup,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );
        $tags = [];
        foreach ($matches as $match) {
            $tags[] = [
                'tag' => strtolower($match[1][0]),
                'attributes' => $match[2][0],
                'after' => substr($markup, $match[0][1] + strlen($match[0][0])),
            ];
        }
        return $tags;
    }

    /**
     * The class tokens of a tag's raw attribute text.
     *
     * @param string $attributes Raw attribute text, as returned by tags().
     * @return array List of class tokens.
     */
    private function classes(string $attributes): array {
        if (!preg_match('/(?<![\w-])class="([^"]*)"/', $attributes, $m)) {
            return [];
        }
        return preg_split('/\s+/', trim($m[1]), -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Every status icon is an empty, hidden span carrying its family class, and none is an img.
     *
     * The icons are decorative: the text beside each one carries the state, which is why every img
     * they replace had an empty alt and aria-hidden. A span with content would put that content
     * over the mask.
     *
     * Change that must make it fail: put an img back for any status icon, drop aria-hidden from one
     * of the spans, or drop local-dimensions-icon from one of them.
     *
     * @return void
     */
    public function test_status_icons_render_as_empty_hidden_spans(): void {
        $offenders = [];
        $rendered = [];
        foreach ($this->templates() as $path => $markup) {
            foreach ($this->tags($markup) as $tag) {
                $classes = $this->classes($tag['attributes']);
                $icons = array_intersect(array_keys(self::ICONS), $classes);
                $families = array_intersect(array_unique(array_column(self::ICONS, 'base')), $classes);
                if (!$icons && !$families) {
                    continue;
                }
                $where = basename($path) . ' (' . implode(' ', $classes) . ')';
                if ($tag['tag'] !== 'span') {
                    $offenders[] = $where . ' is <' . $tag['tag'] . '>, not a span';
                    continue;
                }
                if (count($icons) !== 1) {
                    $offenders[] = $where . ' names ' . count($icons) . ' status icons instead of one';
                    continue;
                }
                $icon = reset($icons);
                $rendered[$icon] = true;
                if (!in_array(self::ICONS[$icon]['base'], $classes, true)) {
                    $offenders[] = $where . ' lacks ' . self::ICONS[$icon]['base'];
                }
                if (!preg_match('/(?<![\w-])aria-hidden="true"/', $tag['attributes'])) {
                    $offenders[] = $where . ' is not aria-hidden';
                }
                if (!str_starts_with($tag['after'], '</' . $tag['tag'] . '>')) {
                    $offenders[] = $where . ' has content';
                }
            }
        }
        foreach (array_keys(self::ICONS) as $icon) {
            if (!isset($rendered[$icon])) {
                $offenders[] = $icon . ' is rendered by no template';
            }
        }
        $this->assertSame([], $offenders, 'Status icons must be empty aria-hidden spans: ' . implode('; ', $offenders));
    }

    /**
     * Each status icon is a mask of its pix image, painted by a declared colour token.
     *
     * The base rule of each family sizes and places the mask and keeps the background in print,
     * and every mask property has its -webkit- twin, which Chromium before 120 reads instead.
     *
     * Change that must make it fail: set background-color: #198754 on .local-dimensions-icon-check,
     * delete a -webkit-mask-image line, point a mask at an image pix/ does not hold, or delete the
     * print-color-adjust declaration of a family.
     *
     * @return void
     */
    public function test_each_status_icon_is_a_token_painted_mask(): void {
        $tokens = $this->tokens();
        $this->assertContains('--local-dimensions-success-ink', $tokens, 'The token block was not found.');
        $offenders = [];
        foreach (array_unique(array_column(self::ICONS, 'base')) as $base) {
            $declared = $this->declared_for('.' . $base);
            $expected = [
                'mask-size' => 'contain',
                'mask-repeat' => 'no-repeat',
                'mask-position' => 'center',
                'print-color-adjust' => 'exact',
            ];
            foreach ($expected as $property => $value) {
                foreach (['', '-webkit-'] as $prefix) {
                    if (($declared[$prefix . $property] ?? null) !== $value) {
                        $offenders[] = '.' . $base . ' ' . $prefix . $property . ' is not ' . $value;
                    }
                }
            }
        }
        foreach (self::ICONS as $icon => $spec) {
            $declared = $this->declared_for('.' . $icon);
            $url = "url('[[pix:local_dimensions|" . $spec['mask'] . "]]')";
            foreach (['mask-image', '-webkit-mask-image'] as $property) {
                if (($declared[$property] ?? null) !== $url) {
                    $offenders[] = '.' . $icon . ' ' . $property . ' is not ' . $url;
                }
            }
            if (!is_file($this->plugin_root() . '/pix/' . $spec['mask'] . '.svg')) {
                $offenders[] = '.' . $icon . ' masks with pix/' . $spec['mask'] . '.svg, which does not exist';
            }
            $paint = $declared['background-color'] ?? '';
            if (!preg_match('/^var\((--local-dimensions-[a-z-]+)\)$/', $paint, $m) || !in_array($m[1], $tokens, true)) {
                $offenders[] = '.' . $icon . ' is painted with "' . $paint . '", not a declared colour token';
            }
        }
        $this->assertSame([], $offenders, 'Every status icon must be a token-painted mask: ' . implode('; ', $offenders));
    }

    /**
     * No rule paints fill or stroke on an icon or an image the plugin renders as HTML.
     *
     * fill and stroke paint SVG shapes. On an img they reach nothing inside the file, and on a span
     * they paint nothing at all, so such a rule reads as colouring an icon while colouring nothing:
     * the defect the status icons carried.
     *
     * Change that must make it fail: add fill: var(--local-dimensions-success-ink) to
     * .local-dimensions-icon-check, or stroke to .local-dimensions-rules-child-icon-image.
     *
     * @return void
     */
    public function test_no_fill_or_stroke_on_html_icons(): void {
        $htmlclasses = array_merge(array_keys(self::ICONS), array_column(self::ICONS, 'base'));
        $sources = $this->templates() + $this->scripts();
        foreach ($sources as $source) {
            preg_match_all('/<img\b[^>]*?\bclass=\\\\?"([^"\\\\]*)/', $source, $matches);
            foreach ($matches[1] as $list) {
                $htmlclasses = array_merge($htmlclasses, preg_split('/\s+/', trim($list), -1, PREG_SPLIT_NO_EMPTY));
            }
        }
        $htmlclasses = array_values(array_unique($htmlclasses));
        $this->assertContains(
            'local-dimensions-rules-child-icon-image',
            $htmlclasses,
            'The img elements the scripts build were not found, so they would not be checked.'
        );
        $offenders = [];
        $painting = 0;
        foreach ($this->rules() as $rule) {
            $declared = $this->declarations($rule['body']);
            if (!isset($declared['fill']) && !isset($declared['stroke'])) {
                continue;
            }
            $painting++;
            foreach (array_map('trim', explode(',', $rule['selector'])) as $part) {
                $compounds = preg_split('/\s*[\s>+~]\s*/', $part);
                $subject = end($compounds);
                preg_match_all('/\.([a-z0-9_-]+)/i', $subject, $classes);
                if (preg_match('/^img(?![\w-])/i', $subject) || array_intersect($classes[1], $htmlclasses)) {
                    $offenders[] = $part;
                }
            }
        }
        /* The progress rings are inline SVG and do take fill and stroke, so rules exist to compare. */
        $this->assertGreaterThan(0, $painting, 'No fill or stroke rule was found, so nothing was compared.');
        $this->assertSame(
            [],
            $offenders,
            'fill and stroke paint nothing on these HTML elements; colour the icon through its mask instead: '
                . implode('; ', $offenders)
        );
    }

    /**
     * Every glyph icon draws its glyph in a token over its own box, unmirrored in RTL.
     *
     * The glyph is a pseudo-element positioned against the icon, painted in the icon's glyph
     * property, which has to name a declared token. Moodle's RTL flip swaps left and right offsets
     * and background positions but not a rotation, so a glyph rule it does not skip lands off
     * centre in a right-to-left language.
     *
     * Change that must make it fail: delete position: relative from .local-dimensions-icon-check, the
     * glyph property from a glyph icon, a selector from a ::after glyph rule, or its rtl:ignore.
     *
     * @return void
     */
    public function test_glyph_icons_draw_their_glyph(): void {
        $tokens = $this->tokens();
        $raw = file_get_contents($this->plugin_root() . '/styles.css');
        preg_match_all('~/\*\s*rtl:ignore\s*\*/\s*([^{]+)\{~', $raw, $matches);
        $unmirrored = [];
        foreach ($matches[1] as $list) {
            $unmirrored = array_merge($unmirrored, array_map('trim', explode(',', preg_replace('/\s+/', ' ', $list))));
        }
        $offenders = [];
        $checked = 0;
        foreach (self::ICONS as $icon => $spec) {
            if (!$spec['glyph']) {
                continue;
            }
            $checked++;
            $declared = $this->declared_for('.' . $icon);
            $position = $declared['position'] ?? $this->declared_for('.' . $spec['base'])['position'] ?? '';
            if ($position !== 'relative') {
                $offenders[] = '.' . $icon . ' is not positioned, so its glyph is placed against an ancestor';
            }
            $glyph = $declared[self::GLYPH] ?? '';
            if (!preg_match('/^var\((--local-dimensions-[a-z-]+)\)$/', $glyph, $m) || !in_array($m[1], $tokens, true)) {
                $offenders[] = '.' . $icon . ' sets ' . self::GLYPH . ' to "' . $glyph . '", not a declared colour token';
            }
            $after = $this->declared_for('.' . $icon . '::after');
            if (!isset($after['content'])) {
                $offenders[] = '.' . $icon . '::after draws no glyph';
            } else if (!str_contains(implode(' ', $after), 'var(' . self::GLYPH . ')')) {
                $offenders[] = '.' . $icon . '::after is not painted in ' . self::GLYPH;
            }
            if (!in_array('.' . $icon . '::after', $unmirrored, true)) {
                $offenders[] = '.' . $icon . '::after is not under rtl:ignore';
            }
        }
        $this->assertSame(3, $checked, 'The glyph icons were not all checked.');
        $this->assertSame([], $offenders, 'Every glyph icon must draw its glyph: ' . implode('; ', $offenders));
    }

    /**
     * Under forced colours every status icon keeps its own paint, in the forced text colour.
     *
     * Forced colours replace a background colour with the canvas and drop gradient backgrounds, so
     * an icon painted by its background, and a glyph drawn in gradients, would vanish.
     *
     * Change that must make it fail: drop .local-dimensions-icon-info from the forced-colours rule,
     * delete its forced-color-adjust, or its glyph canvas.
     *
     * @return void
     */
    public function test_forced_colours_keep_every_status_icon(): void {
        $offenders = [];
        foreach (self::ICONS as $icon => $spec) {
            $declared = $this->declared_for('.' . $icon, '@media (forced-colors: active)');
            if (($declared['forced-color-adjust'] ?? null) !== 'none') {
                $offenders[] = '.' . $icon . ' does not opt out of forced colours';
            }
            if (($declared['background-color'] ?? null) !== 'currentcolor') {
                $offenders[] = '.' . $icon . ' is not painted in currentcolor under forced colours';
            }
            if ($spec['glyph'] && ($declared[self::GLYPH] ?? null) !== 'canvas') {
                $offenders[] = '.' . $icon . ' does not draw its glyph in canvas under forced colours';
            }
        }
        $this->assertSame([], $offenders, 'Forced colours would erase these icons: ' . implode('; ', $offenders));
    }
}
