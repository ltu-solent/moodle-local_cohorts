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

namespace local_cohorts\task;

use context_course;
use context_system;
use core\task\scheduled_task;
use core_text;
use local_cohorts\helper;
use stdClass;

/**
 * Class location_cohort_sync
 *
 * @package    local_cohorts
 * @author Mark Sharp <mark.sharp@solent.ac.uk>
 * @copyright  2024 Solent University {@link https://www.solent.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class location_cohort_sync extends scheduled_task {
    /**
     * Get task name
     *
     * @return string
     */
    public function get_name() {
        return get_string('synclocationcohorts', 'local_cohorts');
    }

    /**
     * Execute task
     *
     * @return void
     */
    public function execute() {
        global $DB;
         // Get currently running modules or modules in the current academic year.
         // If account is suspended exclude.
         //
         // Some considerations:
         // - Don't want students dropping off between semesters or sessions.
         // - S2 ends in May restarts in October. ~3.5-4 months.
         // - Reenrolments open some time in August, so it's possible for students to drop off
         // at the beginning of August.
         // - Need to capture Cross-sessional
         // - Take module enrolment status into consideration, but only if they don't
         // have any active enrolments in the current session.

        $currentsession = helper::get_current_session();

        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);

        // Get all possible locations - Process alphabetically.
        $sql = "SELECT DISTINCT(cfd.value) loc
            FROM {customfield_data} cfd
            WHERE cfd.fieldid = (SELECT id FROM {customfield_field} WHERE shortname = 'location')
                AND cfd.value != '' ORDER BY loc ASC";
        $locations = $DB->get_records_sql($sql);
        $systemcontext = context_system::instance();

        foreach ($locations as $location) {
            mtrace("Start processing for \"{$location->loc}\" cohort");
            // Idnumber max length 100 loc_ and _stu is 8 chars.
            $locationslug = core_text::substr(helper::slugify($location->loc), 0, 92);
            $idnumber = 'loc_' . $locationslug . '_stu';
            $cohort = $DB->get_record('cohort', [
                'idnumber' => $idnumber,
                'component' => 'local_cohorts',
                'contextid' => $systemcontext->id,
            ]);
            // Create the location cohort if it doesn't already exist.
            if (!$cohort) {
                $cohort = new stdClass();
                $cohort->idnumber = $idnumber;
                $cohort->name = get_string('studentcohort', 'local_cohorts', ['name' => $location->loc]);
                $cohort->description = get_string('cohortdescription', 'local_cohorts', ['name' => $cohort->name]);
                $cohort->contextid = $systemcontext->id;
                $cohort->component = 'local_cohorts';
                $cohortid = cohort_add_cohort($cohort);
                $cohort = $DB->get_record('cohort', ['id' => $cohortid]);
            }
            $currentmembers = $DB->get_records_sql("SELECT cm.userid, u.username
                FROM {cohort_members} cm
                JOIN {user} u ON u.id = cm.userid
                WHERE cm.cohortid = :cohortid", ['cohortid' => $cohort->id]);
            $prospectivemembers = [];

            // Get all currently running courseids for the given location.
            $likesession = $DB->sql_like('c.shortname', ':session');
            $sql = "SELECT cfd.instanceid courseid
                FROM {customfield_data} cfd
                JOIN {course} c on c.id = cfd.instanceid
                WHERE cfd.fieldid = (SELECT id FROM {customfield_field} WHERE shortname = 'location')
                    AND cfd.value = :location
                    AND ({$likesession}
                        OR (c.startdate < :startdate AND c.enddate > :enddate))";
            $locationcourses = $DB->get_records_sql($sql, [
                'session' => '%\_' . $currentsession,
                'startdate' => time(),
                'enddate' => time(),
                'location' => $location->loc,
            ]);

            foreach ($locationcourses as $course) {
                $coursecontext = context_course::instance($course->courseid);
                $sql = "SELECT ra.userid, u.username, u.suspended, ue.status, ue.timestart, ue.timeend
                    FROM {role_assignments} ra
                    JOIN {user} u ON u.id = ra.userid
                    JOIN {user_enrolments} ue ON ue.userid = ra.userid AND ue.enrolid = ra.itemid
                    WHERE ra.contextid = :contextid
                        AND ra.component = 'enrol_solaissits'
                        AND ra.roleid = :roleid
                        AND u.suspended = 0";
                $students = $DB->get_records_sql($sql, [
                    'contextid' => $coursecontext->id,
                    'roleid' => $studentroleid,
                ]);
                foreach ($students as $student) {
                    if ($student->status != ENROL_USER_ACTIVE) {
                        // Not a prospective member for this module, but might be on another.
                        continue;
                    }
                    if (!isset($prospectivemembers[$student->userid])) {
                        $prospectivemembers[$student->userid] = $student->username;
                    }
                }
            }
            foreach ($prospectivemembers as $userid => $prospectivemember) {
                if (isset($currentmembers[$userid])) {
                    unset($currentmembers[$userid]);
                } else {
                    mtrace(" - Adding {$prospectivemember}");
                    cohort_add_member($cohort->id, $userid);
                }
            }
            // Any remaining entries in currentmembers are to be removed.
            foreach ($currentmembers as $member) {
                mtrace(" - Removing {$member->username}");
                cohort_remove_member($cohort->id, $member->userid);
            }
            mtrace("End processing for \"{$location->loc}\" cohort");
        }
    }

}
