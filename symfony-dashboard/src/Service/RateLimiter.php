<?php

namespace App\Service;

use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Psr\Log\LoggerInterface;

class RateLimiter
{
    private AdapterInterface $cache;
    private LoggerInterface $logger;

    public function __construct(string $redisUrl, LoggerInterface $logger, ?AdapterInterface $cache = null)
    {
        $this->logger = $logger;
        if ($cache) {
            $this->cache = $cache;
        } else {
            $client = RedisAdapter::createConnection($redisUrl);
            $this->cache = new RedisAdapter($client);
        }
    }

    public function checkRateLimit(Request $request, string $identifier, int $maxRequests = 100, int $windowSeconds = 3600): void
    {
        $safeId = md5($identifier);
        $key = "rate_limit_{$safeId}";

        // Get current count
        $item = $this->cache->getItem($key);
        $currentCount = $item->get() ?? 0;

        if ($currentCount >= $maxRequests) {
            $this->logger->warning('Rate limit exceeded', [
                'identifier' => $identifier,
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'current_count' => $currentCount,
                'max_requests' => $maxRequests
            ]);

            throw new TooManyRequestsHttpException(
                null,
                sprintf('Rate limit exceeded. Maximum %d requests per %d seconds.', $maxRequests, $windowSeconds)
            );
        }

        // Increment counter
        $item->set($currentCount + 1);
        $item->expiresAfter($windowSeconds);
        $this->cache->save($item);
    }

    public function getRateLimitInfo(Request $request, string $identifier, int $maxRequests = 100, int $windowSeconds = 3600): array
    {
        $safeId = md5($identifier);
        $key = "rate_limit_{$safeId}";
        $item = $this->cache->getItem($key);
        $currentCount = $item->get() ?? 0;

        return [
            'current' => $currentCount,
            'max' => $maxRequests,
            'remaining' => max(0, $maxRequests - $currentCount),
            'reset_time' => time() + $windowSeconds
        ];
    }
}