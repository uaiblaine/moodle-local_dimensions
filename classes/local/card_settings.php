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

/**
 * The reader of Boost Union's course overview settings for the plan view's course cards.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

/**
 * The one reader of the three Boost Union settings that decide a course card (the course card contract, section 8).
 *
 * The settings apply while the request's theme is Boost Union or names it among its parents, and Boost Union is
 * installed; under any other theme every answer is the plugin's own rule: the progress row drawn, the cover drawn,
 * and a course with no image left to the cover fallback token, with no generated image asked for. The same rules as
 * block_compass' card_settings and theme_dimensions' boost_union reader, copied rather than called: the plugin works
 * under any theme (contract D1).
 *
 * An instance holds whether the settings apply, decided once from the theme ({@see of_request()}); the readers then
 * read get_config(), which the config cache serves without a database read. Every value is compared with literals
 * equal to Boost Union's constants (THEME_BOOST_UNION_SETTING_* in its lib.php), which exist only once that file is
 * loaded. A missing value reads as Boost Union's default: its settings page writes one on install, and an upgrade
 * that has not run yet must not hide a card's progress or its cover.
 *
 * The plan view has one card surface, the "Related content" carousel, which is a card view: the list and summary
 * views of Boost Union's image setting change nothing here (D15).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class card_settings {
    /** @var string Boost Union's component, the owner of the three settings. */
    public const string COMPONENT = 'theme_boost_union';

    /** @var string Boost Union's theme name, as the request's theme or one of its parents spells it. */
    public const string THEME = 'boost_union';

    /** @var string Whether the course progress is shown: {@see YES} or 'no'. */
    public const string SETTING_PROGRESS = 'courseoverviewshowcourseprogress';

    /** @var string Where the image of a course with none comes from: {@see SOURCE_PATTERN} or {@see SOURCE_FALLBACK}. */
    public const string SETTING_IMAGESOURCE = 'courseoverviewimagesource';

    /** @var string In which views the course image is shown: a comma list of {@see VIEWS}, '' for none. */
    public const string SETTING_IMAGES = 'courseoverviewshowcourseimages';

    /** @var string Boost Union's "yes" (THEME_BOOST_UNION_SETTING_SELECT_YES in its lib.php). */
    public const string YES = 'yes';

    /** @var string The course image, else the generated pattern: Boost Union's default. */
    public const string SOURCE_PATTERN = 'coursepluspattern';

    /** @var string The course image, else Boost Union's fallback image. */
    public const string SOURCE_FALLBACK = 'courseplusfallback';

    /** @var string The card view: the plan carousel's cover. */
    public const string VIEW_CARD = 'card';

    /** @var string The list view: no plan surface is one. */
    public const string VIEW_LIST = 'list';

    /** @var string The summary view: no plan surface is one (D15). */
    public const string VIEW_SUMMARY = 'summary';

    /** @var string[] The views of {@see SETTING_IMAGES}, all three on by default. */
    public const array VIEWS = [self::VIEW_CARD, self::VIEW_LIST, self::VIEW_SUMMARY];

    /**
     * The settings of one request.
     *
     * @param bool $active Whether Boost Union's settings decide the cards ({@see applies()}).
     */
    public function __construct(
        /** @var bool Whether Boost Union's settings decide the cards. */
        private readonly bool $active,
    ) {
    }

    /**
     * The settings for the theme a page draws in.
     *
     * @param object|null $theme The page's theme (a theme_config: name and parents); the request's ($PAGE->theme) by
     *     default. In a web service it is read after validate_context(), which sets the theme the request draws in.
     * @return self
     */
    public static function of_request(?object $theme = null): self {
        global $PAGE;

        $theme ??= $PAGE->theme;

        return new self(self::applies($theme, \core_component::get_component_directory(self::COMPONENT) !== null));
    }

    /**
     * Whether Boost Union's settings decide the cards of a theme.
     *
     * @param object $theme A theme: its name, and its parents when it has any.
     * @param bool $installed Whether Boost Union is installed: a child theme left behind without it reads nothing.
     * @return bool True when the theme is Boost Union or a child of it, and Boost Union is installed.
     */
    public static function applies(object $theme, bool $installed): bool {
        $chain = array_merge([(string) ($theme->name ?? '')], array_map('strval', (array) ($theme->parents ?? [])));

        return $installed && in_array(self::THEME, $chain, true);
    }

    /**
     * Whether Boost Union's settings decide the cards of this request.
     *
     * @return bool
     */
    public function active(): bool {
        return $this->active;
    }

    /**
     * Whether the cards draw the progress row and its percentage.
     *
     * Only the row: the completed seal and the green base stay whatever this says (D13).
     *
     * @return bool False only when Boost Union applies and its setting says anything but "yes".
     */
    public function show_progress(): bool {
        if (!$this->active) {
            return true;
        }
        $value = get_config(self::COMPONENT, self::SETTING_PROGRESS);

        return $value === false || $value === self::YES;
    }

    /**
     * Where the image of a course with none of its own comes from, as Boost Union's renderer reads it.
     *
     * The plugin asks the renderer whichever it is ({@see course_card::generated_images()}); the answer decides only
     * whether one renderer answer can serve the whole response.
     *
     * @return string {@see SOURCE_FALLBACK} when Boost Union applies and it is chosen, {@see SOURCE_PATTERN}
     *     otherwise: missing, empty or a value Boost Union does not know.
     */
    public function image_source(): string {
        if (!$this->active) {
            return self::SOURCE_PATTERN;
        }
        $value = get_config(self::COMPONENT, self::SETTING_IMAGESOURCE);

        return $value === self::SOURCE_FALLBACK ? self::SOURCE_FALLBACK : self::SOURCE_PATTERN;
    }

    /**
     * Whether the course image is drawn in one view.
     *
     * The setting is a multi-checkbox: missing means all three views (Boost Union's default), while an empty value
     * is an administrator who unchecked every box, and shows none.
     *
     * @param string $view One of {@see VIEWS}.
     * @return bool
     * @throws \coding_exception For a view outside {@see VIEWS}.
     */
    public function show_images(string $view): bool {
        if (!in_array($view, self::VIEWS, true)) {
            throw new \coding_exception('Unknown course overview view: ' . $view);
        }
        if (!$this->active) {
            return true;
        }
        $value = get_config(self::COMPONENT, self::SETTING_IMAGES);
        if ($value === false) {
            return true;
        }

        return in_array($view, array_map('trim', explode(',', (string) $value)), true);
    }

    /**
     * What the plan's cards need, as get_competency_courses ships it on every course row.
     *
     * @return array progress (the progress row and its percentage), cover (the card draws a cover) and generated (a
     *     course with no image gets the theme renderer's: Boost Union applies and the card view still draws a
     *     cover), each a bool.
     */
    public function flags(): array {
        $cover = $this->show_images(self::VIEW_CARD);

        return [
            'progress' => $this->show_progress(),
            'cover' => $cover,
            'generated' => $this->active && $cover,
        ];
    }
}
