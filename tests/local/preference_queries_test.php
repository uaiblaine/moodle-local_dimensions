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
 * The preference media queries of styles.css match, and their overrides take effect.
 *
 * A query naming a value its feature does not define is valid CSS that the browser evaluates as
 * false, and an override that loses on specificity is valid CSS that never applies. stylelint
 * reads syntax, so it passes both. block_dimensions pins the same rules in its card_layout_test;
 * the cascade helpers here are that file's, and a fix to one belongs in both.
 *
 * Each test names the change that must make it fail. When editing a test, apply that change and
 * confirm it does: a test that still passes against it certifies nothing.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\local\colour_mode
 */
final class preference_queries_test extends \basic_testcase {
    /**
     * @var array Media feature => the values Media Queries Level 5 defines for it.
     *
     * A value outside the list is not an error to the browser, which evaluates the query as false,
     * so a block written against it never applies.
     */
    private const MEDIA_FEATURE_VALUES = [
        'prefers-contrast' => ['no-preference', 'more', 'less', 'custom'],
        'prefers-reduced-motion' => ['no-preference', 'reduce'],
        'prefers-color-scheme' => ['light', 'dark'],
        'forced-colors' => ['none', 'active'],
    ];

    /** @var string Pattern matching the at-rule preludes whose rules are compared as overrides. */
    private const CONTRAST_PRELUDE = '/prefers-contrast/';

    /** @var string Pattern matching every conditional prelude whose rules are never a base rule. */
    private const CONDITIONAL_PRELUDE = '/prefers-|forced-colors|(?<![\w-])print(?![\w-])/';

    /** @var string Pattern matching a user-action pseudo-class, which puts a selector in a state. */
    private const STATE_PSEUDO = '/:(?:hover|active|focus(?:-visible|-within)?)(?![\w-])/';

    /**
     * @var array Custom properties carrying a colour the admin chose for a branded island.
     *
     * Keep in step with colour_tokens_test::ADMIN_COLOUR_NAMES, which lists the same transport.
     */
    private const ADMIN_COLOURS = [
        '--dimension-custombgcolor',
        '--dimension-customtextcolor',
        '--hero-overlay-color',
        '--local-dimensions-fab-color',
    ];

    /**
     * Read the plugin stylesheet with its comments blanked, line numbers preserved.
     *
     * Comments are removed before any rule matching, or a comment naming a selector is read as
     * part of the selector of the rule below it.
     *
     * @return string
     */
    protected function styles(): string {
        $css = (string) file_get_contents(__DIR__ . '/../../styles.css');

        return (string) preg_replace_callback('~/\*.*?\*/~s', static function (array $m): string {
            return str_repeat("\n", substr_count($m[0], "\n"));
        }, $css);
    }

    /**
     * Every style rule of the stylesheet, at-rule wrappers unwrapped, in source order.
     *
     * @return array List of arrays with keys line (where the selector starts), selector
     *               (whitespace collapsed), body and at (the enclosing at-rule preludes).
     */
    protected function flat_rules(): array {
        $css = $this->styles();
        $rules = [];
        $stack = [];
        $start = 0;
        $length = strlen($css);
        for ($i = 0; $i < $length; $i++) {
            if ($css[$i] === '{') {
                $prelude = substr($css, $start, $i - $start);
                $line = substr_count(substr($css, 0, $start), "\n") + substr_count($prelude, "\n")
                    - substr_count(ltrim($prelude), "\n") + 1;
                $stack[] = [trim(preg_replace('/\s+/', ' ', $prelude)), $i, $line];
                $start = $i + 1;
            } else if ($css[$i] === '}') {
                if ($stack) {
                    [$selector, $open, $line] = array_pop($stack);
                    $body = substr($css, $open + 1, $i - $open - 1);
                    if (!str_contains($body, '{')) {
                        $rules[] = [
                            'line' => $line,
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
     * @return array Lower-case property name => value, whitespace collapsed.
     */
    protected function declarations(string $body): array {
        $found = [];
        foreach (explode(';', $body) as $declaration) {
            if (!str_contains($declaration, ':')) {
                continue;
            }
            [$property, $value] = explode(':', $declaration, 2);
            $property = strtolower(trim($property));
            if ($property !== '') {
                $found[$property] = trim(preg_replace('/\s+/', ' ', $value));
            }
        }

        return $found;
    }

    /**
     * Every preference media query asks for a value its feature defines.
     *
     * The browser reads an undefined value as false, so a block written against one is valid CSS
     * that never applies. prefers-contrast: high is the case this exists for: it was an early draft
     * value no engine shipped, and the feature's raised-contrast value is more.
     *
     * Change that must make it fail: write (prefers-contrast: high) in any block.
     *
     * @return void
     */
    public function test_preference_queries_use_defined_values(): void {
        $offenders = [];
        $checked = [];
        preg_match_all('/@media\s*([^{]+)\{/', $this->styles(), $preludes);
        foreach ($preludes[1] as $prelude) {
            preg_match_all('/\(\s*([a-z-]+)\s*:\s*([a-z-]+)\s*\)/', $prelude, $features, PREG_SET_ORDER);
            foreach ($features as [, $feature, $value]) {
                if (!isset(self::MEDIA_FEATURE_VALUES[$feature])) {
                    continue;
                }
                $checked[$feature] = true;
                if (!in_array($value, self::MEDIA_FEATURE_VALUES[$feature], true)) {
                    $offenders[] = '(' . $feature . ': ' . $value . ')';
                }
            }
        }

        $this->assertArrayHasKey(
            'prefers-contrast',
            $checked,
            'No prefers-contrast query was found, so the stylesheet has no raised-contrast styles to check.'
        );
        $this->assertSame(
            [],
            $offenders,
            'These media queries name a value their feature does not define, so they never match: '
                . implode(', ', array_unique($offenders))
        );
    }

    /**
     * The style rules the cascade tests compare, keyframes left out.
     *
     * @return array flat_rules() entries with these keys added: order (source position), override
     *               (inside a prefers-contrast block), base (inside no conditional preference or
     *               print block), parts (the selector list) and families (from families()).
     */
    protected function cascade_rules(): array {
        $rules = [];
        foreach ($this->flat_rules() as $order => $rule) {
            if (str_contains($rule['at'], '@keyframes')) {
                continue;
            }
            $rule['order'] = $order;
            $rule['override'] = (bool) preg_match(self::CONTRAST_PRELUDE, $rule['at']);
            $rule['base'] = !preg_match(self::CONDITIONAL_PRELUDE, $rule['at']);
            $rule['parts'] = array_map('trim', explode(',', $rule['selector']));
            $rule['families'] = $this->families($this->declarations($rule['body']));
            $rules[] = $rule;
        }

        return $rules;
    }

    /**
     * Each pairing of a prefers-contrast override with a base rule it competes with.
     *
     * A pair is a base rule (keyframes aside) that sets the same property family to a different
     * value on the same subject compound (one set of simple selectors containing the other) in the
     * same user-action state. Base rules in another state are left alone: a hover that changes a
     * border under the preference is a design choice, not a lost override.
     *
     * @return array List of arrays with keys override and base (cascade_rules() entries), family,
     *               basepart and rivals (the overlapping override parts).
     */
    protected function contrast_pairs(): array {
        $rules = $this->cascade_rules();
        $pairs = [];
        foreach ($rules as $override) {
            if (!$override['override']) {
                continue;
            }
            foreach ($rules as $base) {
                if (!$base['base']) {
                    continue;
                }
                foreach ($override['families'] as $family => $values) {
                    if (!isset($base['families'][$family]) || $base['families'][$family] === $values) {
                        continue;
                    }
                    foreach ($base['parts'] as $basepart) {
                        $rivals = $this->overlapping_parts($override['parts'], $basepart);
                        if ($rivals) {
                            $pairs[] = [
                                'override' => $override,
                                'base' => $base,
                                'family' => $family,
                                'basepart' => $basepart,
                                'rivals' => $rivals,
                            ];
                        }
                    }
                }
            }
        }

        return $pairs;
    }

    /**
     * Whether one selector part beats another in the cascade.
     *
     * @param string $part The selector part that should win.
     * @param int $order Its rule's source position.
     * @param string $rivalpart The selector part it competes with.
     * @param int $rivalorder That rule's source position.
     * @return bool True when $part is more specific, or equally specific and later.
     */
    private function outranks(string $part, int $order, string $rivalpart, int $rivalorder): bool {
        $rank = $this->specificity($part) <=> $this->specificity($rivalpart);

        return $rank > 0 || ($rank === 0 && $order > $rivalorder);
    }

    /**
     * Whether the overlapping parts of one override rule win against a base selector part.
     *
     * A rival whose subject compound names no simple selector the base part lacks matches every
     * element the base part matches, so when such broad rivals exist one of them has to win. A
     * narrower rival matches only some of those elements, so it cannot settle the pair for the
     * rest; without a broad rival, every narrow one has to win on the elements it does match.
     *
     * @param array $rivals Parts of the override rule, from overlapping_parts().
     * @param int $order The override rule's source position.
     * @param string $basepart The base selector part.
     * @param int $baseorder The base rule's source position.
     * @return bool True when the override applies wherever it competes with the base part.
     */
    private function rivals_win(array $rivals, int $order, string $basepart, int $baseorder): bool {
        $basetokens = $this->subject($basepart)[2];
        $broad = [];
        $narrow = [];
        foreach ($rivals as $part) {
            $tokens = $this->subject($part)[2];
            if (array_diff($tokens, $basetokens)) {
                $narrow[] = $part;
            } else {
                $broad[] = $part;
            }
        }
        foreach ($broad as $part) {
            if ($this->outranks($part, $order, $basepart, $baseorder)) {
                return true;
            }
        }
        if ($broad) {
            return false;
        }
        foreach ($narrow as $part) {
            if (!$this->outranks($part, $order, $basepart, $baseorder)) {
                return false;
            }
        }

        return (bool) $narrow;
    }

    /**
     * A rule in a prefers-contrast block wins over the base rule it overrides.
     *
     * Such a block is written after the rule it overrides and relies on source order, which only
     * decides between equal specificities: a base rule written under an extra ancestor class
     * outranks it wherever it comes. Only prefers-contrast overrides are compared. The
     * forced-colors block restates outline parts that the base focus rules already set to the same
     * effect, and the subject comparison cannot tell two button rules on different components
     * apart, so widening the scope needs both handled first.
     *
     * Changes that must make it fail: write the raised-contrast readout rule as
     * .local-dimensions-progress-text; drop :not(:last-child) from the evidence section divider.
     *
     * @return void
     */
    public function test_contrast_overrides_are_not_outranked(): void {
        $offenders = [];
        $overrides = 0;
        foreach ($this->cascade_rules() as $rule) {
            $overrides += (int) $rule['override'];
        }
        $pairs = $this->contrast_pairs();
        foreach ($pairs as $pair) {
            $override = $pair['override'];
            $base = $pair['base'];
            if (!$this->rivals_win($pair['rivals'], $override['order'], $pair['basepart'], $base['order'])) {
                $offenders[] = 'styles.css:' . $override['line'] . ' (' . implode(', ', $pair['rivals']) . ') loses '
                    . $pair['family'] . ' to styles.css:' . $base['line'] . ' (' . $pair['basepart'] . ')';
            }
        }

        $this->assertGreaterThan(0, $overrides, 'No prefers-contrast rule was found, so this test checks nothing.');
        $this->assertNotEmpty($pairs, 'No override was compared with a base rule, so this test checks nothing.');
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'A prefers-contrast override that loses on specificity never applies: ' . implode('; ', $offenders)
        );
    }

    /**
     * A prefers-contrast override keeps every admin colour its base rule reads.
     *
     * On a branded island the admin chose the ground and the ink as a pair, so the only ink that is
     * known to contrast with the ground is the admin's own. An override that drops the admin's
     * property in favour of a fixed ink raises the contrast of the fallback and can wreck it for
     * every site that set a colour: white forced over a light hero the admin paired with dark text.
     *
     * Change that must make it fail: write the hero's raised-contrast colour as
     * var(--local-dimensions-on-brand-fill) alone.
     *
     * @return void
     */
    public function test_contrast_overrides_keep_the_admin_colour(): void {
        $offenders = [];
        $reached = 0;
        foreach ($this->contrast_pairs() as $pair) {
            $basevalue = implode(' ', $pair['base']['families'][$pair['family']]);
            $overridevalue = implode(' ', $pair['override']['families'][$pair['family']]);
            foreach (self::ADMIN_COLOURS as $name) {
                if (!str_contains($basevalue, $name)) {
                    continue;
                }
                $reached++;
                if (!str_contains($overridevalue, $name)) {
                    $offenders[] = 'styles.css:' . $pair['override']['line'] . ' (' . implode(', ', $pair['rivals'])
                        . ') drops ' . $name . ', which styles.css:' . $pair['base']['line'] . ' reads';
                }
            }
        }

        $this->assertGreaterThan(
            0,
            $reached,
            'No prefers-contrast override meets a base rule reading an admin colour, so this test checks nothing.'
        );
        sort($offenders);
        $this->assertSame(
            [],
            array_values(array_unique($offenders)),
            'A raised-contrast ink that replaces the admin\'s own is chosen against a ground it cannot see: '
                . implode('; ', array_unique($offenders))
        );
    }

    /**
     * Group declarations by the shorthand family their property belongs to.
     *
     * border-color and border both set the border colour, so they compete; border-radius is not
     * part of the border shorthand and stands alone.
     *
     * @param array $declarations Property => value, from declarations().
     * @return array Family => (property => value), properties sorted.
     */
    private function families(array $declarations): array {
        $families = [];
        foreach ($declarations as $property => $value) {
            $family = $property;
            if (preg_match('/^(border|outline|background|transition|animation)(?:-|$)/', $property, $m)) {
                $family = $m[1];
            }
            if (in_array($property, ['border-radius', 'border-collapse', 'border-spacing', 'outline-offset'], true)) {
                $family = $property;
            }
            $families[$family][$property] = $value;
        }
        foreach ($families as $family => $values) {
            ksort($values);
            $families[$family] = $values;
        }

        return $families;
    }

    /**
     * The parts of an override selector that target the same elements as a base selector part.
     *
     * Two parts overlap when they are in the same user-action state, name the same pseudo-element,
     * and the simple selectors of one subject compound include those of the other.
     *
     * @param array $parts The override rule's selector parts.
     * @param string $basepart One selector part of the base rule.
     * @return array The overlapping override parts.
     */
    private function overlapping_parts(array $parts, string $basepart): array {
        [$basestate, $baseelement, $basetokens] = $this->subject($basepart);
        $overlapping = [];
        foreach ($parts as $part) {
            [$state, $element, $tokens] = $this->subject($part);
            if ($state !== $basestate || $element !== $baseelement) {
                continue;
            }
            if (!array_diff($tokens, $basetokens) || !array_diff($basetokens, $tokens)) {
                $overlapping[] = $part;
            }
        }

        return $overlapping;
    }

    /**
     * Describe the element a selector part targets.
     *
     * @param string $part One selector part, e.g. ".local-dimensions-return-fab:hover".
     * @return array [state, pseudo-element, simple selectors]: the sorted user-action pseudo-classes
     *               anywhere in the part, the subject's pseudo-element (or ''), and the sorted
     *               simple selectors of the subject compound with both of those removed.
     */
    private function subject(string $part): array {
        preg_match_all(self::STATE_PSEUDO, $part, $states);
        $state = array_unique($states[0]);
        sort($state);
        $compound = preg_split('/\s*[>+~]\s*|\s+/', preg_replace('/\s+/', ' ', trim($part)));
        $compound = (string) end($compound);
        $compound = (string) preg_replace(self::STATE_PSEUDO, '', $compound);
        $element = preg_match('/::[\w-]+/', $compound, $m) ? $m[0] : '';
        $compound = str_replace($element, '', $compound);
        preg_match_all('/[a-z][\w-]*|[#.][\w-]+|\[[^\]]*\]|:[\w-]+(?:\([^()]*\))?/i', $compound, $simple);
        $tokens = array_values(array_unique($simple[0]));
        sort($tokens);

        return [implode('', $state), $element, $tokens];
    }

    /**
     * Selector specificity, for the selectors this stylesheet writes.
     *
     * :not(), :is() and :has() count their argument, :where() counts nothing, and an attribute
     * selector counts as a class.
     *
     * @param string $part One selector part.
     * @return array [ids, classes, types].
     */
    private function specificity(string $part): array {
        $ids = 0;
        $classes = 0;
        $types = 0;
        while (preg_match('/:(?:not|is|has)\(([^()]*)\)/', $part, $m, PREG_OFFSET_CAPTURE)) {
            [$innerids, $innerclasses, $innertypes] = $this->specificity($m[1][0]);
            $ids += $innerids;
            $classes += $innerclasses;
            $types += $innertypes;
            $part = substr_replace($part, ' ', $m[0][1], strlen($m[0][0]));
        }
        $part = (string) preg_replace('/:where\([^()]*\)/', ' ', $part);
        $classes += preg_match_all('/\[[^\]]*\]/', $part);
        $part = (string) preg_replace('/\[[^\]]*\]/', ' ', $part);
        $ids += preg_match_all('/#[\w-]+/', $part);
        $classes += preg_match_all('/\.[\w-]+/', $part);
        $classes += preg_match_all('/(?<!:):[\w-]+/', $part);
        $types += preg_match_all('/::[\w-]+/', $part);
        $types += preg_match_all('/(?:^|[\s>+~])[a-z][\w-]*/i', $part);

        return [$ids, $classes, $types];
    }
}
