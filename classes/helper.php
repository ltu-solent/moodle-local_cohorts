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

namespace local_cohorts;

use context_system;
use core_user;

/**
 * Class helper
 *
 * @package    local_cohorts
 * @copyright  2024 Solent University {@link https://www.solent.ac.uk}
 * @author Mark Sharp <mark.sharp@solent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    public static function students_in_level(string $level, array $categoryids, array $excludecourseids = []): array {
        global $DB;
        // This assumes all the courses in these categories are all running.
        // Can't deselect hidden pages as these could be used for meta-linking.
        // Could do an exclude list of course pages.
        [$insql, $inparams] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED);
        $excludein = '';
        $excludeparams = [];
        if (!empty($excludecourseids)) {
            [$excludein, $excludeparams] = $DB->get_in_or_equal($excludecourseids, SQL_PARAMS_NAMED, 'exc', false);
        }
        $params = array_merge(
            $inparams,
            $excludeparams,
            ['level' => $level]
        );
        if (!empty($excludein)) {
            $excludein = ' AND c.id ' . $excludein;
        }
        $sql = "SELECT DISTINCT(u.id)
            FROM {course} c
            JOIN {groups} g ON g.courseid = c.id AND g.name = :level
            JOIN {groups_members} gm ON gm.groupid = g.id
            JOIN {user} u ON u.id = gm.userid AND u.suspended = 0
            WHERE c.category {$insql} {$excludein}
        ";
        $inparams['level'] = $level;
        $members = $DB->get_records_sql($sql, $params);
        return $members;
    }

    public static function get_courses_categories() {
        global $DB;
        $select = $DB->sql_like('idnumber', 'idnumber');
        $params = ['idnumber' => 'courses_%'];
        return $DB->get_records_select_menu('course_categories', $select, $params, '', 'id, idnumber');
    }

    /**
     * Update members for given cohort.
     *
     * @param int $cohortid
     * @return void
     */
    public static function update_user_department_cohort(int $cohortid) {
        global $DB;
        $config = get_config('local_cohorts');
        $cohort = $DB->get_record('cohort', ['id' => $cohortid]);
        $existingmembers = $DB->get_records_select('cohort_members',
            'cohortid = :cohortid', [
                'cohortid' => $cohortid,
            ],
            '',
            'userid'
        );

        // Build query for potential members.
        $emailexcludepattern = $config->emailexcludepattern ?? '';
        $excludeemails = [];
        if (!empty($emailexcludepattern)) {
            $excludeemails = explode(',', $emailexcludepattern);
        }
        $excludesql = [];
        foreach ($excludeemails as $email) {
            $excludesql[] = $DB->sql_like('email', ':' . $email . 'email', false, false, true);
        }

        $emailnotlike = '';
        if (count($excludesql) > 0) {
            $emailnotlike = ' AND (' . join(' AND ', $excludesql) . ')';
        }
        $solentlike = $DB->sql_like('email', ':solent', false, false);
        $select = "deleted = 0 AND suspended = 0
            AND (department = :department)
            {$emailnotlike}
            AND {$solentlike}";
        $params = [
            'department' => $cohort->idnumber,
            'solent' => '%@solent.ac.uk',
        ];
        foreach ($excludeemails as $email) {
            $params[$email . 'email'] = $email . '%';
        }
        $potentialmembers = $DB->get_records_select('user', $select, $params);

        foreach ($potentialmembers as $userid => $potentialmember) {
            // If potential member isn't in existing members list, add them.
            $ismember = array_filter($existingmembers, function($member) use ($userid) {
                return $userid == $member->userid;
            });
            if (empty($ismember)) {
                cohort_add_member($cohortid, $userid);
            } else {
                // Remove current item from existing members, so we're left with a list of existing members to delete.
                unset($existingmembers[$userid]);
            }
        }
        // Any existing members left should be removed.
        foreach ($existingmembers as $userid => $existingmember) {
            cohort_remove_member($cohortid, $userid);
        }
    }



    /**
     * Update cohort enrolment for a user based on their department field.
     *
     * @param int $userid
     * @return void
     */
    public static function sync_user_department(int $userid) {
        global $DB;
        $config = get_config('local_cohorts');
        $user = core_user::get_user($userid);
        $systemcohorts = trim($config->systemcohorts);
        if ($systemcohorts == '') {
            return;
        }
        $depts = explode(',', $systemcohorts);
        [$insql, $inparams] = $DB->get_in_or_equal($depts, SQL_PARAMS_NAMED);
        $systemcontext = context_system::instance();
        $inparams['userid'] = $userid;
        $inparams['contextid'] = $systemcontext->id;
        $existingmembership = $DB->get_records_sql("
            SELECT c.id, c.idnumber, c.name
            FROM {cohort} c
            JOIN {cohort_members} cm ON cm.userid = :userid
            WHERE c.idnumber {$insql}
            AND c.contextid = :contextid
        ", $inparams);
        if (empty($user->department) && count($existingmembership) == 0) {
            return;
        }
        // If user is in no department or has been suspended remove from all system cohorts.
        if (empty($user->department) || $user->suspended == 1) {
            foreach ($existingmembership as $cohort) {
                cohort_remove_member($cohort->id, $userid);
            }
            return;
        }
        $emailexcludepattern = $config->emailexcludepattern ?? '';
        $excludeemails = [];
        if (!empty($emailexcludepattern)) {
            $excludeemails = explode(',', $emailexcludepattern);
        }
        $drop = false;
        foreach ($excludeemails as $excludeemail) {
            // This user shouldn't be in any of these cohorts.
            if (strpos($user->email, $excludeemail) === 0) {
                $drop = true;
            }
        }
        // Not a solent email address.
        if (strpos($user->email, '@solent.ac.uk') === false) {
            $drop = true;
        }

        // Has the user changed department?
        foreach ($existingmembership as $existing) {
            if ($existing->idnumber != $user->department) {
                cohort_remove_member($existing->id, $userid);
            }
            // Doesn't have a valid email address.
            if ($drop && cohort_is_member($existing->id, $userid)) {
                cohort_remove_member($existing->id, $userid);
            }
        }
        if ($drop) {
            // Already dropped, and not adding to anything.
            return;
        }
        if (!in_array($user->department, $depts)) {
            // We're not adding people to this department.
            return;
        }

        $cohortid = $DB->get_field('cohort', 'id', ['idnumber' => $user->department]);
        if ($cohortid) {
            cohort_add_member($cohortid, $userid);
        }
    }
}
