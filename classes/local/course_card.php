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
 * The image of a plan card whose course has none of its own.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

/**
 * The theme's image for a course with none of its own (the course card contract, section 8).
 *
 * Under Boost Union or a child theme the plan's card asks the theme's renderer, which answers Boost Union's fallback
 * image, core's geometric pattern or, under theme_dimensions, the course category's cover. Under any other theme it
 * asks nothing and the client paints the cover fallback token. The same rules as block_compass' cards, copied rather
 * than called (contract D1).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_card {
    /**
     * The images with the renderer's answer for every course that has none, asked only when one has none.
     *
     * Whatever the closure costs (the request's theme and core's renderer, {@see generated_images()}) is never paid
     * by a response whose every course has its image.
     *
     * @param array $images Course id => the course's own image URL, '' for a course without one.
     * @param callable $generated Course ids => [course id => the renderer's image].
     * @return array The same map, a valid generated image ({@see is_generated_image()}) in place of each '' it
     *     answered; an invalid answer, or one for a course that has its own image, is dropped.
     */
    public static function with_generated(array $images, callable $generated): array {
        $missing = array_keys(array_filter($images, static fn($image): bool => $image === ''));
        if (empty($missing)) {
            return $images;
        }
        foreach ($generated($missing) as $courseid => $image) {
            $courseid = (int) $courseid;
            if (($images[$courseid] ?? null) === '' && is_string($image) && self::is_generated_image($image)) {
                $images[$courseid] = $image;
            }
        }

        return $images;
    }

    /**
     * Whether a renderer's answer for a course with no image is one the client may draw.
     *
     * Two shapes only (the course card contract, section 8): a base64 SVG data URI, which core's pattern
     * (core_geopattern::datauri()) and theme_dimensions' category cover are, and a URL of this site's pluginfile.php,
     * which Boost Union's fallback image is (with slash arguments or without). Anything else - another host, another
     * script, another scheme, a quote - is refused: the value reaches an img src, and get_competency_courses returns
     * it as PARAM_RAW because PARAM_URL refuses every data URI.
     *
     * @param string $image The answer.
     * @return bool
     */
    public static function is_generated_image(string $image): bool {
        global $CFG;

        if (preg_match('~^data:image/svg\+xml;base64,[A-Za-z0-9+/]+={0,2}$~D', $image)) {
            return true;
        }
        $pluginfile = $CFG->wwwroot . '/pluginfile.php';
        $next = substr($image, strlen($pluginfile), 1);
        if (!str_starts_with($image, $pluginfile) || ($next !== '/' && $next !== '?')) {
            return false;
        }

        return clean_param($image, PARAM_URL) === $image && !preg_match('/[\s"\'<>\\\\]/', $image);
    }

    /**
     * The renderer's image for courses with none of their own, under Boost Union or a child theme only.
     *
     * The closure {@see with_generated()} calls once a course of the response lacks an image: only then is core's
     * renderer created. The general target, never the request's: a web service request's factory answers core's ajax
     * renderer, which no theme overrides, and would lose Boost Union's get_generated_image_for_id().
     *
     * Boost Union's fallback image is one file for every course, and its renderer reads the file area on every call:
     * while the fallback is the administrator's choice, its first answer that is not a data URI (the file's URL) is
     * reused for the rest of the response, one read where there would be one per course. Re-read Boost Union's
     * get_generated_image_for_id() on every update of it: an answer that ever depends on the course breaks this.
     *
     * @param card_settings|null $settings The request's settings; read from the request's theme when null.
     * @param object|null $renderer Stands in for core's renderer in tests (get_generated_image_for_id() only).
     * @return callable Course ids => [course id => the renderer's image]; nothing under another theme.
     */
    public static function generated_images(?card_settings $settings = null, ?object $renderer = null): callable {
        return static function (array $courseids) use ($settings, $renderer): array {
            global $PAGE;

            $settings ??= card_settings::of_request();
            if (!$settings->active()) {
                return [];
            }
            $renderer ??= $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
            $fallback = $settings->image_source() === card_settings::SOURCE_FALLBACK;
            $shared = null;
            $images = [];
            foreach ($courseids as $courseid) {
                $image = $shared ?? (string) $renderer->get_generated_image_for_id((int) $courseid);
                if ($fallback && !str_starts_with($image, 'data:')) {
                    $shared = $image;
                }
                $images[(int) $courseid] = $image;
            }

            return $images;
        };
    }
}
