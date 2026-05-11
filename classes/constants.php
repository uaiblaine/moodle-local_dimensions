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
 * Plugin constants for display modes and custom field names.
 *
 * @package   local_dimensions
 * @copyright 2026 Anderson Blaine (anderson@blaine.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions;

/**
 * Plugin constants for display modes and custom field names.
 *
 * @package   local_dimensions
 * @copyright 2026 Anderson Blaine (anderson@blaine.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class constants {
    /** @var string Custom field shortname for display mode */
    const CFIELD_DISPLAYMODE = 'local_dimensions_displaymode';

    /** @var string Custom field shortname for card image */
    const CFIELD_CUSTOMCARD = 'local_dimensions_customcard';

    /** @var string Custom field shortname for background image */
    const CFIELD_CUSTOMBGIMAGE = 'local_dimensions_custombgimage';

    /** @var string Custom field shortname for background color */
    const CFIELD_CUSTOMBGCOLOR = 'local_dimensions_custombgcolor';

    /** @var string Custom field shortname for text color */
    const CFIELD_CUSTOMTEXTCOLOR = 'local_dimensions_customtextcolor';

    /** @var string Custom field shortname for tag1 (Year/Ano) */
    const CFIELD_TAG1 = 'local_dimensions_tag1';

    /** @var string Custom field shortname for tag2 (Category/Categoria) */
    const CFIELD_TAG2 = 'local_dimensions_tag2';

    /** @var string Custom field shortname for type (e.g., unidade, modulo, etapa) */
    const CFIELD_TYPE = 'local_dimensions_type';

    /** @var string Custom field shortname for custom SCSS code */
    const CFIELD_CUSTOMSCSS = 'local_dimensions_customscss';

    /** @var string Custom field shortname for the dynamic accordion subline source (lp area) */
    const CFIELD_SUBLINE_SOURCE = 'local_dimensions_subline_source';

    /** @var string Custom field shortname for the template identifier (lp area only — analogue of competency framework idnumber) */
    const CFIELD_TEMPLATE_IDNUMBER = 'local_dimensions_template_idnumber';

    /** @var string Custom field shortname for per-template enrollment filter (lp area only) */
    const CFIELD_ENROLLMENTFILTER = 'local_dimensions_enrollmentfilter';

    /** @var string Custom field shortname for per-template single-course redirect (lp area only) */
    const CFIELD_SINGLECOURSEREDIRECT = 'local_dimensions_singlecourseredirect';

    /** @var string Enrollment filter: inherit the global setting */
    const ENROLLMENTFILTER_INHERIT = 'inherit';

    /** @var string Enrollment filter: show all linked courses */
    const ENROLLMENTFILTER_ALL = 'all';

    /** @var string Enrollment filter: show only courses where user is enrolled */
    const ENROLLMENTFILTER_ENROLLED = 'enrolled';

    /** @var string Enrollment filter: show only courses with active enrollments */
    const ENROLLMENTFILTER_ACTIVE = 'active';

    /** @var string Single-course redirect: inherit the global setting */
    const SINGLECOURSEREDIRECT_INHERIT = 'inherit';

    /** @var string Single-course redirect: enable for this template */
    const SINGLECOURSEREDIRECT_YES = 'yes';

    /** @var string Single-course redirect: disable for this template */
    const SINGLECOURSEREDIRECT_NO = 'no';

    /** @var string Subline source: hide the subline */
    const SUBLINE_NONE = 'none';

    /** @var string Subline source: completion status (current default behaviour) */
    const SUBLINE_STATUS = 'status';

    /** @var string Subline source: competency assessment rating */
    const SUBLINE_RATING = 'rating';

    /** @var string Subline source: tag1 competency custom field */
    const SUBLINE_TAG1 = 'tag1';

    /** @var string Subline source: tag2 competency custom field */
    const SUBLINE_TAG2 = 'tag2';

    /** @var int Display competencies as cards (default) */
    const DISPLAYMODE_COMPETENCIES = 1;

    /** @var int Display entire plan as a single card */
    const DISPLAYMODE_PLAN = 2;

    /**
     * Get localized display mode options for select field.
     *
     * @return \lang_string[]
     */
    public static function display_mode_options(): array {
        return [
            self::DISPLAYMODE_COMPETENCIES => new \lang_string('displaymode_competencies', 'local_dimensions'),
            self::DISPLAYMODE_PLAN => new \lang_string('displaymode_plan', 'local_dimensions'),
        ];
    }

    /**
     * Localized options for the per-template "subline source" select.
     *
     * @return array<string, \lang_string> keyed by source identifier
     */
    public static function subline_source_options(): array {
        return [
            self::SUBLINE_STATUS => new \lang_string('subline_source_status', 'local_dimensions'),
            self::SUBLINE_RATING => new \lang_string('subline_source_rating', 'local_dimensions'),
            self::SUBLINE_TAG1 => new \lang_string('subline_source_tag1', 'local_dimensions'),
            self::SUBLINE_TAG2 => new \lang_string('subline_source_tag2', 'local_dimensions'),
            self::SUBLINE_NONE => new \lang_string('subline_source_none', 'local_dimensions'),
        ];
    }

    /**
     * Localized options for the per-template enrollment filter select.
     *
     * The leading "inherit" option resolves to the site-wide
     * `local_dimensions/enrollmentfilter` setting at read time.
     *
     * @return array<string, \lang_string> keyed by option identifier
     */
    public static function enrollmentfilter_options(): array {
        return [
            self::ENROLLMENTFILTER_INHERIT => new \lang_string('enrollmentfilter_inherit', 'local_dimensions'),
            self::ENROLLMENTFILTER_ALL => new \lang_string('enrollmentfilter_all', 'local_dimensions'),
            self::ENROLLMENTFILTER_ENROLLED => new \lang_string('enrollmentfilter_enrolled', 'local_dimensions'),
            self::ENROLLMENTFILTER_ACTIVE => new \lang_string('enrollmentfilter_active', 'local_dimensions'),
        ];
    }

    /**
     * Localized options for the per-template single-course redirect select.
     *
     * The leading "inherit" option resolves to the site-wide
     * `local_dimensions/singlecourseredirect` setting at read time.
     *
     * @return array<string, \lang_string> keyed by option identifier
     */
    public static function singlecourseredirect_options(): array {
        return [
            self::SINGLECOURSEREDIRECT_INHERIT => new \lang_string('singlecourseredirect_inherit', 'local_dimensions'),
            self::SINGLECOURSEREDIRECT_YES => new \lang_string('singlecourseredirect_yes', 'local_dimensions'),
            self::SINGLECOURSEREDIRECT_NO => new \lang_string('singlecourseredirect_no', 'local_dimensions'),
        ];
    }
}
