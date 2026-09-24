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
 * Endpoint helper for the Prometheus exporter.
 *
 * @package    monitoringexporter_prometheus
 * @copyright  2025 MootDACH DevCamp
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace monitoringexporter_prometheus;

use core\di;
use core\exception\coding_exception;
use dml_exception;
use tool_monitoring\exceptions\tag_not_found;
use tool_monitoring\exceptions\tags_disabled;
use tool_monitoring\registered_metrics;

/**
 * Helper class for handling Prometheus scraping requests.
 *
 * Provides extraction of authorization credentials, validation, metric retrieval,
 * and formatting responses suitable for HTTP entry points.
 *
 * @package    monitoringexporter_prometheus
 * @copyright  2025 MootDACH DevCamp
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class endpoint_helper {
    /**
     * Extracts the authorization token from server variables or request parameters.
     *
     * Supports:
     * - `$_SERVER['HTTP_AUTHORIZATION']`
     * - `$_SERVER['REDIRECT_HTTP_AUTHORIZATION']` (Apache with PHP-FPM)
     * - `getallheaders()['authorization']`
     * - Query parameter fallback
     *
     * @param array $server Server environment array (defaults to empty).
     * @param string|null $paramtoken Optional token passed via query parameter.
     * @return string The extracted token, or empty string if none found.
     */
    public static function extract_token(array $server = [], ?string $paramtoken = null): string {
        $authorization = $server['HTTP_AUTHORIZATION'] ?? $server['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if ($authorization === '' && function_exists('getallheaders')) {
            $headers = array_change_key_case(getallheaders(), CASE_LOWER);
            $authorization = $headers['authorization'] ?? '';
        }

        if ($authorization !== '' && preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            return $matches[1];
        }

        return $paramtoken ?? '';
    }

    /**
     * Verifies if the request is authorized against the expected token.
     *
     * @param string $expectedtoken The expected token configured in the site.
     * @param array $server Server environment array.
     * @param string|null $paramtoken Optional token passed via query parameter.
     * @return bool True if authorized, false otherwise.
     */
    public static function is_authorized(string $expectedtoken, array $server = [], ?string $paramtoken = null): bool {
        if ($expectedtoken === '') {
            return true;
        }

        $token = self::extract_token($server, $paramtoken);
        return hash_equals($expectedtoken, $token);
    }

    /**
     * Executes the exporter endpoint logic.
     *
     * @param string $expectedtoken The expected token configured in the site.
     * @param array $server Server environment array.
     * @param string|null $paramtoken Optional token passed via query parameter.
     * @param string $taglist Comma-separated list of tags to filter by.
     * @param registered_metrics|null $metricsregistry Optional metrics registry instance for testing.
     * @return array{status: int, content_type: string, body: string} HTTP response specification.
     */
    public static function execute(
        string $expectedtoken,
        array $server = [],
        ?string $paramtoken = null,
        string $taglist = '',
        ?registered_metrics $metricsregistry = null,
    ): array {
        if (!self::is_authorized($expectedtoken, $server, $paramtoken)) {
            return [
                'status' => 403,
                'content_type' => 'text/plain; charset=utf-8',
                'body' => 'Invalid auth token',
            ];
        }

        $tagnames = $taglist === '' ? [] : explode(',', $taglist);

        try {
            $registry = $metricsregistry ?? di::get(registered_metrics::class);
            $metrics = $registry->filter(enabled: true, tagnames: $tagnames);
        } catch (tag_not_found | tags_disabled $e) {
            return [
                'status' => 422,
                'content_type' => 'text/plain; charset=utf-8',
                'body' => $e->getMessage(),
            ];
        } catch (coding_exception | dml_exception) {
            debugging('Failed to collect Prometheus metrics.');
            return [
                'status' => 500,
                'content_type' => 'text/plain; charset=utf-8',
                'body' => 'Error in Prometheus exporter',
            ];
        }

        return [
            'status' => 200,
            'content_type' => 'text/plain; charset=utf-8',
            'body' => exporter::export(...$metrics),
        ];
    }
}
