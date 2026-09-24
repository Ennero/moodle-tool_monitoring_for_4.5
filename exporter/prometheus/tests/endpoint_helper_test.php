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
 * Definition of the {@see endpoint_helper_test} class.
 *
 * @package    monitoringexporter_prometheus
 * @copyright  2025 MootDACH DevCamp
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * {@noinspection PhpIllegalPsrClassPathInspection}
 */

namespace monitoringexporter_prometheus;

use advanced_testcase;
use PHPUnit\Framework\Attributes\CoversClass;
use tool_monitoring\local\testing\test_metric_tag;
use tool_monitoring\local\testing\test_registered_metric;
use tool_monitoring\local\testing\test_registered_metrics;
use tool_monitoring\metric_value;

/**
 * Unit tests for the {@see endpoint_helper} class.
 *
 * @package    monitoringexporter_prometheus
 * @copyright  2025 MootDACH DevCamp
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Tests the covered class.
 *
 * @covers \monitoringexporter_prometheus\endpoint_helper
 */
#[CoversClass(endpoint_helper::class)]
final class endpoint_helper_test extends advanced_testcase {
    /**
     * Tests token extraction from various headers and query parameters.
     */
    public function test_extract_token(): void {
        // HTTP_AUTHORIZATION header.
        self::assertSame('secret1', endpoint_helper::extract_token(['HTTP_AUTHORIZATION' => 'Bearer secret1']));
        // Case-insensitive bearer prefix.
        self::assertSame('secret2', endpoint_helper::extract_token(['HTTP_AUTHORIZATION' => 'bEaReR secret2']));
        // REDIRECT_HTTP_AUTHORIZATION header for Apache PHP-FPM.
        self::assertSame('secret3', endpoint_helper::extract_token(['REDIRECT_HTTP_AUTHORIZATION' => 'Bearer secret3']));
        // Query parameter fallback when no header present.
        self::assertSame('paramsecret', endpoint_helper::extract_token([], 'paramsecret'));
        // Header takes precedence over query parameter.
        self::assertSame(
            'headersecret',
            endpoint_helper::extract_token(['HTTP_AUTHORIZATION' => 'Bearer headersecret'], 'paramsecret')
        );
        // Empty when neither provided.
        self::assertSame('', endpoint_helper::extract_token([], ''));
    }

    /**
     * Tests authorization checks.
     */
    public function test_is_authorized(): void {
        // When expected token is empty string, all requests are authorized.
        self::assertTrue(endpoint_helper::is_authorized(''));
        self::assertTrue(endpoint_helper::is_authorized('', ['HTTP_AUTHORIZATION' => 'Bearer ignored']));

        // When expected token is configured:
        // Matching header.
        self::assertTrue(endpoint_helper::is_authorized('mytoken', ['HTTP_AUTHORIZATION' => 'Bearer mytoken']));
        // Matching redirect header.
        self::assertTrue(endpoint_helper::is_authorized('mytoken', ['REDIRECT_HTTP_AUTHORIZATION' => 'Bearer mytoken']));
        // Matching query param.
        self::assertTrue(endpoint_helper::is_authorized('mytoken', [], 'mytoken'));
        // Mismatched token.
        self::assertFalse(endpoint_helper::is_authorized('mytoken', ['HTTP_AUTHORIZATION' => 'Bearer wrong']));
        self::assertFalse(endpoint_helper::is_authorized('mytoken', [], 'wrong'));
        // Missing token.
        self::assertFalse(endpoint_helper::is_authorized('mytoken', []));
    }

    /**
     * Tests endpoint execution when unauthorized.
     */
    public function test_execute_unauthorized(): void {
        $result = endpoint_helper::execute('required_token', ['HTTP_AUTHORIZATION' => 'Bearer wrong']);
        self::assertSame(403, $result['status']);
        self::assertSame('text/plain; charset=utf-8', $result['content_type']);
        self::assertSame('Invalid auth token', $result['body']);
    }

    /**
     * Tests endpoint execution with valid authorization returning exported metrics.
     */
    public function test_execute_success(): void {
        $metric = new test_registered_metric('untagged', values: [new metric_value(42)]);
        $registry = new test_registered_metrics($metric);

        $result = endpoint_helper::execute(
            expectedtoken: 'secret',
            server: ['HTTP_AUTHORIZATION' => 'Bearer secret'],
            metricsregistry: $registry,
        );

        self::assertSame(200, $result['status']);
        self::assertSame('text/plain; charset=utf-8', $result['content_type']);
        self::assertStringContainsString('tool_monitoring_untagged 42', $result['body']);
    }

    /**
     * Tests endpoint execution with tag filter.
     */
    public function test_execute_tag_filtering(): void {
        $tag = new test_metric_tag('prod');
        $metric1 = new test_registered_metric('tagged_prod', tags: [$tag], values: [new metric_value(10)]);
        $metric2 = new test_registered_metric('untagged', values: [new metric_value(20)]);
        $registry = new test_registered_metrics($metric1, $metric2);

        $result = endpoint_helper::execute(
            expectedtoken: '',
            taglist: 'prod',
            metricsregistry: $registry,
        );

        self::assertSame(200, $result['status']);
        self::assertStringContainsString('tool_monitoring_tagged_prod 10', $result['body']);
        self::assertStringNotContainsString('tool_monitoring_untagged', $result['body']);
    }
}
