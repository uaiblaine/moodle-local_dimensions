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

use local_dimensions\helper;

/**
 * The custom SCSS rules of styles.css reach the field core renders.
 *
 * The field is a textarea customfield, which core renders as an editor element named after the
 * field shortname. Every rule the stylesheet writes for it was once keyed to a shortname, an
 * element id or a field type that no longer rendered, so the format selector the hub modals hide
 * and the code-editor skin both applied to nothing. The field is rendered here the way the hub
 * modal forms render it, and each selector is matched against that markup.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\helper
 */
final class customscss_field_styles_test extends \advanced_testcase {
    /**
     * Render the custom SCSS field as the competency and template modal forms do.
     *
     * The markup is wrapped in the modal-form-dialogue element core_form/modalform opens it in.
     *
     * @return \DOMXPath Query object over the rendered markup.
     */
    private function rendered_field(): \DOMXPath {
        global $CFG, $PAGE;
        require_once($CFG->libdir . '/formslib.php');

        $PAGE->set_url('/local/dimensions/central.php');
        $PAGE->set_context(\context_system::instance());
        $field = helper::get_customscss_field(helper::AREA_LP);
        $this->assertNotNull($field, 'The custom SCSS field could not be provisioned.');
        $mform = new \MoodleQuickForm('local_dimensions_customscss', 'post', '');
        \core_customfield\data_controller::create(0, null, $field)->instance_form_definition($mform);
        helper::force_customscss_plain($mform);

        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?><div class="modal-form-dialogue">' . $mform->toHtml() . '</div>');
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new \DOMXPath($document);
    }

    /**
     * Every rule of styles.css whose selector names the custom SCSS field.
     *
     * @return array List of arrays with keys selector (whitespace collapsed) and body.
     */
    private function customscss_rules(): array {
        $css = preg_replace('~/\*.*?\*/~s', '', file_get_contents(dirname(__DIR__, 2) . '/styles.css'));
        $rules = [];
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $selector = trim(preg_replace('/\s+/', ' ', $match[1]));
            if (str_contains($selector, 'customscss')) {
                $rules[] = ['selector' => $selector, 'body' => $match[2]];
            }
        }
        return $rules;
    }

    /**
     * Split one complex selector into its compounds.
     *
     * @param string $selector One selector, without commas.
     * @return array List of [combinator, compound], the combinator being ' ' or '>'.
     */
    private function compounds(string $selector): array {
        $compounds = [];
        $current = '';
        $combinator = ' ';
        $quote = null;
        $depth = 0;
        foreach (str_split(trim($selector)) as $char) {
            if ($quote !== null) {
                $current .= $char;
                $quote = $char === $quote ? null : $quote;
                continue;
            }
            if ($char === '"' || $char === "'") {
                $quote = $char;
            } else if ($char === '[') {
                $depth++;
            } else if ($char === ']') {
                $depth--;
            }
            if ($depth === 0 && ($char === ' ' || $char === '>')) {
                if ($current !== '') {
                    $compounds[] = [$combinator, $current];
                    $current = '';
                    $combinator = ' ';
                }
                if ($char === '>') {
                    $combinator = '>';
                }
                continue;
            }
            $current .= $char;
        }
        if ($current !== '') {
            $compounds[] = [$combinator, $current];
        }
        return $compounds;
    }

    /**
     * Translate one compound selector into an XPath step.
     *
     * Supports a tag, ids, classes and attribute tests with =, ^=, $=, *= and ~=. Pseudo-classes
     * such as :focus are dropped, because the element they qualify is what has to exist.
     *
     * @param string $compound One compound selector.
     * @return string|null The XPath step, or null for syntax this translation does not cover.
     */
    private function step(string $compound): ?string {
        $simple = '#[\w-]+|\.[\w-]+|\[[\w-]+(?:[\^$*~]?=(?:"[^"]*"|\'[^\']*\'))?\]';
        if (!preg_match('/^([a-z][a-z0-9]*|\*)?((?:' . $simple . ')*)(?::{1,2}[a-z-]+)*$/i', $compound, $m)) {
            return null;
        }
        $predicates = [];
        preg_match_all(
            '/#([\w-]+)|\.([\w-]+)|\[([\w-]+)(?:([\^$*~]?=)(?:"([^"]*)"|\'([^\']*)\'))?\]/',
            $m[2],
            $parts,
            PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL
        );
        foreach ($parts as $part) {
            if ($part[1] !== null) {
                $predicates[] = "@id='" . $part[1] . "'";
                continue;
            }
            if ($part[2] !== null) {
                $predicates[] = "contains(concat(' ', normalize-space(@class), ' '), ' " . $part[2] . " ')";
                continue;
            }
            $attribute = '@' . $part[3];
            $value = $part[5] ?? $part[6] ?? '';
            $literal = str_contains($value, "'") ? '"' . $value . '"' : "'" . $value . "'";
            $predicates[] = match ($part[4]) {
                null => $attribute,
                '=' => $attribute . '=' . $literal,
                '^=' => 'starts-with(' . $attribute . ', ' . $literal . ')',
                '*=' => 'contains(' . $attribute . ', ' . $literal . ')',
                '$=' => 'substring(' . $attribute . ', string-length(' . $attribute . ') - string-length(' . $literal
                    . ') + 1) = ' . $literal,
                '~=' => "contains(concat(' ', normalize-space(" . $attribute . "), ' '), concat(' ', " . $literal . ", ' '))",
            };
        }
        $tag = ($m[1] ?? '') === '' ? '*' : strtolower($m[1]);
        return $tag . ($predicates ? '[' . implode(' and ', $predicates) . ']' : '');
    }

    /**
     * Translate one complex selector into an XPath query.
     *
     * @param string $selector One selector, without commas.
     * @return string|null The query, or null for syntax step() does not cover.
     */
    private function to_xpath(string $selector): ?string {
        $query = '';
        foreach ($this->compounds($selector) as [$combinator, $compound]) {
            $step = $this->step($compound);
            if ($step === null) {
                return null;
            }
            $query .= ($query !== '' && $combinator === '>' ? '/' : '//') . $step;
        }
        return $query === '' ? null : $query;
    }

    /**
     * Every custom SCSS selector in the stylesheet matches the field core renders.
     *
     * Changes that must make it fail: key the format selector's rule to the name the field had
     * before its shortname gained the plugin prefix (customfield_customscss_editor); add back a rule
     * for #fitem_id_customfield_customscss_editorformat, an id core never renders; key the skin to
     * data-fieldtype textarea.
     *
     * @return void
     */
    public function test_custom_scss_selectors_match_the_rendered_field(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $xpath = $this->rendered_field();
        $this->assertSame(
            1,
            $xpath->query('//textarea[contains(@name, "customscss")]')->length,
            'The rendered field carries no custom SCSS textarea.'
        );
        $this->assertSame(
            1,
            $xpath->query('//select[contains(@name, "customscss") and contains(@name, "[format]")]')->length,
            'With the format pinned to plain text the editor renders a format selector, which is what the '
                . 'stylesheet hides.'
        );
        $rules = $this->customscss_rules();
        $this->assertNotEmpty($rules, 'styles.css has no custom SCSS rule to check.');
        $offenders = [];
        foreach ($rules as $rule) {
            foreach (explode(',', $rule['selector']) as $selector) {
                $query = $this->to_xpath($selector);
                if ($query === null) {
                    $offenders[] = trim($selector) . ' (syntax this test cannot translate)';
                } else if ($xpath->query($query)->length === 0) {
                    $offenders[] = trim($selector) . ' (matches nothing core renders)';
                }
            }
        }
        $this->assertSame(
            [],
            $offenders,
            'These custom SCSS selectors match nothing in the field core renders: ' . implode('; ', $offenders)
        );
    }

    /**
     * The format selector the hub modals pin to plain text is hidden by a rule that reaches it.
     *
     * Change that must make it fail: delete the display: none rule for the format selector.
     *
     * @return void
     */
    public function test_the_pinned_format_selector_is_hidden(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $xpath = $this->rendered_field();
        $format = $xpath->query('//select[contains(@name, "customscss") and contains(@name, "[format]")]')->item(0);
        $this->assertNotNull($format, 'The rendered field carries no format selector.');
        $hidden = false;
        foreach ($this->customscss_rules() as $rule) {
            if (!preg_match('/(?:^|;)\s*display\s*:\s*none\s*(?:;|$)/', trim($rule['body']))) {
                continue;
            }
            foreach (explode(',', $rule['selector']) as $selector) {
                $query = $this->to_xpath($selector);
                if ($query === null) {
                    continue;
                }
                foreach ($xpath->query($query) as $node) {
                    $hidden = $hidden || $node->isSameNode($format);
                }
            }
        }
        $this->assertTrue($hidden, 'No display: none rule in styles.css reaches the rendered format selector.');
    }
}
