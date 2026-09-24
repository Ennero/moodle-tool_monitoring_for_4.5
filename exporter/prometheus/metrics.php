<?php
// This file is part of the tool_monitoring plugin for Moodle - https://moodle.org/
//
// tool_monitoring is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// tool_monitoring is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with tool_monitoring.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Portable Prometheus endpoint for Moodle 4.5.
 *
 * @package    monitoringexporter_prometheus
 * @copyright  2025 MootDACH DevCamp
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use monitoringexporter_prometheus\endpoint_helper;

// phpcs:ignore moodle.Files.RequireLogin.Missing -- Authentication uses the configured Prometheus token.
require_once(__DIR__ . '/../../../../../config.php');

$expectedtoken = (string) get_config('monitoringexporter_prometheus', 'prometheus_token');
$paramtoken = optional_param('token', '', PARAM_RAW);
$taglist = optional_param('tag', '', PARAM_TAGLIST);

$result = endpoint_helper::execute(
    expectedtoken: $expectedtoken,
    server: $_SERVER,
    paramtoken: $paramtoken,
    taglist: $taglist,
);

http_response_code($result['status']);
header('Content-Type: ' . $result['content_type']);
echo $result['body'];
if ($result['status'] >= 400) {
    die();
}
