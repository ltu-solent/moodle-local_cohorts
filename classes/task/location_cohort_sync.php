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
use core_customfield\category;
use core_customfield\field;
use core_text;
use local_cohorts\helper;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/cohort/lib.php');

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
     * SITS category field
     *
     * @var category
     */
    private $category = null;

    /**
     * Current session e.g. 2024/25
     *
     * @var string
     */
    private $currentsession = '';

    /**
     * List of distinct levels
     *
     * @var array
     */
    private $levelcohorts = [];

    /**
     * Level custom field
     *
     * @var field
     */
    private $levelfield = null;

    /**
     * Location custom field
     *
     * @var field
     */
    private $locationfield = null;

    /**
     * Student role id
     *
     * @var int
     */
    private $studentroleid = null;

    /**
     * Skip cohort
     *
     * @var array
     */
    private $skip = [];

    /**
     * Skip level processing
     *
     * @var bool
     */
    public $skiplevel = true;

    /**
     * Get task name
     *
     * @return string
     */
    public function get_name() {
        return get_string('synclocationcohorts', 'local_cohorts');
    }

    /**
     * Manage all location based cohorts
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
         // - Take into account academic levels too.
         // Assuming there are levels 03,04,05 and the location "Southampton Solent University" you will get the following:
         // - All Level 03 students
         // - All Level 04 students
         // - All Level 05 students
         // - Southampton Solent University All levels
         // - Southampton Solent University Level 03 Students
         // - Southampton Solent University Level 04 Students
         // - Southampton Solent University Level 05 Students.

        $this->currentsession = helper::get_current_session();

        $this->studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);

        // May create setting for this when it's working properly.
        $this->skiplevel = true;
        if (!$this->validate_customfields()) {
            return;
        }
        // All unique location and level values.
        $locations = $this->get_field_data($this->locationfield->get('id'));
        if (!$this->skiplevel) {
            $levels = $this->get_field_data($this->levelfield->get('id'));
            // Set up all top level Level cohorts.
            $this->setup_level_cohorts($levels);
        }

        foreach ($locations as $location) {
            mtrace("Start processing for \"{$location->fvalue}\" cohort");
            [$cohort, $cohortstatus] = $this->get_location_cohort($location);
            $currentmembers = helper::get_members($cohort);
            $prospectivemembers = [];
            $locationcourses = $this->get_currently_running_courses_at($location);

            if (!$this->skiplevel) {
                // Create or get existing Location x Level cohorts.
                $this->setup_loclevel_cohorts($location, $levels);
            }

            if ($cohortstatus->enabled == 0) {
                if (count($currentmembers) > 0) {
                    mtrace("The cohort \"{$cohort->name}\" has been disabled. Removing all users.");
                    foreach ($currentmembers as $existingmember) {
                        mtrace("- Removing {$existingmember->username}");
                        cohort_remove_member($cohort->id, $existingmember->userid);
                    }
                }
                $currentmembers = [];
                $this->skip[$location->fvalue] = true;
            }

            foreach ($locationcourses as $course) {
                $students = $this->get_course_students($course);
                $coursehaslevel = !is_null($course->alevel);
                if ($coursehaslevel && !$this->skiplevel) {
                    $alllevelname = 'All level ' . $course->alevel . ' students';
                    $alllevelslug = core_text::substr(helper::slugify($alllevelname), 0, 100);
                    $loclevname = $location->fvalue . ' level ' . $course->alevel . ' students';
                    $loclevslug = core_text::substr(helper::slugify($loclevname), 0, 100);
                }

                foreach ($students as $student) {
                    if ($student->status != ENROL_USER_ACTIVE) {
                        // Not a prospective member for this module, but might be on another.
                        continue;
                    }
                    if (!isset($prospectivemembers[$student->userid]) && !isset($this->skip[$location->fvalue])) {
                        $prospectivemembers[$student->userid] = $student->username;
                    }
                    if ($coursehaslevel && !$this->skiplevel) {
                        if (!isset($this->levelcohorts[$alllevelslug]['prospectivemembers'][$student->userid]) &&
                            !isset($this->skip[$alllevelslug])) {
                            if ($this->levelcohorts[$alllevelslug]['status']->enabled) {
                                $this->levelcohorts[$alllevelslug]['prospectivemembers'][$student->userid] = $student->username;
                            }
                        }
                        if (!isset($this->levelcohorts[$loclevslug]['prospectivemembers'][$student->userid]) &&
                            !isset($this->skip[$loclevslug])) {
                            if ($this->levelcohorts[$loclevslug]['status']->enabled) {
                                $this->levelcohorts[$loclevslug]['prospectivemembers'][$student->userid] = $student->username;
                            }
                        }
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
            mtrace("End processing for \"{$location->fvalue}\" cohort");
        }
        // Add any level & loc x level prospective members.
        foreach ($this->levelcohorts as $loclevslug => $leveldata) {
            mtrace("Processing members for {$leveldata['cohort']->name}");
            foreach ($leveldata['prospectivemembers'] as $userid => $prospectivemember) {
                if (isset($leveldata['currentmembers'][$userid])) {
                    unset($leveldata['currentmembers'][$userid]);
                } else {
                    mtrace(" - Adding {$prospectivemember}");
                    cohort_add_member($leveldata['cohort']->id, $userid);
                }
            }
            // Remove any loc x level current members.
            foreach ($leveldata['currentmembers'] as $userid => $member) {
                mtrace(" - Removing {$member->username}");
                cohort_remove_member($leveldata['cohort']->id, $userid);
            }
            mtrace("End processing members for {$leveldata['cohort']->name}");
        }
    }

    /**
     * Can't use get_cohort here because the idnumbers would mess up.
     *
     * @param stdClass $location
     * @return array
     */
    private function get_location_cohort($location): array {
        global $DB;
        $systemcontext = context_system::instance();
        // Can't use get_cohort here because the idnumbers would mess up.
        // Idnumber max length 100 loc_ and _stu is 8 chars.
        $locationslug = core_text::substr(helper::slugify($location->fvalue), 0, 92);
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
            $cohort->name = get_string('studentcohort', 'local_cohorts', ['name' => $location->fvalue]);
            $cohort->description = get_string('locationcohortdescription', 'local_cohorts', ['name' => $cohort->name]);
            $cohort->contextid = $systemcontext->id;
            $cohort->component = 'local_cohorts';
            $cohortid = cohort_add_cohort($cohort);
            $cohort = $DB->get_record('cohort', ['id' => $cohortid]);
            helper::update_cohort_status($cohort, true);
        }

        $cohortstatus = $DB->get_record('local_cohorts_status', ['cohortid' => $cohort->id]);
        if (!$cohortstatus) {
            $cohortstatus = helper::update_cohort_status($cohort, true);
        }
        return [$cohort, $cohortstatus];
    }

    /**
     * Get field data for fieldid
     *
     * @param id $fieldid
     * @return array
     */
    private function get_field_data($fieldid): array {
        global $DB;
        $sql = "SELECT DISTINCT(cfd.value) fvalue
            FROM {customfield_data} cfd
            WHERE cfd.fieldid = :fieldid
                AND cfd.value != '' ORDER BY value ASC";
        return $DB->get_records_sql($sql, ['fieldid' => $fieldid]);
    }

    /**
     * Set up top level cohorts
     *
     * @param array $levels
     * @return void
     */
    private function setup_level_cohorts($levels) {
        $systemcontext = context_system::instance();
        foreach ($levels as $level) {
            $name = 'All level ' . $level->fvalue . ' students';
            $description = get_string('locationcohortdescription', 'local_cohorts', ['name' => $name]);
            [$cohort, $status] = helper::get_cohort($name, $description, $systemcontext);
            $this->levelcohorts[$cohort->idnumber] = [
                'cohort' => $cohort,
                'status' => $status,
                'currentmembers' => helper::get_members($cohort),
                'prospectivemembers' => [],
            ];
            if ($status->enabled == 0) {
                if (count($this->levelcohorts[$cohort->idnumber]['currentmembers']) > 0) {
                    mtrace("The cohort \"{$cohort->name}\" has been disabled. Removing all users.");
                    foreach ($this->levelcohorts[$cohort->idnumber]['currentmembers'] as $existingmember) {
                        mtrace("- Removing {$existingmember->username}");
                        cohort_remove_member($cohort->id, $existingmember->userid);
                    }
                }
                $this->levelcohorts[$cohort->idnumber]['currentmembers'] = [];
                $this->skip[$cohort->idnumber] = true;
            }
        }
    }

    /**
     * Get sits enrolled students
     *
     * @param stdClass $course
     * @return array
     */
    private function get_course_students($course): array {
        global $DB;
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
            'roleid' => $this->studentroleid,
        ]);
        return $students;
    }

    /**
     * Get all currently running courses for given location
     *
     * @param stdClass $location
     * @return array
     */
    private function get_currently_running_courses_at($location): array {
        global $DB;
        $likesession = $DB->sql_like('c.shortname', ':session');
        $sql = "SELECT cfd.instanceid courseid, levelcfd.value alevel
            FROM {customfield_data} cfd
            JOIN {course} c on c.id = cfd.instanceid
            LEFT JOIN {customfield_data} levelcfd ON levelcfd.fieldid = :levelfieldid AND levelcfd.instanceid = c.id
            WHERE cfd.fieldid = :fieldid
                AND cfd.value = :location
                AND ({$likesession}
                    OR (c.startdate < :startdate AND c.enddate > :enddate))";
        $locationcourses = $DB->get_records_sql($sql, [
            'session' => '%\_' . $this->currentsession,
            'startdate' => time(),
            'enddate' => time(),
            'location' => $location->fvalue,
            'fieldid' => $this->locationfield->get('id'),
            'levelfieldid' => $this->levelfield->get('id'),
        ]);
        return $locationcourses;
    }

    /**
     * Given a location and a set of levels, set up cohorts, if not already creatd.
     *
     * @param stdClass $location
     * @param array $levels
     * @return void
     */
    private function setup_loclevel_cohorts($location, $levels) {
        $systemcontext = context_system::instance();
        foreach ($levels as $level) {
            $loclevname = $location->fvalue . ' level ' . $level->fvalue . ' students';
            $loclevdescription = get_string('locationcohortdescription', 'local_cohorts', ['name' => $loclevname]);
            [$loclevcohort, $loclevstatus] = helper::get_cohort($loclevname, $loclevdescription, $systemcontext, '', '');
            $this->levelcohorts[$loclevcohort->idnumber] = [
                'cohort' => $loclevcohort,
                'status' => $loclevstatus,
                'currentmembers' => helper::get_members($loclevcohort),
                'prospectivemembers' => [],
            ];
            if ($loclevstatus->enabled == 0) {
                if (count($this->levelcohorts[$loclevcohort->idnumber]['currentmembers']) > 0) {
                    mtrace("The cohort \"{$loclevcohort->name}\" has been disabled. Removing all users.");
                    foreach ($this->levelcohorts[$loclevcohort->idnumber]['currentmembers'] as $existingmember) {
                        mtrace("- Removing {$existingmember->username}");
                        cohort_remove_member($loclevcohort->id, $existingmember->userid);
                    }
                    $this->levelcohorts[$loclevcohort->idnumber]['currentmembers'] = [];
                }
            }
        }
    }

    /**
     * Check required custom fields are set up
     *
     * @return bool
     */
    private function validate_customfields(): bool {
        $this->category = category::get_record([
            'name' => 'Student Records System',
            'area' => 'course',
            'component' => 'core_course',
        ]);
        if (!$this->category) {
            mtrace('Student Records System custom category not set up.');
            return false;
        }
        $this->locationfield = field::get_record([
            'shortname' => 'location_name',
            'categoryid' => $this->category->get('id'),
        ]);
        if (!$this->locationfield) {
            mtrace('location_name custom field not set up');
            return false;
        }
        $this->levelfield = field::get_record([
            'shortname' => 'level_code',
            'categoryid' => $this->category->get('id'),
        ]);
        if (!$this->levelfield) {
            mtrace('level_code custom field not set up');
            return false;
        }
        return true;
    }
}
