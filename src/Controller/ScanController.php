<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\UnifiScanService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class ScanController extends AbstractController
{
    #[Route('/scan', name: 'scan', methods: ['POST'])]
    public function index(
        UnifiScanService $scanService,
        LoggerInterface $logger,
        TranslatorInterface $translator,
    ): Response {
        if (!$scanService->isConfigured()) {
            $this->addFlash('danger', $translator->trans('message.scan.not_configured'));

            return $this->redirectToRoute('overview');
        }

        try {
            $result = $scanService->scan();
            $this->addFlash('success', $translator->trans('message.scan.success', $result));
        } catch (\RuntimeException $e) {
            $logger->error('Scan failed: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
            $this->addFlash('danger', $translator->trans('message.scan.error', ['error' => $e->getMessage()]));
        } catch (\Throwable $e) {
            $logger->error('Unexpected scan error: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
            $this->addFlash('danger', $translator->trans('message.scan.error.generic'));
        }

        return $this->redirectToRoute('overview');
    }
}
