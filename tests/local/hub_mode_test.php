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

use advanced_testcase;
use context_coursecat;
use context_system;
use core_external\external_api;
use local_dimensions\constants;
use local_dimensions\helper;
use local_dimensions\output\dynamictabs\frameworks;
use local_dimensions\output\dynamictabs\plans;
use local_dimensions\output\dynamictabs\structure;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The hub's two modes: the workspace (the default) and the classic hub behind a link.
 *
 * The refusals come first: a `classic` value on the address without the session key, or with a wrong
 * one, changes the visit and saves nothing, beside the same request with the key, which saves; leaving
 * the Structures tab out of the strip opens and closes nothing, since each tab's own is_available()
 * still decides, through the dynamic-tabs web service too.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(hub_mode::class)]
final class hub_mode_test extends advanced_testcase {
    /**
     * The saved display preference, decoded.
     *
     * @return array
     */
    private function saved_display(): array {
        $raw = json_decode((string) get_user_preferences(constants::PREF_CENTRAL_DISPLAY, ''), true);
        return is_array($raw) ? $raw : [];
    }

    /**
     * A manager scoped to a fresh course category, with no competency access at the site.
     *
     * @return array Keys: user (stdClass), category (stdClass).
     */
    private function category_manager(): array {
        global $CFG;
        $generator = $this->getDataGenerator();
        $category = $generator->create_category();
        $categorycontext = context_coursecat::instance((int) $category->id);
        $user = $generator->create_user();
        $roleid = $generator->create_role();
        foreach (['competencyview', 'competencymanage', 'templateview', 'templatemanage'] as $capname) {
            assign_capability('moodle/competency:' . $capname, CAP_ALLOW, $roleid, $categorycontext->id);
        }
        role_assign($roleid, (int) $user->id, $categorycontext->id);
        assign_capability(
            'moodle/competency:competencyview',
            CAP_PREVENT,
            (int) $CFG->defaultuserroleid,
            context_system::instance()->id,
            true
        );
        accesslib_clear_all_caches_for_unit_testing();
        return ['user' => $user, 'category' => $category];
    }

    /**
     * The three tabs as central.php builds them for one context.
     *
     * @param array $pane The pane data every tab shares.
     * @return array Shortname to tab, in the strip's order.
     */
    private function instances(array $pane): array {
        return [
            'frameworks' => new frameworks($pane),
            'structure' => new structure($pane),
            'plans' => new plans($pane),
        ];
    }

    /**
     * Refusal: a `classic` value without the session key changes the visit, and the saved choice stays.
     *
     * @return void
     */
    public function test_an_address_without_the_session_key_changes_the_visit_only(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertTrue(hub_mode::from_request(1, ''));
        $this->assertArrayNotHasKey('classic', $this->saved_display());
        $this->assertFalse(hub_mode::saved());

        // Control: the same request with the session key saves the choice.
        $this->assertTrue(hub_mode::from_request(1, sesskey()));
        $this->assertTrue($this->saved_display()['classic']);
        $this->assertTrue(hub_mode::saved());
    }

    /**
     * Refusal: a wrong session key saves nothing, in either direction.
     *
     * @return void
     */
    public function test_a_wrong_session_key_saves_nothing(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_user_preference(constants::PREF_CENTRAL_DISPLAY, json_encode(['classic' => true, 'structure' => ['tax' => true]]));

        $this->assertFalse(hub_mode::from_request(0, 'not-the-key'));
        $this->assertTrue($this->saved_display()['classic']);

        // Control: the key saves it, and the rest of the preference is kept as it was.
        $this->assertFalse(hub_mode::from_request(0, sesskey()));
        $this->assertFalse($this->saved_display()['classic']);
        $this->assertTrue($this->saved_display()['structure']['tax']);
    }

    /**
     * The address beats the saved choice, the saved choice beats the default (off); anything but 0 or 1 is no value.
     *
     * @return void
     */
    public function test_the_address_beats_the_saved_choice_and_the_default_is_off(): void {
        $this->assertFalse(hub_mode::resolve(null, false));
        $this->assertTrue(hub_mode::resolve(null, true));
        $this->assertTrue(hub_mode::resolve(1, false));
        $this->assertFalse(hub_mode::resolve(0, true));
        $this->assertTrue(hub_mode::resolve(7, true));
        $this->assertFalse(hub_mode::resolve(-1, false));

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->assertFalse(hub_mode::from_request(null, sesskey()));
        // Nothing asked: nothing saved, even with the key.
        $this->assertArrayNotHasKey('classic', $this->saved_display());
    }

    /**
     * The strip: Competencies and Learning plans by default, the Structures tab first in classic mode.
     *
     * @return void
     */
    public function test_the_strip_offers_the_structures_tab_in_classic_mode_only(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $pane = ['contexttype' => 'system', 'categoryid' => 0, 'locked' => 0];

        $this->assertSame(['structure' => true, 'plans' => true], hub_mode::strip($this->instances($pane), false));
        $this->assertSame(
            ['frameworks' => true, 'structure' => true, 'plans' => true],
            hub_mode::strip($this->instances($pane), true)
        );
    }

    /**
     * A request for the Structures tab lands on Competencies without classic mode, and stays with it.
     *
     * @return void
     */
    public function test_a_request_for_the_structures_tab_lands_on_competencies_by_default(): void {
        $this->assertSame('structure', hub_mode::requested_tab('frameworks', false));
        $this->assertSame('frameworks', hub_mode::requested_tab('frameworks', true));
        $this->assertSame('plans', hub_mode::requested_tab('plans', false));
        $this->assertSame('structure', hub_mode::requested_tab('structure', true));
        // Anything else is the strip's first tab, as it was.
        $this->assertSame('structure', hub_mode::requested_tab('bogus', false));
        $this->assertSame('frameworks', hub_mode::requested_tab('', true));
    }

    /**
     * A category manager on the locked entry gets the same strip as everyone, each tab admitted as before.
     *
     * @return void
     */
    public function test_a_category_manager_on_the_locked_entry_gets_the_workspace_by_default(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $fixture = $this->category_manager();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $categorycontext = context_coursecat::instance((int) $fixture['category']->id);
        $framework = $ccg->create_framework(['shortname' => 'Category framework', 'contextid' => $categorycontext->id]);
        $this->setUser($fixture['user']);

        $pane = ['contexttype' => 'coursecat', 'categoryid' => (int) $fixture['category']->id, 'locked' => 1];
        $this->assertSame(['structure' => true, 'plans' => true], hub_mode::strip($this->instances($pane), false));
        $this->assertSame(
            ['frameworks' => true, 'structure' => true, 'plans' => true],
            hub_mode::strip($this->instances($pane), true)
        );

        // The Competencies tab opens the workspace, in Both, on the category's framework.
        global $PAGE;
        $tab = new structure($pane + ['classic' => 0, 'frameworkid' => 0]);
        $data = $tab->export_for_template($PAGE->get_renderer('core'));
        $this->assertSame('both', $data['view']);
        $this->assertTrue($data['isworkspace']);
        $this->assertSame((int) $framework->get('id'), $data['selectedframeworkid']);

        // The control: at the site the same manager is refused every tab, in both modes.
        $site = ['contexttype' => 'system', 'categoryid' => 0, 'locked' => 0];
        $this->assertSame(['structure' => false, 'plans' => false], hub_mode::strip($this->instances($site), false));
        $this->assertSame(
            ['frameworks' => false, 'structure' => false, 'plans' => false],
            hub_mode::strip($this->instances($site), true)
        );
    }

    /**
     * Leaving the Structures tab out of the strip closes nothing and opens nothing: the dynamic-tabs web service
     * still serves its pane by its own is_available(), in the manager's category, and refuses it at the site.
     *
     * @return void
     */
    public function test_the_structures_pane_answers_as_before_when_the_strip_leaves_it_out(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $fixture = $this->category_manager();
        $this->setUser($fixture['user']);
        $_POST['sesskey'] = sesskey();

        $incategory = external_api::call_external_function('core_dynamic_tabs_get_content', [
            'tab' => frameworks::class,
            'jsondata' => json_encode([
                'contexttype' => 'coursecat',
                'categoryid' => (int) $fixture['category']->id,
                'locked' => 1,
                'classic' => 0,
            ]),
        ]);
        $this->assertFalse($incategory['error'], 'The Structures pane is still served in the manager\'s category');

        $atsystem = external_api::call_external_function('core_dynamic_tabs_get_content', [
            'tab' => frameworks::class,
            'jsondata' => json_encode(['contexttype' => 'system', 'categoryid' => 0, 'classic' => 1]),
        ]);
        $this->assertTrue($atsystem['error'], 'The Structures pane is still refused at the site, classic or not');
        $this->assertSame('nopermissiontoaccesspage', $atsystem['exception']->errorcode);
    }

    /**
     * The context bar's link: the other mode, on the Competencies tab, with the session key, the framework and the
     * context the site entry needs; on the locked entry, the category entry's address and no context parameters.
     *
     * @return void
     */
    public function test_the_switch_link_names_the_other_mode_on_the_same_place(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $categorycontext = context_coursecat::instance((int) $category->id);

        $site = hub_mode::switch_url(context_system::instance(), false, false, 'coursecat', (int) $category->id, 12);
        $this->assertSame(
            (new \moodle_url('/local/dimensions/central.php'))->out_omit_querystring(),
            $site->out_omit_querystring()
        );
        $this->assertSame('structure', $site->get_param('tab'));
        $this->assertSame(1, (int) $site->get_param('classic'));
        $this->assertSame(sesskey(), $site->get_param('sesskey'));
        $this->assertSame(12, (int) $site->get_param('frameworkid'));
        $this->assertSame('coursecat', $site->get_param('contexttype'));
        $this->assertSame((int) $category->id, (int) $site->get_param('categoryid'));

        $locked = hub_mode::switch_url($categorycontext, true, true, 'coursecat', (int) $category->id, 0);
        $this->assertSame($categorycontext->id, (int) $locked->get_param('pagecontextid'));
        $this->assertSame(0, (int) $locked->get_param('classic'));
        $this->assertNull($locked->get_param('contexttype'));
        $this->assertNull($locked->get_param('categoryid'));
        $this->assertNull($locked->get_param('frameworkid'));

        $this->assertSame(get_string('central_view_classic', 'local_dimensions'), hub_mode::switch_label(false));
        $this->assertSame(get_string('central_view_workspace', 'local_dimensions'), hub_mode::switch_label(true));
    }

    /**
     * The saved preference is read through the hub's one sanitiser.
     *
     * @return void
     */
    public function test_the_saved_choice_is_the_sanitised_preference(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_user_preference(constants::PREF_CENTRAL_DISPLAY, json_encode(['classic' => 1]));
        $this->assertTrue(hub_mode::saved());
        $this->assertTrue(helper::get_central_prefs()['display']['classic']);
        set_user_preference(constants::PREF_CENTRAL_DISPLAY, '{broken');
        $this->assertFalse(hub_mode::saved());
    }

    /**
     * Write the display preference as the hub's script does, through core's preference service.
     *
     * @param array $display The display state the client sends.
     * @return void
     */
    private function client_saves_display(array $display): void {
        $_POST['sesskey'] = sesskey();
        $result = external_api::call_external_function('core_user_set_user_preferences', [
            'preferences' => [['name' => constants::PREF_CENTRAL_DISPLAY, 'value' => json_encode($display), 'userid' => 0]],
        ]);
        $this->assertFalse($result['error'], 'The client\'s own display save is accepted');
    }

    /**
     * Refusal: a display save from the client never changes the mode, whether it omits `classic` (as the hub's
     * script does), names the other value, or comes from a page left open in the other mode; its other choices are
     * stored. Control: the link's request (the session key) changes it.
     *
     * @return void
     */
    public function test_a_display_save_from_the_client_does_not_change_the_mode(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->assertTrue(hub_mode::from_request(1, sesskey()));

        $this->client_saves_display(['structure' => ['tax' => true]]);
        $this->assertTrue(hub_mode::saved());
        $this->assertTrue($this->saved_display()['structure']['tax']);

        $this->client_saves_display(['classic' => false, 'structure' => ['tax' => false]]);
        $this->assertTrue(hub_mode::saved());
        $this->assertFalse($this->saved_display()['structure']['tax']);

        // Control: the link's request switches it back, and a client write cannot turn it on again either.
        $this->assertFalse(hub_mode::from_request(0, sesskey()));
        $this->assertFalse(hub_mode::saved());
        $this->client_saves_display(['classic' => true]);
        $this->assertFalse(hub_mode::saved());
        $this->assertArrayNotHasKey('classic', $this->saved_display());
    }
}
