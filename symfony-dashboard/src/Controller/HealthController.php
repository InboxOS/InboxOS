<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;

class HealthController extends AbstractController
{
    #[Route('/health', name: 'app_health', methods: ['GET'])]
    public function health(EntityManagerInterface $entityManager): JsonResponse
    {
        $status = 'healthy';
        $checks = [];

        // Database check
        try {
            $entityManager->getConnection()->connect();
            $checks['database'] = 'ok';
        } catch (\Exception $e) {
            $checks['database'] = 'error: ' . $e->getMessage();
            $status = 'unhealthy';
        }

        // Redis check
        try {
            $redis = RedisAdapter::createConnection($_ENV['REDIS_URL'] ?? 'redis://localhost:6379');
            $redis->ping();
            $checks['redis'] = 'ok';
        } catch (\Exception $e) {
            $checks['redis'] = 'error: ' . $e->getMessage();
            $status = 'unhealthy';
        }

        // Mail server check (basic connectivity)
        try {
            $connection = @fsockopen('mailserver', 25, $errno, $errstr, 5);
            if ($connection) {
                fclose($connection);
                $checks['mailserver'] = 'ok';
            } else {
                $checks['mailserver'] = 'error: ' . $errstr;
                $status = 'unhealthy';
            }
        } catch (\Exception $e) {
            $checks['mailserver'] = 'error: ' . $e->getMessage();
            $status = 'unhealthy';
        }

        return $this->json([
            'status' => $status,
            'timestamp' => time(),
            'checks' => $checks,
        ], $status === 'healthy' ? 200 : 503);
    }
}