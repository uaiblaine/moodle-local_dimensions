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
 * Open a browser session for a user without a password (development sites only, CLI).
 *
 * The screenshot capture drives a headless browser, and nothing types a password into it: this
 * script, run by someone who already holds a shell on the server, writes a session record through
 * the site's own session handler, as core's login_user() would, and prints the cookie that
 * carries it, "MoodleSession<suffix>=<sid>". The session keeps the theme named by --theme in
 * $SESSION, which core honours while allowthemechangeonurl is on, so the pictures show Boost
 * whatever the site theme is.
 *
 * Usage, inside the stack's webserver container:
 *   php public/local/dimensions/docs/screenshots/tools/session.php --username=demo.student [--theme=boost]
 *   php public/local/dimensions/docs/screenshots/tools/session.php --destroy=SID
 *
 * Destroy each session once the capture is done: it is a live sign-in until it times out.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(
    ['username' => '', 'theme' => 'boost', 'destroy' => '', 'help' => false],
    ['h' => 'help']
);
if ($unrecognised || $options['help'] || ($options['username'] === '' && $options['destroy'] === '')) {
    echo "Print a session cookie for a user, or destroy one.\n\nOptions:\n  --username=NAME  the user\n"
        . "  --theme=NAME     session theme (boost)\n  --destroy=SID    end that session\n";
    exit($options['help'] ? 0 : 1);
}

$class = \core\session\manager::get_handler_class();
$handler = new $class();
$handler->init();

if ($options['destroy'] !== '') {
    $handler->destroy(clean_param($options['destroy'], PARAM_ALPHANUM));
    echo 'Destroyed.' . PHP_EOL;
    exit(0);
}

$user = get_complete_user_data('username', $options['username']);
if (!$user) {
    cli_error('No such user: ' . $options['username']);
}

$handler->set_requires_write_lock(true);
$sid = bin2hex(random_bytes(16));
session_id($sid);
$handler->start();

// What manager::set_user() leaves in the session after complete_user_login().
unset($user->description, $user->password);
$user->sesskey = random_string(10);
$_SESSION['USER'] = $user;
$_SESSION['SESSION'] = (object) ['theme' => clean_param($options['theme'], PARAM_THEME)];
$handler->add_session((int) $user->id);
session_write_close();

echo 'MoodleSession' . $CFG->sessioncookie . '=' . $sid . PHP_EOL;
