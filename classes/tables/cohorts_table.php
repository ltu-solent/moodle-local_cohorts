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

namespace local_cohorts\tables;

use action_menu;
use action_menu_link;
use context;
use context_system;
use html_writer;
use lang_string;
use moodle_url;
use paging_bar;
use pix_icon;
use stdClass;
use table_sql;

defined('MOODLE_INTERNAL') || die();
require_once("$CFG->libdir/tablelib.php");

/**
 * Class cohorts_table
 *
 * @package    local_cohorts
 * @copyright  2024 Solent University {@link https://www.solent.ac.uk}
 * @author Mark Sharp <mark.sharp@solent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cohorts_table extends table_sql {
    /**
     * Constructor to set up table.
     *
     * @param string $uniqueid
     */
    public function __construct($uniqueid) {
        parent::__construct($uniqueid);
        $columns = [
            'id',
            'name',
            'idnumber',
            'description',
            'visible',
            'enabled',
            'members',
            'actions',
        ];

        $columnheadings = [
            'id',
            new lang_string('name', 'cohort'),
            new lang_string('idnumber', 'cohort'),
            new lang_string('description', 'cohort'),
            new lang_string('visible', 'cohort'),
            new lang_string('enabled', 'local_cohorts'),
            new lang_string('memberscount', 'cohort'),
            new lang_string('actions'),
        ];

        $this->define_columns($columns);
        $this->define_headers($columnheadings);
        $this->no_sorting('actions');
        $this->collapsible(false);
        $this->define_baseurl(new moodle_url('/local/cohorts/index.php'));

        $select = "c.*, lcs.enabled";
        $from = "{cohort} c
                LEFT JOIN {local_cohorts_status} lcs ON lcs.cohortid = c.id
            ";
            $where = "contextid = :contextid AND component = 'local_cohorts'";

        $this->set_sql($select, $from, $where, [
            'contextid' => context_system::instance()->id,
        ]);
    }

    /**
     * Actions column
     *
     * @param stdClass $row
     * @return string HTML for actions
     */
    public function col_actions($row): string {
        global $OUTPUT;
        $context = context::instance_by_id($row->contextid);
        if (!has_capability('moodle/cohort:manage', $context)) {
            return '';
        }
        $returnurl = new moodle_url('/local/cohorts/index.php');
        $actionmenu = new action_menu();
        $actionmenu->set_menu_trigger(get_string('actions'));
        $actionmenu->set_boundary('window');
        $actionmenu->add(new action_menu_link(
            new moodle_url('/local/cohorts/members.php', ['cohortid' => $row->id]),
            new pix_icon('t/cohort', '', 'core'),
            get_string('viewmembers', 'local_cohorts'),
            false,
        ));
        if ($row->visible == 1) {
            $actionmenu->add(new action_menu_link(
                new moodle_url('/local/cohorts/edit.php', [
                    'id' => $row->id,
                    'hide' => 1,
                    'returnurl' => $returnurl,
                    'sesskey' => sesskey(),
                ]),
                new pix_icon('t/hide', '', 'core'),
                get_string('hidecohort', 'local_cohorts'),
                false,
            ));
        } else {
            $actionmenu->add(new action_menu_link(
                new moodle_url('/local/cohorts/edit.php', [
                    'id' => $row->id,
                    'show' => 1,
                    'returnurl' => $returnurl,
                    'sesskey' => sesskey(),
                ]),
                new pix_icon('t/show', '', 'core'),
                get_string('showcohort', 'local_cohorts'),
                false,
            ));
        }
        if ($row->enabled == 1) {
            $actionmenu->add(new action_menu_link(
                new moodle_url('/local/cohorts/edit.php', [
                    'id' => $row->id,
                    'disable' => 1,
                    'returnurl' => $returnurl,
                    'sesskey' => sesskey(),
                ]),
                new pix_icon('t/go', '', 'core'),
                get_string('disablecohort', 'local_cohorts', ''),
                false,
            ));
        } else {
            $actionmenu->add(new action_menu_link(
                new moodle_url('/local/cohorts/edit.php', [
                    'id' => $row->id,
                    'enable' => 1,
                    'returnurl' => $returnurl,
                    'sesskey' => sesskey(),
                ]),
                new pix_icon('t/stop', '', 'core'),
                get_string('enablecohort', 'local_cohorts', ''),
                false,
            ));
        }
        $actionmenu->add(new action_menu_link(
            new moodle_url('/local/cohorts/edit.php', [
                'id' => $row->id,
                'delete' => 1,
                'returnurl' => $returnurl,
                'sesskey' => sesskey(),
            ]),
            new pix_icon('t/delete', '', 'core'),
            get_string('deletecohort', 'local_cohorts'),
            false,
        ));

        return $OUTPUT->render($actionmenu);
    }

    /**
     * Enabled status
     *
     * @param stdClass $row
     * @return string HTML for status
     */
    public function col_enabled($row): string {
        if ($row->enabled == 1) {
            return get_string('enabled', 'local_cohorts');
        }
        return get_string('notenabled', 'local_cohorts');
    }

    /**
     * Member count
     *
     * @param stdClass $row
     * @return string HTML count of members
     */
    public function col_members($row): string {
        global $DB;
        return $DB->count_records('cohort_members', ['cohortid' => $row->id]);
    }

    /**
     * Name of cohort with link
     *
     * @param stdClass $row
     * @return string
     */
    public function col_name($row): string {
        $url = new moodle_url('/local/cohorts/members.php', ['cohortid' => $row->id]);
        return html_writer::link($url, $row->name, ['title' => get_string('viewmembersof', 'local_cohorts', $row->name)]);
    }

    /**
     * Cohort visibility
     *
     * @param object $row
     * @return string HTML description of visibility
     */
    public function col_visible($row): string {
        if ($row->visible == 1) {
            return get_string('visible');
        }
        return get_string('notvisible', 'local_cohorts');
    }

    /**
     * CSS class for the row.
     *
     * @param stdClass $row
     * @return string
     */
    public function get_row_class($row): string {
        $css = (!$row->visible) ? 'text-muted' : '';
        $css = (!$row->enabled) ? 'text-danger' : $css;
        return $css;
    }
}
