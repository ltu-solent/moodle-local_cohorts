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

use context_coursecat;
use context_system;

/**
 * Tests for SOL Cohorts
 *
 * @package    local_cohorts
 * @category   test
 * @copyright  2024 Solent University {@link https://www.solent.ac.uk}
 * @author Mark Sharp <mark.sharp@solent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper_test extends \advanced_testcase {
    /**
     * Update user department cohort memberships
     * @covers \local_cohorts\helper::update_user_department_cohort
     *
     * @return void
     */
    public function test_update_user_department_cohort() {
        global $DB;
        $this->resetAfterTest();
        $systemcontext = context_system::instance();
        // I don't want events to trigger for this test.
        $sink = $this->redirectEvents();
        // Create users for each department.
        $supportaccounts = ['academic', 'consultant', 'jobshop'];
        set_config('staffcohorts', 'academic,management,support', 'local_cohorts');
        set_config('emailexcludepattern', join(',', $supportaccounts), 'local_cohorts');
        $cohortusers = [];
        $counter = 0;
        $supportusers = [];
        $systemcohorts = [];

        $deptcohorts = ['academic', 'management', 'support', 'student'];
        $instcohorts = [
            'Warshash Maritime School',
            'Information & Communications Technology',
            'Arts and Music',
            'Social Sciences and Nursing',
            'Science and Engineering',
        ];

        foreach ($deptcohorts as $dept) {
            if (!$DB->record_exists('cohort', ['idnumber' => $dept, 'contextid' => $systemcontext->id])) {
                $systemcohorts[$dept] = $this->getDataGenerator()->create_cohort([
                    'name' => ucwords($dept),
                    'idnumber' => $dept,
                    'contextid' => $systemcontext->id,
                    'component' => 'local_cohorts',
                ]);
            }
            $cohort = $systemcohorts[$dept];

            for ($x = 0; $x < 5; $x++) {
                $institution = $instcohorts[$x];
                // Students don't get the institution field filled.
                if ($dept == 'student') {
                    $institution = '';
                }
                // Preexisting cohort member.
                $user = $this->getDataGenerator()->create_user([
                    'auth' => 'ldap',
                    'department' => $dept,
                    'institution' => $institution,
                    'email' => 'preuser' . $x . $counter . '@solent.ac.uk',
                    'username' => 'preuser' . $x . $counter,
                ]);
                cohort_add_member($cohort->id, $user->id);
                $cohortusers[$dept]['activemembers'][] = $user;

                // Member to be added by function.
                $user = $this->getDataGenerator()->create_user([
                    'auth' => 'ldap',
                    'department' => $dept,
                    'institution' => $institution,
                    'email' => 'postuser' . $x . $counter . '@solent.ac.uk',
                    'username' => 'postuser' . $x . $counter,
                ]);
                $cohortusers[$dept]['newmembers'][] = $user;

                // Suspended member who will be removed.
                $user = $this->getDataGenerator()->create_user([
                    'auth' => 'ldap',
                    'department' => $dept,
                    'institution' => $institution,
                    'suspended' => 1,
                    'email' => 'usersuspended' . $x . $counter . '@solent.ac.uk',
                    'username' => 'usersuspended' . $x . $counter,
                ]);
                cohort_add_member($cohort->id, $user->id);
                $cohortusers[$dept]['suspendedmembers'][] = $user;

                // Some support accounts that shouldn't be added.
                foreach ($supportaccounts as $supportaccount) {
                    $supportusers[$dept][] = $this->getDataGenerator()->create_user([
                        'auth' => 'ldap',
                        'department' => $dept,
                        'institution' => $institution,
                        'email' => $supportaccount . $x . $counter . '@solent.ac.uk',
                        'username' => $supportaccount . $x . $counter,
                    ]);
                }
                // User with invalid email address for inclusion.
                $supportusers[$dept][] = $this->getDataGenerator()->create_user([
                    'auth' => 'ldap',
                    'department' => $dept,
                    'institution' => $institution,
                    'email' => 'other' . $x . $counter . '@example.com',
                    'username' => 'other' . $x . $counter,
                ]);
                $counter++;
            }
        }

        // Create the institute cohorts, but don't add anyone. Leave that for the task.
        foreach ($instcohorts as $instcohort) {
            $key = 'inst_' . helper::slugify($instcohort);
            $systemcohorts[$key] = $this->getDataGenerator()->create_cohort([
                'name' => $instcohort,
                'idnumber' => $key,
                'contextid' => $systemcontext->id,
                'component' => 'local_cohorts',
            ]);
        }
        $systemcohorts['all-staff'] = $this->getDataGenerator()->create_cohort([
            'name' => 'All Staff',
            'idnumber' => 'all-staff',
            'contextid' => $systemcontext->id,
            'component' => 'local_cohorts',
        ]);

        foreach ($systemcohorts as $key => $cohort) {
            $premembers = $DB->get_records('cohort_members', ['cohortid' => $cohort->id]);
            $type = explode('_', $cohort->idnumber)[0];
            if ($type == 'inst') {
                $type = 'institution';
            } else if ($cohort->idnumber != 'all-staff') {
                $type = 'department';
            }

            if ($type == 'institution') {
                $this->assertCount(0, $premembers);
            } else if ($cohort->idnumber == 'all-staff') {
                $this->assertCount(0, $premembers);
            } else {
                $this->assertCount(10, $premembers);
                foreach ($cohortusers[$key]['activemembers'] as $member) {
                    $this->assertTrue(cohort_is_member($cohort->id, $member->id));
                }
                // Not yet removed.
                foreach ($cohortusers[$key]['suspendedmembers'] as $member) {
                    $this->assertTrue(cohort_is_member($cohort->id, $member->id));
                }
                // Not yet added.
                foreach ($cohortusers[$key]['newmembers'] as $member) {
                    $this->assertFalse(cohort_is_member($cohort->id, $member->id));
                }
            }

            // Function under test - this is going to add institution data.
            if ($cohort->idnumber == 'all-staff') {
                helper::update_all_staff_cohort();
            } else {
                helper::update_user_profile_cohort($cohort->id, $type);
            }

            $postmembers = $DB->get_records('cohort_members', ['cohortid' => $cohort->id]);
            if ($type == 'institution') {
                $this->assertCount(6, $postmembers);
            } else if ($cohort->idnumber == 'all-staff') {
                $this->assertCount(30, $postmembers);
            } else {
                $this->assertCount(10, $postmembers);
                foreach ($cohortusers[$key]['activemembers'] as $member) {
                    $this->assertTrue(cohort_is_member($cohort->id, $member->id));
                }
                foreach ($cohortusers[$key]['newmembers'] as $member) {
                    $this->assertTrue(cohort_is_member($cohort->id, $member->id));
                }
                foreach ($cohortusers[$key]['suspendedmembers'] as $member) {
                    $this->assertFalse(cohort_is_member($cohort->id, $member->id));
                }
                foreach ($supportusers[$key] as $supportuser) {
                    $this->assertFalse(cohort_is_member($cohort->id, $supportuser->id));
                }
            }
            $this->expectOutputRegex("/Added.*?academic/");
        }
    }

    /**
     * Tests the sync_user_profile_cohort function via user events
     *
     * @param array $before Conditions before update
     * @param array $after Conditions after update
     * @dataProvider sync_user_profile_cohort_provider
     * @covers \local_cohorts\helper::sync_user_profile_cohort
     * @covers \local_cohorts\observers::user_updated
     * @covers \local_cohorts\observers::user_created
     * @return void
     */
    public function test_sync_user_profile_cohort($before, $after) {
        global $DB;
        $this->resetAfterTest();
        $systemcontext = context_system::instance();
        $supportaccounts = ['academic', 'consultant', 'jobshop'];
        set_config('staffcohorts', 'academic,management,support', 'local_cohorts');
        set_config('emailexcludepattern', join(',', $supportaccounts), 'local_cohorts');
        // Set up the cohorts.
        $deptcohorts = ['academic', 'management', 'support', 'student'];
        // I've deliberately left out Warsash, as this will be dynamically created.
        $instcohorts = [
            'Information & Communications Technology',
            'Arts and Music',
            'Social Sciences and Nursing',
            'Science and Engineering',
        ];
        foreach ($deptcohorts as $dept) {
            if (!$DB->record_exists('cohort', ['idnumber' => $dept, 'contextid' => $systemcontext->id])) {
                $this->getDataGenerator()->create_cohort([
                    'name' => ucwords($dept),
                    'idnumber' => $dept,
                    'contextid' => $systemcontext->id,
                    'component' => 'local_cohorts',
                ]);
            }
        }
        foreach ($instcohorts as $instcohort) {
            $key = 'inst_' . helper::slugify($instcohort);
            $this->getDataGenerator()->create_cohort([
                'name' => $instcohort,
                'idnumber' => $key,
                'contextid' => $systemcontext->id,
                'component' => 'local_cohorts',
            ]);
        }
        $this->getDataGenerator()->create_cohort([
            'name' => 'All Staff',
            'idnumber' => 'all-staff',
            'contextid' => $systemcontext->id,
            'component' => 'local_cohorts',
        ]);
        $this->getDataGenerator()->create_cohort([
            'name' => 'Student 6 months',
            'idnumber' => 'student6',
            'contextid' => $systemcontext->id,
            'component' => 'local_cohorts',
        ]);
        // This will trigger the user_created event.
        $user = $this->getDataGenerator()->create_user($before['user']);
        $systemcohorts = $DB->get_records('cohort', [
            'contextid' => $systemcontext->id,
            'component' => 'local_cohorts',
        ]);

        foreach ($systemcohorts as $cohort) {
            $ismember = cohort_is_member($cohort->id, $user->id);
            if (in_array($cohort->idnumber, $before['ismember'])) {
                $this->assertTrue($ismember);
            } else {
                $this->assertFalse($ismember);
            }
        }

        $deleteme = false;
        foreach ($after['user'] as $field => $value) {
            if ($field == 'deleted') {
                $deleteme = true;
            } else {
                $user->{$field} = $value;
            }
        }

        if ($deleteme) {
            // Delete deletes all cohort memberships without needing events.
            user_delete_user($user);
        } else {
            // This will trigger the user_updated event.
            user_update_user($user, false);
        }

        $systemcohorts = $DB->get_records('cohort', [
            'contextid' => $systemcontext->id,
            'component' => 'local_cohorts',
        ]);
        foreach ($systemcohorts as $cohort) {
            $ismember = cohort_is_member($cohort->id, $user->id);
            if (in_array($cohort->idnumber, $after['ismember'])) {
                $this->assertTrue($ismember);
            } else {
                $this->assertFalse($ismember);
            }
        }
    }

    /**
     * Provider for test_sync_user_profile_cohort
     *
     * @return array
     */
    public static function sync_user_profile_cohort_provider(): array {
        return [
            'academic-warsash' => [
                'before' => [
                    'user' => [
                        'auth' => 'ldap',
                        'department' => 'academic',
                        'email' => 'jamie.teacher@solent.ac.uk',
                        'username' => 'teacherj',
                    ],
                    'ismember' => [
                        'academic',
                        'all-staff',
                    ],
                ],
                'after' => [
                    'user' => [
                        'auth' => 'ldap',
                        'department' => 'academic',
                        'email' => 'jamie.teacher@solent.ac.uk',
                        'institution' => 'Warsash Maritime School',
                        'username' => 'teacherj',
                    ],
                    'ismember' => [
                        'academic',
                        'all-staff',
                        'inst_warsash-maritime-school',
                    ],
                ],
            ],
            // No manual accounts are added to cohorts.
            'manual-academic-warsash' => [
                'before' => [
                    'user' => [
                        'auth' => 'manual',
                        'department' => 'academic',
                        'institution' => 'Warsash Maritime School',
                        'email' => 'jamie.teacher@solent.ac.uk',
                        'username' => 'teacherj',
                    ],
                    'ismember' => [],
                ],
                'after' => [
                    'user' => [
                        'auth' => 'manual',
                        'department' => 'academic',
                        'institution' => 'ICT',
                        'email' => 'jamie.teacher@solent.ac.uk',
                        'username' => 'teacherj',
                    ],
                    'ismember' => [],
                ],
            ],
            // Any username with academic in it isn't included.
            'consultant-ee-account-with-named-email' => [
                'before' => [
                    'user' => [
                        'auth' => 'ldap',
                        'email' => 'jamie.teacher@solent.ac.uk',
                        'username' => 'academic123',
                    ],
                    'ismember' => [],
                ],
                'after' => [
                    'user' => [
                        'auth' => 'ldap',
                        'department' => 'academic',
                        'institution' => 'External',
                        'email' => 'jamie.teacher@solent.ac.uk',
                        'username' => 'academic123',
                    ],
                    'ismember' => [],
                ],
            ],
            // Any email with academic in it isn't included.
            'consultant-ee-account' => [
                'before' => [
                    'user' => [
                        'auth' => 'ldap',
                        'email' => 'academic123@solent.ac.uk',
                        'username' => 'academic123',
                    ],
                    'ismember' => [],
                ],
                'after' => [
                    'user' => [
                        'auth' => 'ldap',
                        'department' => 'academic',
                        'institution' => 'External',
                        'email' => 'academic123@solent.ac.uk',
                        'username' => 'academic123',
                    ],
                    'ismember' => [],
                ],
            ],
            'solent_academic_suspended' => [
                'before' => [
                    'user' => [
                        'auth' => 'ldap',
                        'department' => 'academic',
                        'email' => 'john.smith@solent.ac.uk',
                    ],
                    'ismember' => ['academic', 'all-staff'],
                ],
                'after' => [
                    'user' => [
                        'department' => 'academic',
                        'suspended' => 1,
                    ],
                    'ismember' => [],
                ],
            ],
            'solent_academic_suspended_to_active' => [
                'before' => [
                    'user' => [
                        'auth' => 'ldap',
                        'department' => 'academic',
                        'email' => 'john.smith@solent.ac.uk',
                        'suspended' => 1,
                    ],
                    'ismember' => [],
                ],
                'after' => [
                    'user' => [
                        'department' => 'academic',
                        'suspended' => 0,
                    ],
                    'ismember' => ['academic', 'all-staff'],
                ],
            ],
            'solent_academic_deleted' => [
                'before' => [
                    'user' => [
                        'auth' => 'ldap',
                        'department' => 'academic',
                        'email' => 'john.smith@solent.ac.uk',
                    ],
                    'ismember' => ['academic', 'all-staff'],
                ],
                'after' => [
                    'user' => [
                        'department' => 'academic',
                        'deleted' => 1,
                    ],
                    'ismember' => [],
                ],
            ],
            'support_jobshop' => [
                'before' => [
                    'user' => [
                        'auth' => 'ldap',
                        'email' => 'jobshop1@solent.ac.uk',
                    ],
                    'ismember' => [],
                ],
                'after' => [
                    'user' => [
                        'department' => 'support',
                    ],
                    'ismember' => [],
                ],
            ],
            'support_consultant' => [
                'before' => [
                    'user' => [
                        'email' => 'bobby.console@solent.ac.uk',
                        'username' => 'consultant1',
                    ],
                    'ismember' => [],
                ],
                'after' => [
                    'user' => [
                        'department' => 'support',
                    ],
                    'ismember' => [],
                ],
            ],
            // External email addresses aren't included.
            'external-email-even-with-ldap' => [
                'before' => [
                    'user' => [
                        'auth' => 'ldap',
                        'department' => 'academic',
                        'institution' => 'External',
                        'email' => 'jamie.teacher@external.ac.uk',
                        'username' => 'jamie.teacher',
                    ],
                    'ismember' => [],
                ],
                'after' => [
                    'user' => [],
                    'ismember' => [],
                ],
            ],
            'solent_academic_to_support' => [
                'before' => [
                    'user' => [
                        'auth' => 'ldap',
                        'email' => 'john.smith@solent.ac.uk',
                        'department' => 'academic',
                    ],
                    'ismember' => ['academic', 'all-staff'],
                ],
                'after' => [
                    'user' => [
                        'department' => 'support',
                    ],
                    'ismember' => ['support', 'all-staff'],
                ],
            ],
            'random_dept' => [
                'before' => [
                    'user' => [
                        'auth' => 'ldap',
                        'department' => 'random',
                        'email' => 'john.smith@solent.ac.uk',
                    ],
                    'ismember' => ['random'],
                ],
                'after' => [
                    'user' => [],
                    'ismember' => ['random'],
                ],
            ],
            'student' => [
                'before' => [
                    'user' => [
                        'auth' => 'ldap',
                        'department' => 'student',
                        'email' => 'smithj1@solent.ac.uk',
                    ],
                    'ismember' => ['student'],
                ],
                'after' => [
                    'user' => [],
                    'ismember' => ['student'],
                ],
            ],
            // Manual accounts should not be included.
            'student-manual' => [
                'before' => [
                    'user' => [
                        'auth' => 'ldap',
                        'department' => 'student',
                        'institution' => '',
                        'email' => 'julie.student@solent.ac.uk',
                        'username' => '0studentj1',
                    ],
                    'ismember' => ['student'],
                ],
                'after' => [
                    'user' => [
                        'auth' => 'manual',
                        'department' => 'student',
                        'institution' => '',
                        'email' => 'julie.student@solent.ac.uk',
                        'username' => '0studentj1',
                    ],
                    'ismember' => [],
                ],
            ],
        ];
    }

    /**
     * Test adopt_a_cohort
     * @covers \local_cohort\helper::adopt_a_cohort
     *
     * @return void
     */
    public function test_adopt_a_cohort() {
        $this->resetAfterTest();
        $systemcontext = context_system::instance();
        $cohorts = [];
        $cohorts['system'] = $this->getDataGenerator()->create_cohort([
            'description' => 'Something something Auto populated something something',
            'idnumber' => 'system-cohort',
            'contextid' => $systemcontext->id,
        ]);
        $cohorts['noidnumber'] = $this->getDataGenerator()->create_cohort([
            'name' => 'No Idnumber',
            'idnumber' => '',
            'contextid' => $systemcontext->id,
        ]);
        $cohorts['manually'] = $this->getDataGenerator()->create_cohort([
            'name' => 'Manually',
            'idnumber' => 'manually',
            'description' => 'Manually populated',
            'contextid' => $systemcontext->id,
        ]);
        $cohorts['auto'] = $this->getDataGenerator()->create_cohort([
            'name' => 'Auto populated',
            'idnumber' => 'auto',
            'description' => 'Auto populated',
            'contextid' => $systemcontext->id,
        ]);
        $cohorts['system-owned'] = $this->getDataGenerator()->create_cohort([
            'idnumber' => 'system-owned',
            'contextid' => $systemcontext->id,
            'component' => 'local_something',
        ]);
        $cat = $this->getDataGenerator()->create_category();
        $catccontext = context_coursecat::instance($cat->id);
        $cohorts['cat'] = $this->getDataGenerator()->create_cohort([
            'idnumber' => 'cat',
            'contextid' => $catccontext->id,
        ]);
        $this->assertTrue(helper::adopt_a_cohort($cohorts['system']->id));
        $this->assertFalse(helper::adopt_a_cohort($cohorts['noidnumber']->id));
        $this->assertFalse(helper::adopt_a_cohort($cohorts['manually']->id));
        $this->assertTrue(helper::adopt_a_cohort($cohorts['auto']->id));
        $this->assertFalse(helper::adopt_a_cohort($cohorts['system-owned']->id));
        $this->assertFalse(helper::adopt_a_cohort($cohorts['cat']->id));
    }

    /**
     * Test migrate cohorts
     * @covers \local_cohorts\helper::migrate_cohorts
     *
     * @return void
     */
    public function test_migrate_cohorts() {
        global $DB;
        $this->resetAfterTest();
        // I don't want events to trigger for this test.
        $sink = $this->redirectEvents();
        $systemcontext = context_system::instance();
        $cohorts = [];
        // No migration: Incorrect description.
        $cohorts['system'] = $this->getDataGenerator()->create_cohort([
            'name' => 'System cohorts',
            'idnumber' => 'system-cohort',
            'contextid' => $systemcontext->id,
        ]);
        // Yes migration: Auto populated in description and not owned by another plugin.
        $cohorts['system-description'] = $this->getDataGenerator()->create_cohort([
            'description' => 'Something something Auto populated something something',
            'idnumber' => 'system-cohort',
            'contextid' => $systemcontext->id,
        ]);
        // No migration: No idnumber.
        $cohorts['noidnumber'] = $this->getDataGenerator()->create_cohort([
            'name' => 'No Idnumber',
            'idnumber' => '',
            'contextid' => $systemcontext->id,
        ]);
        // No migration: No Auto populated in description.
        $cohorts['manually'] = $this->getDataGenerator()->create_cohort([
            'name' => 'Manually',
            'idnumber' => 'manually',
            'description' => 'Manually populated',
            'contextid' => $systemcontext->id,
        ]);
        // Yes migration: Auto populated in description.
        $cohorts['auto'] = $this->getDataGenerator()->create_cohort([
            'name' => 'Auto populated',
            'idnumber' => 'auto',
            'description' => 'Auto populated',
            'contextid' => $systemcontext->id,
        ]);
        $migrated = $DB->get_records('cohort', [
            'contextid' => $systemcontext->id,
            'component' => 'local_cohorts',
        ]);
        $this->assertCount(0, $migrated);

        helper::migrate_cohorts();
        $migrated = $DB->get_records('cohort', [
            'contextid' => $systemcontext->id,
            'component' => 'local_cohorts',
        ]);
        $this->assertCount(2, $migrated);

        // Duplicate to ensure each cohort is only created once.
        $this->getDataGenerator()->create_user(['department' => 'Academic', 'institution' => 'Warsash Maritime School']);
        $this->getDataGenerator()->create_user(['department' => 'Academic', 'institution' => 'Warsash Maritime School']);
        helper::migrate_cohorts();
        $migrated = $DB->get_records('cohort', [
            'contextid' => $systemcontext->id,
            'component' => 'local_cohorts',
        ]);
        $this->assertCount(4, $migrated);

        $this->getDataGenerator()->create_user([
            'department' => 'support',
            'institution' => 'Information & Communications Technology',
        ]);
        helper::migrate_cohorts();
        $migrated = $DB->get_records('cohort', [
            'contextid' => $systemcontext->id,
            'component' => 'local_cohorts',
        ]);
        $this->assertCount(6, $migrated);

        $this->getDataGenerator()->create_user(['department' => 'Management', 'institution' => 'Solent Students\' Union']);
        helper::migrate_cohorts();
        $migrated = $DB->get_records('cohort', [
            'contextid' => $systemcontext->id,
            'component' => 'local_cohorts',
        ]);
        $this->assertCount(8, $migrated);
    }
}
