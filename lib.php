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
 * Cohorts lib file.
 *
 * @package   local_cohorts
 * @author    Mark Sharp <mark.sharp@solent.ac.uk>
 * @copyright 2022 Solent University {@link https://www.solent.ac.uk}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/lib/adminlib.php");
require_once("$CFG->dirroot/cohort/lib.php");

/**
 * Process academic cohort
 *
 * @return void
 */
function academic() {
    global $DB;
    $validusers = $DB->get_records('user', ['deleted' => 0, 'department' => 'academic']);
    $cohortid = $DB->get_field('cohort', 'id', ['idnumber' => 'academic']);
    if ($validusers) {
        foreach ($validusers as $user) {
            if ($user->suspended == 0 && !cohort_is_member($cohortid, $user->id)) {
                cohort_add_member($cohortid, $user->id);
                mtrace("{$user->username} added to 'academic' cohort");
            }
            if ($user->suspended == 1 && cohort_is_member($cohortid, $user->id)) {
                cohort_remove_member($cohortid, $user->id);
                mtrace("{$user->username} removed from 'academic' cohort");
            }
        }
    }
    $newmembers = $DB->get_records('cohort_members', ['cohortid' => $cohortid]);
    // Remove invalid users from cohort.
    foreach ($newmembers as $member) {
        $memberdetails = $DB->get_record('user', array('id' => $member->userid));
        if ($memberdetails->department != 'academic' || $memberdetails->suspended == 1) {
            cohort_remove_member($cohortid, $member->userid);
            mtrace("{$memberdetails->username} removed from 'academic' cohort");
        }
    }
}

/**
 * Process Support cohort.
 *
 * @return void
 */
function support() {
    global $DB;
    $validusers = $DB->get_records('user', ['deleted' => 0, 'department' => 'support']);
    $cohortid = $DB->get_field('cohort', 'id', ['idnumber' => 'support']);
    if ($validusers) {
        foreach ($validusers as $user) {
            if ($user->suspended == 0 && !cohort_is_member($cohortid, $user->id)) {
                cohort_add_member($cohortid, $user->id);
                mtrace("{$user->username} added to 'support' cohort");
            }
            if ($user->suspended == 1 && cohort_is_member($cohortid, $user->id)) {
                cohort_remove_member($cohortid, $user->id);
                mtrace("{$user->username} removed from 'support' cohort");
            }
        }
    }
    $newmembers = $DB->get_records('cohort_members', ['cohortid' => $cohortid]);
    // Remove invalid users from cohort.
    foreach ($newmembers as $member) {
        $memberdetails = $DB->get_record('user', array('id' => $member->userid));
        if ($memberdetails->department != 'support' || $memberdetails->suspended == 1) {
            cohort_remove_member($cohortid, $member->userid);
            mtrace("{$memberdetails->username} removed from 'support' cohort");
        }
    }
}

/**
 * Process Management cohort
 *
 * @return void
 */
function management() {
    global $DB;
    $validusers = $DB->get_records('user', ['deleted' => 0, 'department' => 'management']);
    $cohortid = $DB->get_field('cohort', 'id', ['idnumber' => 'management']);
    if ($validusers) {
        foreach ($validusers as $user) {
            if ($user->suspended == 0 && !cohort_is_member($cohortid, $user->id)) {
                cohort_add_member($cohortid, $user->id);
                mtrace("{$user->username} added to 'management' cohort");
            }
            if ($user->suspended == 1 && cohort_is_member($cohortid, $user->id)) {
                cohort_remove_member($cohortid, $user->id);
                mtrace("{$user->username} removed from 'management' cohort");
            }
        }
    }
    $newmembers = $DB->get_records('cohort_members', ['cohortid' => $cohortid]);
    // Remove invalid users from cohort.
    foreach ($newmembers as $member) {
        $memberdetails = $DB->get_record('user', array('id' => $member->userid));
        if ($memberdetails->department != 'management' || $memberdetails->suspended == 1) {
            cohort_remove_member($cohortid, $member->userid);
            mtrace("{$memberdetails->username} removed from 'management' cohort");
        }
    }
}

/**
 * Process myDevelopment cohort
 *
 * @return void
 */
function mydevelopment() {
    global $DB;

    $sql = "SELECT * FROM {user} WHERE deleted = ? AND (department = ? OR department = ? OR department = ?)
		AND email NOT LIKE ? AND email NOT LIKE ? AND email NOT LIKE ? AND email LIKE ?";
    $params = array(0, 'support', 'academic', 'management', 'academic%', 'consultant%', 'jobshop%', '%@solent.ac.uk');
    $resultusersall = $DB->get_records_sql($sql, $params);

    $cohortid = $DB->get_record('cohort', array('idnumber' => 'mydevelopment'), 'id');

    if (empty($resultusersall)) {
        echo "No users </ br>";
    } else {
        foreach ($resultusersall as $user) {
            if ($user->suspended == 0 && !cohort_is_member($cohortid->id, $user->id)) {
                cohort_add_member($cohortid->id, $user->id);
            }
            if ($user->suspended == 1 && cohort_is_member($cohortid->id, $user->id)) {
                cohort_remove_member($cohortid->id, $user->id);
            }
        }
    }
}

/**
 * Process new students to a 6 month only cohort.
 *
 * @return void
 */
function student6() {
    global $DB;

    $sql = "SELECT *
            FROM {user}
            WHERE (timecreated > unix_timestamp((NOW()) - INTERVAL 6 MONTH)
            AND  deleted = ? AND suspended = ? AND department = ?)";
    $params = array(0, 0, 'student');
    $resultusersall = $DB->get_records_sql($sql, $params);

    $cohortid = $DB->get_record('cohort', array('idnumber' => 'student6'), 'id');

    if (empty($resultusersall)) {
        echo "No users </ br>";
    } else {
        foreach ($resultusersall as $user) {
            if (!cohort_is_member($cohortid->id, $user->id)) {
                cohort_add_member($cohortid->id, $user->id);
            }
        }
    }

    $sql = ("SELECT u.id, c.id cohortid
            FROM {cohort_members} cm
            JOIN {user} u ON u.id = cm.userid
            JOIN {cohort} c ON c.id = cm.cohortid
            WHERE c.idnumber = ?
            AND (u.timecreated < unix_timestamp((NOW()) - INTERVAL 6 MONTH)
            OR (suspended = 1 OR deleted = 1 OR department != 'student'))");
    $params = array($cohortid->idnumber = 'student6');
    $cohortmembers = $DB->get_records_sql($sql, $params);

    if (empty($cohortmembers)) {
        echo "No users </ br>";
    } else {
        foreach ($cohortmembers as $user) {
            cohort_remove_member($user->cohortid, $user->id);
        }
    }
}
