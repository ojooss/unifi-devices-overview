<?php

namespace App\Controller;

use App\Form\UploadType;
use App\Service\SupportFileParser;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class UploadController extends AbstractController
{
    #[Route('/upload', name: 'upload', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        SupportFileParser $parser,
        LoggerInterface $logger,
        TranslatorInterface $translator,
    ): Response {
        $form = $this->createForm(UploadType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $file = $form->get('file')->getData();
                $count = $parser->parse($file);
                $this->addFlash('success', $translator->trans('message.upload.success', ['count' => $count]));

                return $this->redirectToRoute('overview');
            } catch (\Throwable $e) {
                $logger->error('Import failed: {message}', [
                    'message' => $e->getMessage(),
                    'exception' => $e,
                    'file' => $form->get('file')->getData()?->getClientOriginalName(),
                ]);
                $this->addFlash('danger', $translator->trans('message.upload.error', ['error' => $e->getMessage()]));
            }
        }

        return $this->render('upload/index.html.twig', [
            'form' => $form,
        ]);
    }
}
