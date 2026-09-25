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
 * Contracts between the templates and the stylesheet that no linter reads.
 *
 * The mustache lint validates each template as a document on its own and stylelint reads CSS
 * syntax, so neither sees a rule styling markup that no longer renders, a control with no
 * accessible name, a status region that cannot be announced, or a divider whose touch drag the
 * browser takes over. Each test names the change that must make it fail.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class stylesheet_markup_contract_test extends \basic_testcase {
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
            /* The docblocks carry example markup and prose about attributes; only rendered markup counts. */
            $contents = preg_replace('/\{\{!.*?\}\}/s', '', file_get_contents($file->getPathname()));
            $templates[$file->getPathname()] = $contents;
        }
        ksort($templates);
        return $templates;
    }

    /**
     * The rules of styles.css, with comments removed and at-rule wrappers unwrapped.
     *
     * @return array List of arrays with keys selector (whitespace collapsed), body and nested
     *               (true inside an at-rule such as a media query).
     */
    private function rules(): array {
        $css = preg_replace('~/\*.*?\*/~s', '', file_get_contents($this->plugin_root() . '/styles.css'));
        $rules = [];
        $stack = [];
        $start = 0;
        $length = strlen($css);
        for ($i = 0; $i < $length; $i++) {
            if ($css[$i] === '{') {
                $stack[] = [trim(substr($css, $start, $i - $start)), $i];
                $start = $i + 1;
            } else if ($css[$i] === '}') {
                if ($stack) {
                    [$selector, $open] = array_pop($stack);
                    $body = substr($css, $open + 1, $i - $open - 1);
                    if (!str_contains($body, '{')) {
                        $rules[] = [
                            'selector' => trim(preg_replace('/\s+/', ' ', $selector)),
                            'body' => $body,
                            'nested' => count($stack) > 0,
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
     * @return array Lower-case property => trimmed value.
     */
    private function declarations(string $body): array {
        $found = [];
        foreach (explode(';', $body) as $declaration) {
            if (!str_contains($declaration, ':')) {
                continue;
            }
            [$property, $value] = explode(':', $declaration, 2);
            $found[strtolower(trim($property))] = trim($value);
        }
        return $found;
    }

    /**
     * Every opening tag in a template.
     *
     * @param string $markup Template markup.
     * @return array List of arrays with keys tag (lower-case name), attributes (raw text), offset.
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
                'offset' => $match[0][1],
                'end' => $match[0][1] + strlen($match[0][0]),
            ];
        }
        return $tags;
    }

    /**
     * The value of one attribute in a tag's raw attribute text.
     *
     * @param string $attributes Raw attribute text, as returned by tags().
     * @param string $name Attribute name.
     * @return string|null The value, or null when the attribute is absent.
     */
    private function attribute(string $attributes, string $name): ?string {
        if (preg_match('/(?<![\w-])' . preg_quote($name, '/') . '="([^"]*)"/', $attributes, $m)) {
            return $m[1];
        }
        if (preg_match('/(?<![\w-])' . preg_quote($name, '/') . '(?![\w="-])/', $attributes)) {
            return '';
        }
        return null;
    }

    /**
     * The markup inside the first element carrying a class, up to its matching closing tag.
     *
     * @param string $markup Template markup.
     * @param string $class Class token the element carries.
     * @return string The inner markup, or '' when no such element exists.
     */
    private function inner_markup(string $markup, string $class): string {
        foreach ($this->tags($markup) as $tag) {
            $classes = preg_split('/\s+/', (string) $this->attribute($tag['attributes'], 'class'));
            if (!in_array($class, $classes, true)) {
                continue;
            }
            $depth = 1;
            $pattern = '~<(/?)' . preg_quote($tag['tag'], '~') . '\b[^>]*>~';
            preg_match_all($pattern, $markup, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE, $tag['end']);
            foreach ($matches as $match) {
                $depth += $match[1][0] === '/' ? -1 : 1;
                if ($depth === 0) {
                    return substr($markup, $tag['end'], $match[0][1] - $tag['end']);
                }
            }
        }
        return '';
    }

    /**
     * A timeline marker rule may style only markup the marker renders.
     *
     * The marker holds the status icons and the progress ring. Rules for Font Awesome glyphs inside
     * it outlived the markup that used them, and a rule that matches nothing is a rule nobody can
     * safely edit.
     *
     * Change that must make it fail: add a .local-dimensions-timeline-marker .fa-lock rule.
     *
     * @return void
     */
    public function test_timeline_marker_rules_style_only_its_rendered_markup(): void {
        $container = 'local-dimensions-timeline-marker';
        $template = $this->templates()[$this->plugin_root() . '/templates/progress_card_body.mustache'];
        $inner = $this->inner_markup($template, $container);
        $this->assertStringContainsString(
            'local-dimensions-progress-ring-container',
            $inner,
            'The timeline marker was not found in progress_card_body.mustache, so nothing would be compared.'
        );
        preg_match_all('/class="([^"]*)"/', $inner, $matches);
        $rendered = preg_split('/\s+/', implode(' ', $matches[1]), -1, PREG_SPLIT_NO_EMPTY);
        $offenders = [];
        foreach ($this->rules() as $rule) {
            foreach (explode(',', $rule['selector']) as $part) {
                $position = strpos($part, '.' . $container . ' ');
                if ($position === false) {
                    continue;
                }
                $descendants = substr($part, $position + strlen($container) + 2);
                preg_match_all('/\.([a-z][a-z0-9_-]*)/i', $descendants, $classes);
                foreach ($classes[1] as $class) {
                    if (!in_array($class, $rendered, true)) {
                        $offenders[] = trim($part) . ' (' . $class . ' is not rendered inside the marker)';
                    }
                }
            }
        }
        $this->assertSame(
            [],
            $offenders,
            'These rules style markup the timeline marker no longer renders; delete them: '
                . implode('; ', $offenders)
        );
    }

    /**
     * Every plugin class a rule styles is written by a template, a script or a PHP file.
     *
     * A rule whose class nothing writes styles nothing and passes every gate: the colour for file
     * evidence and the competency path separator both outlived their only writers. A class counts
     * as written when it appears as a whole token in a template (outside its comments), an AMD
     * source module or a PHP file, or when a class attribute or className assignment composes it
     * from a literal prefix and a value, as local-dimensions-pill- is followed by a tone. A prefix
     * built into an id or an aria attribute does not count, since it names no class.
     *
     * Change that must make it fail: add a .local-dimensions-path-sep rule back to styles.css.
     *
     * @return void
     */
    public function test_every_class_a_rule_styles_is_written(): void {
        $styled = [];
        foreach ($this->rules() as $rule) {
            preg_match_all('/\.(local-dimensions-[a-z0-9_-]+)/i', $rule['selector'], $matches);
            $styled = array_merge($styled, $matches[1]);
        }
        $styled = array_values(array_unique($styled));
        $this->assertContains(
            'local-dimensions-timeline-marker',
            $styled,
            'The stylesheet rules were not read, so nothing would be compared.'
        );

        $root = $this->plugin_root();
        $sources = array_values($this->templates());
        $paths = glob($root . '/*.php') ?: [];
        foreach (['amd/src', 'classes', 'db'] as $relative) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $relative));
            foreach ($iterator as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['js', 'php'], true)) {
                    $paths[] = $file->getPathname();
                }
            }
        }
        foreach ($paths as $path) {
            $sources[] = file_get_contents($path);
        }
        $written = [];
        $prefixes = [];
        foreach ($sources as $source) {
            foreach (explode("\n", $source) as $line) {
                preg_match_all('/(?<![\w-])(local-dimensions-[\w-]*[a-z0-9])(?![\w-])/i', $line, $matches);
                $written = array_merge($written, $matches[1]);
                preg_match_all('/(local-dimensions-[\w-]*-)(?=\$\{|[\'"]\s*\+|\{\{)/i', $line, $matches, PREG_OFFSET_CAPTURE);
                foreach ($matches[1] as [$prefix, $offset]) {
                    preg_match_all(
                        '/class(?:Name|List)?|(?<![\w-])id|aria-[a-z]+|(?<![\w-])for|href|data-[a-z-]+/',
                        substr($line, 0, $offset),
                        $attributes
                    );
                    $last = end($attributes[0]);
                    if ($last !== false && str_starts_with($last, 'class')) {
                        $prefixes[] = $prefix;
                    }
                }
            }
        }
        $written = array_flip($written);
        $prefixes = array_unique($prefixes);

        $offenders = [];
        foreach ($styled as $class) {
            if (isset($written[$class])) {
                continue;
            }
            foreach ($prefixes as $prefix) {
                if (str_starts_with($class, $prefix)) {
                    continue 2;
                }
            }
            $offenders[] = '.' . $class;
        }
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'Nothing in the plugin writes these classes, so the rules styling them style nothing; delete them: '
                . implode(' ', $offenders)
        );
    }

    /**
     * An export loader is announced while it shows and can be hidden when it does not.
     *
     * role=status with a visually hidden label is what makes the wait audible; the spinner itself
     * is aria-hidden. A d-* display utility is display !important and comes after Bootstrap's own
     * hidden-attribute rule, so the hidden attribute stops working.
     *
     * Change that must make it fail: put class="d-inline-flex" back on the frameworks export loader,
     * or drop its role or its label.
     *
     * @return void
     */
    public function test_export_loaders_announce_the_wait_and_can_hide(): void {
        $offenders = [];
        $found = 0;
        foreach ($this->templates() as $path => $markup) {
            foreach ($this->tags($markup) as $tag) {
                if ($this->attribute($tag['attributes'], 'data-region') !== 'export-loader') {
                    continue;
                }
                $found++;
                $where = basename($path);
                if ($this->attribute($tag['attributes'], 'role') !== 'status') {
                    $offenders[] = $where . ' has no role="status"';
                }
                $classes = preg_split(
                    '/\s+/',
                    (string) $this->attribute($tag['attributes'], 'class'),
                    -1,
                    PREG_SPLIT_NO_EMPTY
                );
                foreach ($classes as $class) {
                    if (preg_match('/^d-/', $class)) {
                        $offenders[] = $where . ' carries ' . $class . ', which outranks the hidden attribute';
                    }
                }
                $close = (int) strpos($markup, '</' . $tag['tag'] . '>', $tag['end']);
                $inner = substr($markup, $tag['end'], max(0, $close - $tag['end']));
                if (!preg_match('/class="visually-hidden">\s*\{\{#str\}\}/', $inner)) {
                    $offenders[] = $where . ' has no visually hidden label';
                }
            }
        }
        $this->assertGreaterThanOrEqual(2, $found, 'The plans and frameworks export loaders were not found.');
        $this->assertSame(
            [],
            $offenders,
            'An export loader must be a labelled status region that the hidden attribute can hide: '
                . implode('; ', $offenders)
        );
    }

    /**
     * Templates carry no HTML comments.
     *
     * An HTML comment ships to every browser that renders the template and goes stale unseen, as
     * one naming the progress ring's colours did once the colours became tokens. A Mustache comment
     * says the same thing to the reader of the template and never leaves the server.
     *
     * Change that must make it fail: put an HTML comment in any template.
     *
     * @return void
     */
    public function test_templates_ship_no_html_comments(): void {
        $offenders = [];
        foreach ($this->templates() as $path => $markup) {
            foreach (explode("\n", $markup) as $line) {
                if (str_contains($line, '<!--')) {
                    $offenders[] = basename($path) . ': ' . trim($line);
                }
            }
        }
        $this->assertSame(
            [],
            $offenders,
            'Use a Mustache comment instead of an HTML comment: ' . implode('; ', $offenders)
        );
    }

    /**
     * Every form control a template renders has an accessible name.
     *
     * A name comes from aria-label, from aria-labelledby naming ids the same template renders, from
     * a label element whose for attribute is the control's id, or from a wrapping label. A control
     * without one is announced as its role alone, as the per-competency points and required inputs
     * of the rule modal were.
     *
     * Change that must make it fail: drop the label of the points input in rule_config.mustache, or
     * the id its aria-labelledby names.
     *
     * @return void
     */
    public function test_template_form_controls_have_accessible_names(): void {
        /* The admin setting frame (format_admin_setting) renders this one's label around the template. */
        $labelledelsewhere = ['setting_iconpicker.mustache'];
        $offenders = [];
        $checked = 0;
        foreach ($this->templates() as $path => $markup) {
            if (in_array(basename($path), $labelledelsewhere, true)) {
                continue;
            }
            $tags = $this->tags($markup);
            $fors = [];
            $ids = [];
            foreach ($tags as $tag) {
                if ($tag['tag'] === 'label' && $this->attribute($tag['attributes'], 'for') !== null) {
                    $fors[] = $this->attribute($tag['attributes'], 'for');
                }
                if ($this->attribute($tag['attributes'], 'id') !== null) {
                    $ids[] = $this->attribute($tag['attributes'], 'id');
                }
            }
            foreach ($tags as $tag) {
                if (!in_array($tag['tag'], ['input', 'select', 'textarea'], true)) {
                    continue;
                }
                $type = $this->attribute($tag['attributes'], 'type');
                if (in_array($type, ['hidden', 'submit', 'button', 'reset', 'image'], true)) {
                    continue;
                }
                /* A hidden textarea is a data carrier (the custom CSS source), not a control. */
                if ($this->attribute($tag['attributes'], 'hidden') !== null) {
                    continue;
                }
                $checked++;
                if ($this->attribute($tag['attributes'], 'aria-label') !== null) {
                    continue;
                }
                $labelledby = $this->attribute($tag['attributes'], 'aria-labelledby');
                if ($labelledby !== null) {
                    $missing = array_diff(preg_split('/\s+/', trim($labelledby)), $ids);
                    if ($missing) {
                        $offenders[] = basename($path) . ': ' . $tag['tag'] . ' names missing ids '
                            . implode(' ', $missing);
                    }
                    continue;
                }
                $id = $this->attribute($tag['attributes'], 'id');
                if ($id !== null && in_array($id, $fors, true)) {
                    continue;
                }
                $before = substr($markup, 0, $tag['offset']);
                if ((int) strrpos($before, '<label') > (int) strrpos($before, '</label>')) {
                    continue;
                }
                $name = $this->attribute($tag['attributes'], 'name') ?? $id ?? '(unnamed)';
                $offenders[] = basename($path) . ': ' . $tag['tag'] . ' ' . $name;
            }
        }
        $this->assertGreaterThan(0, $checked, 'No form control was found in the templates.');
        $this->assertSame(
            [],
            $offenders,
            'These form controls have no accessible name, so a screen reader announces only their role: '
                . implode('; ', $offenders)
        );
    }

    /**
     * A pane divider the pointer drags must refuse touch panning.
     *
     * Without touch-action: none a touch drag on the divider can be claimed by the browser as a
     * page pan, which cancels the pointer mid-resize.
     *
     * Change that must make it fail: delete touch-action from .local-dimensions-central-plans-resizer.
     *
     * @return void
     */
    public function test_pane_dividers_take_touch_drags(): void {
        $dividers = [];
        foreach ($this->templates() as $path => $markup) {
            foreach ($this->tags($markup) as $tag) {
                if ($this->attribute($tag['attributes'], 'role') !== 'separator') {
                    continue;
                }
                $class = preg_split('/\s+/', trim((string) $this->attribute($tag['attributes'], 'class')))[0];
                $dividers[$class][] = basename($path);
            }
        }
        $this->assertNotEmpty($dividers, 'No role="separator" divider was found in the templates.');
        $offenders = [];
        foreach ($dividers as $class => $paths) {
            $touchaction = null;
            foreach ($this->rules() as $rule) {
                if ($rule['nested'] || $rule['selector'] !== '.' . $class) {
                    continue;
                }
                $touchaction = $this->declarations($rule['body'])['touch-action'] ?? $touchaction;
            }
            if ($touchaction !== 'none') {
                $offenders[] = '.' . $class . ' (' . implode(', ', array_unique($paths)) . ')';
            }
        }
        $this->assertSame(
            [],
            $offenders,
            'These dividers are dragged with pointer events but let touch pan the page: '
                . implode('; ', $offenders)
        );
    }
}
