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

function xmldb_local_lambda_dedication_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2015092100) {

        // Define table local_ld_import_logs_status to be created.
        $table = new xmldb_table('local_ld_import_logs_status');

        // Adding fields to table local_ld_import_logs_status.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('adhoctaskid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'notscheduled');
        $table->add_field('progress', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0.0');
        $table->add_field('timestarted', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timefinished', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table local_ld_import_logs_status.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('task_adhoc', XMLDB_KEY_FOREIGN, array('adhoctaskid'), 'task_adhoc', array('id'));

        // Conditionally launch create table for local_ld_import_logs_status.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Lambda_dedication savepoint reached.
        upgrade_plugin_savepoint(true, 2015092100, 'local', 'lambda_dedication');
    }

    return true;
}
