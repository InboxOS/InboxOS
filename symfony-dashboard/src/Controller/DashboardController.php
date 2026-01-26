<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Domain;
use App\Entity\MailUser;
use App\Entity\MailLog;
use App\Service\MailServerManager;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        ChartBuilderInterface $chartBuilder,
        EntityManagerInterface $entityManager,
        MailServerManager $mailServerManager
    ): Response {
        // Get statistics
        $domainCount = $entityManager->getRepository(Domain::class)->count([]);
        $userCount = $entityManager->getRepository(MailUser::class)->count([]);
        $activeUsers = $entityManager->getRepository(MailUser::class)->count(['isActive' => true]);

        // Mail server statistics
        $serverStats = $mailServerManager->getServerStatistics();

        // Create charts
        $storageChart = $this->createStorageChart($chartBuilder, $mailServerManager);
        $trafficChart = $this->createTrafficChart($chartBuilder, $mailServerManager);

        return $this->render('dashboard/index.html.twig', [
            'domain_count' => $domainCount,
            'user_count' => $userCount,
            'active_users' => $activeUsers,
            'server_stats' => $serverStats,
            'storage_chart' => $storageChart,
            'traffic_chart' => $trafficChart,
        ]);
    }

    private function createStorageChart(
        ChartBuilderInterface $chartBuilder,
        MailServerManager $mailServerManager
    ): Chart {
        $storageData = $mailServerManager->getStorageUsage();

        $chart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $chart->setData([
            'labels' => ['Used', 'Free'],
            'datasets' => [[
                'data' => [$storageData['used'], $storageData['free']],
                'backgroundColor' => ['#FF6384', '#36A2EB'],
                'hoverBackgroundColor' => ['#FF6384', '#36A2EB'],
            ]],
        ]);

        $chart->setOptions([
            'responsive' => true,
            'plugins' => [
                'legend' => [
                    'position' => 'top',
                ],
                'title' => [
                    'display' => true,
                    'text' => 'Storage Usage',
                ],
            ],
        ]);

        return $chart;
    }

    private function createTrafficChart(
        ChartBuilderInterface $chartBuilder,
        MailServerManager $mailServerManager
    ): Chart {
        $trafficData = $mailServerManager->getTrafficData();

        $chart = $chartBuilder->createChart(Chart::TYPE_LINE);
        $chart->setData([
            'labels' => $trafficData['labels'],
            'datasets' => [
                [
                    'label' => 'Incoming',
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'borderColor' => 'rgba(54, 162, 235, 1)',
                    'data' => $trafficData['incoming'],
                ],
                [
                    'label' => 'Outgoing',
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'borderColor' => 'rgba(255, 99, 132, 1)',
                    'data' => $trafficData['outgoing'],
                ],
            ],
        ]);

        $chart->setOptions([
            'responsive' => true,
            'plugins' => [
                'title' => [
                    'display' => true,
                    'text' => 'Mail Traffic (Last 7 Days)',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Messages',
                    ],
                ],
            ],
        ]);

        return $chart;
    }

    #[Route('/dashboard/domains', name: 'app_domains')]
    public function domains(EntityManagerInterface $entityManager): Response
    {
        $domains = $entityManager->getRepository(Domain::class)->findAll();

        return $this->render('dashboard/domains.html.twig', [
            'domains' => $domains,
        ]);
    }

    #[Route('/dashboard/users', name: 'app_users')]
    public function users(EntityManagerInterface $entityManager): Response
    {
        $users = $entityManager->getRepository(MailUser::class)->findAll();

        return $this->render('dashboard/users.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/dashboard/logs', name: 'app_logs')]
    public function logs(EntityManagerInterface $entityManager): Response
    {
        $logs = $entityManager->getRepository(MailLog::class)->findBy(
            [],
            ['createdAt' => 'DESC'],
            100
        );

        return $this->render('dashboard/logs.html.twig', [
            'logs' => $logs,
        ]);
    }
}
