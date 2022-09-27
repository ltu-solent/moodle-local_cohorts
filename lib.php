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

    // Add new users to cohort.
    $sql = ("SELECT * FROM mdl_user WHERE deleted = ? AND department = ?");
    $params = array(0, 'academic');
    $resultusersall = $DB->get_records_sql($sql, $params);

    $cohortid = $DB->get_record('cohort', array(idnumber => 'academic'), 'id');

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

    // Remove invalid users from cohort.
    $sql = ("SELECT * FROM mdl_cohort_members c JOIN mdl_user u ON u.id = c.userid WHERE c.cohortid = ?");
    $params = array($cohortid->id);
    $cohortmembers = $DB->get_records_sql($sql, $params);

    if (empty($cohortmembers)) {
        echo "No users </ br>";
    } else {
        // Process request.
        foreach ($cohortmembers as $user) {
            $memberdetails = $DB->get_record('user', array('id' => $user->userid));
            if ($memberdetails->department != 'academic' || $memberdetails->suspended == 1) {
                cohort_remove_member($cohortid->id, $user->id);
            }
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

    $sql = ("SELECT * FROM mdl_user WHERE deleted = ? AND department = ?");
    $params = array(0, 'support');
    $resultusersall = $DB->get_records_sql($sql, $params);

    $cohortid = $DB->get_record('cohort', array(idnumber => 'support'), 'id');

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

    // Remove invalid users from cohort.
    $sql = ("SELECT * FROM mdl_cohort_members c JOIN mdl_user u ON u.id = c.userid WHERE c.cohortid = ?");
    $params = array($cohortid->id);
    $cohortmembers = $DB->get_records_sql($sql, $params);

    if (empty($cohortmembers)) {
        echo "No users </ br>";
    } else {
        foreach ($cohortmembers as $user) {
            $memberdetails = $DB->get_record('user', array('id' => $user->userid));
            if ($memberdetails->department != 'support' || $memberdetails->suspended == 1) {
                cohort_remove_member($cohortid->id, $user->id);
            }
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
    $sql = ("SELECT * FROM mdl_user WHERE deleted = ? AND department = ?");
    $params = array(0, 'management');
    $resultusersall = $DB->get_records_sql($sql, $params);

    $cohortid = $DB->get_record('cohort', array(idnumber => 'management'), 'id');

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

    // Remove invalid users from cohort.
    $sql = ("SELECT * FROM mdl_cohort_members c JOIN mdl_user u ON u.id = c.userid WHERE c.cohortid = ?");
    $params = array($cohortid->id);
    $cohortmembers = $DB->get_records_sql($sql, $params);

    if (empty($cohortmembers)) {
        echo "No users </ br>";
    } else {
        foreach ($cohortmembers as $user) {
            $memberdetails = $DB->get_record('user', array('id' => $user->userid));
            if ($memberdetails->department != 'management' || $memberdetails->suspended == 1) {
                cohort_remove_member($cohortid->id, $user->id);
            }
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

    $sql = "SELECT * FROM mdl_user WHERE deleted = ? AND (department = ? OR department = ? OR department = ?)
		AND email NOT LIKE ? AND email NOT LIKE ? AND email NOT LIKE ? AND email LIKE ?";
    $params = array(0, 'support', 'academic', 'management', 'academic%', 'consultant%', 'jobshop%', '%@solent.ac.uk');
    $resultusersall = $DB->get_records_sql($sql, $params);

    $cohortid = $DB->get_record('cohort', array(idnumber => 'mydevelopment'), 'id');

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

function student6() {
    global $DB;

    $sql = "SELECT *
            FROM mdl_user
            WHERE (timecreated > unix_timestamp((NOW()) - INTERVAL 6 MONTH)
            AND  deleted = ? AND suspended = ? AND department = ?)";
    $params = array(0, 0, 'student');
    $resultusersall = $DB->get_records_sql($sql, $params);

    $cohortid = $DB->get_record('cohort', array(idnumber => 'student6'), 'id');

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
            FROM mdl_cohort_members cm
            JOIN mdl_user u ON u.id = cm.userid
            JOIN mdl_cohort c ON c.id = cm.cohortid
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
