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
 * Seeds the S0LOAD framework: 2,008 competencies on three levels, for the hub v2 spike.
 *
 * A development tool, run once on a dev stack and never shipped:
 * docker exec -i m503b-webserver-1 php < seed_s0load.php
 *
 * Shape: 8 domains, 10 competencies in each, 24 indicators under each competency, a rule on
 * twenty competencies and forty relations. It refuses to run twice.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');

use core_competency\api;
use core_competency\competency;
use core_competency\competency_framework;

\core\session\manager::set_user(get_admin());

if (competency_framework::record_exists_select('idnumber = ?', ['S0LOAD'])) {
    cli_error('S0LOAD is already there.');
}

// Any framework lends its scale and scale configuration.
$sources = competency_framework::get_records([], 'id', 'ASC', 0, 1);
if (!$sources) {
    cli_error('The site has no framework to copy a scale from.');
}
$source = reset($sources);
$framework = api::create_framework((object) [
    'shortname' => 'S0LOAD: 2,000 competencies (hub v2 spike)',
    'idnumber' => 'S0LOAD',
    'description' => 'Load framework of the hub v2 spike of 2026-11-23. Safe to delete.',
    'descriptionformat' => FORMAT_HTML,
    'visible' => 1,
    'scaleid' => $source->get('scaleid'),
    'scaleconfiguration' => $source->get('scaleconfiguration'),
    'contextid' => context_system::instance()->id,
    'taxonomies' => 'domain,competency,indicator,indicator',
]);
$frameworkid = (int) $framework->get('id');

$start = microtime(true);
$middle = [];
$count = 0;
for ($d = 1; $d <= 8; $d++) {
    $domain = api::create_competency((object) [
        'shortname' => "Domain {$d}",
        'idnumber' => "S0-{$d}",
        'competencyframeworkid' => $frameworkid,
        'parentid' => 0,
    ]);
    $count++;
    for ($c = 1; $c <= 10; $c++) {
        $competency = api::create_competency((object) [
            'shortname' => "Competency {$d}.{$c}",
            'idnumber' => "S0-{$d}.{$c}",
            'competencyframeworkid' => $frameworkid,
            'parentid' => (int) $domain->get('id'),
        ]);
        $count++;
        $middle[] = (int) $competency->get('id');
        for ($i = 1; $i <= 24; $i++) {
            api::create_competency((object) [
                'shortname' => "Indicator {$d}.{$c}.{$i}",
                'idnumber' => "S0-{$d}.{$c}.{$i}",
                'competencyframeworkid' => $frameworkid,
                'parentid' => (int) $competency->get('id'),
            ]);
            $count++;
        }
    }
    cli_writeln("Domain {$d}: {$count} competencies so far.");
}

// Creating a child resets its parent's rule, so the rules go on last.
for ($n = 0; $n < 20; $n++) {
    $competency = new competency($middle[$n * 4]);
    $competency->set('ruletype', 'core_competency\\competency_rule_all');
    $competency->set('ruleoutcome', competency::OUTCOME_COMPLETE);
    api::update_competency($competency->to_record());
}
for ($n = 0; $n < 40; $n++) {
    api::add_related_competency($middle[$n], $middle[79 - $n]);
}

$seconds = round(microtime(true) - $start, 1);
cli_writeln("S0LOAD: framework {$frameworkid}, {$count} competencies, 20 rules, 40 relations, {$seconds} s.");
