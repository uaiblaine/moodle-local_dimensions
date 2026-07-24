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
 * Tests for the local_dimensions privacy provider.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\privacy;

use advanced_testcase;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;
use local_dimensions\constants;

/**
 * Unit tests for the preference-only privacy provider.
 *
 * @covers \local_dimensions\privacy\provider
 */
final class provider_test extends advanced_testcase {
    /**
     * Reset the privacy writer between tests so an export in one case does not leak into another.
     *
     * @return void
     */
    protected function tearDown(): void {
        writer::reset();
        parent::tearDown();
    }

    /**
     * get_metadata declares exactly the four view-state preferences.
     *
     * @return void
     */
    public function test_get_metadata_declares_every_preference(): void {
        $collection = new collection('local_dimensions');
        $items = provider::get_metadata($collection)->get_collection();
        $this->assertCount(4, $items);
        $names = array_map(static fn($item) => $item->get_name(), $items);
        $this->assertContains(constants::PREF_CENTRAL_NAV, $names);
        $this->assertContains(constants::PREF_CENTRAL_DISPLAY, $names);
        $this->assertContains(constants::PREF_LEARNER_VIEW, $names);
        $this->assertContains(constants::PREF_LEARNER_FAV, $names);
    }

    /**
     * Every declared preference is also exported, so neither list can drift from the other.
     *
     * @return void
     */
    public function test_every_declared_preference_is_exported(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $collection = new collection('local_dimensions');
        $names = array_map(
            static fn($item) => $item->get_name(),
            provider::get_metadata($collection)->get_collection()
        );
        foreach ($names as $name) {
            set_user_preference($name, json_encode(['x' => 1]), $USER->id);
        }
        provider::export_user_preferences((int) $USER->id);
        $exported = (array) writer::with_context(\context_user::instance($USER->id))
            ->get_user_preferences('local_dimensions');
        foreach ($names as $name) {
            $this->assertArrayHasKey($name, $exported);
        }
    }

    /**
     * A set preference is exported for the user.
     *
     * @return void
     */
    public function test_export_user_preferences_exports_set_values(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_user_preference(constants::PREF_CENTRAL_NAV, json_encode(['tab' => 'plans']), $USER->id);
        provider::export_user_preferences((int) $USER->id);
        $writer = writer::with_context(\context_user::instance($USER->id));
        $this->assertTrue($writer->has_any_data());
        $exported = (array) $writer->get_user_preferences('local_dimensions');
        $this->assertArrayHasKey(constants::PREF_CENTRAL_NAV, $exported);
    }

    /**
     * A real favourites value is exported verbatim, under the description that explains it.
     *
     * The description carries the whole weight here: the value is nothing but plan ids and
     * competency ids, so an export whose description does not name that shape is unreadable
     * to the person who asked for it.
     *
     * @return void
     */
    public function test_export_user_preferences_describes_the_favourites_value(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $value = json_encode(['12' => [45, 67]]);
        set_user_preference(constants::PREF_LEARNER_FAV, $value, $USER->id);
        provider::export_user_preferences((int) $USER->id);
        $exported = (array) writer::with_context(\context_user::instance($USER->id))
            ->get_user_preferences('local_dimensions');
        $this->assertArrayHasKey(constants::PREF_LEARNER_FAV, $exported);
        $this->assertSame($value, $exported[constants::PREF_LEARNER_FAV]->value);
        $this->assertSame(
            get_string('privacy:metadata:preference:learner_fav', 'local_dimensions'),
            $exported[constants::PREF_LEARNER_FAV]->description
        );
    }

    /**
     * The export reads the subject's preferences, never the logged-in user's.
     *
     * A data request is processed by whoever runs it, so reading $USER instead of the passed
     * id would export the wrong person's favourites into the subject's archive.
     *
     * @return void
     */
    public function test_export_user_preferences_reads_the_target_user(): void {
        $this->resetAfterTest();
        $subject = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $subjectvalue = json_encode(['1' => [2]]);
        set_user_preference(constants::PREF_LEARNER_FAV, $subjectvalue, $subject->id);
        set_user_preference(constants::PREF_LEARNER_FAV, json_encode(['9' => [8]]), $other->id);

        $this->setUser($other);
        provider::export_user_preferences((int) $subject->id);

        $exported = (array) writer::with_context(\context_user::instance($subject->id))
            ->get_user_preferences('local_dimensions');
        $this->assertSame($subjectvalue, $exported[constants::PREF_LEARNER_FAV]->value);
    }

    /**
     * Nothing is exported when the user has no stored preferences.
     *
     * @return void
     */
    public function test_export_user_preferences_skips_unset(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        provider::export_user_preferences((int) $USER->id);
        $writer = writer::with_context(\context_user::instance($USER->id));
        $this->assertFalse($writer->has_any_data());
    }
}
