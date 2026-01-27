<?php

namespace App\Tests\Controller;

use App\Controller\ApiController;
use App\Service\RateLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class ApiControllerTest extends TestCase
{
    private ApiController $controller;
    private RateLimiter $rateLimiter;

    protected function setUp(): void
    {
        $this->rateLimiter = $this->createMock(RateLimiter::class);
        $this->controller = new ApiController($this->rateLimiter);
    }

    public function testApplyRateLimit(): void
    {
        $request = new Request();
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $this->rateLimiter->expects($this->once())
            ->method('checkRateLimit')
            ->with($request, '127.0.0.1', 100, 3600);

        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('applyRateLimit');
        $method->setAccessible(true);

        $method->invoke($this->controller, $request);

        $this->assertTrue(true);
    }
}