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
 * The Competency hub's two modes: the workspace (the default) and the classic hub.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

use local_dimensions\constants;
use local_dimensions\helper;

/**
 * Which tabs and views the hub offers: the workspace by default, the classic hub (the Structures tab and
 * the classic list of the Competencies tab) for one release behind the context bar's link.
 *
 * The mode is a display choice, never access: it decides which tabs the strip offers and which views the
 * Competencies tab's switch draws, and nothing else. Every tab is still admitted by its own is_available()
 * and require_access(), through central.php and through core's dynamic-tabs web service alike, so a tab
 * left out of the strip is neither opened nor closed to anybody.
 *
 * The choice is the `classic` member of the hub's display preference. The address may name it
 * (`classic=1` or `classic=0`) for the visit; it is saved only when the request carries the session
 * key, as the context bar's link does, so a link from elsewhere never changes what the viewer keeps.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hub_mode {
    /** @var string[] The strip in classic mode, in order. */
    public const array CLASSIC_TABS = ['frameworks', 'structure', 'plans'];

    /** @var string[] The strip in the workspace mode, in order: the Structures tab lives on the root node. */
    public const array WORKSPACE_TABS = ['structure', 'plans'];

    /**
     * The viewer's saved choice, as the hub's preference sanitiser reads it.
     *
     * @return bool Whether classic mode is saved; false when nothing is.
     */
    public static function saved(): bool {
        return (bool) helper::get_central_prefs()['display']['classic'];
    }

    /**
     * The mode for a visit: the address's value when it names one (0 or 1), else the saved choice.
     *
     * @param int|null $requested The address's `classic` value, null when it names none.
     * @param bool $saved The saved choice.
     * @return bool Whether the visit is in classic mode.
     */
    public static function resolve(?int $requested, bool $saved): bool {
        if ($requested === 0 || $requested === 1) {
            return $requested === 1;
        }
        return $saved;
    }

    /**
     * The mode for this request, saving the address's value when the request carries the session key.
     *
     * A value named without the key (a link from elsewhere, a hand-typed address) holds for the visit only.
     * Nothing is written when the address names no value or the value is already the saved one.
     *
     * @param int|null $requested The address's `classic` value, null when it names none.
     * @param string $sesskey The request's session key, '' when it carries none.
     * @return bool Whether the visit is in classic mode.
     */
    public static function from_request(?int $requested, string $sesskey): bool {
        $saved = self::saved();
        $classic = self::resolve($requested, $saved);
        if (($requested === 0 || $requested === 1) && $classic !== $saved && $sesskey !== '' && confirm_sesskey($sesskey)) {
            self::save($classic);
        }
        return $classic;
    }

    /**
     * Save the choice in the display preference, keeping every other member as it is stored.
     *
     * @param bool $classic The choice.
     * @return void
     */
    public static function save(bool $classic): void {
        $raw = json_decode((string) get_user_preferences(constants::PREF_CENTRAL_DISPLAY, ''), true);
        $display = is_array($raw) ? $raw : [];
        $display['classic'] = $classic;
        set_user_preference(constants::PREF_CENTRAL_DISPLAY, json_encode($display));
    }

    /**
     * Clean a write of the display preference from the client: the `classic` member is always the stored one, so
     * save() is its one writer and a page left open in the other mode cannot write its mode back over a choice
     * saved since. core_user_set_user_preferences stores what this returns; core's REST route would refuse the
     * write instead ({@see \local_dimensions\output\hub_map::clean_display()}).
     *
     * The preference is writable by its owner only (local_dimensions_user_preferences()), so the stored value read
     * here is the writer's own.
     *
     * @param mixed $value The value the client sent (JSON), or null.
     * @param string $name The preference's name.
     * @return string|null The value to store.
     */
    public static function clean_display($value, string $name): ?string {
        if ($value === null) {
            return null;
        }
        $decoded = json_decode((string) $value, true);
        $display = is_array($decoded) ? $decoded : [];
        unset($display['classic']);
        if (self::saved()) {
            $display['classic'] = true;
        }
        return (string) json_encode($display);
    }

    /**
     * The tabs the strip offers, in order.
     *
     * @param bool $classic Whether the visit is in classic mode.
     * @return string[] Tab shortnames.
     */
    public static function tabs(bool $classic): array {
        return $classic ? self::CLASSIC_TABS : self::WORKSPACE_TABS;
    }

    /**
     * The tab a request names, as the strip offers it: one the strip has, the Structures tab as Competencies
     * outside classic mode (the framework's actions are on the workspace's root), anything else the first.
     *
     * Availability is decided after this, by helper::pick_available_tab() over the strip.
     *
     * @param string $requested The tab the address or the saved navigation names.
     * @param bool $classic Whether the visit is in classic mode.
     * @return string A shortname of tabs($classic).
     */
    public static function requested_tab(string $requested, bool $classic): string {
        $tabs = self::tabs($classic);
        if (in_array($requested, $tabs, true)) {
            return $requested;
        }
        if ($requested === 'frameworks') {
            return 'structure';
        }
        return $tabs[0];
    }

    /**
     * The strip: each offered tab with whether the viewer may open it, in the strip's order.
     *
     * @param array $instances Every tab, keyed by shortname (\core\output\dynamic_tabs\base values).
     * @param bool $classic Whether the visit is in classic mode.
     * @return array Shortname to availability (bool), only for the tabs the strip offers.
     */
    public static function strip(array $instances, bool $classic): array {
        $strip = [];
        foreach (self::tabs($classic) as $shortname) {
            if (isset($instances[$shortname])) {
                $strip[$shortname] = (bool) $instances[$shortname]->is_available();
            }
        }
        return $strip;
    }

    /**
     * The context bar's link to the other mode: the Competencies tab, in the same context and on the same
     * framework, with the session key, so the choice is saved.
     *
     * The page keeps the link in step with a framework or context chosen after it rendered
     * (amd/src/central/context.js); without JavaScript it is the place the page rendered.
     *
     * @param \context $context The page's context (the category on the locked entry, else the system).
     * @param bool $locked Whether the page is the locked category entry, which names its context itself.
     * @param bool $classic The mode of this visit; the link names the other one.
     * @param string $contexttype The resolved context type ('system' or 'coursecat').
     * @param int $categoryid The resolved category, 0 for none.
     * @param int $frameworkid The framework shown, 0 for none.
     * @return \moodle_url The link.
     */
    public static function switch_url(
        \context $context,
        bool $locked,
        bool $classic,
        string $contexttype,
        int $categoryid,
        int $frameworkid
    ): \moodle_url {
        $params = ['tab' => 'structure'];
        if (!$locked) {
            $params['contexttype'] = $contexttype === 'coursecat' ? 'coursecat' : 'system';
            $params['categoryid'] = max(0, $categoryid);
        }
        if ($frameworkid > 0) {
            $params['frameworkid'] = $frameworkid;
        }
        $params['classic'] = $classic ? 0 : 1;
        $params['sesskey'] = sesskey();
        return helper::hub_page_url($locked ? $context : \context_system::instance(), $params);
    }

    /**
     * The link's words: the mode it leads to.
     *
     * @param bool $classic The mode of this visit.
     * @return string The label.
     */
    public static function switch_label(bool $classic): string {
        if ($classic) {
            return get_string('central_view_workspace', 'local_dimensions');
        }
        return get_string('central_view_classic', 'local_dimensions');
    }
}
