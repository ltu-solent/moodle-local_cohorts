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
        // I don't want events to trigger for this test.
        $sink = $this->redirectEvents();
        // Create users for each department.
        $systemcohorts = ['academic' => null, 'management' => null, 'support' => null];
        $supportaccounts = ['academic', 'consultant', 'jobshop'];
        set_config('systemcohorts', join(',', array_keys($systemcohorts)), 'local_cohorts');
        set_config('emailexcludepattern', join(',', $supportaccounts), 'local_cohorts');
        $cohortusers = [];
        $counter = 0;
        $supportusers = [];
        foreach ($systemcohorts as $key => $cohort) {
            $systemcohorts[$key] = $this->getDataGenerator()->create_cohort([
                'name' => ucwords($key),
                'idnumber' => $key,
            ]);
            for ($x = 0; $x < 5; $x++) {
                // Preexisting cohort member.
                $user = $this->getDataGenerator()->create_user([
                    'department' => $key,
                    'email' => 'user' . $x . $counter . '@solent.ac.uk',
                ]);
                cohort_add_member($systemcohorts[$key]->id, $user->id);
                $cohortusers[$key]['activemembers'][] = $user;

                // Member to be added by function.
                $user = $this->getDataGenerator()->create_user([
                    'department' => $key,
                    'email' => 'user' . $x . $counter . '@solent.ac.uk',
                ]);
                $cohortusers[$key]['newmembers'][] = $user;

                // Suspended member who will be removed.
                $user = $this->getDataGenerator()->create_user([
                    'department' => $key,
                    'suspended' => 1,
                    'email' => 'user' . $x . $counter . '@solent.ac.uk',
                ]);
                cohort_add_member($systemcohorts[$key]->id, $user->id);
                $cohortusers[$key]['suspendedmembers'][] = $user;

                // Some support accounts that shouldn't be added.
                foreach ($supportaccounts as $supportaccount) {
                    $supportusers[$key][] = $this->getDataGenerator()->create_user([
                        'department' => $key,
                        'email' => $supportaccount . $x . $counter . '@solent.ac.uk',
                    ]);
                }
                // User with invalid email address for inclusion.
                $supportusers[$key][] = $this->getDataGenerator()->create_user([
                    'department' => $key,
                    'email' => 'other' . $x . $counter . '@example.com',
                ]);
                $counter++;
            }
        }
        foreach ($systemcohorts as $key => $cohort) {
            $premembers = $DB->get_records('cohort_members', ['cohortid' => $cohort->id]);
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

            // Function under test.
            helper::update_user_department_cohort($cohort->id);

            $postmembers = $DB->get_records('cohort_members', ['cohortid' => $cohort->id]);
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
    }

    /**
     * Tests the sync_user_department function via user events
     *
     * @param array $before Conditions before update
     * @param array $after Conditions after update
     * @dataProvider sync_user_department_provider
     * @covers \local_cohorts\helper::sync_user_department
     * @covers \local_cohorts\observers::user_updated
     * @covers \local_cohorts\observers::user_created
     * @return void
     */
    public function test_sync_user_department($before, $after) {
        $this->resetAfterTest();
        $systemcohorts = ['academic' => null, 'management' => null, 'support' => null];
        $supportaccounts = ['academic', 'consultant', 'jobshop'];
        set_config('systemcohorts', join(',', array_keys($systemcohorts)), 'local_cohorts');
        set_config('emailexcludepattern', join(',', $supportaccounts), 'local_cohorts');
        foreach ($systemcohorts as $key => $cohort) {
            $systemcohorts[$key] = $this->getDataGenerator()->create_cohort([
                'name' => ucwords($key),
                'idnumber' => $key,
            ]);
        }
        $randomcohort = $this->getDataGenerator()->create_cohort(['idnumber' => 'random']);

        // This will trigger the user_created event.
        $user = $this->getDataGenerator()->create_user($before['user']);
        foreach ($systemcohorts as $idnumber => $cohort) {
            $ismember = cohort_is_member($cohort->id, $user->id);
            if (in_array($idnumber, $before['ismember'])) {
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
            user_update_user($user, false, true);
        }

        foreach ($systemcohorts as $idnumber => $cohort) {
            $ismember = cohort_is_member($cohort->id, $user->id);
            if (in_array($idnumber, $after['ismember'])) {
                $this->assertTrue($ismember);
            } else {
                $this->assertFalse($ismember);
            }
        }
        $this->assertFalse(cohort_is_member($randomcohort->id, $user->id));
    }

    /**
     * Provider for test_sync_user_department
     *
     * @return array
     */
    public static function sync_user_department_provider(): array {
        return [
            'solent_academic' => [
                'before' => [
                    'user' => [
                        'email' => 'john.smith@solent.ac.uk',
                    ],
                    'ismember' => [],
                ],
                'after' => [
                    'user' => [
                        'department' => 'academic',
                    ],
                    'ismember' => ['academic'],
                ],
            ],
            'support_academic' => [
                'before' => [
                    'user' => [
                        'email' => 'academic1@solent.ac.uk',
                    ],
                    'ismember' => [],
                ],
                'after' => [
                    'user' => [
                        'department' => 'academic',
                    ],
                    'ismember' => [],
                ],
            ],
            'solent_academic_suspended' => [
                'before' => [
                    'user' => [
                        'department' => 'academic',
                        'email' => 'john.smith@solent.ac.uk',
                    ],
                    'ismember' => ['academic'],
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
                    'ismember' => ['academic'],
                ],
            ],
            'solent_academic_deleted' => [
                'before' => [
                    'user' => [
                        'department' => 'academic',
                        'email' => 'john.smith@solent.ac.uk',
                    ],
                    'ismember' => ['academic'],
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
                        'email' => 'consultant1@solent.ac.uk',
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
            'external_account' => [
                'before' => [
                    'user' => [
                        'email' => 'john.smith@another.ac.uk',
                        'department' => 'academic',
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
                        'email' => 'john.smith@solent.ac.uk',
                        'department' => 'academic',
                    ],
                    'ismember' => ['academic'],
                ],
                'after' => [
                    'user' => [
                        'department' => 'support',
                    ],
                    'ismember' => ['support'],
                ],
            ],
            'random_dept' => [
                'before' => [
                    'user' => [
                        'department' => 'random',
                        'email' => 'john.smith@solent.ac.uk',
                    ],
                    'ismember' => [],
                ],
                'after' => [
                    'user' => [],
                    'ismember' => [],
                ],
            ],
            'student_no_cohort' => [
                'before' => [
                    'user' => [
                        'department' => 'student',
                        'email' => 'smithj1@solent.ac.uk',
                    ],
                    'ismember' => [],
                ],
                'after' => [
                    'user' => [],
                    'ismember' => [],
                ],
            ],
        ];
    }
}
