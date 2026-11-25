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
 * Tests for the set_competencies_appearance external function.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core_external\external_api;
use local_dimensions\competency_metadata_cache;
use local_dimensions\constants;
use local_dimensions\customfield\competency_handler;
use local_dimensions\event\competency_customfields_updated;
use local_dimensions\helper;
use local_dimensions\local\appearance_writer;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The bulk appearance service: its refusals first, each beside the accepted call that changes the rows, then what it
 * writes, logs and invalidates.
 *
 * Every call goes through the real door, external_api::call_external_function() with a sesskey, as the workspace's
 * dock sends it. The fixture puts the framework in a course category whose manager holds competencymanage there
 * only, so the convenient fact (a manager somewhere) and the right one (a manager in the framework's own context)
 * differ.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(set_competencies_appearance::class)]
#[CoversClass(appearance_writer::class)]
final class set_competencies_appearance_test extends \advanced_testcase {
    /** @var string Web service under test. */
    private const WSNAME = 'local_dimensions_set_competencies_appearance';

    /**
     * @var int The most statements one more competency may add to a call (reads and writes; a recordset counts three on
     *     PostgreSQL). Estimated from the code at about 18 (the save's own reads and writes, its audit log row, the
     *     validation's data read), not yet measured: set it from the first run's message, a little above.
     */
    private const PER_COMPETENCY = 30;

    /** @var string The background colour every fixture competency starts with. */
    private const SEEDED = '#111111';

    /**
     * Start from a clean handler: its field list is cached on a singleton that outlives a test.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);
        competency_handler::create()->reset_configuration_cache();
    }

    /**
     * Drop the sesskey the calls plant in $_POST, which resetAfterTest() does not restore.
     *
     * @return void
     */
    protected function tearDown(): void {
        unset($_POST['sesskey']);
        parent::tearDown();
    }

    /**
     * A framework in a course category with four competencies, another framework's competency in the same category,
     * a manager of the category, a manager of another category and a reader of the category.
     *
     * Every competency starts with the background colour SEEDED and the first option of tag 1.
     *
     * @return array Keys: frameworkid, a, b, c, d, foreign (competency ids), manager, othermanager, reader (users).
     */
    private function fixture(): array {
        $generator = $this->getDataGenerator();
        $ccg = $generator->get_plugin_generator('core_competency');
        $category = $generator->create_category();
        $categorycontext = \context_coursecat::instance((int) $category->id);
        $othercategory = $generator->create_category();
        $othercontext = \context_coursecat::instance((int) $othercategory->id);

        $framework = $ccg->create_framework(['contextid' => $categorycontext->id]);
        $frameworkid = (int) $framework->get('id');
        $ids = [];
        foreach (['a', 'b', 'c', 'd'] as $key) {
            $ids[$key] = (int) $ccg->create_competency(['competencyframeworkid' => $frameworkid])->get('id');
        }
        $foreignframework = $ccg->create_framework(['contextid' => $categorycontext->id]);
        $ids['foreign'] = (int) $ccg->create_competency(['competencyframeworkid' => $foreignframework->get('id')])->get('id');

        $this->setAdminUser();
        foreach ($ids as $id) {
            competency_handler::create()->instance_form_save((object) [
                'id' => $id,
                'customfield_' . constants::CFIELD_CUSTOMBGCOLOR => self::SEEDED,
                'customfield_' . constants::CFIELD_TAG1 => 1,
            ]);
        }

        $managerrole = $generator->create_role();
        assign_capability('moodle/competency:competencyview', CAP_ALLOW, $managerrole, \context_system::instance()->id);
        assign_capability('moodle/competency:competencymanage', CAP_ALLOW, $managerrole, \context_system::instance()->id);
        $readerrole = $generator->create_role();
        assign_capability('moodle/competency:competencyview', CAP_ALLOW, $readerrole, \context_system::instance()->id);

        $manager = $generator->create_user();
        role_assign($managerrole, (int) $manager->id, $categorycontext->id);
        $othermanager = $generator->create_user();
        role_assign($managerrole, (int) $othermanager->id, $othercontext->id);
        $reader = $generator->create_user();
        role_assign($readerrole, (int) $reader->id, $categorycontext->id);
        accesslib_clear_all_caches_for_unit_testing();

        return $ids + [
            'frameworkid' => $frameworkid,
            'manager' => $manager,
            'othermanager' => $othermanager,
            'reader' => $reader,
        ];
    }

    /**
     * Call the web service as the current user, through the door the workspace uses.
     *
     * @param int $frameworkid The framework.
     * @param array $ids The competencies.
     * @param array $fields The field entries.
     * @return array The raw response: error, and data or exception.
     */
    private function call(int $frameworkid, array $ids, array $fields): array {
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function(self::WSNAME, [
            'frameworkid' => $frameworkid,
            'competencyids' => $ids,
            'fields' => $fields,
        ]);
    }

    /**
     * Call the web service and require success.
     *
     * @param int $frameworkid The framework.
     * @param array $ids The competencies.
     * @param array $fields The field entries.
     * @return array The cleaned response data.
     */
    private function accepted(int $frameworkid, array $ids, array $fields): array {
        $response = $this->call($frameworkid, $ids, $fields);
        $this->assertFalse($response['error'], json_encode($response));
        return $response['data'];
    }

    /**
     * Call the web service and require a refusal.
     *
     * @param int $frameworkid The framework.
     * @param array $ids The competencies.
     * @param array $fields The field entries.
     * @param string $errorcode The refusal's error code.
     * @return \stdClass The exception as the door reports it.
     */
    private function refused(int $frameworkid, array $ids, array $fields, string $errorcode): \stdClass {
        $response = $this->call($frameworkid, $ids, $fields);
        $this->assertTrue($response['error'], 'The call was accepted: ' . json_encode($response));
        $this->assertSame($errorcode, $response['exception']->errorcode, json_encode($response['exception']));
        return $response['exception'];
    }

    /**
     * The appearance fields of a competency as stored, read past every cache.
     *
     * @param int $id The competency.
     * @return array Map of the request's field name to the stored value (a hex, or an option position).
     */
    private function stored(int $id): array {
        $byshortname = [];
        foreach (competency_handler::create()->get_instance_data($id, true) as $data) {
            $byshortname[$data->get_field()->get('shortname')] = $data->get_value();
        }
        $values = [];
        foreach (set_competencies_appearance::FIELDS as $name => $shortname) {
            $values[$name] = $byshortname[$shortname] ?? null;
        }
        return $values;
    }

    /**
     * One "set" entry.
     *
     * @param string $name The field.
     * @param string $value The value.
     * @return array The entry.
     */
    private static function set(string $name, string $value): array {
        return ['name' => $name, 'action' => 'set', 'value' => $value];
    }

    /**
     * A visitor who is not logged in is refused at the door, and nothing is written; the control is the manager's
     * same call, which writes.
     *
     * @return void
     */
    public function test_a_visitor_who_is_not_logged_in_is_refused(): void {
        $f = $this->fixture();
        $this->setUser(0);
        $this->refused($f['frameworkid'], [$f['a']], [self::set('bgcolor', '#abcdef')], 'servicerequireslogin');
        $this->assertSame(self::SEEDED, $this->stored($f['a'])['bgcolor']);

        $this->setUser($f['manager']);
        $this->accepted($f['frameworkid'], [$f['a']], [self::set('bgcolor', '#abcdef')]);
        $this->assertSame('#abcdef', $this->stored($f['a'])['bgcolor']);
    }

    /**
     * The guest is refused as a guest, before the capability is asked, and nothing is written.
     *
     * @return void
     */
    public function test_the_guest_is_refused(): void {
        $f = $this->fixture();
        $this->setGuestUser();
        $this->refused($f['frameworkid'], [$f['a']], [self::set('bgcolor', '#abcdef')], 'requireloginerror');
        $this->assertSame(self::SEEDED, $this->stored($f['a'])['bgcolor']);
    }

    /**
     * A reader of the framework's category, who does not manage competencies, is refused.
     *
     * @return void
     */
    public function test_a_reader_who_does_not_manage_is_refused(): void {
        $f = $this->fixture();
        $this->setUser($f['reader']);
        $this->refused($f['frameworkid'], [$f['a']], [self::set('bgcolor', '#abcdef')], 'nopermissions');
        $this->assertSame(self::SEEDED, $this->stored($f['a'])['bgcolor']);
    }

    /**
     * A manager of another category is refused: managing competencies somewhere is not managing them in the
     * framework's own context. The control is the framework category's manager, whose same call writes.
     *
     * @return void
     */
    public function test_a_manager_of_another_category_is_refused(): void {
        $f = $this->fixture();
        $this->setUser($f['othermanager']);
        $this->refused($f['frameworkid'], [$f['a'], $f['b']], [self::set('bgcolor', '#abcdef')], 'nopermissions');
        $this->assertSame(self::SEEDED, $this->stored($f['a'])['bgcolor']);
        $this->assertSame(self::SEEDED, $this->stored($f['b'])['bgcolor']);

        $this->setUser($f['manager']);
        $this->accepted($f['frameworkid'], [$f['a'], $f['b']], [self::set('bgcolor', '#abcdef')]);
        $this->assertSame('#abcdef', $this->stored($f['b'])['bgcolor']);
    }

    /**
     * A competency of another framework among valid ones refuses the whole call before any write: neither the valid
     * ones nor the foreign one change. The control is the same call without the foreign id, which writes both.
     *
     * @return void
     */
    public function test_a_competency_of_another_framework_refuses_the_whole_call(): void {
        $f = $this->fixture();
        $this->setUser($f['manager']);
        $fields = [self::set('bgcolor', '#abcdef')];
        $this->refused($f['frameworkid'], [$f['a'], $f['foreign'], $f['b']], $fields, 'invalidparameter');
        foreach (['a', 'foreign', 'b'] as $key) {
            $this->assertSame(self::SEEDED, $this->stored($f[$key])['bgcolor'], $key);
        }

        $this->accepted($f['frameworkid'], [$f['a'], $f['b']], [self::set('bgcolor', '#abcdef')]);
        $this->assertSame('#abcdef', $this->stored($f['a'])['bgcolor']);
        $this->assertSame('#abcdef', $this->stored($f['b'])['bgcolor']);
    }

    /**
     * A missing id refuses the call with the very answer a foreign one gets, so the call tells nothing about what
     * lies outside the framework.
     *
     * @return void
     */
    public function test_a_missing_competency_gets_the_answer_a_foreign_one_gets(): void {
        $f = $this->fixture();
        $this->setUser($f['manager']);
        $fields = [self::set('bgcolor', '#abcdef')];
        $missing = $this->refused($f['frameworkid'], [$f['a'], $f['c'] + 1000], $fields, 'invalidparameter');
        $foreign = $this->refused($f['frameworkid'], [$f['a'], $f['foreign']], $fields, 'invalidparameter');
        $this->assertSame($foreign->message, $missing->message);
        $this->assertSame($foreign->debuginfo ?? null, $missing->debuginfo ?? null);
        $this->assertSame(self::SEEDED, $this->stored($f['a'])['bgcolor']);
    }

    /**
     * More than 200 ids are refused for their number. The control, 200 ids of which most do not exist, is refused for
     * another reason, so the bound is what refused the 201.
     *
     * @return void
     */
    public function test_more_than_two_hundred_competencies_are_refused(): void {
        $f = $this->fixture();
        $this->setUser($f['manager']);
        $fields = [self::set('bgcolor', '#abcdef')];
        $over = range($f['a'], $f['a'] + set_competencies_appearance::MAX_COMPETENCIES);
        $this->assertCount(201, $over);
        $refusal = $this->refused($f['frameworkid'], $over, $fields, 'invalidparameter');
        $this->assertStringContainsString('At most 200', (string) ($refusal->debuginfo ?? ''));

        $bounded = $this->refused($f['frameworkid'], array_slice($over, 0, 200), $fields, 'invalidparameter');
        $this->assertStringNotContainsString('At most 200', (string) ($bounded->debuginfo ?? ''));
        $this->assertSame(self::SEEDED, $this->stored($f['a'])['bgcolor']);
    }

    /**
     * A field outside the appearance allowlist is refused, and nothing is written, not even the allowed field beside
     * it.
     *
     * @return void
     */
    public function test_an_unknown_field_is_refused(): void {
        $f = $this->fixture();
        $this->setUser($f['manager']);
        $exception = $this->refused(
            $f['frameworkid'],
            [$f['a']],
            [self::set('bgcolor', '#abcdef'), self::set('customscss', 'body{}')],
            'invalidparameter'
        );
        // The allowlist answers, by name, before any field is looked up: a later guard refuses an unknown
        // name too, so the code alone would not tell the two apart.
        $this->assertStringContainsString('Unknown appearance field: customscss', (string) ($exception->debuginfo ?? ''));
        $this->assertSame(self::SEEDED, $this->stored($f['a'])['bgcolor']);
    }

    /**
     * A colour that is not a hex colour is refused; the control, a hex one, is stored.
     *
     * @return void
     */
    public function test_an_invalid_colour_is_refused(): void {
        $f = $this->fixture();
        $this->setUser($f['manager']);
        foreach (['red', '#12345', '#abcdeg', 'url(x)'] as $colour) {
            $this->refused($f['frameworkid'], [$f['a']], [self::set('textcolor', $colour)], 'invalidparameter');
            $this->assertSame('', (string) $this->stored($f['a'])['textcolor'], $colour);
        }
        $this->accepted($f['frameworkid'], [$f['a']], [self::set('textcolor', 'fff')]);
        $this->assertSame('#fff', $this->stored($f['a'])['textcolor']);
    }

    /**
     * A select's position outside its options, or its empty position, is refused; the control, the last option, is
     * stored.
     *
     * @return void
     */
    public function test_a_position_outside_the_options_is_refused(): void {
        $f = $this->fixture();
        $this->setUser($f['manager']);
        $field = helper::find_field_by_shortname(constants::CFIELD_TYPE, helper::AREA_COMPETENCY);
        $count = count(helper::select_raw_options($field));
        foreach (['0', (string) ($count + 1), '-1', '1.5', 'Lessons'] as $value) {
            $this->refused($f['frameworkid'], [$f['a']], [self::set('type', $value)], 'invalidparameter');
            $this->assertSame(0, (int) $this->stored($f['a'])['type'], $value);
        }
        $this->accepted($f['frameworkid'], [$f['a']], [self::set('type', (string) $count)]);
        $this->assertSame($count, (int) $this->stored($f['a'])['type']);
    }

    /**
     * An action other than set and clear is refused.
     *
     * @return void
     */
    public function test_an_unknown_action_is_refused(): void {
        $f = $this->fixture();
        $this->setUser($f['manager']);
        $this->refused(
            $f['frameworkid'],
            [$f['a']],
            [['name' => 'bgcolor', 'action' => 'replace', 'value' => '#abcdef']],
            'invalidparameter'
        );
        $this->assertSame(self::SEEDED, $this->stored($f['a'])['bgcolor']);
    }

    /**
     * A call naming no field is refused; so is one naming a field twice, which could store either value. The control
     * names the field once and stores it.
     *
     * @return void
     */
    public function test_no_field_or_a_field_twice_is_refused(): void {
        $f = $this->fixture();
        $this->setUser($f['manager']);
        $this->refused($f['frameworkid'], [$f['a']], [], 'invalidparameter');
        $this->refused(
            $f['frameworkid'],
            [$f['a']],
            [self::set('bgcolor', '#abcdef'), self::set('bgcolor', '#fedcba')],
            'invalidparameter'
        );
        $this->assertSame(self::SEEDED, $this->stored($f['a'])['bgcolor']);

        $this->accepted($f['frameworkid'], [$f['a']], [self::set('bgcolor', '#fedcba')]);
        $this->assertSame('#fedcba', $this->stored($f['a'])['bgcolor']);
    }

    /**
     * With competencies disabled on the site the call is refused, as every competency service is.
     *
     * @return void
     */
    public function test_competencies_disabled_refuse_the_call(): void {
        $f = $this->fixture();
        $this->setUser($f['manager']);
        set_config('enabled', 0, 'core_competency');
        $this->refused($f['frameworkid'], [$f['a']], [self::set('bgcolor', '#abcdef')], 'competenciesarenotenabled');
        $this->assertSame(self::SEEDED, $this->stored($f['a'])['bgcolor']);
    }

    /**
     * Change one setting of a field's configuration, as an administrator would on the custom fields page.
     *
     * @param string $shortname The field.
     * @param string $key The setting.
     * @param mixed $value Its value.
     * @return void
     */
    private function configure(string $shortname, string $key, $value): void {
        global $DB;
        $field = helper::find_field_by_shortname($shortname, helper::AREA_COMPETENCY);
        $config = json_decode((string) $DB->get_field('customfield_field', 'configdata', ['id' => $field->get('id')]), true);
        $config[$key] = $value;
        $DB->set_field('customfield_field', 'configdata', json_encode($config), ['id' => $field->get('id')]);
        competency_handler::create()->reset_configuration_cache();
    }

    /**
     * The fields' own rules apply as the form applies them: a required select cannot be cleared, and a field taking
     * unique values refuses one value for two competencies. The controls: the same select set, and the unique field
     * set on one competency.
     *
     * @return void
     */
    public function test_the_fields_own_rules_apply(): void {
        $f = $this->fixture();
        $this->setUser($f['manager']);
        $this->configure(constants::CFIELD_TAG1, 'required', 1);
        $this->refused($f['frameworkid'], [$f['a']], [['name' => 'tag1', 'action' => 'clear', 'value' => '']], 'invalidparameter');
        $this->assertSame(1, (int) $this->stored($f['a'])['tag1']);
        $this->accepted($f['frameworkid'], [$f['a']], [self::set('tag1', '2')]);
        $this->assertSame(2, (int) $this->stored($f['a'])['tag1']);

        $this->configure(constants::CFIELD_TAG2, 'uniquevalues', 1);
        $this->refused($f['frameworkid'], [$f['a'], $f['b']], [self::set('tag2', '2')], 'invalidparameter');
        $this->assertSame(0, (int) $this->stored($f['a'])['tag2']);
        $this->assertSame(0, (int) $this->stored($f['b'])['tag2']);
        $this->accepted($f['frameworkid'], [$f['b']], [self::set('tag2', '2')]);
        $this->assertSame(2, (int) $this->stored($f['b'])['tag2']);
    }

    /**
     * Set then clear each field on two competencies, the manager of the framework's category being the caller; the
     * answer gives the effective values in plain text.
     *
     * @return void
     */
    public function test_each_field_is_set_and_cleared(): void {
        $f = $this->fixture();
        $this->setUser($f['manager']);
        $ids = [$f['a'], $f['b']];
        $cases = ['bgcolor' => '#0a0b0c', 'textcolor' => '#fafafa', 'tag1' => '2', 'tag2' => '3', 'type' => '2'];
        foreach ($cases as $name => $value) {
            $data = $this->accepted($f['frameworkid'], $ids, [self::set($name, $value)]);
            foreach ($ids as $id) {
                $this->assertEquals($value, $this->stored($id)[$name], "{$name} set on {$id}");
            }
            $this->assertEqualsCanonicalizing($ids, $data['changed']);
            $answered = $data['competencies'][0]['fields'];
            $this->assertCount(1, $answered);
            $this->assertSame($name, $answered[0]['name']);
            if (in_array($name, ['bgcolor', 'textcolor'], true)) {
                $this->assertSame($value, $answered[0]['value']);
            } else {
                $field = helper::find_field_by_shortname(set_competencies_appearance::FIELDS[$name], helper::AREA_COMPETENCY);
                $this->assertSame(helper::select_raw_options($field)[(int) $value - 1], $answered[0]['value']);
            }

            $this->accepted($f['frameworkid'], $ids, [['name' => $name, 'action' => 'clear', 'value' => '']]);
            foreach ($ids as $id) {
                $this->assertEmpty($this->stored($id)[$name], "{$name} cleared on {$id}");
            }
        }
    }

    /**
     * A field the call does not name is left as it is: tag 1 keeps its option while the colour changes.
     *
     * @return void
     */
    public function test_a_field_not_named_is_left_as_it_is(): void {
        $f = $this->fixture();
        $this->setUser($f['manager']);
        $this->accepted($f['frameworkid'], [$f['a']], [self::set('bgcolor', '#abcdef')]);
        $stored = $this->stored($f['a']);
        $this->assertSame('#abcdef', $stored['bgcolor']);
        $this->assertSame(1, (int) $stored['tag1']);
    }

    /**
     * The audit event fires once per competency whose effective values changed, and not for one already holding
     * the value; the control is the competency that changed, logged with the diff.
     *
     * @return void
     */
    public function test_the_audit_event_names_each_changed_competency_once(): void {
        $f = $this->fixture();
        competency_handler::create()->instance_form_save((object) [
            'id' => $f['b'],
            'customfield_' . constants::CFIELD_CUSTOMBGCOLOR => '#abcdef',
        ]);
        $this->setUser($f['manager']);

        $sink = $this->redirectEvents();
        $data = $this->accepted($f['frameworkid'], [$f['a'], $f['b']], [self::set('bgcolor', '#abcdef')]);
        $events = array_values(array_filter(
            $sink->get_events(),
            static fn($event): bool => $event instanceof competency_customfields_updated
        ));
        $sink->close();

        $this->assertCount(1, $events);
        $this->assertSame($f['a'], (int) $events[0]->objectid);
        $this->assertSame(
            ['old' => self::SEEDED, 'new' => '#abcdef'],
            $events[0]->other['changed'][constants::CFIELD_CUSTOMBGCOLOR]
        );
        $this->assertSame([$f['a']], $data['changed']);
    }

    /**
     * A read through the plugin's metadata cache after the call returns the new value, though the old one was cached
     * (with static acceleration) before it.
     *
     * @return void
     */
    public function test_the_metadata_cache_is_invalidated(): void {
        $f = $this->fixture();
        $this->setUser($f['manager']);
        $this->assertSame(self::SEEDED, competency_metadata_cache::get_competency_metadata($f['a'])['bgcolor']);

        $this->accepted($f['frameworkid'], [$f['a']], [self::set('bgcolor', '#abcdef')]);
        $this->assertSame('#abcdef', competency_metadata_cache::get_competency_metadata($f['a'])['bgcolor']);
    }

    /**
     * A write that fails on the last competency leaves the first as it was: the writes share one transaction. The
     * failing writer counts its calls, so the failure is known to have come after the first write.
     *
     * The audit event of the first write is triggered inside the transaction. An event sink (redirectEvents()) records
     * it at the trigger and knows nothing of the rollback, so the test asks what a request keeps: the standard log
     * store, written unbuffered in the same transaction, holds no row for it after the rollback, and holds one after
     * the same call succeeds (the control).
     *
     * @return void
     */
    public function test_a_failure_on_the_last_competency_writes_nothing(): void {
        // The test's own transaction would make the service's the inner one, which cannot really roll back.
        $this->preventResetByRollback();
        $f = $this->fixture();
        $this->setUser($f['manager']);
        $writer = new class ($f['b']) extends appearance_writer {
            /** @var int The competencies saved before the failure. */
            public int $saved = 0;

            /** @var int The competency whose save throws. */
            private int $failon;

            /**
             * Fail on one competency.
             *
             * @param int $failon The competency whose save throws.
             */
            public function __construct(int $failon) {
                $this->failon = $failon;
            }

            /**
             * Save, unless this is the competency to fail on.
             *
             * @param \stdClass $record The record.
             * @return void
             */
            public function save(\stdClass $record): void {
                if ((int) $record->id === $this->failon) {
                    throw new \moodle_exception('error');
                }
                parent::save($record);
                $this->saved++;
            }
        };
        \core\di::set(appearance_writer::class, $writer);
        $this->log_to_the_standard_store();

        $this->refused($f['frameworkid'], [$f['a'], $f['b']], [self::set('bgcolor', '#abcdef')], 'error');
        $this->assertSame(1, $writer->saved);
        $this->assertSame(self::SEEDED, $this->stored($f['a'])['bgcolor']);
        $this->assertSame(self::SEEDED, $this->stored($f['b'])['bgcolor']);
        $this->assertSame(0, $this->logged($f['a']));

        \core\di::set(appearance_writer::class, new appearance_writer());
        $this->accepted($f['frameworkid'], [$f['a'], $f['b']], [self::set('bgcolor', '#abcdef')]);
        $this->assertSame(1, $this->logged($f['a']));
    }

    /**
     * Log events to the standard store at once, as a site with it enabled does, so a test reads what a request keeps.
     *
     * @return void
     */
    private function log_to_the_standard_store(): void {
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        get_log_manager(true);
    }

    /**
     * How many custom-field audit events the standard store kept for a competency.
     *
     * @param int $id The competency.
     * @return int The rows.
     */
    private function logged(int $id): int {
        global $DB;
        return $DB->count_records('logstore_standard_log', [
            'eventname' => '\\' . competency_customfields_updated::class,
            'objectid' => $id,
        ]);
    }

    /**
     * A competency deleted while the call writes (here by the writer itself, before that competency's save) makes the
     * call refuse at the re-read before the commit: nothing is written for any competency, and no data row outlives
     * the deleted one. The caller is a site administrator, the case where a deleted competency's save would otherwise
     * be allowed.
     *
     * @return void
     */
    public function test_a_competency_deleted_during_the_write_writes_nothing(): void {
        global $DB;
        // The test's own transaction would make the service's the inner one, which cannot really roll back.
        $this->preventResetByRollback();
        $f = $this->fixture();
        $this->setAdminUser();
        $rows = $DB->count_records('customfield_data', ['instanceid' => $f['b']]);
        $writer = new class ($f['b']) extends appearance_writer {
            /** @var int The competency deleted before its save. */
            private int $victim;

            /**
             * Delete one competency before its save.
             *
             * @param int $victim The competency.
             */
            public function __construct(int $victim) {
                $this->victim = $victim;
            }

            /**
             * Delete the victim's row, then save as usual.
             *
             * @param \stdClass $record The record.
             * @return void
             */
            public function save(\stdClass $record): void {
                global $DB;
                if ((int) $record->id === $this->victim) {
                    $DB->delete_records('competency', ['id' => $this->victim]);
                }
                parent::save($record);
            }
        };
        \core\di::set(appearance_writer::class, $writer);

        $this->refused($f['frameworkid'], [$f['a'], $f['b']], [self::set('bgcolor', '#abcdef')], 'invalidparameter');
        $this->assertSame(self::SEEDED, $this->stored($f['a'])['bgcolor']);
        $this->assertTrue($DB->record_exists('competency', ['id' => $f['b']]));
        $this->assertSame($rows, $DB->count_records('customfield_data', ['instanceid' => $f['b']]));
        $this->assertSame(self::SEEDED, $this->stored($f['b'])['bgcolor']);
    }

    /**
     * No id, an id of 0 and a negative id are refused, each as a competency outside the framework is, and nothing is
     * written for the valid id beside them.
     *
     * @return void
     */
    public function test_no_id_a_zero_id_and_a_negative_id_are_refused(): void {
        $f = $this->fixture();
        $this->setUser($f['manager']);
        $fields = [self::set('bgcolor', '#abcdef')];
        $this->refused($f['frameworkid'], [], $fields, 'invalidparameter');
        $foreign = $this->refused($f['frameworkid'], [$f['a'], $f['foreign']], $fields, 'invalidparameter');
        foreach ([0, -1] as $id) {
            $refusal = $this->refused($f['frameworkid'], [$f['a'], $id], $fields, 'invalidparameter');
            $this->assertSame($foreign->debuginfo ?? null, $refusal->debuginfo ?? null, (string) $id);
        }
        $this->assertSame(self::SEEDED, $this->stored($f['a'])['bgcolor']);
    }

    /**
     * A framework that does not exist is refused as an invalid parameter, and one the caller does not manage, a
     * framework in the system context called by a manager of a category only, as a missing capability: core's own
     * competency services answer the same two cases differently too (invalidrecord, then nopermissions).
     *
     * @return void
     */
    public function test_a_missing_framework_and_one_not_managed_are_refused(): void {
        global $DB;
        $f = $this->fixture();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $sitefw = $ccg->create_framework();
        $sitecompetency = (int) $ccg->create_competency(['competencyframeworkid' => $sitefw->get('id')])->get('id');
        $this->setUser($f['manager']);
        $fields = [self::set('bgcolor', '#abcdef')];
        $missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {competency_framework}') + 1;
        $this->refused($missing, [$f['a']], $fields, 'invalidparameter');
        $this->refused((int) $sitefw->get('id'), [$sitecompetency], $fields, 'nopermissions');
        $this->assertSame('', (string) $this->stored($sitecompetency)['bgcolor']);
        $this->assertSame(self::SEEDED, $this->stored($f['a'])['bgcolor']);
    }

    /**
     * Give tag 2 two options whose labels hold markup, and the site its strip-tags setting.
     *
     * @param bool $striptags Whether format_string() strips every tag.
     * @return void
     */
    private function markup_options(bool $striptags): void {
        $this->configure(constants::CFIELD_TAG2, 'options', "R&D <beta>\n<b>Bold</b>");
        \core\di::get(\core\formatting::class)->set_striptags($striptags);
    }

    /**
     * The answer for an option label holding markup: the call succeeds, the position is stored, and the label comes
     * back as text a PARAM_TEXT field keeps unchanged, with no tag left.
     *
     * @param array $f The fixture.
     * @return array The two labels answered, by position.
     */
    private function answered_labels(array $f): array {
        $labels = [];
        foreach (['1', '2'] as $position) {
            $data = $this->accepted($f['frameworkid'], [$f['a']], [self::set('tag2', $position)]);
            $this->assertSame((int) $position, (int) $this->stored($f['a'])['tag2']);
            $value = $data['competencies'][0]['fields'][0]['value'];
            $this->assertSame($value, clean_param($value, PARAM_TEXT));
            $this->assertStringNotContainsString('<', $value);
            $labels[$position] = $value;
        }
        return $labels;
    }

    /**
     * With formatstringstriptags on, labels holding markup come back as their text: "R&D <beta>" loses the tag it
     * seems to hold, "<b>Bold</b>" is "Bold".
     *
     * @return void
     */
    public function test_option_labels_holding_markup_come_back_as_text_with_tags_stripped(): void {
        $f = $this->fixture();
        $this->setUser($f['manager']);
        $this->markup_options(true);
        $labels = $this->answered_labels($f);
        $this->assertStringStartsWith('R&D', $labels['1']);
        $this->assertSame('Bold', $labels['2']);
    }

    /**
     * With formatstringstriptags off, format_string() returns cleaned HTML ("<b>Bold</b>", "R&amp;D"), which a
     * PARAM_TEXT answer would refuse after the commit; the answer is the same plain text as with it on.
     *
     * @return void
     */
    public function test_option_labels_holding_markup_come_back_as_text_with_tags_kept(): void {
        $f = $this->fixture();
        $this->setUser($f['manager']);
        $this->markup_options(false);
        $labels = $this->answered_labels($f);
        $this->assertStringStartsWith('R&D', $labels['1']);
        $this->assertSame('Bold', $labels['2']);
    }

    /**
     * The cost of a call grows by a bounded number of statements per competency: four competencies against two, the
     * same field changed on each, after a call that warmed the handler's field list and the capability cache. A
     * change that multiplies the work per competency (reading the competency and its framework again for every field,
     * as can_edit() did before the handler remembered the call's contexts) exceeds the bound.
     *
     * @return void
     */
    public function test_the_statement_count_grows_by_a_bounded_amount_per_competency(): void {
        global $DB;
        $f = $this->fixture();
        $this->setUser($f['manager']);
        $this->accepted($f['frameworkid'], [$f['c']], [self::set('bgcolor', '#000001')]);
        // The statements one call runs, reads and writes, setting a colour none of the competencies has.
        $count = function (array $ids, string $colour) use ($DB, $f): int {
            $before = $DB->perf_get_reads() + $DB->perf_get_writes();
            $this->accepted($f['frameworkid'], $ids, [self::set('bgcolor', $colour)]);
            return $DB->perf_get_reads() + $DB->perf_get_writes() - $before;
        };
        $two = $count([$f['a'], $f['b']], '#000002');
        $four = $count([$f['a'], $f['b'], $f['c'], $f['d']], '#000003');
        $percompetency = ($four - $two) / 2;
        $this->assertGreaterThan(0, $percompetency);
        $this->assertLessThanOrEqual(
            self::PER_COMPETENCY,
            $percompetency,
            "Two competencies cost {$two} statements, four {$four}."
        );
    }
}
