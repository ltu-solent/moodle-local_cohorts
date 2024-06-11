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
 * TODO describe file edit
 *
 * @package    local_cohorts
 * @copyright  2024 Solent University {@link https://www.solent.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_cohorts\helper;

require('../../config.php');
require_once($CFG->dirroot . '/cohort/lib.php');

$id        = optional_param('id', 0, PARAM_INT);
$enable    = optional_param('enable', 0, PARAM_BOOL);
$disable   = optional_param('disable', 0, PARAM_BOOL);
$delete    = optional_param('delete', 0, PARAM_BOOL);
$show      = optional_param('show', 0, PARAM_BOOL);
$hide      = optional_param('hide', 0, PARAM_BOOL);
$confirm   = optional_param('confirm', 0, PARAM_BOOL);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

require_login();

$cohort = $DB->get_record('cohort', ['id' => $id], '*', MUST_EXIST);
$context = context::instance_by_id($cohort->contextid, MUST_EXIST);
$status = $DB->get_record('local_cohorts_status', ['cohortid' => $cohort->id]);

require_capability('moodle/cohort:manage', $context);

$PAGE->set_context($context);
$baseurl = new moodle_url('/local/cohorts/edit.php', ['id' => $cohort->id]);
$PAGE->set_url($baseurl);

if ($returnurl) {
    $returnurl = new moodle_url($returnurl);
} else {
    $returnurl = new moodle_url('/local/cohorts/index.php');
}

// We're only going to manage our own cohorts.
if ($cohort->component != 'local_cohorts') {
    redirect($returnurl);
}

require_sesskey();

if ($hide && $cohort->id) {
    if ($cohort->visible) {
        $record = (object)['id' => $cohort->id, 'visible' => 0, 'contextid' => $cohort->contextid];
        cohort_update_cohort($record);
    }
    redirect($returnurl);
}

if ($show && $cohort->id) {
    if (!$cohort->visible) {
        $record = (object)['id' => $cohort->id, 'visible' => 1, 'contextid' => $cohort->contextid];
        cohort_update_cohort($record);
    }
    redirect($returnurl);
}

if ($enable && $cohort->id) {
    if ($status->enabled == 0) {
        helper::update_cohort_status($cohort, true);
    }
    redirect($returnurl);
}

if ($disable && $cohort->id) {
    if ($confirm) {
        helper::update_cohort_status($cohort, false);
        redirect($returnurl);
    }
    $strheading = get_string('disablecohort', 'local_cohorts', format_string($cohort->name));
    $PAGE->navbar->add($strheading);
    $PAGE->set_title($strheading);
    echo $OUTPUT->header();
    echo $OUTPUT->heading($strheading);
    $yesurl = new moodle_url('/local/cohorts/edit.php', [
        'id' => $cohort->id,
        'disable' => 1,
        'confirm' => 1,
        'returnurl' => $returnurl->out_as_local_url(),
    ]);
    $message = get_string('disableconfirm', 'local_cohorts', format_string($cohort->name));
    echo $OUTPUT->confirm($message, $yesurl, $returnurl);
    echo $OUTPUT->footer();
    die();
}

if ($delete && $cohort->id) {
    if ($confirm) {
        cohort_delete_cohort($cohort);
        redirect($returnurl);
    }
    $strheading = get_string('deletecohortfor', 'local_cohorts', format_string($cohort->name));
    $PAGE->navbar->add($strheading);
    $PAGE->set_title($strheading);
    echo $OUTPUT->header();
    echo $OUTPUT->heading($strheading);
    $yesurl = new moodle_url('/local/cohorts/edit.php', [
        'id' => $cohort->id,
        'delete' => 1,
        'confirm' => 1,
        'returnurl' => $returnurl->out_as_local_url(),
    ]);
    $message = get_string('deleteconfirm', 'local_cohorts', format_string($cohort->name));
    echo $OUTPUT->confirm($message, $yesurl, $returnurl);
    echo $OUTPUT->footer();
    die();
}

redirect($returnurl);
