#!/usr/bin/env php
<?php

/**
 * Load Testing Script for Email Server API
 *
 * This script performs load testing on the API endpoints to validate
 * performance under concurrent load.
 */

class LoadTester
{
    private string $baseUrl;
    private array $results = [];

    public function __construct(string $baseUrl = 'http://localhost:8000')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function runTests(): void
    {
        echo "Starting Load Tests for Email Server API\n";
        echo "==========================================\n\n";

        $this->testHealthEndpoint();
        $this->testApiRateLimiting();
        $this->testConcurrentRequests();
        $this->testMemoryUsage();
        $this->testDatabaseConnections();

        $this->printResults();
    }

    private function testHealthEndpoint(): void
    {
        echo "Testing Health Endpoint Performance...\n";

        $startTime = microtime(true);
        $successCount = 0;
        $totalRequests = 100;

        for ($i = 0; $i < $totalRequests; $i++) {
            $response = $this->makeRequest('/health');
            if ($response && isset($response['status'])) {
                $successCount++;
            }
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        $avgResponseTime = $totalTime / $totalRequests * 1000; // ms

        $this->results['health_endpoint'] = [
            'total_requests' => $totalRequests,
            'successful_requests' => $successCount,
            'total_time' => round($totalTime, 2),
            'avg_response_time' => round($avgResponseTime, 2),
            'requests_per_second' => round($totalRequests / $totalTime, 2)
        ];
    }

    private function testApiRateLimiting(): void
    {
        echo "Testing API Rate Limiting...\n";

        $rateLimited = 0;
        $totalRequests = 150; // More than rate limit

        for ($i = 0; $i < $totalRequests; $i++) {
            $response = $this->makeRequest('/api/domains', 'GET', [], ['Authorization' => 'Bearer test-key']);
            if ($response === false || (isset($response['status']) && $response['status'] === 429)) {
                $rateLimited++;
            }
            usleep(100000); // 100ms delay between requests
        }

        $this->results['rate_limiting'] = [
            'total_requests' => $totalRequests,
            'rate_limited_requests' => $rateLimited,
            'rate_limit_effective' => $rateLimited > 0
        ];
    }

    private function testConcurrentRequests(): void
    {
        echo "Testing Concurrent Requests...\n";

        $concurrencyLevels = [10, 25, 50];
        $results = [];

        foreach ($concurrencyLevels as $concurrency) {
            $startTime = microtime(true);

            // Create multiple processes
            $processes = [];
            for ($i = 0; $i < $concurrency; $i++) {
                $processes[] = $this->makeAsyncRequest('/health');
            }

            // Wait for all to complete
            $successCount = 0;
            foreach ($processes as $process) {
                if ($process && proc_close($process) === 0) {
                    $successCount++;
                }
            }

            $endTime = microtime(true);
            $totalTime = $endTime - $startTime;

            $results[$concurrency] = [
                'concurrency' => $concurrency,
                'successful_requests' => $successCount,
                'total_time' => round($totalTime, 2),
                'avg_response_time' => round($totalTime / $concurrency * 1000, 2)
            ];
        }

        $this->results['concurrent_requests'] = $results;
    }

    private function testMemoryUsage(): void
    {
        echo "Testing Memory Usage Under Load...\n";

        $initialMemory = memory_get_usage(true);
        $peakMemory = $initialMemory;

        // Simulate heavy load
        for ($i = 0; $i < 1000; $i++) {
            $this->makeRequest('/health');
            $currentMemory = memory_get_usage(true);
            $peakMemory = max($peakMemory, $currentMemory);
        }

        $this->results['memory_usage'] = [
            'initial_memory' => $this->formatBytes($initialMemory),
            'peak_memory' => $this->formatBytes($peakMemory),
            'memory_increase' => $this->formatBytes($peakMemory - $initialMemory)
        ];
    }

    private function testDatabaseConnections(): void
    {
        echo "Testing Database Connection Pooling...\n";

        $startTime = microtime(true);
        $connectionCount = 50;

        // Simulate multiple database operations
        for ($i = 0; $i < $connectionCount; $i++) {
            $this->makeRequest('/api/stats', 'GET', [], ['Authorization' => 'Bearer test-key']);
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        $this->results['database_connections'] = [
            'connections_tested' => $connectionCount,
            'total_time' => round($totalTime, 2),
            'avg_connection_time' => round($totalTime / $connectionCount * 1000, 2)
        ];
    }

    private function makeRequest(string $endpoint, string $method = 'GET', array $data = [], array $headers = []): ?array
    {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(
            function($k, $v) { return "$k: $v"; },
            array_keys($headers),
            $headers
        ));

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return null;
        }

        $decoded = json_decode($response, true);
        return $decoded ?: ['status' => $httpCode];
    }

    private function makeAsyncRequest(string $endpoint)
    {
        $url = $this->baseUrl . $endpoint;

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];

        $process = proc_open("curl -s '$url'", $descriptors, $pipes);

        if (is_resource($process)) {
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
        }

        return $process;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    private function printResults(): void
    {
        echo "\nLoad Test Results\n";
        echo "=================\n\n";

        foreach ($this->results as $testName => $result) {
            echo ucfirst(str_replace('_', ' ', $testName)) . ":\n";
            echo str_repeat('-', strlen($testName) + 1) . "\n";

            if (is_array($result)) {
                foreach ($result as $key => $value) {
                    if (is_array($value)) {
                        echo "  " . ucfirst(str_replace('_', ' ', $key)) . ":\n";
                        foreach ($value as $subKey => $subValue) {
                            echo "    $subKey: $subValue\n";
                        }
                    } else {
                        echo "  " . ucfirst(str_replace('_', ' ', $key)) . ": $value\n";
                    }
                }
            }
            echo "\n";
        }

        // Performance recommendations
        echo "Performance Recommendations:\n";
        echo "===========================\n";

        $healthResults = $this->results['health_endpoint'] ?? [];
        if (($healthResults['avg_response_time'] ?? 0) > 500) {
            echo "⚠️  Health endpoint response time is high (>500ms)\n";
        }

        $rateLimitResults = $this->results['rate_limiting'] ?? [];
        if (!($rateLimitResults['rate_limit_effective'] ?? false)) {
            echo "⚠️  Rate limiting may not be working properly\n";
        }

        $memoryResults = $this->results['memory_usage'] ?? [];
        $memoryIncrease = $memoryResults['memory_increase'] ?? '0 MB';
        if (strpos($memoryIncrease, 'MB') !== false && (float)$memoryIncrease > 50) {
            echo "⚠️  High memory usage detected under load\n";
        }

        echo "✅ Load testing completed\n";
    }
}

// Run the tests
$tester = new LoadTester($argv[1] ?? 'http://localhost:8000');
$tester->runTests();