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

namespace local_dimensions\hook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the competency_panel_sections hook object: what a listener may add, and what it cannot.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_dimensions\hook\competency_panel_sections::class)]
final class competency_panel_sections_test extends \advanced_testcase {
    /** @var array One row every section below carries, so it is kept whatever its link becomes. */
    private const ROW = ['label' => 'Kind', 'value' => 'Regulatory'];

    /**
     * A hook for competency 7 of framework 3, in the system context, for a manager.
     *
     * @return competency_panel_sections
     */
    private function hook(): competency_panel_sections {
        return new competency_panel_sections(7, 3, \context_system::instance(), true);
    }

    /**
     * The only section a hook kept, after one add.
     *
     * @param competency_panel_sections $hook The hook.
     * @return array The section.
     */
    private function only(competency_panel_sections $hook): array {
        $sections = $hook->get_sections();
        $this->assertCount(1, $sections);
        return $sections[0];
    }

    /**
     * The accessors return what the dispatcher decided.
     *
     * @return void
     */
    public function test_the_accessors_carry_the_dispatch_facts(): void {
        $context = \context_system::instance();
        $hook = new competency_panel_sections(7, 3, $context, false);
        $this->assertSame(7, $hook->get_competencyid());
        $this->assertSame(3, $hook->get_frameworkid());
        $this->assertSame($context, $hook->get_context());
        $this->assertFalse($hook->can_manage());
        $this->assertTrue($this->hook()->can_manage());
        $this->assertSame([], $hook->get_sections());
    }

    /**
     * Sections come back in the order added, each key prefixed with its component, every field present.
     *
     * @return void
     */
    public function test_sections_are_kept_in_order_with_their_component_prefix(): void {
        global $CFG;
        $hook = $this->hook();
        $first = [['label' => 'A', 'value' => 'B', 'tone' => 'success']];
        $hook->add_section('local_dimensions', 'first', 'First', $first, 'A note', '/local/dimensions/central.php', 'Open');
        $hook->add_section('tool_lp', 'first', 'Second', [self::ROW]);
        $this->assertSame([
            [
                'key' => 'local_dimensions-first',
                'component' => 'local_dimensions',
                'title' => 'First',
                'rows' => [['label' => 'A', 'value' => 'B', 'tone' => 'success']],
                'note' => 'A note',
                'linklabel' => 'Open',
                'linkurl' => $CFG->wwwroot . '/local/dimensions/central.php',
            ],
            [
                'key' => 'tool_lp-first',
                'component' => 'tool_lp',
                'title' => 'Second',
                'rows' => [['label' => 'Kind', 'value' => 'Regulatory', 'tone' => '']],
                'note' => '',
                'linklabel' => '',
                'linkurl' => '',
            ],
        ], $hook->get_sections());
    }

    /**
     * The debugging message a refusal leaves.
     *
     * @param string $component The component claimed.
     * @param string $key The key given.
     * @param string $reason The reason.
     * @return string
     */
    private static function refusal(string $component, string $key, string $reason): string {
        return "competency_panel_sections: section '{$key}' of '{$component}' refused: {$reason}";
    }

    /**
     * A second section under a taken key is dropped with a debugging message and the first one stays, so a later
     * listener cannot replace an earlier one's section; another component may use the same key (the control).
     *
     * @return void
     */
    public function test_one_component_cannot_add_two_sections_with_one_key(): void {
        $hook = $this->hook();
        $hook->add_section('local_dimensions', 'policy', 'One', [self::ROW]);
        $hook->add_section('tool_lp', 'policy', 'Other', [self::ROW]);
        $this->assertCount(2, $hook->get_sections());
        $hook->add_section('local_dimensions', 'policy', 'Two', [self::ROW]);
        $this->assertDebuggingCalled(self::refusal('local_dimensions', 'policy', 'key already taken, the first section stays'));
        $this->assertSame(['One', 'Other'], array_column($hook->get_sections(), 'title'));
    }

    /**
     * Names that are not components of this site.
     *
     * @return array Case => [name].
     */
    public static function not_components(): array {
        return [
            'empty' => [''],
            'words' => ['not a component'],
            'missing plugin' => ['local_nosuchpluginhere'],
            'capitals' => ['Local_Dimensions'],
            'path' => ['../local_dimensions'],
        ];
    }

    /**
     * A section under a name that is not an installed component is dropped with a debugging message, and the
     * dispatch goes on: the plugin's own section before it stays (the control), and one after it is still kept.
     *
     * @param string $component The name.
     * @return void
     */
    #[DataProvider('not_components')]
    public function test_a_name_that_is_not_a_component_is_refused(string $component): void {
        $hook = $this->hook();
        $hook->add_section('local_dimensions', 'control', 'Control', [self::ROW]);
        $hook->add_section($component, 'policy', 'Title', [self::ROW]);
        $this->assertDebuggingCalled(self::refusal($component, 'policy', 'not a component of this site'));
        $hook->add_section('tool_lp', 'after', 'After', [self::ROW]);
        $this->assertSame(['local_dimensions-control', 'tool_lp-after'], array_column($hook->get_sections(), 'key'));
    }

    /**
     * Keys that break KEY_PATTERN.
     *
     * @return array Case => [key].
     */
    public static function malformed_keys(): array {
        return [
            'empty' => [''],
            'capital' => ['Policy'],
            'dash' => ['has-dash'],
            'digit first' => ['1policy'],
            'space' => ['a b'],
            'too long' => [str_repeat('a', 31)],
        ];
    }

    /**
     * A section with a malformed key is dropped with a debugging message; a well-formed key of the longest length
     * is accepted (the control).
     *
     * @param string $key The key.
     * @return void
     */
    #[DataProvider('malformed_keys')]
    public function test_a_malformed_key_is_refused(string $key): void {
        $hook = $this->hook();
        $hook->add_section('local_dimensions', str_repeat('a', 30), 'Control', [self::ROW]);
        $hook->add_section('local_dimensions', $key, 'Title', [self::ROW]);
        $this->assertDebuggingCalled(self::refusal('local_dimensions', $key, 'malformed key'));
        $this->assertSame(['Control'], array_column($hook->get_sections(), 'title'));
    }

    /**
     * Every documented tone and none are kept; any other is dropped with a debugging message, since it becomes a CSS
     * class, and its row stays without a tone, its text still saying what the tone would have.
     *
     * @return void
     */
    public function test_an_unknown_tone_is_refused(): void {
        $hook = $this->hook();
        $rows = [['label' => 'None', 'value' => 'x', 'tone' => '']];
        foreach (competency_panel_sections::tones() as $tone) {
            $rows[] = ['label' => $tone, 'value' => 'x', 'tone' => $tone];
        }
        $rows[] = ['label' => 'Odd', 'value' => 'Due', 'tone' => 'primary'];
        $hook->add_section('local_dimensions', 'tones', 'Tones', $rows);
        $reason = 'unknown tone primary, the row is kept without it';
        $this->assertDebuggingCalled(self::refusal('local_dimensions', 'tones', $reason));
        $kept = $this->only($hook)['rows'];
        $this->assertSame(['', 'success', 'warning', 'danger', 'info', 'neutral', ''], array_column($kept, 'tone'));
        $this->assertSame(['label' => 'Odd', 'value' => 'Due', 'tone' => ''], end($kept));
    }

    /**
     * Sections past MAX_SECTIONS are dropped, whoever adds them; those within it are kept in order.
     *
     * @return void
     */
    public function test_sections_beyond_the_bound_are_dropped(): void {
        $hook = $this->hook();
        for ($i = 1; $i <= competency_panel_sections::MAX_SECTIONS + 2; $i++) {
            $hook->add_section('local_dimensions', 's' . $i, 'Section ' . $i, [self::ROW]);
        }
        $titles = array_column($hook->get_sections(), 'title');
        $this->assertCount(competency_panel_sections::MAX_SECTIONS, $titles);
        $this->assertSame('Section 1', $titles[0]);
        $this->assertSame('Section ' . competency_panel_sections::MAX_SECTIONS, end($titles));
    }

    /**
     * Rows past MAX_ROWS are dropped, and a row without a label or a value is skipped without counting.
     *
     * @return void
     */
    public function test_rows_beyond_the_bound_are_dropped(): void {
        $rows = [['label' => '', 'value' => 'no label'], ['label' => 'no value', 'value' => '']];
        for ($i = 1; $i <= competency_panel_sections::MAX_ROWS + 3; $i++) {
            $rows[] = ['label' => 'Row ' . $i, 'value' => (string) $i];
        }
        $hook = $this->hook();
        $hook->add_section('local_dimensions', 'rows', 'Rows', $rows);
        $kept = array_column($this->only($hook)['rows'], 'label');
        $this->assertCount(competency_panel_sections::MAX_ROWS, $kept);
        $this->assertSame('Row 1', $kept[0]);
        $this->assertSame('Row ' . competency_panel_sections::MAX_ROWS, end($kept));
    }

    /**
     * Every text longer than its bound is cut to it and ends with an ellipsis; a text at its bound is whole.
     *
     * @return void
     */
    public function test_texts_are_cut_to_their_bound(): void {
        $long = static fn(int $max): string => str_repeat('x', $max + 20);
        $hook = $this->hook();
        $hook->add_section(
            'local_dimensions',
            'long',
            $long(competency_panel_sections::MAX_TITLE),
            [['label' => $long(competency_panel_sections::MAX_LABEL), 'value' => $long(competency_panel_sections::MAX_VALUE)]],
            $long(competency_panel_sections::MAX_NOTE),
            '/local/dimensions/central.php',
            $long(competency_panel_sections::MAX_LINKLABEL)
        );
        $section = $this->only($hook);
        $cases = [
            [$section['title'], competency_panel_sections::MAX_TITLE],
            [$section['rows'][0]['label'], competency_panel_sections::MAX_LABEL],
            [$section['rows'][0]['value'], competency_panel_sections::MAX_VALUE],
            [$section['note'], competency_panel_sections::MAX_NOTE],
            [$section['linklabel'], competency_panel_sections::MAX_LINKLABEL],
        ];
        foreach ($cases as [$text, $max]) {
            $this->assertSame($max, \core_text::strlen($text));
            $this->assertStringEndsWith('…', $text);
        }
        $this->assertSame(str_repeat('y', 100), competency_panel_sections::plain(str_repeat('y', 100), 100));
    }

    /**
     * Texts as a listener may pass them, and the plain text each must become.
     *
     * @return array Case => [text, plain].
     */
    public static function marked_up_texts(): array {
        return [
            'tags' => ['<b>Bold</b> &amp; co', 'Bold & co'],
            'escaped script' => ['&lt;script&gt;alert(1)&lt;/script&gt;Safe', 'alert(1)Safe'],
            'escaped twice' => ['&amp;lt;img src=x onerror=alert(1)&amp;gt;Done', 'Done'],
            'multilang span' => ['<span lang="en" class="multilang">One</span> two', 'One two'],
            'unclosed tag' => ['Keep <b this', 'Keep'],
            'whitespace' => ["Line\n   two\t", 'Line two'],
            'a bare comparison' => ['3 < 4', '3 < 4'],
        ];
    }

    /**
     * No markup survives in any text, and each text is one PARAM_TEXT leaves as it is.
     *
     * @param string $text The text passed.
     * @param string $plain The plain text expected.
     * @return void
     */
    #[DataProvider('marked_up_texts')]
    public function test_markup_never_survives(string $text, string $plain): void {
        $hook = $this->hook();
        $hook->add_section('local_dimensions', 'text', $text, [['label' => $text, 'value' => $text]], $text);
        $section = $this->only($hook);
        foreach ([$section['title'], $section['rows'][0]['label'], $section['rows'][0]['value'], $section['note']] as $value) {
            $this->assertSame($plain, $value);
            $this->assertSame($value, clean_param($value, PARAM_TEXT));
        }
    }

    /**
     * A title of a zero-width space alone, of bidirectional controls alone or of soft hyphens alone has nothing
     * visible: the section is dropped as untitled; a control inside a real title is removed and the rest kept.
     *
     * @return void
     */
    public function test_invisible_text_is_empty(): void {
        $hook = $this->hook();
        $hook->add_section('local_dimensions', 'zwsp', "\u{200B}", [self::ROW]);
        $hook->add_section('local_dimensions', 'bidi', "\u{202E}\u{2066}\u{FEFF} ", [self::ROW]);
        $hook->add_section('local_dimensions', 'shy', "\u{00AD}\u{00AD}", [self::ROW]);
        $hook->add_section('local_dimensions', 'label', 'Label', [['label' => "\u{200B}", 'value' => 'Hidden label']]);
        $hook->add_section('local_dimensions', 'link', 'Link', [self::ROW], '', '/local/dimensions/central.php', "\u{2060}");
        $hook->add_section('local_dimensions', 'spoof', "\u{202E}Policy", [self::ROW]);
        $hook->add_section('local_dimensions', 'entity', '&#x200B;Kept', [self::ROW]);
        $sections = $hook->get_sections();
        $keys = ['local_dimensions-link', 'local_dimensions-spoof', 'local_dimensions-entity'];
        $this->assertSame($keys, array_column($sections, 'key'));
        $this->assertSame('', $sections[0]['linkurl']);
        $this->assertSame('', $sections[0]['linklabel']);
        $this->assertSame('Policy', $sections[1]['title']);
        $this->assertSame('Kept', $sections[2]['title']);
    }

    /**
     * Right-to-left text in Hebrew and Arabic letters, and a soft hyphen inside a word, are kept intact.
     *
     * @return void
     */
    public function test_right_to_left_text_is_kept_intact(): void {
        $hebrew = 'מדיניות תוקף';
        $arabic = 'سياسة الصلاحية';
        $hook = $this->hook();
        $rows = [['label' => $arabic, 'value' => $hebrew]];
        $hook->add_section('local_dimensions', 'rtl', $hebrew, $rows, '', '/local/dimensions/central.php', $arabic);
        $section = $this->only($hook);
        $this->assertSame($hebrew, $section['title']);
        $this->assertSame([['label' => $arabic, 'value' => $hebrew, 'tone' => '']], $section['rows']);
        $this->assertSame($arabic, $section['linklabel']);
        $this->assertSame("Vali\u{00AD}dity", competency_panel_sections::plain("Vali\u{00AD}dity", 100));
    }

    /**
     * Both values of formatstringstriptags.
     *
     * @return array Case => [value].
     */
    public static function striptags_settings(): array {
        return ['strip tags' => [1], 'keep tags' => [0]];
    }

    /**
     * A title run through format_string() ends as the same plain text whatever formatstringstriptags says: escaped
     * with the tags stripped, or escaped with the tags kept.
     *
     * @param int $striptags The setting's value.
     * @return void
     */
    #[DataProvider('striptags_settings')]
    public function test_text_is_plain_whatever_formatstringstriptags_says(int $striptags): void {
        $this->resetAfterTest();
        set_config('formatstringstriptags', $striptags);
        // The setting's value is in the text, so format_string()'s own cache cannot answer for the other case.
        $formatted = format_string('<em>Due</em> & soon ' . $striptags, true, ['context' => \context_system::instance()]);
        $this->assertStringContainsString('&amp;', $formatted);
        $hook = $this->hook();
        $hook->add_section('local_dimensions', 'formatted', $formatted, [['label' => $formatted, 'value' => $formatted]]);
        $section = $this->only($hook);
        $this->assertSame('Due & soon ' . $striptags, $section['title']);
        $this->assertSame('Due & soon ' . $striptags, $section['rows'][0]['value']);
    }

    /**
     * Links as strings, and the address each must become; {wwwroot} stands for $CFG->wwwroot.
     *
     * @return array Case => [link, expected address or ''].
     */
    public static function string_links(): array {
        return [
            'external' => ['https://evil.example.org/x', ''],
            'javascript' => ['javascript:alert(1)', ''],
            'protocol-relative' => ['//evil.example.org/x', ''],
            'backslash' => ['/\\evil.example.org/x', ''],
            'look-alike root' => ['{wwwroot}.evil.example.org/x', ''],
            'relative' => ['local/x.php', ''],
            'local path' => [
                '/local/evidencehub/policies.php?frameworkid=3',
                '{wwwroot}/local/evidencehub/policies.php?frameworkid=3',
            ],
            'full local url' => ['{wwwroot}/local/evidencehub/policies.php', '{wwwroot}/local/evidencehub/policies.php'],
        ];
    }

    /**
     * A string link is kept only as a page of this site; anything else is dropped with its label, never repaired.
     *
     * @param string $link The link passed.
     * @param string $expected The address kept, or ''.
     * @return void
     */
    #[DataProvider('string_links')]
    public function test_the_link_is_local_or_dropped(string $link, string $expected): void {
        global $CFG;
        $link = str_replace('{wwwroot}', $CFG->wwwroot, $link);
        $expected = str_replace('{wwwroot}', $CFG->wwwroot, $expected);
        $hook = $this->hook();
        $hook->add_section('local_dimensions', 'link', 'Link', [self::ROW], '', $link, 'Edit policy');
        $section = $this->only($hook);
        $this->assertSame($expected, $section['linkurl']);
        $this->assertSame($expected === '' ? '' : 'Edit policy', $section['linklabel']);
    }

    /**
     * A moodle_url is kept when it is a page of this site: an external one, a relative one and one whose path
     * repaired a protocol-relative address are dropped.
     *
     * @return void
     */
    public function test_a_link_object_is_local_or_dropped(): void {
        $local = new \moodle_url('/local/evidencehub/policies.php', ['frameworkid' => 3, 'q' => 'a b']);
        $cases = [
            [$local, $local->out(false)],
            [new \moodle_url('https://evil.example.org/x'), ''],
            [new \moodle_url('local/x.php'), ''],
            [new \moodle_url('//evil.example.org/x'), ''],
        ];
        foreach ($cases as $index => [$link, $expected]) {
            $hook = $this->hook();
            $hook->add_section('local_dimensions', 'link', 'Link', [self::ROW], '', $link, 'Open');
            $this->assertSame($expected, $this->only($hook)['linkurl'], "Case {$index}");
        }
    }

    /**
     * A link without a label is dropped; the same link with one is kept (the control).
     *
     * @return void
     */
    public function test_a_link_without_a_label_is_dropped(): void {
        $hook = $this->hook();
        $hook->add_section('local_dimensions', 'nolabel', 'No label', [self::ROW], '', '/local/dimensions/central.php', '  ');
        $hook->add_section('local_dimensions', 'label', 'Label', [self::ROW], '', '/local/dimensions/central.php', 'Open');
        [$without, $with] = $hook->get_sections();
        $this->assertSame('', $without['linkurl']);
        $this->assertSame('', $without['linklabel']);
        $this->assertNotSame('', $with['linkurl']);
    }

    /**
     * A section without a title, or with nothing to show, is ignored; a note alone is something to show.
     *
     * @return void
     */
    public function test_empty_sections_are_ignored(): void {
        $hook = $this->hook();
        $hook->add_section('local_dimensions', 'notitle', '<b></b>', [self::ROW]);
        $empty = [['label' => '', 'value' => '']];
        $hook->add_section('local_dimensions', 'nothing', 'Nothing', $empty, '', 'https://evil.example.org', 'X');
        $hook->add_section('local_dimensions', 'noteonly', 'Note only', [], 'Only a note');
        $this->assertSame(['local_dimensions-noteonly'], array_column($hook->get_sections(), 'key'));
    }
}
