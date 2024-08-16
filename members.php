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
 * TODO describe file members
 *
 * @package    local_cohorts
 * @copyright  2024 Solent University {@link https://www.solent.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_cohorts\tables\cohorts_members_table;

require('../../config.php');
require_once("$CFG->libdir/tablelib.php");
require_once("$CFG->dirroot/cohort/lib.php");

require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_cohorts_cohorts');

$context = context_system::instance();
require_capability('moodle/cohort:manage', $context);

$cohortid = required_param('cohortid', PARAM_INT);
$cohort = $DB->get_record('cohort', ['id' => $cohortid, 'component' => 'local_cohorts', 'contextid' => $context->id]);


$url = new moodle_url('/local/cohorts/members.php', []);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_heading(get_string('viewingmembers', 'local_cohorts', $cohort->name));
$PAGE->set_pagelayout('admin');
$PAGE->navbar->add(get_string('pluginname', 'local_cohorts'), new moodle_url('/local/cohorts/index.php'))
    ->add(get_string('viewmembersof', 'local_cohorts', format_string($cohort->name)));
echo $OUTPUT->header();

$PAGE->set_title(get_string('viewingmembers', 'local_cohorts', format_string($cohort->name)));

echo format_text($cohort->description);
echo '<br />';
$table = new cohorts_members_table('local_cohorts_cohorts_members', ['cohortid' => $cohortid]);
$table->out(100, true);

echo $OUTPUT->footer();
