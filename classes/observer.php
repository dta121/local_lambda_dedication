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

namespace local_lambda_dedication;

defined('MOODLE_INTERNAL') || die();

class observer {

    const DEDICATION_LIMIT = 1800; // 30 minutes.

    private static function update_totaldedication($table, $params, $dedication) {
        global $DB;

        $id = $DB->get_field($table, 'id', $params);
        $should_update = true;
        if (!$id) {
            // Record not found, so we'll create a new one.
            $dedication_row = (object) $params;
            $dedication_row->totaldedication = $dedication;
            try {
                $id = $DB->insert_record($table, $dedication_row);
                // New record has just been created, no need to update it.
                $should_update = false;
            } catch (\dml_write_exception $exc) {
                // It seems like someone has just inserted this record.
                // Get just the id of that record.
                $id = $DB->get_field($table, 'id', $params);
                if (!$id) {
                    debugging("Unable to update $table");
                    $should_update = false;
                } else {
                    $should_update = true;
                }
            }
        }
        if ($should_update) {
            $DB->execute(
                    'update {' . $table . '} set totaldedication = totaldedication + :dedication where id = :id',
                    array('dedication' => $dedication, 'id' => $id));
        }

        return $id;
    }

    private static function update_daydedication($table, $fkcolumn, $fkvalue, $time, $dedication) {
        global $DB;

        $day = new \DateTime();
        $day->setTimestamp($time);
        $day->setTime(0, 0, 0);

        $id = $DB->get_field($table, 'id', array($fkcolumn => $fkvalue, 'daytime' => $day->getTimestamp()));
        $should_update = true;
        if (!$id) {
            $day_dedication_row = new \stdClass();
            $day_dedication_row->{$fkcolumn} = $fkvalue;
            $day_dedication_row->daytime = $day->getTimestamp();
            $day_dedication_row->day = $day->format('Y-m-d');
            $day_dedication_row->dedication = $dedication;
            try {
                $id = $DB->insert_record($table, $day_dedication_row);
                $should_update = false;
            } catch (\dml_write_exception $exc) {
                // It seems like someone has just inserted this record.
                $id = $DB->get_field($table, 'id', array($fkcolumn => $fkvalue, 'daytime' => $day->getTimestamp()));
                if (!$id) {
                    debugging("Unable to update $table for $fkcolumn = $fkvalue and daytime = " . $day->getTimestamp());
                    $should_update = false;
                } else {
                    $should_update = true;
                }
            }
        }
        if ($should_update) {
            $DB->execute(
                    'update {' . $table . '} set dedication = dedication + :dedication where id = :id',
                    array('dedication' => $dedication, 'id' => $id));
        }
    }

    private static function course_dedication($userid, $courseid, $time, $dedication) {
        $id = self::update_totaldedication(
                'local_ld_course',
                array('userid' => $userid, 'courseid' => $courseid),
                $dedication);
        if ($id > 0) {
            self::update_daydedication('local_ld_course_day', 'ldcourseid', $id, $time, $dedication);
        }
        return $id;
    }

    private static function module_dedication($ldcourseid, $cmid, $time, $dedication) {
        $id = self::update_totaldedication(
                'local_ld_module',
                array('ldcourseid' => $ldcourseid, 'coursemoduleid' => $cmid),
                $dedication);
        if ($id > 0) {
            self::update_daydedication('local_ld_module_day', 'ldmoduleid', $id, $time, $dedication);
        }
    }

    /**
     * Calculate activity dedication
     *
     * @param \core\event\base $event
     */
    public static function observe_all($event) {
        global $DB;
        if ($event->userid > 0) {
            $lastactivity = $DB->get_record('local_ld_lastactivity', array('userid' => $event->userid));
            $update = true;
            if (!$lastactivity) {
                // Last activity record not found. create it.
                $update = false;
                $lastactivity = new \stdClass();
                $lastactivity->userid = $event->userid;
                $lastactivity->lastaccess = $event->timecreated;
                $lastactivity->courseid = $event->courseid;
                $lastactivity->coursemoduleid = ($event->contextlevel == CONTEXT_MODULE ? $event->contextinstanceid : 0);
                try {
                    $DB->insert_record('local_ld_lastactivity', $lastactivity);
                } catch (\dml_write_exception $ex) {
                    // It seems like the record has just been inserted.
                    // We'll need to update it.
                    $lastactivity = $DB->get_record('local_ld_lastactivity', array('userid' => $event->userid));
                    if ($lastactivity) {
                        $update = true;
                    } else {
                        // Last activity still not found? something is wrong here.
                        debugging("local_lambda_dedication: Unable to update last activity for"
                                . " user {$event->userid} and course {$event->courseid}");
                        return;
                    }
                }
            }
            if ($update) {
                $updateactivity = new \stdClass();
                $updateactivity->id = $lastactivity->id;
                $updateactivity->lastaccess = $event->timecreated;
                $updateactivity->courseid = $event->courseid;
                $updateactivity->coursemoduleid = ($event->contextlevel == CONTEXT_MODULE ? $event->contextinstanceid : 0);
                $DB->update_record('local_ld_lastactivity', $updateactivity);
            }
            $dedication = $event->timecreated - $lastactivity->lastaccess;
            if ($lastactivity->courseid > 1 && $dedication > 0 && $dedication <= self::DEDICATION_LIMIT) {
                $ldcourseid = self::course_dedication(
                        $lastactivity->userid,
                        $lastactivity->courseid,
                        $lastactivity->lastaccess,
                        $dedication);
                if (!$ldcourseid) {
                    debugging("local_lambda_dedication: Unable to update dedication for"
                            . " user {$lastactivity->userid} and course {$lastactivity->courseid}");
                } else {
                    if ($lastactivity->coursemoduleid > 0) {
                        self::module_dedication($ldcourseid, $lastactivity->coursemoduleid, $lastactivity->lastaccess, $dedication);
                    }
                }
            }
        }
    }

}
