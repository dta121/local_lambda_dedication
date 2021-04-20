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

class local_lambda_dedication {
    const DEDICATION_LIMIT = 1800;

    protected $dedication = 0;

    protected $days = array();

    protected function update_day($time, $dedication) {
        $day = new \DateTime();
        $day->setTimestamp($time);
        $day->setTime(0, 0, 0);
        $timestamp = $day->getTimestamp();
        if (array_key_exists($timestamp, $this->days)) {
            $this->days[$timestamp] += $dedication;
        } else {
            $this->days[$timestamp] = $dedication;
        }
    }

    public function update_dedication($time, $dedication) {
        if ($dedication > 0 && $dedication <= self::DEDICATION_LIMIT) {
            $this->dedication += $dedication;
            $this->update_day($time, $dedication);
        }
    }

    public function get_dedication() {
        return array(
            'dedication' => $this->dedication,
            'days' => $this->days
        );
    }

}

class local_lambda_dedication_course extends local_lambda_dedication {

    protected $courseid;
    protected $userid;

    protected $modules = array();

    public function __construct($userid, $courseid) {
        $this->courseid = $courseid;
        $this->userid = $userid;
    }

    public function update_course_dedication($time, $contextlevel, $cmid, $dedication) {
        $this->update_dedication($time, $dedication);
        if ($contextlevel == 70) {
            if (!array_key_exists($cmid, $this->modules)) {
                $this->modules[$cmid] = new local_lambda_dedication();
            }
            $this->modules[$cmid]->update_dedication($time, $dedication);
        }
    }

    public function update_database() {
        global $DB;

        $ldcourse = new stdClass();
        $ldcourse->userid = $this->userid;
        $ldcourse->courseid = $this->courseid;
        $ldcourse->totaldedication = $this->dedication;
        try {
            $ldcourseid = $DB->insert_record('local_ld_course', $ldcourse);
        } catch (dml_write_exception $ex) {
            // This row already exists in the table, so let's just update its dedication.
            $params = array(
                'userid' => $ldcourse->userid,
                'courseid' => $ldcourse->courseid
            );
            $ldcourseid = $DB->get_field('local_ld_course', 'id', $params);
            if ($ldcourseid) {
                $DB->execute(
                    'update {local_ld_course}'
                        . ' set totaldedication = totaldedication + :dedication'
                        . ' where id = :id',
                    array('dedication' => $ldcourse->totaldedication, 'id' => $ldcourseid));
            }
        }

        if (!$ldcourseid) {
            // The $ldcourseid is still not found?
            // This should not happen.
            $ldcoursedump = "\ncourseid: $ldcourse->courseid"
                    . "\nuserid: $ldcourse->userid"
                    . "\ntotaldedication: $ldcourse->totaldedication";
            debugging('Failed to write this to local_ld_course table:' . $ldcoursedump);
            return;
        }

        $ldcourseday = new stdClass();
        $ldcourseday->ldcourseid = $ldcourseid;
        foreach ($this->days as $day => $dedication) {
            try {
                $ldcourseday->daytime = $day;
                $ldcourseday->day = (new DateTime('@' . $day))->format('Y-m-d');
                $ldcourseday->dedication = $dedication;
                $DB->insert_record('local_ld_course_day', $ldcourseday);
            } catch (dml_write_exception $ex) {
                $DB->execute(
                    'update {local_ld_course_day}'
                        . ' set dedication = dedication + :dedication'
                        . ' where ldcourseid = :ldcourseid and daytime = :daytime',
                    array('dedication' => $dedication, 'ldcourseid' => $ldcourseid, 'daytime' => $day));
            }
        }

        foreach ($this->modules as $cmid => $module) {
            $md = $module->get_dedication();
            $ldmodule = new stdClass();
            $ldmodule->ldcourseid = $ldcourseid;
            $ldmodule->coursemoduleid = $cmid;
            $ldmodule->totaldedication = $md['dedication'];
            try {
                $ldmoduleid = $DB->insert_record('local_ld_module', $ldmodule);
            } catch (dml_write_exception $ex) {
                $ldmoduleid = $DB->get_field('local_ld_module', 'id',
                        array('ldcourseid' => $ldcourseid, 'coursemoduleid' => $cmid));
                if ($ldmoduleid) {
                    $DB->execute(
                        'update {local_ld_module} set totaldedication = totaldedication + :dedication where id = :id',
                        array('dedication' => $ldmodule->totaldedication, 'id' => $ldmoduleid));
                }
            }
            if (!$ldmoduleid) {
                // The $ldmoduleid is still not found?
                // This should not happen.
                $ldmoduledump = "\nldcourseid: $ldmodule->ldcourseid"
                        . "\ncoursemoduleid: $ldmodule->coursemoduleid"
                        . "\ntotaldedication: $ldmodule->totaldedication";
                debugging('Failed to write this to local_ld_module table: ' . $ldmoduledump);
                continue;
            }

            $ldmoduleday = new stdClass();
            $ldmoduleday->ldmoduleid = $ldmoduleid;
            foreach ($md['days'] as $day => $dedication) {
                try {
                    $ldmoduleday->daytime = $day;
                    $ldmoduleday->day = (new DateTime('@' . $day))->format('Y-m-d');
                    $ldmoduleday->dedication = $dedication;
                    $DB->insert_record('local_ld_module_day', $ldmoduleday);
                } catch (dml_write_exception $ex) {
                    $DB->execute(
                        'update {local_ld_module_day}'
                            . ' set dedication = dedication + :dedication'
                            . ' where ldmoduleid = :ldmoduleid and daytime = :daytime',
                        array('dedication' => $dedication, 'ldmoduleid' => $ldmoduleid, 'daytime' => $day));
                }
            }

        }
    }
}

function local_lambda_dedication_import_user($userid, $timelimit) {
    global $DB;
    $logs = $DB->get_recordset_sql(
            "select courseid,
                    contextlevel,
                    contextinstanceid,
                    timecreated
               from {logstore_standard_log}
              where userid = :userid1
                and timecreated < :timecreated1
              union
             select course as courseid,
                    case when cmid > 0 then 70 else 50 end as contextlevel,
                    cmid as contextinstanceid,
                    time as timecreated
               from {log}
              where userid = :userid2
                and time < :timecreated2
              order by timecreated", array(
                  'userid1' => $userid,
                  'timecreated1' => $timelimit,
                  'userid2' => $userid,
                  'timecreated2' => $timelimit
              ));
    $courses = array();
    if ($logs->valid()) {
        $prevlog = new stdClass();
        $prevlog->courseid = 0;
        $prevlog->contextlevel = 0;
        $prevlog->contextinstanceid = 0;
        $prevlog->timecreated = 0;
        foreach ($logs as $log) {
            // Skip dedication for site level course.
            if ($prevlog->courseid > 1) {
                $dedication = $log->timecreated - $prevlog->timecreated;
                if (!array_key_exists($prevlog->courseid, $courses)) {
                    $courses[$prevlog->courseid] = new local_lambda_dedication_course($userid, $prevlog->courseid);
                }
                $courses[$prevlog->courseid]->update_course_dedication(
                        $prevlog->timecreated,
                        $prevlog->contextlevel,
                        $prevlog->contextinstanceid,
                        $dedication);
            }
            $prevlog = $log;
        }
    }
    $logs->close();
    foreach ($courses as $course) {
        $course->update_database();
    }
}
