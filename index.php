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
 * TODO describe file index
 *
 * @package    local_cohorts
 * @copyright  2024 Solent University {@link https://www.solent.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\context;
use core\url;
use local_cohorts\tables\cohorts_table;

require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_cohorts_cohorts');
$context = context\system::instance();
require_capability('moodle/cohort:manage', $context);

$url = new url('/local/cohorts/index.php', []);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());

$PAGE->set_heading(get_string('solentmanagedcohorts', 'local_cohorts'));
echo $OUTPUT->header();

$PAGE->set_title(get_string('solentmanagedcohorts', 'local_cohorts'));

echo format_text(get_string('managecohorts_help', 'local_cohorts'));

$table = new cohorts_table('local_cohorts_cohorts');
$table->out(100, false);

echo $OUTPUT->footer();
