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
 * Tests for helper::sql_like_ai() accent-insensitive search.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions;

use advanced_testcase;

/**
 * @covers \local_dimensions\helper::sql_like_ai
 */
final class helper_sql_like_ai_test extends advanced_testcase {
    /**
     * A search without accents matches a stored value that has accents.
     *
     * @return void
     */
    public function test_search_is_accent_insensitive(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $cgen = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $cgen->create_framework();
        $cgen->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'shortname' => 'Língua Portuguesa',
        ]);

        // On PostgreSQL the unaccent extension must be creatable; if not, this behaviour cannot
        // be delivered there — skip rather than fail (the fallback is accent-sensitive by design).
        if ($DB->get_dbfamily() === 'postgres' && !helper::ensure_unaccent()) {
            $this->markTestSkipped('PostgreSQL unaccent extension is unavailable in this environment.');
        }

        $like = helper::sql_like_ai('shortname', ':q');
        $records = $DB->get_records_select(
            'competency',
            "competencyframeworkid = :fw AND ($like)",
            ['fw' => $framework->get('id'), 'q' => '%lingua portuguesa%']
        );

        $this->assertCount(1, $records);
    }
}
