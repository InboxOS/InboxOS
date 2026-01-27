<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Domain;
use App\Entity\MailUser;
use App\Entity\ApiKey;
use App\Service\RateLimiter;

#[Route('/api')]
class ApiController extends AbstractController
{
    public function __construct(
        private RateLimiter $rateLimiter
    ) {}

    #[Route('/domains', name: 'api_domains_list', methods: ['GET'])]
    public function listDomains(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->applyRateLimit($request);
        $this->checkApiAccess();

        $domains = $entityManager->getRepository(Domain::class)->findAll();
        $data = [];

        foreach ($domains as $domain) {
            $data[] = [
                'id' => $domain->getId(),
                'name' => $domain->getName(),
                'is_active' => $domain->isIsActive(),
                'quota_mb' => $domain->getQuotaMb(),
            ];
        }

        return $this->json([
            'success' => true,
            'data' => $data,
            'total' => count($data),
        ]);
    }

    #[Route('/domains/{id}', name: 'api_domains_get', methods: ['GET'])]
    public function getDomain(Request $request, int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->applyRateLimit($request);
        $this->checkApiAccess();

        $domain = $entityManager->getRepository(Domain::class)->find($id);

        if (!$domain) {
            return $this->json([
                'success' => false,
                'error' => 'Domain not found',
            ], 404);
        }

        return $this->json([
            'success' => true,
            'data' => [
                'id' => $domain->getId(),
                'name' => $domain->getName(),
                'is_active' => $domain->isIsActive(),
                'quota_mb' => $domain->getQuotaMb(),
                'dkim_selector' => $domain->getDkimSelector(),
                'dkim_public_key' => $domain->getDkimPublicKey(),
            ],
        ]);
    }

    #[Route('/domains', name: 'api_domains_create', methods: ['POST'])]
    public function createDomain(
        Request $request,
        EntityManagerInterface $entityManager,
        MailServerManager $mailServerManager
    ): JsonResponse {
        $this->applyRateLimit($request);
        $this->checkApiAccess();

        $data = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid JSON payload',
            ], 400);
        }

        $domain = new Domain();
        $domain->setName($data['name'] ?? '');
        $domain->setQuotaMb($data['quota_mb'] ?? 1024);

        $errors = [];
        if (empty($domain->getName())) {
            $errors[] = 'Domain name is required';
        } elseif (!preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $domain->getName())) {
            $errors[] = 'Invalid domain name format';
        }

        if ($domain->getQuotaMb() < 1 || $domain->getQuotaMb() > 10000) {
            $errors[] = 'Quota must be between 1 and 10000 MB';
        }

        if ($errors) {
            return $this->json([
                'success' => false,
                'errors' => $errors,
            ], 400);
        }

        // Create in mail server
        if (!$mailServerManager->createDomain($domain)) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to create domain in mail server',
            ], 500);
        }

        $entityManager->persist($domain);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Domain created successfully',
            'data' => [
                'id' => $domain->getId(),
                'name' => $domain->getName(),
            ],
        ], 201);
    }

    #[Route('/users', name: 'api_users_create', methods: ['POST'])]
    public function createUser(
        Request $request,
        EntityManagerInterface $entityManager,
        MailServerManager $mailServerManager
    ): JsonResponse {
        $this->applyRateLimit($request);
        $this->checkApiAccess();

        $data = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid JSON payload',
            ], 400);
        }

        $user = new MailUser();
        $user->setEmail($data['email'] ?? '');
        $user->setPassword(password_hash($data['password'] ?? '', PASSWORD_DEFAULT));
        $user->setQuotaLimit($data['quota_limit'] ?? 1024);

        if (isset($data['domain_id'])) {
            $domain = $entityManager->getRepository(Domain::class)->find($data['domain_id']);
            $user->setDomain($domain);
        }

        $errors = [];
        if (empty($user->getEmail())) {
            $errors[] = 'Email is required';
        } elseif (!filter_var($user->getEmail(), FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }

        if (empty($data['password'])) {
            $errors[] = 'Password is required';
        } elseif (strlen($data['password']) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }

        if ($user->getQuotaLimit() < 1 || $user->getQuotaLimit() > 10000) {
            $errors[] = 'Quota must be between 1 and 10000 MB';
        }

        if ($errors) {
            return $this->json([
                'success' => false,
                'errors' => $errors,
            ], 400);
        }

        // Create in mail server
        if (!$mailServerManager->createUser($user)) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to create user in mail server',
            ], 500);
        }

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
            ],
        ], 201);
    }

    #[Route('/stats', name: 'api_stats', methods: ['GET'])]
    public function getStats(
        Request $request,
        MailServerManager $mailServerManager,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $this->applyRateLimit($request);
        $this->checkApiAccess();

        $domainCount = $entityManager->getRepository(Domain::class)->count([]);
        $userCount = $entityManager->getRepository(MailUser::class)->count([]);
        $activeUsers = $entityManager->getRepository(MailUser::class)->count(['isActive' => true]);

        $serverStats = $mailServerManager->getServerStatistics();
        $storageUsage = $mailServerManager->getStorageUsage();

        return $this->json([
            'success' => true,
            'data' => [
                'domains' => $domainCount,
                'users' => $userCount,
                'active_users' => $activeUsers,
                'server' => $serverStats,
                'storage' => $storageUsage,
            ],
        ]);
    }

    private function checkApiAccess(): void
    {
        // Check API key from header
        $apiKey = $this->getApiKeyFromRequest();

        if (!$apiKey) {
            throw new AccessDeniedException('API key required');
        }

        // Validate API key
        $entityManager = $this->getDoctrine()->getManager();
        $apiKeyEntity = $entityManager->getRepository(ApiKey::class)
            ->findOneBy(['token' => $apiKey, 'isActive' => true]);

        if (!$apiKeyEntity) {
            throw new AccessDeniedException('Invalid API key');
        }

        // Check expiration
        if ($apiKeyEntity->getExpiresAt() && $apiKeyEntity->getExpiresAt() < new \DateTime()) {
            throw new AccessDeniedException('API key expired');
        }

        // Update last used
        $apiKeyEntity->setLastUsed(new \DateTime());
        $entityManager->flush();
    }

    private function applyRateLimit(Request $request): void
    {
        $identifier = $request->getClientIp();
        $this->rateLimiter->checkRateLimit($request, $identifier, 100, 3600); // 100 requests per hour
    }

    private function getApiKeyFromRequest(): ?string
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();

        // Check Authorization header
        $authHeader = $request->headers->get('Authorization');
        if ($authHeader && preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // Check query parameter
        return $request->query->get('api_key');
    }
}
