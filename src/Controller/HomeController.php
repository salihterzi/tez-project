<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig', [
            'message' => 'Symfony çalışıyor 🎉',
        ]);
    }

    #[Route('/health', name: 'app_health')]
    public function health(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'symfony' => \Symfony\Component\HttpKernel\Kernel::VERSION,
            'php' => \PHP_VERSION,
            'time' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }
}
