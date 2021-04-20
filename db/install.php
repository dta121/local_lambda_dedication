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

defined('MOODLE_INTERNAL') || die();

function xmldb_local_lambda_dedication_install() {
    /* @var $DB moodle_database */
    global $DB;

    $now = time();
    set_config('timeinstalled', $now, 'local_lambda_dedication');

    $import_logs_task = new \local_lambda_dedication\task\import_logs();
    $import_logs_task->set_component('local_lambda_dedication');
    $taskid = \core\task\manager::queue_adhoc_task($import_logs_task);
    if ($taskid) {
        // Schedule import task for 3am.
        $date = new DateTime('3am');
        // If 3am has already passed, schedule it for 3am tomorrow.
        if ($date < (new DateTime())) {
            $date->add(new DateInterval('P1D'));
        }
        $task = new stdClass();
        $task->id = $taskid;
        $task->nextruntime = $date->getTimestamp();
        $DB->update_record('task_adhoc', $task);

        $task_status = new stdClass();
        $task_status->adhoctaskid = $taskid;
        $task_status->status = 'scheduled';
        $DB->insert_record('local_ld_import_logs_status', $task_status);
    }

    return true;
}
