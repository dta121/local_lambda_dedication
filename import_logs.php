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

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once('locallib.php');

$component = 'local_lambda_dedication';
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('importlogs', $component));
$PAGE->set_heading(get_string('importlogs', $component));
$thispage = '/local/lambda_dedication/import_logs.php';
$PAGE->set_url($CFG->wwwroot . $thispage);

require_login();

echo $OUTPUT->header();

$doimport = optional_param('doimport', '', PARAM_ALPHA);

if (is_siteadmin($USER->id)) {

    // There should be at most one record in local_ld_import_logs_status table.
    $task_status = $DB->get_record('local_ld_import_logs_status', array());
    if (!$task_status) {
        $task_status = new stdClass();
        // Check if the import is already done in previous version of this plugin.
        $legacy_import_status = get_config('local_lambda_dedication', 'importstatus');
        if ($legacy_import_status == 'inprogress' || $legacy_import_status == 'finished') {
            $task_status->status = 'legacyfinished';
        } else {
            $task_status->status = 'notscheduled';
        }
        $task_status->id = $DB->insert_record('local_ld_import_logs_status', $task_status);
    }

    if ($doimport == 'doimport' && $task_status->status != 'scheduled' && $task_status->status != 'inprogress') {
        $import_logs_task = new \local_lambda_dedication\task\import_logs();
        $import_logs_task->set_component('local_lambda_dedication');
        $taskid = \core\task\manager::queue_adhoc_task($import_logs_task);
        if ($taskid) {
            $task_status->adhoctaskid = $taskid;
            $task_status->status = 'scheduled';
            $DB->update_record('local_ld_import_logs_status', $task_status);
        }
    }

    $action = new moodle_url($thispage, array('doimport' => 'doimport'));
    switch ($task_status->status) {
        case 'notscheduled':
            echo $OUTPUT->box(get_string('taskdescription', $component));
            echo $OUTPUT->single_button($action, get_string('startimport', $component));
            break;
        case 'scheduled':
            $nextruntime = $DB->get_field('task_adhoc', 'nextruntime', array('id' => $task_status->adhoctaskid));
            echo $OUTPUT->box(get_string('taskscheduled', $component, userdate($nextruntime)));
            break;
        case 'inprogress':
            echo $OUTPUT->box(get_string('taskinprogress', $component));
            echo $OUTPUT->box(get_string('taskstarted', $component, userdate($task_status->timestarted)));
            echo $OUTPUT->box(get_string('taskprogress', $component, $task_status->progress));
            if ($task_status->progress > 0) {
                $timeleft = floor(($task_status->timemodified - $task_status->timestarted) * (100 - $task_status->progress) / $task_status->progress);
                echo $OUTPUT->box(get_string('tasktimeleft', $component, format_time($timeleft)));
                echo $OUTPUT->box(get_string('taskfinishtime', $component, userdate(time() + $timeleft)));
            }
            break;
        case 'finished':
            echo $OUTPUT->box(get_string('taskfinished', $component, userdate($task_status->timefinished)));
            if ($task_status->timefinished > $task_status->timestarted) {
                // Show this info only if import took more than zero seconds.
                echo $OUTPUT->box(get_string('tasktime', $component, format_time($task_status->timefinished - $task_status->timestarted)));
            }
            echo $OUTPUT->single_button($action, get_string('restartimport', $component));
            break;
        case 'legacyfinished':
            echo $OUTPUT->box(get_string('tasklegacyfinished', $component));
            echo $OUTPUT->single_button($action, get_string('restartimport', $component));
            break;
        default:
            break;
    }

}

echo $OUTPUT->footer();
