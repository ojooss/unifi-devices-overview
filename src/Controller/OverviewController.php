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
use Symfony\Contracts\Translation\TranslatorInterface;

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

    #[Route('/export/csv', name: 'overview_csv', methods: ['GET'])]
    public function exportCsv(
        Request $request,
        ClientDeviceRepository $leaseRepository,
    ): Response {
        $filters = [
            'network' => $request->query->get('network'),
            'search' => $request->query->get('search'),
            'type' => $request->query->get('type'),
        ];

        $leases = $leaseRepository->findFiltered($filters);

        $out = fopen('php://temp', 'w+');
        fputcsv($out, [
            'Name', 'UniFi Alias', 'Hostname', 'MAC Address', 'IP Address',
            'Type', 'Network', 'Last Updated', 'Seen At', 'Remark',
        ], escape: '');
        foreach ($leases as $lease) {
            fputcsv($out, [
                $lease->getCustomName() ?? '',
                $lease->getUnifiAlias() ?? '',
                $lease->getHostname() ?? '',
                $lease->getMacAddress(),
                $lease->getIpAddress(),
                $lease->getIpType(),
                $lease->getNetwork()?->getName() ?? '',
                $lease->getLastUpdatedAt()->format('Y-m-d H:i:s'),
                $lease->getSeenAt()->format('Y-m-d H:i:s'),
                $lease->getRemark() ?? '',
            ], escape: '');
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="devices.csv"',
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

    #[Route('/lease/{id}/delete', name: 'lease_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        ClientDevice $lease,
        EntityManagerInterface $em,
        TranslatorInterface $translator,
    ): Response {
        $em->remove($lease);
        $em->flush();

        $this->addFlash('success', $translator->trans('message.lease.deleted'));

        $returnUrl = $request->request->get('_return') ?: $this->generateUrl('overview');

        return $this->redirect($returnUrl);
    }
}
