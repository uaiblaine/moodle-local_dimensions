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
 * Attribute names of the dark-mode contract shared with block_dimensions.
 *
 * Constants only, with deliberately no is_dark()-style helper: whether the host page is dark is not
 * knowable on the server (Moodle 5.3 resolves it from a user preference, a cookie and a head script
 * reading matchMedia), and a wrong guess would paint the plugin dark on a light page.
 *
 * HOST_ATTRIBUTE is Bootstrap's colour-mode attribute, written by Moodle itself (theme_boost's
 * before_html_attributes listener and its head script). The plugin only reads it, from CSS:
 * {@see \local_dimensions\local\colour_tokens_test::test_plugin_never_writes_the_host_signal()}
 * fails if code in any other file names it, Behat steps apart. Those switch the dark layer on the
 * way production does: set HOST_ATTRIBUTE to DARK on document.documentElement.
 *
 * block_dimensions ships an identical class under its own namespace, with the same values.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class colour_mode {
    /** @var string Bootstrap's own colour-mode attribute, set by the host page on the html element. */
    public const HOST_ATTRIBUTE = 'data-bs-theme';

    /** @var string The value of HOST_ATTRIBUTE that activates the plugin's dark layer. */
    public const DARK = 'dark';

    /** @var string The value of HOST_ATTRIBUTE that pins light. */
    public const LIGHT = 'light';

    /** @var string Gate on the inert prefers-color-scheme block in styles.css; nothing writes it (tested). */
    public const MEDIA_OPTIN_ATTRIBUTE = 'data-dimensions-media-optin';
}
