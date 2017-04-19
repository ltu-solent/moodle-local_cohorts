<?php

namespace local_cohorts\task;

class add_new_cohort_members extends \core\task\scheduled_task {      
    public function get_name() {
        // Shown in admin screens
        return get_string('pluginname', 'local_cohorts');
    }
                                                                     
    public function execute() {       
		global $CFG;
        require_once($CFG->dirroot.'/local/cohorts/lib.php');
		add_academics();
		add_support();
		add_management();
    }                                                                                                                               
} 