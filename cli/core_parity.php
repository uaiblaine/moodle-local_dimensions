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
 * Compares a core tree with the core code the progress rule of local_dimensions was audited against.
 *
 * Exit status 0 when the tree matches the manifest, 1 when something differs (each difference on a
 * line of its own). With --rebuild, prints the manifest read again from the tree, for the commit that
 * records an audit (docs/core-parity.md in the repository).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_dimensions\local\core_fingerprint;

[$options, $unrecognised] = cli_get_params(
    ['root' => '', 'rebuild' => '', 'help' => false],
    ['h' => 'help']
);
if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode(PHP_EOL . '  ', $unrecognised)));
}
if ($options['help']) {
    echo "Compares a core tree with the core code the progress rule of local_dimensions was audited against.

Options:
--root=PATH        The core tree's directory holding lib/ and availability/ (default: this site's)
--rebuild=RELEASE  Print the manifest read again from the tree, audited against RELEASE
-h, --help         This help
";
    exit(0);
}

$root = $options['root'] !== '' ? rtrim($options['root'], '/') : $CFG->dirroot;
if (!is_dir($root . '/availability')) {
    cli_error("Not a core tree: $root");
}
$manifest = core_fingerprint::manifest();

if ($options['rebuild'] !== '') {
    $rebuilt = core_fingerprint::rebuild($root, $manifest, $options['rebuild']);
    echo json_encode($rebuilt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

$differences = core_fingerprint::differences($root, $manifest);
if (!$differences) {
    echo "Matches the core audited for {$manifest['audited']}." . PHP_EOL;
    exit(0);
}
echo "Differs from the core audited for {$manifest['audited']}:" . PHP_EOL;
foreach ($differences as $line) {
    echo "  $line" . PHP_EOL;
}
exit(1);
