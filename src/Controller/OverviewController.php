<?php

namespace App\Controller;

use App\Entity\ClientDevice;
use App\Repository\ClientDeviceRepository;
use App\Repository\NetworkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class OverviewController extends AbstractController
{
    #[Route('/', name: 'overview', methods: ['GET'])]
    public function index(
        Request $request,
        ClientDeviceRepository $leaseRepository,
        NetworkRepository $networkRepository,
    ): Response {
        $filters = [
            'network' => $request->query->get('network'),
            'search' => $request->query->get('search'),
            'type' => $request->query->get('type'),
        ];

        $leases = $leaseRepository->findFiltered($filters);
        $networks = $networkRepository->findBy([], ['name' => 'ASC']);

        return $this->render('overview/index.html.twig', [
            'leases' => $leases,
            'networks' => $networks,
            'filters' => $filters,
        ]);
    }

    #[Route('/lease/{id}/name', name: 'lease_name', methods: ['POST'])]
    public function saveName(
        Request $request,
        ClientDevice $lease,
        EntityManagerInterface $em,
    ): Response {
        $name = trim($request->request->get('custom_name', ''));
        $lease->setCustomName($name !== '' ? $name : null);
        $em->flush();

        $returnUrl = $request->request->get('_return') ?: $this->generateUrl('overview');

        return $this->redirect($returnUrl);
    }

    #[Route('/lease/{id}/remark', name: 'lease_remark', methods: ['POST'])]
    public function saveRemark(
        Request $request,
        ClientDevice $lease,
        EntityManagerInterface $em,
    ): Response {
        $remark = trim($request->request->get('remark', ''));
        $lease->setRemark($remark !== '' ? $remark : null);
        $em->flush();

        $returnUrl = $request->request->get('_return') ?: $this->generateUrl('overview');

        return $this->redirect($returnUrl);
    }
}
