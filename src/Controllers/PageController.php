<?php

namespace App\Controllers;

use App\Models\Reserva;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class PageController
{
    private Environment $twig;

    public function __construct(private Reserva $modelo)
    {
        // Carpeta donde se encuentran nuestras vistas.
        $loader = new FilesystemLoader(
            dirname(__DIR__, 2) . '/views'
        );

        // Configuración del motor de plantillas Twig.
        $this->twig = new Environment($loader, [
            'autoescape' => 'html'
        ]);
    }

    // GET /reservas
    public function listar(): void
    {
        $reservas = $this->modelo->listar();

        header('Content-Type: text/html; charset=utf-8');

        echo $this->twig->render('reservas/index.twig', [
            'reservas' => $reservas
        ]);
    }

    // GET /reservas/{id}
    public function detalle(int $id): void
    {
        $reserva = $this->modelo->buscarPorId($id);

        if ($reserva === null) {
            http_response_code(404);
            header('Content-Type: text/html; charset=utf-8');

            echo '<h1>404 - Reserva no encontrada</h1>';
            return;
        }

        $historial = $this->modelo->obtenerHistorial($id);

        header('Content-Type: text/html; charset=utf-8');

        echo $this->twig->render('reservas/detalle.twig', [
            'reserva' => $reserva,
            'historial' => $historial
        ]);
    }
}
