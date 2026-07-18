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

namespace local_dimensions;

use local_dimensions\customfield\competency_handler;
use local_dimensions\customfield\lp_handler;

/**
 * Tests for the Feel/Look custom-field category reorganization.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\helper::organize_customfield_categories
 */
final class helper_customfield_categories_test extends \advanced_testcase {
    /**
     * Provisioning sorts every field into exactly two categories (Feel + Look),
     * removes the empty default, places behaviour vs styling fields correctly, and
     * keeps Look ordered last (so the built-in image filemanagers land inside it).
     *
     * @return void
     */
    public function test_fields_sorted_into_two_categories(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        helper::ensure_custom_fields_exist(helper::AREA_LP);

        $handler = lp_handler::create();
        $handler->reset_configuration_cache();
        $categories = $handler->get_categories_with_fields();

        // Only the two managed categories remain (the default is removed once empty).
        // Filter to owned categories so a core shared-category default cannot skew the count.
        $owned = array_filter($categories, function ($category) {
            return $category->get('component') === 'local_dimensions' && $category->get('area') === helper::AREA_LP;
        });
        $this->assertCount(2, $owned);

        $feelid = (int) get_config('local_dimensions', 'cfcat_feel_lp');
        $lookid = (int) get_config('local_dimensions', 'cfcat_look_lp');
        $this->assertGreaterThan(0, $feelid);
        $this->assertGreaterThan(0, $lookid);

        // A behaviour field lands in Feel; a styling field lands in Look.
        $enrol = helper::find_field_by_shortname(constants::CFIELD_ENROLLMENTFILTER, helper::AREA_LP);
        $bgcolor = helper::find_field_by_shortname(constants::CFIELD_CUSTOMBGCOLOR, helper::AREA_LP);
        $this->assertSame($feelid, (int) $enrol->get('categoryid'));
        $this->assertSame($lookid, (int) $bgcolor->get('categoryid'));

        // The new locked-card fields land in Feel too.
        $locked = helper::find_field_by_shortname(constants::CFIELD_LOCKEDCARDMODE, helper::AREA_LP);
        $showdate = helper::find_field_by_shortname(constants::CFIELD_SHOWLOCKEDDATE, helper::AREA_LP);
        $this->assertSame($feelid, (int) $locked->get('categoryid'));
        $this->assertSame($feelid, (int) $showdate->get('categoryid'));

        // Look is ordered last so appended image filemanagers fall inside its section.
        $feelsort = null;
        $looksort = null;
        foreach ($categories as $category) {
            if ((int) $category->get('id') === $feelid) {
                $feelsort = (int) $category->get('sortorder');
            } else if ((int) $category->get('id') === $lookid) {
                $looksort = (int) $category->get('sortorder');
            }
        }
        $this->assertNotNull($feelsort);
        $this->assertNotNull($looksort);
        $this->assertGreaterThan($feelsort, $looksort);
    }

    /**
     * A second organize run (e.g. a different admin language) reuses the tracked
     * category ids instead of creating duplicates.
     *
     * @return void
     */
    public function test_reorganize_reuses_tracked_categories(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);
        $feelid = (int) get_config('local_dimensions', 'cfcat_feel_competency');
        $lookid = (int) get_config('local_dimensions', 'cfcat_look_competency');

        helper::organize_customfield_categories(helper::AREA_COMPETENCY);

        $this->assertSame($feelid, (int) get_config('local_dimensions', 'cfcat_feel_competency'));
        $this->assertSame($lookid, (int) get_config('local_dimensions', 'cfcat_look_competency'));

        $handler = competency_handler::create();
        $handler->reset_configuration_cache();
        $owned = array_filter($handler->get_categories_with_fields(), function ($category) {
            return $category->get('component') === 'local_dimensions'
                && $category->get('area') === helper::AREA_COMPETENCY;
        });
        $this->assertCount(2, $owned);
    }
}
