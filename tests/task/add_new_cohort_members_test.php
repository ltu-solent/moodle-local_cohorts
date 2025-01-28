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

use context_system;
use Exception;
use local_cohorts\helper;

/**
 * Tests for SOL Cohorts
 *
 * @package    local_cohorts
 * @category   test
 * @copyright  2024 Solent University {@link https://www.solent.ac.uk}
 * @author Mark Sharp <mark.sharp@solent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class add_new_cohort_members_test extends \advanced_testcase {
    /**
     * Test task execute
     *
     * @covers \local_cohorts\tasks\add_new_cohort_members::execute
     * @dataProvider execute_provider
     * @param array $before User status before running task
     * @param array $after User status after running task
     * @param string $expectedoutput from the task
     * @return void
     */
    public function test_execute($before, $after, $expectedoutput): void {
        global $DB;
        /** @var local_cohorts_generator $dg */
        $dg = $this->getDataGenerator()->get_plugin_generator('local_cohorts');
        $this->resetAfterTest();
        $systemcontext = context_system::instance();
        // I don't want events to trigger for this test.
        $sink = $this->redirectEvents();
        $supportaccounts = ['academic', 'consultant', 'jobshop'];
        set_config('staffcohorts', 'academic,management,support', 'local_cohorts');
        set_config('emailexcludepattern', join(',', $supportaccounts), 'local_cohorts');
        // Set up the cohorts.
        $deptcohorts = ['academic', 'management', 'support', 'student'];
        $instcohorts = [
            'Warsash Maritime School',
            'Information & Communications Technology',
            'Arts and Music',
            'Social Sciences and Nursing',
            'Science and Engineering',
        ];
        foreach ($deptcohorts as $dept) {
            if (!$DB->record_exists('cohort', [
                    'idnumber' => $dept,
                    'contextid' => $systemcontext->id,
                    'component' => 'local_cohorts',
                ])) {
                $dg->add_managed_cohort([
                    'name' => ucwords($dept),
                    'idnumber' => $dept,
                    'contextid' => $systemcontext->id,
                    'component' => 'local_cohorts',
                ]);
            }
        }
        foreach ($instcohorts as $instcohort) {
            $key = 'inst_' . helper::slugify($instcohort);
            $dg->add_managed_cohort([
                'name' => $instcohort,
                'idnumber' => $key,
                'contextid' => $systemcontext->id,
                'component' => 'local_cohorts',
            ]);
        }
        $dg->add_managed_cohort([
            'name' => 'All Staff',
            'idnumber' => 'all-staff',
            'contextid' => $systemcontext->id,
            'component' => 'local_cohorts',
        ]);

        $dg->add_managed_cohort([
            'name' => 'Student 6 months',
            'idnumber' => 'student6',
            'contextid' => $systemcontext->id,
            'component' => 'local_cohorts',
        ]);

        // I've put the events into a sink, so we can execute the task.
        $user = $this->getDataGenerator()->create_user($before['user']);
        $task = new add_new_cohort_members();
        $task->execute();
        $systemcohorts = $DB->get_records('cohort', [
            'contextid' => $systemcontext->id,
            'component' => 'local_cohorts',
        ]);
        // The user should only be a member of cohorts listed.
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
            // Delete deletes all cohort memberships without needing the task.
            user_delete_user($user);
        } else {
            // I've put the events into a sink, so we can execute the task.
            user_update_user($user, false);
        }

        $task->execute();

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
        $this->expectOutputString($expectedoutput);
    }

    /**
     * Provider for test_execute
     *
     * @return array
     */
    public static function execute_provider(): array {
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
                'expected' => "Added teacherj academic  to Academic (academic)\n" .
                              "Added teacherj academic  to All Staff (all-staff)\n" .
                              "Added teacherj academic Warsash Maritime School to " .
                                "Warsash Maritime School (inst_warsash-maritime-school)\n",
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
                'expected' => '',
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
                'expected' => '',
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
                'expected' => '',
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
                'expected' => "Added username1 academic  to Academic (academic)\n" .
                              "Added username1 academic  to All Staff (all-staff)\n" .
                              "Removed username1 from Academic (academic)\n" .
                              "Removed username1 from All Staff (all-staff)\n",
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
                'expected' => "Added username1 academic  to Academic (academic)\n" .
                              "Added username1 academic  to All Staff (all-staff)\n",
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
                'expected' => "Added username1 academic  to Academic (academic)\n" .
                              "Added username1 academic  to All Staff (all-staff)\n",
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
                'expected' => '',
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
                'expected' => '',
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
                'expected' => '',
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
                'expected' => "Added username1 academic  to Academic (academic)\n" .
                              "Added username1 academic  to All Staff (all-staff)\n" .
                              "Removed username1 from Academic (academic)\n" .
                              "Added username1 support  to Support (support)\n",
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
                'expected' => '',
            ],
            'student' => [
                'before' => [
                    'user' => [
                        'auth' => 'ldap',
                        'department' => 'student',
                        'email' => 'smithj1@solent.ac.uk',
                    ],
                    'ismember' => [
                        'student',
                        'student6',
                    ],
                ],
                'after' => [
                    'user' => [],
                    'ismember' => [
                        'student',
                        'student6',
                    ],
                ],
                'expected' => "Added username1 student  to Student (student)\n" .
                              "username1 added to 'student6' cohort\n",
            ],
            'student6-within-6-months' => [
                'before' => [
                    'user' => [
                        'auth' => 'ldap',
                        'department' => 'student',
                        'institution' => '',
                        'email' => 'julie.student@solent.ac.uk',
                        'username' => '0studentj1',
                        'timecreated' => strtotime("-5 months"),
                    ],
                    'ismember' => [
                        'student6',
                        'student',
                    ],
                ],
                'after' => [
                    'user' => [
                        'auth' => 'ldap',
                        'department' => 'student',
                        'institution' => '',
                        'email' => 'julie.student@solent.ac.uk',
                        'username' => '0studentj1',
                        'timecreated' => strtotime("-7 months"),
                    ],
                    'ismember' => [
                        'student',
                    ],
                ],
                'expected' => "Added 0studentj1 student  to Student (student)\n" .
                              "0studentj1 added to 'student6' cohort\n" .
                              "0studentj1 removed from 'student6' cohort\n",
            ],
            // Manual accounts should not be included.
            'student6-manual' => [
                'before' => [
                    'user' => [
                        'auth' => 'ldap',
                        'department' => 'student',
                        'institution' => '',
                        'email' => 'julie.student@solent.ac.uk',
                        'username' => '0studentj1',
                    ],
                    'ismember' => [
                        'student',
                        'student6',
                    ],
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
                'expected' => "Added 0studentj1 student  to Student (student)\n" .
                              "0studentj1 added to 'student6' cohort\n" .
                              "Removed 0studentj1 from Student (student)\n" .
                              "0studentj1 removed from 'student6' cohort\n",
            ],
        ];
    }
}
