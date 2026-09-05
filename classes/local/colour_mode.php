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
 * Attribute names in the family's dark-mode activation contract.
 *
 * Constants only. This class deliberately exposes no is_dark()-style helper: whether the host page
 * is dark is not server-knowable - core's own 5.3 mechanism resolves it from a per-user preference,
 * a cookie and a synchronous head script reading matchMedia - so any PHP guess would eventually be
 * wrong, and a wrong guess is the exact defect this design exists to prevent: the plugin dark while
 * the page is light.
 *
 * HOST_ATTRIBUTE is Bootstrap 5.3's own colour-mode attribute and is what Moodle itself writes,
 * from theme_boost's before_html_attributes listener and from the head script that resolves "auto"
 * with window.matchMedia. The plugin only ever READS it, from CSS; colour_tokens_test's
 * test_plugin_never_writes_the_host_signal fails the build if any shipped file writes it, and only
 * Behat may name it, because a scenario exercises the host's side of the contract.
 *
 * The documented test hook is therefore the production signal itself: set HOST_ATTRIBUTE to DARK on
 * document.documentElement and the shipping contract runs, rather than a fixture of it.
 * block_dimensions ships the identical class under its own namespace with identical literal values.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class colour_mode {
    /** @var string Bootstrap's own colour-mode attribute, set by the HOST on the html element. */
    public const HOST_ATTRIBUTE = 'data-bs-theme';

    /** @var string The value of HOST_ATTRIBUTE that activates the plugin's dark layer. */
    public const DARK = 'dark';

    /** @var string The value of HOST_ATTRIBUTE that pins light. */
    public const LIGHT = 'light';

    /** @var string Gate on the inert OS-preference block. Written by nothing; a test proves it. */
    public const MEDIA_OPTIN_ATTRIBUTE = 'data-dimensions-media-optin';
}
