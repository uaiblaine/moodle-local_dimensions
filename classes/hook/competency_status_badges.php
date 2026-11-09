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
 * Lets another plugin add small status pills beside a competency on the plan overview and the tracker.
 *
 * Dispatched once per page by local_dimensions, with the learner whose competencies are shown (the plan
 * owner or the tracker's user, never the viewer) and the competency ids on the page. A listener decides
 * what the current user may see and adds a plain label and a tone. There is no URL and no HTML on
 * purpose: the plan overview's header is a button, where a link is invalid, and a plain label keeps a
 * listener from injecting markup into a page it does not own. The component adding a badge is not
 * recorded, since there is nothing to attribute.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label("Lets plugins add status badges beside competencies on the plan overview and the tracker")]
#[\core\attribute\tags('local_dimensions')]
final class competency_status_badges {
    /** @var string Positive state: tone token success. */
    public const TONE_SUCCESS = 'success';

    /** @var string Attention state: tone token warning. */
    public const TONE_WARNING = 'warning';

    /** @var string Negative state: tone token danger. */
    public const TONE_DANGER = 'danger';

    /** @var string Informational state: tone token info. */
    public const TONE_INFO = 'info';

    /** @var string No state to judge: tone token neutral. */
    public const TONE_NEUTRAL = 'neutral';

    /** @var array The badges added so far, competency id => list of ['label' => string, 'tone' => string]. */
    private array $badges = [];

    /** @var array The competency ids on the page, as a set (id => true). */
    private array $onpage = [];

    /**
     * Build the hook for one page.
     *
     * @param int $userid The learner whose competencies are shown (the owner, not the viewer).
     * @param array $competencyids The competency ids on the page, as ints.
     */
    public function __construct(
        /** @var int The learner whose competencies are shown. */
        private readonly int $userid,
        array $competencyids,
    ) {
        foreach ($competencyids as $competencyid) {
            $this->onpage[(int) $competencyid] = true;
        }
    }

    /**
     * The learner whose competencies are shown: the plan owner, never the viewer.
     *
     * @return int The user id.
     */
    public function get_userid(): int {
        return $this->userid;
    }

    /**
     * The competency ids on the page.
     *
     * @return array The ids, as ints.
     */
    public function get_competencyids(): array {
        return array_keys($this->onpage);
    }

    /**
     * Add a badge beside a competency.
     *
     * A competency id that is not on the page is ignored, and so is an empty label: a listener answering
     * from a cached list must not break the page.
     *
     * @param int $competencyid The competency id.
     * @param string $label Plain text, composed in the viewer's language; the template escapes it.
     * @param string $tone One of the TONE_* constants.
     * @throws \coding_exception When the tone is not one of the constants (it becomes a CSS class).
     */
    public function add_badge(int $competencyid, string $label, string $tone): void {
        if (!in_array($tone, self::tones(), true)) {
            throw new \coding_exception('Unknown status badge tone: ' . $tone);
        }
        if (!isset($this->onpage[$competencyid]) || trim($label) === '') {
            return;
        }
        $this->badges[$competencyid][] = ['label' => $label, 'tone' => $tone];
    }

    /**
     * The badges added for a competency, in the order added.
     *
     * @param int $competencyid The competency id.
     * @return array List of ['label' => string, 'tone' => string]; empty when none.
     */
    public function get_badges(int $competencyid): array {
        return $this->badges[$competencyid] ?? [];
    }

    /**
     * The accepted tones.
     *
     * @return array The tone values.
     */
    public static function tones(): array {
        return [self::TONE_SUCCESS, self::TONE_WARNING, self::TONE_DANGER, self::TONE_INFO, self::TONE_NEUTRAL];
    }
}
