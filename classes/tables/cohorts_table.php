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

use context_system;
use html_writer;
use lang_string;
use moodle_url;
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
            'members',
            'actions',
        ];

        $columnheadings = [
            'id',
            new lang_string('name', 'cohort'),
            new lang_string('idnumber', 'cohort'),
            new lang_string('description', 'cohort'),
            new lang_string('visible', 'cohort'),
            new lang_string('memberscount', 'cohort'),
            new lang_string('actions'),
        ];

        $this->define_columns($columns);
        $this->define_headers($columnheadings);
        $this->no_sorting('actions');
        $this->collapsible(false);
        $this->define_baseurl(new moodle_url('/local/cohorts/index.php'));

        $select = "c.*, COUNT(cm.id) members";
        $from = "{cohort} c
            LEFT JOIN {cohort_members} cm ON cm.cohortid = c.id";
            $where = "contextid = :contextid AND component = 'local_cohorts'
            GROUP BY c.id
            ORDER BY c.name ASC";

        $this->set_sql($select, $from, $where, [
            'contextid' => context_system::instance()->id,
        ]);
    }

    /**
     * Name of cohort with link
     *
     * @param stdClass $row
     * @return string
     */
    public function col_name($row): string {
        $url = new moodle_url('/local/cohorts/members.php', ['cohortid' => $row->id]);
        return html_writer::link($url, $row->name, ['title' => get_string('viewmembers', 'local_cohorts', $row->name)]);
    }
}
