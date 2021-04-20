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

if ($hassiteconfig) { // Needs this condition or there is error on login page.
    $ADMIN->add(
            'localplugins',
            new admin_externalpage(
                    'local_lambda_dedication',
                    get_string('pluginname', 'local_lambda_dedication'),
                    $CFG->wwwroot.'/local/lambda_dedication/import_logs.php'
                    )
            );

}
