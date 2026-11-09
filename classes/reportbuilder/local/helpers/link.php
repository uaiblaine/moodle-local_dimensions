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

declare(strict_types=1);

namespace local_dimensions\reportbuilder\local\helpers;

use moodle_url;

/**
 * Links for report columns, rendered from a template so the text is escaped exactly once.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class link {
    /**
     * A link to a Moodle page.
     *
     * @param moodle_url $url Where the link goes.
     * @param string $text The link text in its PLAIN spelling: the template escapes it.
     * @return string The link as HTML.
     */
    public static function to_page(moodle_url $url, string $text): string {
        global $OUTPUT;
        return trim($OUTPUT->render_from_template('local_dimensions/reportbuilder_link', [
            'url' => $url->out(false),
            'text' => $text,
        ]));
    }

    /**
     * A stored, user-supplied URL as a link, or as plain text when it is not a web URL.
     *
     * The value is cleaned with PARAM_URL, which accepts only http, https, ftp and mailto
     * addresses, so a stored `javascript:` value never reaches an href.
     *
     * @param string|null $url The stored value.
     * @return string The link, or the escaped value alone, as HTML.
     */
    public static function to_stored_url(?string $url): string {
        if ($url === null || $url === '') {
            return '';
        }
        global $OUTPUT;
        $clean = clean_param($url, PARAM_URL);
        if ($clean === '') {
            return s($url);
        }
        return trim($OUTPUT->render_from_template('local_dimensions/reportbuilder_link', [
            'url' => $clean,
            'text' => $url,
        ]));
    }
}
