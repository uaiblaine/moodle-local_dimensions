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

namespace local_dimensions\admin;

use core\output\single_button;
use local_dimensions\helper;

/**
 * A settings row that shows the state of the PostgreSQL `unaccent` extension and offers to create it.
 *
 * Nothing is read when the admin tree is built: the probe runs in output_html(), on the settings
 * page itself. The row stores no value.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_unaccent extends \admin_setting {
    /**
     * Create the row.
     *
     * @param string $name Unique setting name, with the plugin prefix.
     * @param string $visiblename The row's label.
     * @param string $description Help text shown under the state.
     */
    public function __construct(string $name, string $visiblename, string $description) {
        parent::__construct($name, $visiblename, $description, '');
    }

    /**
     * The row holds no setting.
     *
     * @return bool
     */
    #[\Override]
    public function get_setting() {
        return true;
    }

    /**
     * The row holds no setting.
     *
     * @param mixed $data Ignored.
     * @return string Empty: nothing to report.
     */
    #[\Override]
    public function write_setting($data) {
        return '';
    }

    /**
     * The extension's state and, on PostgreSQL without it, the button that creates it.
     *
     * @param mixed $data Ignored.
     * @param string $query Search query, for highlighting.
     * @return string The row's HTML.
     */
    #[\Override]
    public function output_html($data, $query = '') {
        global $DB, $OUTPUT;

        $control = '';
        if ($DB->get_dbfamily() !== 'postgres') {
            $state = get_string('unaccent_state_collation', 'local_dimensions');
        } else if (helper::has_unaccent()) {
            $state = get_string('unaccent_state_installed', 'local_dimensions');
        } else {
            $state = get_string('unaccent_state_missing', 'local_dimensions');
            $button = new single_button(
                new \moodle_url('/local/dimensions/unaccent.php'),
                get_string('unaccent_create', 'local_dimensions'),
                'post',
                single_button::BUTTON_PRIMARY
            );
            $control = $OUTPUT->render($button);
        }
        $element = $OUTPUT->render_from_template('local_dimensions/setting_unaccent', [
            'state' => $state,
            'control' => $control,
        ]);
        return format_admin_setting($this, $this->visiblename, $element, $this->description, false, '', null, $query);
    }
}
