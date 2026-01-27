<?php

namespace App\Tests\Service;

use App\Service\RateLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class RateLimiterTest extends TestCase
{
    private RateLimiter $rateLimiter;

    protected function setUp(): void
    {
        // Use an in-memory cache for testing
        $cache = new ArrayAdapter();
        $this->rateLimiter = new RateLimiter('redis://localhost:6379', $this->createMock(\Psr\Log\LoggerInterface::class), $cache);
    }

    public function testRateLimitNotExceeded(): void
    {
        $request = new Request();
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        // This should not throw an exception
        $this->rateLimiter->checkRateLimit($request, '127.0.0.1', 10, 3600);

        $this->assertTrue(true); // If we get here, the test passes
    }

    public function testRateLimitInfo(): void
    {
        $request = new Request();
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $info = $this->rateLimiter->getRateLimitInfo($request, '127.0.0.1', 10, 3600);

        $this->assertArrayHasKey('current', $info);
        $this->assertArrayHasKey('max', $info);
        $this->assertArrayHasKey('remaining', $info);
        $this->assertArrayHasKey('reset_time', $info);
        $this->assertEquals(10, $info['max']);
    }
}