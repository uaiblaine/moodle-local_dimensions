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

/**
 * Lets another plugin add sections of its own to the Competency hub's panel, for the competency selected there.
 *
 * Dispatched by local_dimensions_get_competency_panel_sections once per selection, after the service has checked
 * that the viewer may read the competency in its framework's context. It carries, read-only, the competency, its
 * framework, that framework's context and whether the viewer manages competencies there; a listener still decides
 * by its own capabilities what the viewer may see, and adds nothing for a viewer who may see nothing.
 *
 * A section is data only: a plain title, up to MAX_ROWS rows of a plain label and value (optionally in one of the
 * tones of {@see competency_status_badges}), an optional plain note and at most one link. There is no HTML on
 * purpose: the panel belongs to local_dimensions, and plain text drawn as text keeps a listener from injecting
 * markup, script or styles into a page it does not own. Every text is decoded and stripped until it no longer
 * changes, so it survives PARAM_TEXT whatever formatstringstriptags says (a bare tag would otherwise fail the
 * whole response). The link is local only, a page of this site under $CFG->wwwroot, because the panel's reader
 * trusts it as the hub's own: an external, javascript: or protocol-relative address is dropped, never repaired.
 *
 * Bounds: MAX_SECTIONS sections per call (later ones are dropped), MAX_ROWS rows per section (later rows are
 * dropped) and a length per text (a longer text is cut and ends with an ellipsis). Zero-width and bidirectional
 * control characters are removed, and a text with nothing visible left counts as empty.
 *
 * Nothing a listener passes to add_section() throws: one listener must not blank every plugin's sections. A section
 * whose component is not one or whose key is malformed or taken is dropped, and a row's unknown tone is dropped (the
 * row stays, without a tone: its text carries the meaning), each with a developer debugging() message naming the
 * component and the reason. The component is a claim the hook cannot bind to the calling listener; what it does
 * enforce is that the first section under a `<component>-<key>` wins, so a later listener cannot replace it. A
 * listener that throws by itself is not caught: the dispatcher's read fails, and the panel shows its quiet line
 * instead of every section.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label("Lets plugins add sections to the Competency hub's panel for the selected competency")]
#[\core\attribute\tags('local_dimensions')]
final class competency_panel_sections {
    /** @var string Positive state: tone token success. */
    public const string TONE_SUCCESS = 'success';

    /** @var string Attention state: tone token warning. */
    public const string TONE_WARNING = 'warning';

    /** @var string Negative state: tone token danger. */
    public const string TONE_DANGER = 'danger';

    /** @var string Informational state: tone token info. */
    public const string TONE_INFO = 'info';

    /** @var string No state to judge: tone token neutral. */
    public const string TONE_NEUTRAL = 'neutral';

    /** @var int The most sections one call keeps, whoever adds them. */
    public const int MAX_SECTIONS = 6;

    /** @var int The most rows one section keeps. */
    public const int MAX_ROWS = 12;

    /** @var int The longest title, in characters. */
    public const int MAX_TITLE = 100;

    /** @var int The longest row label, in characters. */
    public const int MAX_LABEL = 100;

    /** @var int The longest row value, in characters. */
    public const int MAX_VALUE = 255;

    /** @var int The longest note, in characters. */
    public const int MAX_NOTE = 500;

    /** @var int The longest link label, in characters. */
    public const int MAX_LINKLABEL = 100;

    /** @var string What a section's key looks like before the hook prefixes it with the component. */
    public const string KEY_PATTERN = '/^[a-z][a-z0-9_]{0,29}$/';

    /** @var int How many decode-and-strip rounds a text may need before it is refused as never settling. */
    private const int PLAIN_ROUNDS = 8;

    /**
     * @var string The zero-width and bidirectional control characters removed from every text: U+200B to U+200F
     *     (zero-width space, non-joiner, joiner, left-to-right and right-to-left marks), U+202A to U+202E
     *     (embeddings and overrides), U+2060 to U+2064 (word joiner and invisible operators), U+2066 to U+2069
     *     (isolates) and U+FEFF. Not the whole of \p{Cf}: that would also take the soft hyphen, the Arabic number
     *     signs and letter mark, and the language tags out of legitimate text. The joiners go even though Persian
     *     and Indic words and emoji sequences use them: a label written without them stays readable.
     */
    private const string INVISIBLE = '/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2064}\x{2066}-\x{2069}\x{FEFF}]/u';

    /** @var array The sections kept, in the order added, keyed by their prefixed key. */
    private array $sections = [];

    /** @var array Every prefixed key added, kept or not, as a set (key => true). */
    private array $used = [];

    /**
     * Build the hook for one competency.
     *
     * @param int $competencyid The competency selected.
     * @param int $frameworkid Its framework, read from the competency's row.
     * @param \context $context The framework's context, where the viewer's rights are decided.
     * @param bool $canmanage Whether the viewer manages competencies in that context.
     */
    public function __construct(
        /** @var int The competency selected. */
        private readonly int $competencyid,
        /** @var int Its framework. */
        private readonly int $frameworkid,
        /** @var \context The framework's context. */
        private readonly \context $context,
        /** @var bool Whether the viewer manages competencies in the framework's context. */
        private readonly bool $canmanage,
    ) {
    }

    /**
     * The competency selected.
     *
     * @return int The competency id.
     */
    public function get_competencyid(): int {
        return $this->competencyid;
    }

    /**
     * The competency's framework.
     *
     * @return int The framework id.
     */
    public function get_frameworkid(): int {
        return $this->frameworkid;
    }

    /**
     * The framework's context (system or a course category): where a listener checks its own capabilities.
     *
     * @return \context
     */
    public function get_context(): \context {
        return $this->context;
    }

    /**
     * Whether the viewer manages competencies in the framework's context, as the hub decided it. A listener's
     * own rights are its own business: this says nothing of them.
     *
     * @return bool
     */
    public function can_manage(): bool {
        return $this->canmanage;
    }

    /**
     * Add a section under the panel's own content.
     *
     * @param string $component The adding plugin's frankenstyle name; the key is prefixed with it.
     * @param string $key The section's key within the component (KEY_PATTERN), unique per component.
     * @param string $title Plain text in the viewer's language; a section without a title is ignored.
     * @param array $rows List of ['label' => string, 'value' => string, 'tone' => one of tones() or ''], plain
     *     text; a row missing its label or value is skipped, and rows after MAX_ROWS are dropped.
     * @param string $note Plain text shown muted under the rows, or ''.
     * @param \moodle_url|string|null $link A page of this site: a moodle_url, a URL under $CFG->wwwroot or a
     *     root-relative path; anything else is dropped.
     * @param string $linklabel The link's plain label; a link without one is dropped.
     * @return void
     */
    public function add_section(
        string $component,
        string $key,
        string $title,
        array $rows,
        string $note = '',
        \moodle_url|string|null $link = null,
        string $linklabel = '',
    ): void {
        if (
            $component === ''
            || clean_param($component, PARAM_COMPONENT) !== $component
            || \core_component::get_component_directory($component) === null
        ) {
            self::refuse($component, $key, 'not a component of this site');
            return;
        }
        if (!preg_match(self::KEY_PATTERN, $key)) {
            self::refuse($component, $key, 'malformed key');
            return;
        }
        $fullkey = $component . '-' . $key;
        if (isset($this->used[$fullkey])) {
            self::refuse($component, $key, 'key already taken, the first section stays');
            return;
        }
        $this->used[$fullkey] = true;
        if (count($this->sections) >= self::MAX_SECTIONS) {
            return;
        }

        $title = self::plain($title, self::MAX_TITLE);
        if ($title === '') {
            return;
        }
        $kept = [];
        foreach ($rows as $row) {
            $label = self::plain((string) ($row['label'] ?? ''), self::MAX_LABEL);
            $value = self::plain((string) ($row['value'] ?? ''), self::MAX_VALUE);
            if ($label === '' || $value === '') {
                continue;
            }
            $tone = (string) ($row['tone'] ?? '');
            if ($tone !== '' && !in_array($tone, self::tones(), true)) {
                self::refuse($component, $key, 'unknown tone ' . $tone . ', the row is kept without it');
                $tone = '';
            }
            $kept[] = ['label' => $label, 'value' => $value, 'tone' => $tone];
            if (count($kept) === self::MAX_ROWS) {
                break;
            }
        }
        $note = self::plain($note, self::MAX_NOTE);
        $linklabel = self::plain($linklabel, self::MAX_LINKLABEL);
        $linkurl = $linklabel === '' ? '' : self::local_url($link);
        if ($linkurl === '') {
            $linklabel = '';
        }
        if (!$kept && $note === '' && $linkurl === '') {
            return;
        }

        $this->sections[$fullkey] = [
            'key' => $fullkey,
            'component' => $component,
            'title' => $title,
            'rows' => $kept,
            'note' => $note,
            'linklabel' => $linklabel,
            'linkurl' => $linkurl,
        ];
    }

    /**
     * Tell the developer why a listener's section or tone was dropped; the dispatch goes on.
     *
     * @param string $component The component the listener claimed.
     * @param string $key The key it gave.
     * @param string $reason What was refused.
     * @return void
     */
    private static function refuse(string $component, string $key, string $reason): void {
        debugging("competency_panel_sections: section '{$key}' of '{$component}' refused: {$reason}", DEBUG_DEVELOPER);
    }

    /**
     * The sections kept, in the order added.
     *
     * @return array List of ['key', 'component', 'title', 'rows' => list of ['label', 'value', 'tone'], 'note',
     *     'linklabel', 'linkurl'], every text plain and every URL local; empty when none.
     */
    public function get_sections(): array {
        return array_values($this->sections);
    }

    /**
     * The accepted tones, those of {@see competency_status_badges}.
     *
     * @return array The tone values.
     */
    public static function tones(): array {
        return [self::TONE_SUCCESS, self::TONE_WARNING, self::TONE_DANGER, self::TONE_INFO, self::TONE_NEUTRAL];
    }

    /**
     * A text as plain text that PARAM_TEXT leaves as it is, cut to a length.
     *
     * Entities are decoded and tags stripped, then PARAM_TEXT cleans it, until a round changes nothing: a text
     * escaped once or twice (format_string() with formatstringstriptags off escapes it) ends as the same plain
     * text, and a tag hidden behind an entity is stripped once decoded. Zero-width and bidirectional control
     * characters (INVISIBLE) are removed in every round, and a text with nothing left but format characters and
     * spaces is empty. A text that never settles is refused.
     *
     * @param string $text The text.
     * @param int $max The most characters kept; a longer text is cut and ends with an ellipsis.
     * @return string The plain text, '' when nothing is left.
     */
    public static function plain(string $text, int $max): string {
        $previous = null;
        for ($round = 0; $round < self::PLAIN_ROUNDS && $text !== $previous; $round++) {
            $previous = $text;
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = strip_tags($text);
            $text = (string) preg_replace(self::INVISIBLE, '', $text);
            $text = clean_param($text, PARAM_TEXT);
            $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        }
        if ($text !== $previous || preg_replace('/[\p{Cf}\s]+/u', '', $text) === '') {
            return '';
        }
        if (\core_text::strlen($text) > $max) {
            $text = rtrim(\core_text::substr($text, 0, $max - 1)) . '…';
        }
        return clean_param($text, PARAM_TEXT) === $text ? $text : '';
    }

    /**
     * A link's address when it is a page of this site, else ''.
     *
     * A string must be absolute under $CFG->wwwroot or root-relative ("/path", never "//host"): moodle_url
     * would read "//host" as a path of this site, and a link the listener did not mean is dropped, not repaired.
     * The address built must then be local by moodle_url's own test and survive PARAM_LOCALURL unchanged.
     *
     * @param \moodle_url|string|null $link The link.
     * @return string The absolute local address, unescaped, or ''.
     */
    public static function local_url(\moodle_url|string|null $link): string {
        global $CFG;
        if ($link === null || $link === '') {
            return '';
        }
        if (is_string($link)) {
            $absolute = $link === $CFG->wwwroot || str_starts_with($link, $CFG->wwwroot . '/');
            $rootrelative = str_starts_with($link, '/') && !str_starts_with($link, '//') && !str_starts_with($link, '/\\');
            if (!$absolute && !$rootrelative) {
                return '';
            }
            try {
                $link = new \moodle_url($link);
            } catch (\moodle_exception $e) {
                return '';
            }
        }
        $url = $link->out(false);
        if (!$link->is_local_url() || clean_param($url, PARAM_LOCALURL) !== $url) {
            return '';
        }
        return $url;
    }
}
