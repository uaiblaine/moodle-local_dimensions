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
 * rule on the img reaches nothing inside it, which is how the check, lock, circle and info icons,
 * the grade badge icons, the Rules tab's child states and the hero's calendar stayed light-mode
 * green, orange and grey on a dark page while the stylesheet appeared to colour them. Each icon is
 * now an empty span whose background is a token (or, on the hero, the island's own text colour)
 * and whose mask is the pix/status SVG. Nothing in the pipeline reads a template, a script, an SVG
 * and the stylesheet together, so these tests do. Each test names the change that must make it
 * fail.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class status_icons_test extends \basic_testcase {
    /**
     * @var array Icon class => the class every icon of its family carries (base), the pix image its
     *            mask reads (mask), what paints it (paint: a colour token, or currentcolor for an
     *            icon on a branded island, which takes the island's text colour), and the token its
     *            glyph is drawn in (glyph: the mark the icon shows inside its filled shape, which an
     *            alpha mask cannot keep; null for an icon without one), and whether the state is
     *            spoken by a visually hidden label right after the icon (label), because no visible
     *            text beside it names the state.
     */
    private const ICONS = [
        'local-dimensions-icon-check' => [
            'base' => 'local-dimensions-icon',
            'mask' => 'status/check-circle-fill',
            'paint' => '--local-dimensions-success-ink',
            'glyph' => '--local-dimensions-surface',
            'label' => false,
        ],
        'local-dimensions-icon-lock' => [
            'base' => 'local-dimensions-icon',
            'mask' => 'status/lock',
            'paint' => '--local-dimensions-ink-muted',
            'glyph' => null,
            'label' => false,
        ],
        'local-dimensions-icon-lock-sm' => [
            'base' => 'local-dimensions-icon',
            'mask' => 'status/lock',
            'paint' => '--local-dimensions-ink-muted',
            'glyph' => null,
            'label' => false,
        ],
        'local-dimensions-icon-circle' => [
            'base' => 'local-dimensions-icon',
            'mask' => 'status/circle-outline',
            'paint' => '--local-dimensions-line',
            'glyph' => null,
            'label' => false,
        ],
        'local-dimensions-icon-info' => [
            'base' => 'local-dimensions-icon',
            'mask' => 'status/info-circle',
            'paint' => '--local-dimensions-ink-muted',
            'glyph' => null,
            'label' => false,
        ],
        'local-dimensions-icon-rules-proficient' => [
            'base' => 'local-dimensions-icon',
            'mask' => 'status/rules-proficient',
            'paint' => '--local-dimensions-success-ink',
            'glyph' => '--local-dimensions-surface',
            'label' => true,
        ],
        'local-dimensions-icon-rules-inprogress' => [
            'base' => 'local-dimensions-icon',
            'mask' => 'status/rules-inprogress',
            'paint' => '--local-dimensions-warning-ink',
            'glyph' => null,
            'label' => true,
        ],
        'local-dimensions-icon-rules-todo' => [
            'base' => 'local-dimensions-icon',
            'mask' => 'status/rules-todo',
            'paint' => '--local-dimensions-neutral-ink',
            'glyph' => null,
            'label' => true,
        ],
        'local-dimensions-duedate-icon' => [
            'base' => 'local-dimensions-icon',
            'mask' => 'status/calendar-light',
            'paint' => 'currentcolor',
            'glyph' => null,
            'label' => false,
        ],
        'local-dimensions-grade-badge-icon-proficient' => [
            'base' => 'local-dimensions-grade-badge-icon',
            'mask' => 'status/check-circle-fill',
            'paint' => '--local-dimensions-success-ink',
            'glyph' => '--local-dimensions-success-tint',
            'label' => false,
        ],
        'local-dimensions-grade-badge-icon-warning' => [
            'base' => 'local-dimensions-grade-badge-icon',
            'mask' => 'status/warning-triangle-fill',
            'paint' => '--local-dimensions-warning-ink',
            'glyph' => '--local-dimensions-warning-tint',
            'label' => false,
        ],
    ];

    /** @var string What an icon on a branded island is painted with: the colour it inherits. */
    private const ISLAND_PAINT = 'currentcolor';

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
     * Every opening tag in a template, or in the markup a script builds.
     *
     * A script builds its markup in single-quoted string literals, so in a script a tag may not
     * cross a quote, a backtick or an angle bracket: a tag assembled by concatenation is not read,
     * and an icon built that way counts as rendered by nothing.
     *
     * @param string $markup Template markup, or the source of an AMD module.
     * @param bool $script Whether $markup is the source of an AMD module.
     * @return array List of arrays with keys tag (lower-case name), attributes (raw text), start and
     *               end (byte offsets of the tag in $markup) and after (the markup that follows it).
     */
    private function tags(string $markup, bool $script = false): array {
        /* The backtick is written \x60, since the coding style keeps it out of string literals. */
        $attributes = $script ? '((?:[^<>"\'\x60]|"[^"\'<>\x60]*")*)' : '((?:[^>"\']|"[^"]*"|\'[^\']*\')*)';
        preg_match_all(
            '/<([a-zA-Z][a-zA-Z0-9]*)\b' . $attributes . '>/s',
            $markup,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );
        $tags = [];
        foreach ($matches as $match) {
            $end = $match[0][1] + strlen($match[0][0]);
            $tags[] = [
                'tag' => strtolower($match[1][0]),
                'attributes' => $match[2][0],
                'start' => $match[0][1],
                'end' => $end,
                'after' => substr($markup, $end),
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
     * over the mask. The Rules tab's icons are built by accordion.js, so the scripts are read as
     * well as the templates, and their state is spoken only by the visually hidden label that
     * follows each one, so that label has to stay.
     *
     * Change that must make it fail: put an img back for any status icon (in a template or in
     * accordion.js), drop aria-hidden from one of the spans, drop local-dimensions-icon from one of
     * them, or drop the visually hidden label after a Rules tab icon.
     *
     * @return void
     */
    public function test_status_icons_render_as_empty_hidden_spans(): void {
        $offenders = [];
        $rendered = [];
        $sources = [];
        foreach ($this->templates() as $path => $markup) {
            $sources[] = [$path, $this->tags($markup)];
        }
        foreach ($this->scripts() as $path => $source) {
            $sources[] = [$path, $this->tags($source, true)];
        }
        foreach ($sources as [$path, $tags]) {
            foreach ($tags as $index => $tag) {
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
                if (self::ICONS[$icon]['label']) {
                    $next = $tags[$index + 1] ?? null;
                    $spoken = $next !== null
                        && $next['tag'] === 'span'
                        && in_array('visually-hidden', $this->classes($next['attributes']), true)
                        && !preg_match('/(?<![\w-])aria-hidden=/', $next['attributes'])
                        && !str_starts_with($next['after'], '</span>');
                    if (!$spoken) {
                        $offenders[] = $where . ' is not followed by the visually hidden label that speaks its state';
                    }
                }
            }
        }
        foreach (array_keys(self::ICONS) as $icon) {
            if (!isset($rendered[$icon])) {
                $offenders[] = $icon . ' is rendered by no template or script';
            }
        }
        $this->assertSame([], $offenders, 'Status icons must be empty aria-hidden spans: ' . implode('; ', $offenders));
    }

    /**
     * Each status icon is a mask of its pix image, painted by the colour token that carries its state.
     *
     * The base rule of each family sizes and places the mask and keeps the background in print,
     * and every mask property has its -webkit- twin, which Chromium before 120 reads instead. The
     * token is the icon's meaning (success, warning, neutral), so it is pinned per icon rather than
     * accepted as any declared token. An icon on a branded island is painted in currentcolor
     * instead; see test_island_icons_take_the_island_ink.
     *
     * Change that must make it fail: set background-color: #198754 on .local-dimensions-icon-check,
     * paint .local-dimensions-icon-rules-inprogress with the success ink, delete a -webkit-mask-image
     * line, point a mask at an image pix/ does not hold, or delete the print-color-adjust declaration
     * of a family.
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
            if ($spec['paint'] === self::ISLAND_PAINT) {
                if ($paint !== self::ISLAND_PAINT) {
                    $offenders[] = '.' . $icon . ' is painted with "' . $paint . '", not ' . self::ISLAND_PAINT;
                }
                continue;
            }
            if (!in_array($spec['paint'], $tokens, true)) {
                $offenders[] = '.' . $icon . ' expects ' . $spec['paint'] . ', which the token block does not declare';
            }
            if ($paint !== 'var(' . $spec['paint'] . ')') {
                $offenders[] = '.' . $icon . ' is painted with "' . $paint . '", not var(' . $spec['paint'] . ')';
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
     * .local-dimensions-icon-check, or stroke to .activityicon, the img accordion.js builds for an
     * activity.
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
            'activityicon',
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
     * property, which has to name the token of the ground the icon sits on, so the glyph reads as a
     * cut-out of the shape. Moodle's RTL flip swaps left and right offsets and background positions
     * but not a rotation, so a glyph rule it does not skip lands off centre in a right-to-left
     * language.
     *
     * Change that must make it fail: delete position: relative from .local-dimensions-icon-check, the
     * glyph property from a glyph icon, a selector from a ::after glyph rule (such as the Rules tab's
     * proficient check), or its rtl:ignore.
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
            if (!in_array($spec['glyph'], $tokens, true)) {
                $offenders[] = '.' . $icon . ' expects a ' . $spec['glyph'] . ' glyph, which the token block does not declare';
            }
            if ($glyph !== 'var(' . $spec['glyph'] . ')') {
                $offenders[] = '.' . $icon . ' sets ' . self::GLYPH . ' to "' . $glyph . '", not var(' . $spec['glyph'] . ')';
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
        $this->assertSame(4, $checked, 'The glyph icons were not all checked.');
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

    /**
     * An icon on a branded island paints in the island's own text colour and reads no mode token.
     *
     * The hero is painted with a colour the admin chose, so a colour token, which follows the page,
     * would measure the calendar against the wrong ground. Its mask takes currentcolor instead, and
     * it sets no colour of its own, so currentcolor is the colour of the element around it: the
     * due-date card, whose colour is the admin's text colour, carried by --dimension-customtextcolor,
     * with the island's white as the fallback. Under forced colours the card's colour is forced to
     * the system text colour, which the icon then inherits.
     *
     * Change that must make it fail: paint .local-dimensions-duedate-icon with a --local-dimensions-
     * token, give it a colour of its own, or take the transported text colour off the due-date card.
     *
     * @return void
     */
    public function test_island_icons_take_the_island_ink(): void {
        $offenders = [];
        $checked = 0;
        foreach (self::ICONS as $icon => $spec) {
            if ($spec['paint'] !== self::ISLAND_PAINT) {
                continue;
            }
            $checked++;
            foreach ($this->rules() as $rule) {
                if (!preg_match('/\.' . preg_quote($icon, '/') . '(?![\w-])/', $rule['selector'])) {
                    continue;
                }
                foreach ($this->declarations($rule['body']) as $property => $value) {
                    if (preg_match('/--local-dimensions-(?!icon-glyph\b)[a-z-]+/', $value, $m)) {
                        $offenders[] = $rule['selector'] . ' ' . $property . ' reads ' . $m[0] . ', not the island\'s ink';
                    }
                }
            }
            if (isset($this->declared_for('.' . $icon)['color'])) {
                $offenders[] = '.' . $icon . ' sets a colour of its own, so currentcolor is not the island\'s ink';
            }
            $containers = 0;
            foreach ($this->templates() as $path => $markup) {
                $tags = $this->tags($markup);
                foreach ($tags as $index => $tag) {
                    if (!in_array($icon, $this->classes($tag['attributes']), true)) {
                        continue;
                    }
                    $containers++;
                    $where = basename($path) . ' (' . $icon . ')';
                    $parent = $tags[$index - 1] ?? null;
                    if ($parent === null || trim(substr($markup, $parent['end'], $tag['start'] - $parent['end'])) !== '') {
                        $offenders[] = $where . ' is not the first child of the element whose colour it takes';
                        continue;
                    }
                    $inked = false;
                    foreach ($this->classes($parent['attributes']) as $class) {
                        $colour = $this->declared_for('.' . $class)['color'] ?? '';
                        if (
                            str_starts_with($colour, 'var(--dimension-customtextcolor,')
                            && !str_contains($colour, '--local-dimensions-')
                        ) {
                            $inked = true;
                        }
                    }
                    if (!$inked) {
                        $offenders[] = $where . ' sits in <' . $parent['tag'] . ' class="'
                            . implode(' ', $this->classes($parent['attributes']))
                            . '">, which does not carry the transported --dimension-customtextcolor';
                    }
                }
            }
            if ($containers === 0) {
                $offenders[] = $icon . ' is rendered by no template, so the colour it takes was not checked';
            }
        }
        $this->assertSame(1, $checked, 'The island icons were not all checked.');
        $this->assertSame(
            [],
            $offenders,
            'An icon on a branded island takes the admin\'s ink, never the page\'s: ' . implode('; ', $offenders)
        );
    }

    /**
     * Every pix/status image is a mask that carries no colour of its own.
     *
     * An alpha mask keeps every opaque pixel whatever its colour, so a second colour in the file is
     * not a second colour on the page: the light fill inside the Rules tab's to-do ring would turn
     * the ring into a solid disc, and a white glyph drawn inside a filled shape vanishes into it
     * (the glyph icons draw theirs in CSS). So each file paints in currentColor alone, over a root
     * that fills nothing, since a shape without a fill of its own is filled black by default. And
     * every file is some status icon's mask: an image shown through an img keeps the colours in the
     * file, which is the defect these masks replaced.
     *
     * Change that must make it fail: put stroke="#E8590C" back in rules-inprogress.svg, the light
     * fill back on the rules-todo.svg ring, or delete the root fill="none" of calendar-light.svg.
     *
     * @return void
     */
    public function test_mask_images_carry_no_colour_of_their_own(): void {
        $masks = array_unique(array_column(self::ICONS, 'mask'));
        $files = glob($this->plugin_root() . '/pix/status/*.svg') ?: [];
        $this->assertNotEmpty($files, 'No pix/status image was found, so nothing was compared.');
        $offenders = [];
        foreach ($files as $path) {
            $name = 'status/' . basename($path, '.svg');
            $where = 'pix/' . $name . '.svg';
            if (!in_array($name, $masks, true)) {
                $offenders[] = $where . ' is the mask of no status icon';
                continue;
            }
            $svg = file_get_contents($path);
            if (!preg_match('/<svg\b[^>]*\sfill="none"/', $svg)) {
                $offenders[] = $where . ' leaves its root filled, so every shape without a fill of its own is solid';
            }
            preg_match_all('/(?<![\w-])(fill|stroke)\s*[=:]\s*["\']?([^"\';\s>]+)/i', $svg, $paints, PREG_SET_ORDER);
            $painted = 0;
            foreach ($paints as [, $property, $value]) {
                if (strcasecmp($value, 'none') === 0) {
                    continue;
                }
                if (strcasecmp($value, 'currentColor') !== 0) {
                    $offenders[] = $where . ' paints ' . $property . ' ' . $value . ', not currentColor';
                    continue;
                }
                $painted++;
            }
            if ($painted === 0) {
                $offenders[] = $where . ' paints nothing, so its mask shows nothing';
            }
        }
        $this->assertSame(
            [],
            $offenders,
            'A status image is a single-colour mask; the stylesheet paints it: ' . implode('; ', $offenders)
        );
    }
}
