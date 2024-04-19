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
use stdClass;

/**
 * Class helper
 *
 * @package    local_cohorts
 * @copyright  2024 Solent University {@link https://www.solent.ac.uk}
 * @author Mark Sharp <mark.sharp@solent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /**
     * Get all students in given level and category for making a cohort
     *
     * @param string $level
     * @param array $categoryids
     * @param array $excludecourseids
     * @return array
     */
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

    /**
     * Get categories used for course pages
     *
     * @return array
     */
    public static function get_courses_categories(): array {
        global $DB;
        $select = $DB->sql_like('idnumber', 'idnumber');
        $params = ['idnumber' => 'courses_%'];
        return $DB->get_records_select_menu('course_categories', $select, $params, '', 'id, idnumber');
    }

    /**
     * Update members for given cohort.
     *
     * @param int $cohortid
     * @param string $field User profile field to check
     * @return void
     */
    public static function update_user_profile_cohort(int $cohortid, string $field = 'department') {
        global $DB;
        $config = get_config('local_cohorts');
        $cohort = $DB->get_record('cohort', ['id' => $cohortid]);
        if (!$cohort) {
            return;
        }
        if (!in_array($field, ['department', 'institution'])) {
            return;
        }
        $existingmembers = $DB->get_records_sql("SELECT cm.userid, u.username, u.department, u.institution, u.idnumber
            FROM {cohort_members} cm
            JOIN {user} u ON u.id = cm.userid
            WHERE cm.cohortid = :cohortid", [
                'cohortid' => $cohortid,
            ]);

        // Build query for potential members.
        $emailexcludepattern = $config->emailexcludepattern ?? '';
        $excludeemails = [];
        if (!empty($emailexcludepattern)) {
            $excludeemails = explode(',', $emailexcludepattern);
        }
        $excludesql = [];
        foreach ($excludeemails as $email) {
            $excludesql[] = $DB->sql_like('email', ':' . $email . 'email', false, false, true);
            $excludesql[] = $DB->sql_like('username', ':' . $email . 'username', false, false, true);
        }

        $emailnotlike = '';
        if (count($excludesql) > 0) {
            $emailnotlike = ' AND (' . join(' AND ', $excludesql) . ')';
        }
        $solentlike = $DB->sql_like('email', ':solent', false, false);
        $select = "deleted = 0 AND suspended = 0
            AND ({$field} = :department)
            {$emailnotlike}
            AND {$solentlike}
            AND auth = 'ldap'";
        $params = [
            'solent' => '%@solent.ac.uk',
        ];
        if ($field == 'department') {
            $params['department'] = $cohort->idnumber;
        } else {
            $params['department'] = $cohort->name;
        }
        foreach ($excludeemails as $email) {
            $params[$email . 'email'] = $email . '%';
            $params[$email . 'username'] = $email . '%';
        }

        $potentialmembers = $DB->get_records_select('user', $select, $params);
        foreach ($potentialmembers as $userid => $potentialmember) {
            // If potential member isn't in existing members list, add them.
            $ismember = array_filter($existingmembers, function($member) use ($userid) {
                return $userid == $member->userid;
            });
            if (empty($ismember)) {
                cohort_add_member($cohortid, $userid);
                mtrace("Added {$potentialmember->username} {$potentialmember->department} {$potentialmember->institution}" .
                    " to {$cohort->name} ({$cohort->idnumber})");
            } else {
                // Remove current item from existing members, so we're left with a list of existing members to delete.
                unset($existingmembers[$userid]);
            }
        }
        // Any existing members left should be removed.
        foreach ($existingmembers as $userid => $existingmember) {
            cohort_remove_member($cohortid, $userid);
            mtrace("Removed {$existingmember->username} from {$cohort->name} ({$cohort->idnumber})");
        }
    }

    /**
     * Update all-staff cohort
     *
     * @return void
     */
    public static function update_all_staff_cohort() {
        global $DB;
        $systemcontext = context_system::instance();
        $config = get_config('local_cohorts');

        $cohort = $DB->get_record('cohort', [
            'idnumber' => 'all-staff',
            'contextid' => $systemcontext->id,
            'component' => 'local_cohorts',
        ]);
        if (!$cohort) {
            return;
        }

        $existingmembers = $DB->get_records_sql("SELECT cm.userid, u.username, u.department, u.institution, u.idnumber
            FROM {cohort_members} cm
            JOIN {user} u ON u.id = cm.userid
            WHERE cm.cohortid = :cohortid", [
                'cohortid' => $cohort->id,
            ]);
        // Build query for potential members.
        $emailexcludepattern = $config->emailexcludepattern ?? '';
        $excludeemails = [];
        if (!empty($emailexcludepattern)) {
            $excludeemails = explode(',', $emailexcludepattern);
        }
        $excludesql = [];
        foreach ($excludeemails as $email) {
            $excludesql[] = $DB->sql_like('email', ':' . $email . 'email', false, false, true);
            $excludesql[] = $DB->sql_like('username', ':' . $email . 'username', false, false, true);
        }

        $emailnotlike = '';
        if (count($excludesql) > 0) {
            $emailnotlike = ' AND (' . join(' AND ', $excludesql) . ')';
        }
        $solentlike = $DB->sql_like('email', ':solent', false, false);
        [$indept, $params] = $DB->get_in_or_equal(['academic', 'management', 'support'], SQL_PARAMS_NAMED);
        $select = "deleted = 0 AND suspended = 0
            AND (department {$indept})
            {$emailnotlike}
            AND {$solentlike}
            AND auth='ldap'";
        $params['solent'] = '%@solent.ac.uk';
        foreach ($excludeemails as $email) {
            $params[$email . 'email'] = $email . '%';
            $params[$email . 'username'] = $email . '%';
        }
        $potentialmembers = $DB->get_records_select('user', $select, $params);

        foreach ($potentialmembers as $userid => $potentialmember) {
            // If potential member isn't in existing members list, add them.
            $ismember = array_filter($existingmembers, function($member) use ($userid) {
                return $userid == $member->userid;
            });
            if (empty($ismember)) {
                cohort_add_member($cohort->id, $userid);
                mtrace("Added {$potentialmember->username} {$potentialmember->department} {$potentialmember->institution}" .
                    " to {$cohort->name} ({$cohort->idnumber})");
            } else {
                // Remove current item from existing members, so we're left with a list of existing members to delete.
                unset($existingmembers[$userid]);
            }
        }
        // Any existing members left should be removed.
        foreach ($existingmembers as $userid => $existingmember) {
            cohort_remove_member($cohort->id, $userid);
            mtrace("Removed {$existingmember->username} from {$cohort->name} ({$cohort->idnumber})");
        }

    }

    /**
     * Update cohort enrolment for a user based on their department and institution field.
     *
     * @param int $userid
     * @return void
     */
    public static function sync_user_profile_cohort(int $userid) {
        global $DB;
        $config = get_config('local_cohorts');
        $user = core_user::get_user($userid);
        $systemcontext = context_system::instance();
        $inparams['userid'] = $userid;
        $inparams['contextid'] = $systemcontext->id;
        $existingmembership = $DB->get_records_sql("
            SELECT c.id, c.idnumber, c.name
            FROM {cohort} c
            JOIN {cohort_members} cm ON cm.cohortid = c.id AND cm.userid = :userid
            WHERE c.contextid = :contextid AND c.component = 'local_cohorts'
        ", $inparams);
        // If not a member of anything and has no dept or inst, stop here.
        if ((empty(trim($user->department)) && empty(trim($user->institution))) && count($existingmembership) == 0) {
            return;
        }
        // If user has been suspended remove from all system cohorts.
        if ($user->suspended == 1) {
            foreach ($existingmembership as $cohort) {
                cohort_remove_member($cohort->id, $user->id);
            }
            // Could remove system roles here too.
            return;
        }

        $drop = false;
        // Only want ldap controlled accounts.
        if ($user->auth != 'ldap') {
            $drop = true;
        }
        // This is checking the username part of the email address for things like "consultant001" which would have a solent domain.
        $emailexcludepattern = $config->emailexcludepattern ?? '';
        $excludeemails = [];
        if (!empty($emailexcludepattern)) {
            $excludeemails = explode(',', $emailexcludepattern);
        }
        foreach ($excludeemails as $excludeemail) {
            // This user shouldn't be in any of these cohorts. This is checking if the email startswith the pattern.
            if (strpos($user->email, $excludeemail) === 0) {
                $drop = true;
            }
            // Also check username.
            if (strpos($user->username, $excludeemail) === 0) {
                $drop = true;
            }
        }

        // Not a solent email address.
        if (strpos($user->email, '@solent.ac.uk') === false) {
            $drop = true;
        }

        // Valid Staff cohort departments for creating all-staff.
        $staffcohortsconfig = $config->staffcohorts ?? '';
        $staffcohorts = [];
        if (!empty($staffcohortsconfig)) {
            $staffcohorts = explode(',', $staffcohortsconfig);
        }
        $isstaff = in_array($user->department, $staffcohorts);

        foreach ($existingmembership as $existing) {
            $type = explode('_', $existing->idnumber)[0];
            $value = $user->department;
            if ($type == 'inst') {
                $value = self::slugify($user->institution);
            }

            if ($existing->idnumber == 'all-staff' && !$isstaff) {
                cohort_remove_member($existing->id, $userid);
            } else if ($existing->idnumber != $value) {
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

        if (!empty(trim($user->department))) {
            $deptcohortid = $DB->get_field('cohort', 'id', [
                'idnumber' => $user->department,
                'contextid' => $systemcontext->id,
                'component' => 'local_cohorts',
            ]);
            if (!$deptcohortid) {
                $cohort = new stdClass();
                $cohort->contextid = $systemcontext->id;
                $cohort->name = ucwords($user->department);
                $cohort->idnumber = strtolower($user->department);
                $cohort->component = 'local_cohorts';
                $deptcohortid = cohort_add_cohort($cohort);
            }
            if ($deptcohortid && !cohort_is_member($deptcohortid, $userid)) {
                cohort_add_member($deptcohortid, $userid);
            }
        }

        if (!empty(trim($user->institution))) {
            $instslug = 'inst_' . self::slugify($user->institution);
            $instcohortid = $DB->get_field('cohort', 'id', [
                'idnumber' => $instslug,
                'contextid' => $systemcontext->id,
                'component' => 'local_cohorts',
            ]);
            if (!$instcohortid) {
                $cohort = new stdClass();
                $cohort->contextid = $systemcontext->id;
                $cohort->name = $user->institution;
                $cohort->idnumber = $instslug;
                $cohort->component = 'local_cohorts';
                $instcohortid = cohort_add_cohort($cohort);
            }
            if ($instcohortid && !cohort_is_member($instcohortid, $userid)) {
                cohort_add_member($instcohortid, $userid);
            }
        }

        $staffcohortid = $DB->get_field('cohort', 'id', [
            'idnumber' => 'all-staff',
            'contextid' => $systemcontext->id,
            'component' => 'local_cohorts',
        ]);
        if (!$staffcohortid) {
            $cohort = new stdClass();
            $cohort->contextid = $systemcontext->id;
            $cohort->name = 'All staff';
            $cohort->idnumber = 'all-staff';
            $cohort->component = 'local_cohorts';
            $staffcohortid = cohort_add_cohort($cohort);
        }
        if ($isstaff && !cohort_is_member($staffcohortid, $userid)) {
            cohort_add_member($staffcohortid, $userid);
        }
    }

    /**
     * Migrate any existing system cohorts, and expand with new ones based on user profile fields.
     *
     * @return void
     */
    public static function migrate_cohorts() {
        global $DB;
        $context = context_system::instance();
        $cohorts = $DB->get_records('cohort', [
            'contextid' => $context->id,
            'component' => '',
        ]);
        foreach ($cohorts as $cohort) {
            self::adopt_a_cohort($cohort->id);
        }
        // Create new cohorts based on user department and institution fields.
        $depts = $DB->get_records_sql("SELECT DISTINCT(department)
            FROM {user}
            WHERE department != '' AND suspended = 0 AND deleted = 0");
        foreach ($depts as $dept) {
            $exists = $DB->record_exists('cohort', [
                'idnumber' => strtolower($dept->department),
                'contextid' => $context->id,
                'component' => 'local_cohorts',
            ]);
            if (!$exists) {
                $newcohort = new stdClass();
                $newcohort->name = ucwords($dept->department);
                $newcohort->idnumber = strtolower($dept->department);
                $newcohort->contextid = $context->id;
                $newcohort->component = 'local_cohorts';
                cohort_add_cohort($newcohort);
            }
        }
        $insts = $DB->get_records_sql("SELECT DISTINCT(institution)
            FROM {user}
            WHERE institution != '' AND suspended = 0 AND deleted = 0");
        foreach ($insts as $inst) {
            $exists = $DB->record_exists('cohort', [
                'idnumber' => 'inst_' . self::slugify($inst->institution),
                'contextid' => $context->id,
                'component' => 'local_cohorts',
            ]);
            if (!$exists) {
                $newcohort = new stdClass();
                $newcohort->name = ucwords($inst->institution);
                $newcohort->idnumber = 'inst_' . self::slugify($inst->institution);
                $newcohort->contextid = $context->id;
                $newcohort->component = 'local_cohorts';
                cohort_add_cohort($newcohort);
            }
        }
    }

    /**
     * Adopt an existing system cohort
     *
     * @param int $cohortid
     * @return bool
     */
    public static function adopt_a_cohort($cohortid): bool {
        global $DB;
        $cohort = $DB->get_record('cohort', ['id' => $cohortid]);
        $systemcontext = context_system::instance();
        if ($cohort->contextid != $systemcontext->id) {
            // Only interested in system cohorts, for now.
            return false;
        }
        if ($cohort->component != '') {
            // Already owned by another plugin.
            return false;
        }
        if ($cohort->idnumber == '') {
            // No idnumber, not one we want to track.
            return false;
        }
        if (strpos($cohort->description, 'Auto populated') === false) {
            // Only add ones marked Auto populated in the description.
            return false;
        }
        $cohort->idnumber = strtolower($cohort->idnumber);
        $cohort->component = 'local_cohorts';
        $cohort->timemodified = time();
        return $DB->update_record('cohort', $cohort);
    }

    /**
     * Create machine-friendly class from name
     *
     * @param string $text
     * @return string
     */
    public static function slugify($text): string {
        // Replace non letter or digits by -.
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        // Transliterate.
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        // Remove unwanted characters.
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        // Remove duplicate -.
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        if (empty($text)) {
            return 'n-a';
        }
        return $text;
    }
}
