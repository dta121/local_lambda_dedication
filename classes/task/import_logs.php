<?php
// This file is part of Zoola Analytics plugin for Moodle.
//
// Zoola Analytics plugin for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Zoola Analytics plugin for Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Zoola Analytics plugin for Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * @package local_lambda_dedication
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author Branko Vukasovic <branko.vukasovic@lambdasolutions.net>
 * @copyright (C) 2017 onwards Lambda Solutions, Inc. (https://www.lambdasolutions.net)
 */

namespace local_lambda_dedication\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/lambda_dedication/locallib.php');

/**
 * Adhoc task that reads logs and recalculates dedication tables.
 */
class import_logs extends \core\task\adhoc_task {

    private $import_task_status;

    private function update_task_status($new_status) {
        global $DB;
        foreach ($new_status as $key => $value) {
            $this->import_task_status->$key = $value;
        }
        $this->import_task_status->timemodified = time();
        $DB->update_record('local_ld_import_logs_status', $this->import_task_status);
    }

    public function execute() {
        /* @var $DB \moodle_database */
        global $DB;

        $timelimit = time();
        $DB->delete_records('local_ld_course');
        $DB->delete_records('local_ld_course_day');
        $DB->delete_records('local_ld_module');
        $DB->delete_records('local_ld_module_day');

        $progress = new \error_log_progress_trace('local_lambda_dedication\task\import_logs: ');

        $this->import_task_status = $DB->get_record('local_ld_import_logs_status', array(), '*', MUST_EXIST);
        $this->update_task_status(array(
            'status' => 'inprogress',
            'timestarted' => time()
        ));

        $userids = $DB->get_fieldset_select('user', 'id', 'deleted = 0 and id > 2');
        $progress->output("Import started");
        $usercount = count($userids);
        $i = 0;
        foreach ($userids as $userid) {
            local_lambda_dedication_import_user($userid, $timelimit);
            $i++;
            if ($i % 1000 === 0) {
                $progress->output("User ID: $userid, $i out of $usercount processed", 1);
            }
            $this->update_task_status(array(
                'progress' => $i * 100 / $usercount
            ));
        }
        $progress->output("Import finished");

        $this->update_task_status(array(
            'status' => 'finished',
            'progress' => 100,
            'timefinished' => time()
        ));
    }

}
