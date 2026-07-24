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
 * Tests for the full plan overview renderable.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\output;

use advanced_testcase;
use core_competency\plan;
use local_dimensions\constants;

/**
 * Unit tests for the plan overview's sort order, view state and favourites resolution.
 *
 * The three resolvers are private statics, but the gates deciding whether they apply at all -
 * the admin setting and the own-plan guard - live in export_for_template. Driving the export is
 * therefore both the shorter test and the one that covers the wiring between them.
 *
 * @covers \local_dimensions\output\view_plan_summary_page
 */
final class view_plan_summary_page_test extends advanced_testcase {
    /**
     * Build a template-backed plan whose competency names sort against their plan order.
     *
     * Plan order is Charlie, Alpha, Bravo, so a name sort has to move every row and cannot
     * pass by accident.
     *
     * @param array $proficient Shortnames to mark proficient for the plan's user.
     * @return array Keys: plan (plan), user (stdClass), ids (shortname => competency id).
     */
    private function create_plan_with_competencies(array $proficient = []): array {
        $this->setAdminUser();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework(['visible' => 1]);
        $template = $ccg->create_template();
        $user = $this->getDataGenerator()->create_user();

        $ids = [];
        foreach (['Charlie', 'Alpha', 'Bravo'] as $shortname) {
            $competency = $ccg->create_competency([
                'competencyframeworkid' => $framework->get('id'),
                'shortname' => $shortname,
            ]);
            $ids[$shortname] = (int) $competency->get('id');
            $ccg->create_template_competency([
                'templateid' => $template->get('id'),
                'competencyid' => $ids[$shortname],
            ]);
        }

        $plan = $ccg->create_plan([
            'userid' => $user->id,
            'templateid' => $template->get('id'),
            // Active (not draft) so the owning user can read it under the default planviewown
            // capability, with no extra role assignment needed here.
            'status' => plan::STATUS_ACTIVE,
        ]);

        foreach ($proficient as $shortname) {
            /* Core's validate_proficiency refuses a proficiency without a grade and a grade
               without a proficiency, so the pair has to be set together. The generator's
               default framework scale is A,B,C,D, which makes 4 its top item. */
            $ccg->create_user_competency([
                'userid' => $user->id,
                'competencyid' => $ids[$shortname],
                'grade' => 4,
                'proficiency' => 1,
            ]);
        }

        return ['plan' => $plan, 'user' => $user, 'ids' => $ids];
    }

    /**
     * Export the renderable for a plan.
     *
     * @param plan $plan The plan to render.
     * @return array The Mustache context.
     */
    private function export(plan $plan): array {
        global $PAGE;
        $page = new view_plan_summary_page($plan);
        return $page->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * The competency shortnames of an exported context, in display order.
     *
     * @param array $data The exported Mustache context.
     * @return array The shortnames.
     */
    private function order_of(array $data): array {
        return array_column($data['competencies'], 'shortname');
    }

    /**
     * With nothing stored, the plan keeps its own order and the view its defaults.
     *
     * The star is on: enablefavourites has never been written, and an unwritten config reads
     * as false, which the renderable has to treat as the documented default of on.
     *
     * @return void
     */
    public function test_defaults_keep_plan_order_and_enable_the_star(): void {
        $this->resetAfterTest();
        $fixture = $this->create_plan_with_competencies();
        $this->setUser($fixture['user']);

        $data = $this->export($fixture['plan']);

        $this->assertSame(['Charlie', 'Alpha', 'Bravo'], $this->order_of($data));
        $this->assertSame('planorder', $data['sortmode']);
        $this->assertTrue($data['sort_is_planorder']);
        $this->assertTrue($data['filter_is_incomplete']);
        $this->assertTrue($data['view_is_list']);
        $this->assertTrue($data['showstar']);
        $this->assertSame(0, $data['favouritecount']);
        $this->assertFalse($data['hasfavourites']);
    }

    /**
     * Sorting by name orders on the shortname, not on the plan's order.
     *
     * @return void
     */
    public function test_name_sort_orders_by_shortname(): void {
        $this->resetAfterTest();
        $fixture = $this->create_plan_with_competencies();
        $this->setUser($fixture['user']);
        set_user_preference(constants::PREF_LEARNER_VIEW, json_encode(['sort' => 'name']));

        $data = $this->export($fixture['plan']);

        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $this->order_of($data));
        $this->assertSame('name', $data['sortmode']);
        $this->assertTrue($data['sort_is_name']);
    }

    /**
     * Sorting by completion lifts the proficient rows and leaves each group in plan order.
     *
     * @return void
     */
    public function test_completed_sort_is_a_stable_partition(): void {
        $this->resetAfterTest();
        $fixture = $this->create_plan_with_competencies(['Bravo']);
        $this->setUser($fixture['user']);
        set_user_preference(constants::PREF_LEARNER_VIEW, json_encode(['sort' => 'completed']));

        $data = $this->export($fixture['plan']);

        $this->assertSame(['Bravo', 'Charlie', 'Alpha'], $this->order_of($data));
        $this->assertTrue($data['sort_is_completed']);
    }

    /**
     * Sorting by favourites lifts the starred rows and leaves each group in plan order.
     *
     * @return void
     */
    public function test_favourites_sort_is_a_stable_partition(): void {
        $this->resetAfterTest();
        $fixture = $this->create_plan_with_competencies();
        $planid = (int) $fixture['plan']->get('id');
        $this->setUser($fixture['user']);
        set_user_preference(constants::PREF_LEARNER_VIEW, json_encode(['sort' => 'favourites']));
        set_user_preference(
            constants::PREF_LEARNER_FAV,
            json_encode([(string) $planid => [$fixture['ids']['Alpha']]])
        );

        $data = $this->export($fixture['plan']);

        $this->assertSame(['Alpha', 'Charlie', 'Bravo'], $this->order_of($data));
        $this->assertTrue($data['sort_is_favourites']);
        $this->assertSame(1, $data['favouritecount']);
        $this->assertTrue($data['hasfavourites']);
        $this->assertSame(2, $data['nonfavouritecount']);
    }

    /**
     * Favourites stored for one plan do not follow the learner into another.
     *
     * @return void
     */
    public function test_favourites_are_scoped_to_their_own_plan(): void {
        $this->resetAfterTest();
        $fixture = $this->create_plan_with_competencies();
        $planid = (int) $fixture['plan']->get('id');
        $this->setUser($fixture['user']);
        // A different plan id, carrying this plan's competency ids.
        set_user_preference(
            constants::PREF_LEARNER_FAV,
            json_encode([(string) ($planid + 1) => array_values($fixture['ids'])])
        );

        $data = $this->export($fixture['plan']);

        $this->assertSame(0, $data['favouritecount']);
        foreach ($data['competencies'] as $competency) {
            $this->assertFalse($competency['isfavourite']);
        }
    }

    /**
     * An unrecognised stored value falls back to the default rather than being trusted.
     *
     * The preference is user-writable through core's own repository, so a hand-edited value and
     * a value left over from a redesign both arrive here the same way.
     *
     * @return void
     */
    public function test_unusable_view_preference_falls_back_to_defaults(): void {
        $this->resetAfterTest();
        $fixture = $this->create_plan_with_competencies();
        $this->setUser($fixture['user']);
        set_user_preference(constants::PREF_LEARNER_VIEW, json_encode([
            'sort' => 'byhandwriting',
            'filter' => 'sometimes',
            'view' => 'carousel',
        ]));

        $data = $this->export($fixture['plan']);

        $this->assertSame('planorder', $data['sortmode']);
        $this->assertTrue($data['filter_is_incomplete']);
        $this->assertTrue($data['view_is_list']);

        // Not JSON at all, and JSON that decodes to a scalar, take the same path.
        set_user_preference(constants::PREF_LEARNER_VIEW, 'not json');
        $this->assertSame('planorder', $this->export($fixture['plan'])['sortmode']);
        set_user_preference(constants::PREF_LEARNER_VIEW, json_encode('grid'));
        $this->assertSame('planorder', $this->export($fixture['plan'])['sortmode']);
    }

    /**
     * Someone else's plan carries no star, and the viewer's own favourites do not bleed into it.
     *
     * @return void
     */
    public function test_another_users_plan_gets_no_star(): void {
        $this->resetAfterTest();
        $fixture = $this->create_plan_with_competencies();
        $planid = (int) $fixture['plan']->get('id');

        // The reviewer has favourites of their own recorded against this very plan id.
        $this->setAdminUser();
        set_user_preference(
            constants::PREF_LEARNER_FAV,
            json_encode([(string) $planid => array_values($fixture['ids'])])
        );

        $data = $this->export($fixture['plan']);

        $this->assertFalse($data['showstar']);
        $this->assertSame(0, $data['favouritecount']);
        foreach ($data['competencies'] as $competency) {
            $this->assertFalse($competency['isfavourite']);
        }
    }

    /**
     * Turning the setting off collapses the whole feature, including a stored sort that needs it.
     *
     * A learner who left the plan sorted by favourites must not be stranded on an order the
     * page can no longer explain, so the sort falls back to the plan's own order.
     *
     * @return void
     */
    public function test_disabled_setting_clears_star_filter_and_sort(): void {
        $this->resetAfterTest();
        set_config('enablefavourites', 0, 'local_dimensions');
        $fixture = $this->create_plan_with_competencies();
        $planid = (int) $fixture['plan']->get('id');
        $this->setUser($fixture['user']);
        set_user_preference(constants::PREF_LEARNER_VIEW, json_encode([
            'sort' => 'favourites',
            'favonly' => true,
        ]));
        set_user_preference(
            constants::PREF_LEARNER_FAV,
            json_encode([(string) $planid => [$fixture['ids']['Alpha']]])
        );

        $data = $this->export($fixture['plan']);

        $this->assertFalse($data['showstar']);
        $this->assertFalse($data['favonly']);
        $this->assertSame('planorder', $data['sortmode']);
        $this->assertTrue($data['sort_is_planorder']);
        $this->assertSame(['Charlie', 'Alpha', 'Bravo'], $this->order_of($data));
        $this->assertSame(0, $data['favouritecount']);
        $this->assertFalse(json_decode($data['viewstatejson'], true)['favonly']);
    }

    /**
     * The whole favourites map is handed to the client, not just this plan's slice.
     *
     * A preference write replaces the entire value, so a page holding only its own plan's list
     * would silently delete every favourite the learner set elsewhere.
     *
     * @return void
     */
    public function test_the_client_receives_the_whole_favourites_map(): void {
        $this->resetAfterTest();
        $fixture = $this->create_plan_with_competencies();
        $planid = (int) $fixture['plan']->get('id');
        $this->setUser($fixture['user']);
        $map = [
            (string) $planid => [$fixture['ids']['Alpha']],
            (string) ($planid + 1) => [$fixture['ids']['Bravo']],
        ];
        set_user_preference(constants::PREF_LEARNER_FAV, json_encode($map));

        $data = $this->export($fixture['plan']);

        $this->assertSame($map, json_decode($data['favouritesjson'], true));
    }

    /**
     * Every key the client can write is seeded, so a save cannot reset one by omission.
     *
     * This is the regression guard for the whole-value preference: the store writes the object
     * back entire, so a key missing here is a key silently reset to its default on the next save.
     *
     * @return void
     */
    public function test_the_seeded_view_state_carries_every_key(): void {
        $this->resetAfterTest();
        $fixture = $this->create_plan_with_competencies();
        $this->setUser($fixture['user']);

        $state = json_decode($this->export($fixture['plan'])['viewstatejson'], true);

        $this->assertSame(
            ['expanded', 'favonly', 'filter', 'sort', 'view'],
            $this->sorted_keys($state)
        );
    }

    /**
     * The hero opens by default and stays folded once the learner folded it.
     *
     * The state has to reach the first paint: applying it after the page renders would show
     * the tall header and then snap it shut under the reader.
     *
     * @return void
     */
    public function test_the_hero_carries_the_stored_open_or_slim_state(): void {
        $this->resetAfterTest();
        $fixture = $this->create_plan_with_competencies();
        $this->setUser($fixture['user']);

        $this->assertFalse($this->export($fixture['plan'])['hero']['slim']);

        set_user_preference(constants::PREF_LEARNER_HERO, '1');
        $this->assertTrue($this->export($fixture['plan'])['hero']['slim']);

        // Anything the preference could hold other than the stored flag reads as open.
        set_user_preference(constants::PREF_LEARNER_HERO, 'yes');
        $this->assertFalse($this->export($fixture['plan'])['hero']['slim']);
    }

    /**
     * The keys of an array, sorted, so an assertion does not depend on insertion order.
     *
     * @param array $value The array to read.
     * @return array The sorted keys.
     */
    private function sorted_keys(array $value): array {
        $keys = array_keys($value);
        sort($keys);
        return $keys;
    }
}
