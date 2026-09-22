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
 * Legacy Prometheus endpoint for Moodle 4.4.
 *
 * Moodle 4.5 and later use the Routing API controller instead.
 *
 * @package    monitoringexporter_prometheus
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\di;
use core\exception\coding_exception;
use dml_exception;
use monitoringexporter_prometheus\exporter as prometheus_exporter;
use tool_monitoring\exceptions\tag_not_found;
use tool_monitoring\exceptions\tags_disabled;
use tool_monitoring\registered_metrics;

require_once(__DIR__ . '/../../../../../config.php');

$expectedtoken = (string) get_config('monitoringexporter_prometheus', 'prometheus_token');
$authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($expectedtoken !== '') {
    if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
        $token = $matches[1];
    } else {
        $token = optional_param('token', '', PARAM_RAW);
    }
    if (!hash_equals($expectedtoken, $token)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Invalid auth token';
        die();
    }
}

$taglist = optional_param('tag', '', PARAM_TAGLIST);
$tagnames = $taglist === '' ? [] : explode(',', $taglist);

try {
    $metrics = di::get(registered_metrics::class)->filter(enabled: true, tagnames: $tagnames);
} catch (tag_not_found | tags_disabled $e) {
    http_response_code(422);
    header('Content-Type: text/plain; charset=utf-8');
    echo $e->getMessage();
    die();
} catch (coding_exception | dml_exception) {
    debugging('Failed to collect Prometheus metrics.');
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Error in Prometheus exporter';
    die();
}

header('Content-Type: text/plain; charset=utf-8');
echo prometheus_exporter::export(...$metrics);
