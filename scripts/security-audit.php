#!/usr/bin/env php
<?php

/**
 * Security Audit Script for Email Server
 *
 * This script performs automated security checks to identify
 * common vulnerabilities and security misconfigurations.
 */

class SecurityAuditor
{
    private string $baseUrl;
    private array $findings = [];
    private array $passed = [];

    public function __construct(string $baseUrl = 'http://localhost:8000')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function runAudit(): void
    {
        echo "Starting Security Audit for Email Server\n";
        echo "========================================\n\n";

        $this->checkSecurityHeaders();
        $this->checkRateLimiting();
        $this->checkInputValidation();
        $this->checkAuthentication();
        $this->checkInformationDisclosure();
        $this->checkSSLConfiguration();
        $this->checkFilePermissions();
        $this->checkDependencyVulnerabilities();

        $this->printReport();
    }

    private function checkSecurityHeaders(): void
    {
        echo "Checking Security Headers...\n";

        $response = $this->makeRequest('/');
        $headers = $response['headers'] ?? [];

        $requiredHeaders = [
            'Strict-Transport-Security' => 'HSTS header',
            'X-Frame-Options' => 'Clickjacking protection',
            'X-Content-Type-Options' => 'MIME sniffing protection',
            'X-XSS-Protection' => 'XSS protection',
            'Content-Security-Policy' => 'CSP header',
            'Referrer-Policy' => 'Referrer policy'
        ];

        foreach ($requiredHeaders as $header => $description) {
            if (isset($headers[$header])) {
                $this->passed[] = "✅ $description present";
            } else {
                $this->findings[] = [
                    'severity' => 'HIGH',
                    'category' => 'Security Headers',
                    'issue' => "Missing $description",
                    'recommendation' => "Add '$header' header to all responses"
                ];
            }
        }
    }

    private function checkRateLimiting(): void
    {
        echo "Checking Rate Limiting...\n";

        $startTime = microtime(true);

        // Make multiple rapid requests
        $responses = [];
        for ($i = 0; $i < 120; $i++) {
            $response = $this->makeRequest('/api/domains', 'GET', [], ['Authorization' => 'Bearer test-key']);
            $responses[] = $response;
            usleep(50000); // 50ms delay
        }

        $endTime = microtime(true);
        $rateLimited = false;

        foreach ($responses as $response) {
            if (isset($response['status']) && $response['status'] === 429) {
                $rateLimited = true;
                break;
            }
        }

        if ($rateLimited) {
            $this->passed[] = "✅ Rate limiting is working";
        } else {
            $this->findings[] = [
                'severity' => 'HIGH',
                'category' => 'Rate Limiting',
                'issue' => 'No rate limiting detected on API endpoints',
                'recommendation' => 'Implement rate limiting to prevent abuse'
            ];
        }
    }

    private function checkInputValidation(): void
    {
        echo "Checking Input Validation...\n";

        // Test SQL injection attempts
        $sqlInjectionPayloads = [
            "' OR '1'='1",
            "'; DROP TABLE users; --",
            "<script>alert('xss')</script>",
            "../../../etc/passwd",
            "javascript:alert('xss')"
        ];

        foreach ($sqlInjectionPayloads as $payload) {
            $response = $this->makeRequest('/api/domains', 'POST', [
                'name' => $payload,
                'quota_mb' => 1024
            ], ['Authorization' => 'Bearer test-key']);

            if (isset($response['status']) && $response['status'] === 400) {
                $this->passed[] = "✅ Input validation working for malicious payload";
            } else {
                $this->findings[] = [
                    'severity' => 'CRITICAL',
                    'category' => 'Input Validation',
                    'issue' => 'Potential injection vulnerability detected',
                    'recommendation' => 'Implement proper input sanitization and validation'
                ];
                break;
            }
        }

        // Test invalid domain format
        $response = $this->makeRequest('/api/domains', 'POST', [
            'name' => 'invalid-domain',
            'quota_mb' => 1024
        ], ['Authorization' => 'Bearer test-key']);

        if (isset($response['status']) && $response['status'] === 400) {
            $this->passed[] = "✅ Domain format validation working";
        } else {
            $this->findings[] = [
                'severity' => 'MEDIUM',
                'category' => 'Input Validation',
                'issue' => 'Domain format validation may be insufficient',
                'recommendation' => 'Strengthen domain name validation'
            ];
        }
    }

    private function checkAuthentication(): void
    {
        echo "Checking Authentication...\n";

        // Test without API key
        $response = $this->makeRequest('/api/domains');
        if (isset($response['status']) && $response['status'] === 403) {
            $this->passed[] = "✅ API authentication working";
        } else {
            $this->findings[] = [
                'severity' => 'CRITICAL',
                'category' => 'Authentication',
                'issue' => 'API endpoints accessible without authentication',
                'recommendation' => 'Implement proper API key authentication'
            ];
        }

        // Test with invalid API key
        $response = $this->makeRequest('/api/domains', 'GET', [], ['Authorization' => 'Bearer invalid-key']);
        if (isset($response['status']) && $response['status'] === 403) {
            $this->passed[] = "✅ Invalid API key properly rejected";
        } else {
            $this->findings[] = [
                'severity' => 'HIGH',
                'category' => 'Authentication',
                'issue' => 'Invalid API keys not properly rejected',
                'recommendation' => 'Strengthen API key validation'
            ];
        }
    }

    private function checkInformationDisclosure(): void
    {
        echo "Checking Information Disclosure...\n";

        $endpoints = ['/.env', '/.git/config', '/phpinfo.php', '/server-status'];

        foreach ($endpoints as $endpoint) {
            $response = $this->makeRequest($endpoint);
            if (isset($response['status']) && $response['status'] !== 404) {
                $this->findings[] = [
                    'severity' => 'HIGH',
                    'category' => 'Information Disclosure',
                    'issue' => "Sensitive file accessible: $endpoint",
                    'recommendation' => 'Remove or protect sensitive files'
                ];
            } else {
                $this->passed[] = "✅ Sensitive file properly protected: $endpoint";
            }
        }

        // Check for version disclosure
        $response = $this->makeRequest('/');
        $headers = $response['headers'] ?? [];
        if (isset($headers['X-Powered-By']) || isset($headers['Server'])) {
            $this->findings[] = [
                'severity' => 'LOW',
                'category' => 'Information Disclosure',
                'issue' => 'Server version information disclosed',
                'recommendation' => 'Remove server version headers'
            ];
        } else {
            $this->passed[] = "✅ Server version information not disclosed";
        }
    }

    private function checkSSLConfiguration(): void
    {
        echo "Checking SSL/TLS Configuration...\n";

        // This would require external SSL testing tools
        // For now, check if HTTPS is enforced
        $response = $this->makeRequest('/', 'GET', [], [], false); // Force HTTP

        if (isset($response['status']) && $response['status'] === 301) {
            $location = $response['headers']['Location'] ?? '';
            if (strpos($location, 'https://') === 0) {
                $this->passed[] = "✅ HTTP to HTTPS redirect working";
            } else {
                $this->findings[] = [
                    'severity' => 'HIGH',
                    'category' => 'SSL/TLS',
                    'issue' => 'HTTP not properly redirected to HTTPS',
                    'recommendation' => 'Configure proper HTTPS redirect'
                ];
            }
        } else {
            $this->findings[] = [
                'severity' => 'MEDIUM',
                'category' => 'SSL/TLS',
                'issue' => 'HTTP to HTTPS redirect not configured',
                'recommendation' => 'Implement HTTPS redirect'
            ];
        }
    }

    private function checkFilePermissions(): void
    {
        echo "Checking File Permissions...\n";

        $criticalFiles = [
            'symfony-dashboard/.env',
            'data/mailserver/config/docker-mailserver.env',
            'certificates/',
            'backup-scripts/backup.sh'
        ];

        foreach ($criticalFiles as $file) {
            $fullPath = __DIR__ . '/../' . $file;
            if (file_exists($fullPath)) {
                $perms = fileperms($fullPath);
                $octal = substr(sprintf('%o', $perms), -4);

                // Check if file is world-readable
                if ($perms & 0x0004) { // World readable
                    $this->findings[] = [
                        'severity' => 'HIGH',
                        'category' => 'File Permissions',
                        'issue' => "File $file is world-readable ($octal)",
                        'recommendation' => 'Restrict file permissions to owner/group only'
                    ];
                } else {
                    $this->passed[] = "✅ File permissions secure: $file";
                }
            }
        }
    }

    private function checkDependencyVulnerabilities(): void
    {
        echo "Checking Dependency Vulnerabilities...\n";

        $composerFile = __DIR__ . '/../symfony-dashboard/composer.json';
        if (file_exists($composerFile)) {
            $composer = json_decode(file_get_contents($composerFile), true);

            // Check for known vulnerable packages (simplified check)
            $vulnerablePackages = [
                'symfony/http-kernel' => '<4.4.0',
                'doctrine/orm' => '<2.8.0'
            ];

            foreach ($vulnerablePackages as $package => $constraint) {
                if (isset($composer['require'][$package])) {
                    $version = $composer['require'][$package];
                    // Simplified version comparison
                    if (strpos($version, '*') !== false || version_compare($version, '2.0.0', '<')) {
                        $this->findings[] = [
                            'severity' => 'HIGH',
                            'category' => 'Dependencies',
                            'issue' => "Potentially vulnerable package: $package@$version",
                            'recommendation' => 'Update to latest secure version'
                        ];
                    }
                }
            }

            $this->passed[] = "✅ Dependency vulnerability check completed";
        }
    }

    private function makeRequest(string $endpoint, string $method = 'GET', array $data = [], array $headers = [], bool $followRedirects = true): ?array
    {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $followRedirects);

        $headerArray = array_map(
            function($k, $v) { return "$k: $v"; },
            array_keys($headers),
            $headers
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            curl_close($ch);
            return null;
        }

        // Parse headers and body
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headerString = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        curl_close($ch);

        // Parse headers
        $headers = [];
        foreach (explode("\r\n", $headerString) as $line) {
            if (strpos($line, ': ') !== false) {
                [$key, $value] = explode(': ', $line, 2);
                $headers[$key] = $value;
            }
        }

        $decoded = json_decode($body, true);
        return [
            'status' => $httpCode,
            'headers' => $headers,
            'body' => $decoded ?: $body
        ];
    }

    private function printReport(): void
    {
        echo "\nSecurity Audit Report\n";
        echo "====================\n\n";

        echo "Summary:\n";
        echo "- Total checks passed: " . count($this->passed) . "\n";
        echo "- Total findings: " . count($this->findings) . "\n\n";

        if (!empty($this->findings)) {
            echo "Security Findings:\n";
            echo "==================\n";

            $severityOrder = ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'];
            $sortedFindings = [];

            foreach ($severityOrder as $severity) {
                foreach ($this->findings as $finding) {
                    if ($finding['severity'] === $severity) {
                        $sortedFindings[] = $finding;
                    }
                }
            }

            foreach ($sortedFindings as $finding) {
                echo "🔴 [" . $finding['severity'] . "] " . $finding['category'] . "\n";
                echo "   Issue: " . $finding['issue'] . "\n";
                echo "   Recommendation: " . $finding['recommendation'] . "\n\n";
            }
        }

        echo "Passed Checks:\n";
        echo "==============\n";
        foreach ($this->passed as $passed) {
            echo "$passed\n";
        }

        // Risk assessment
        $criticalCount = count(array_filter($this->findings, function($f) { return $f['severity'] === 'CRITICAL'; }));
        $highCount = count(array_filter($this->findings, function($f) { return $f['severity'] === 'HIGH'; }));

        echo "\nRisk Assessment:\n";
        echo "================\n";

        if ($criticalCount > 0) {
            echo "🚨 CRITICAL RISK: Immediate action required\n";
        } elseif ($highCount > 0) {
            echo "⚠️  HIGH RISK: Address high-severity issues promptly\n";
        } elseif (count($this->findings) > 0) {
            echo "🟡 MEDIUM RISK: Review and address findings\n";
        } else {
            echo "✅ LOW RISK: Security posture is good\n";
        }

        echo "\nSecurity audit completed at " . date('Y-m-d H:i:s') . "\n";
    }
}

// Run the audit
$auditor = new SecurityAuditor($argv[1] ?? 'http://localhost:8000');
$auditor->runAudit();