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
}
