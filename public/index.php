<?php

declare(strict_types=1);

use App\Controllers\PageController;
use App\Controllers\ReservaController;
use App\Database\Connection;
use App\Models\Reserva;
use App\Services\ReservaService;

require dirname(__DIR__) . '/vendor/autoload.php';

// 1. Cargar las variables de entorno.
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();

// 2. Obtener la ruta y el método HTTP.
$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$metodo = $_SERVER['REQUEST_METHOD'];

// Permitir que el servidor integrado de PHP sirva archivos
// estáticos (CSS, JavaScript e imágenes) directamente.
if (
    PHP_SAPI === 'cli-server' &&
    $ruta !== false &&
    is_file(__DIR__ . $ruta)
) {
    return false;
}

// Determinar si la solicitud corresponde a la API.
$esApi = str_starts_with($ruta ?: '', '/api/');

try {
    // 3. Preparar la conexión y las dependencias.
    $pdo = Connection::create();

    $modelo = new Reserva($pdo);
    $servicio = new ReservaService($modelo);

    // Controlador para respuestas JSON.
    $apiController = new ReservaController($modelo, $servicio);

    // Controlador para vistas HTML con Twig.
    $pageController = new PageController($modelo);

    // 4. Página principal: redirigir al listado.
    if ($ruta === '/' && $metodo === 'GET') {
        header('Location: /reservas', true, 302);
        exit;

    // 5. GET: Mostrar el listado HTML.
    } elseif ($ruta === '/reservas' && $metodo === 'GET') {
        $pageController->listar();

    // 6. GET: Mostrar el detalle HTML.
    } elseif (
        $metodo === 'GET' &&
        preg_match('#^/reservas/([0-9]+)$#', $ruta, $coincidencias)
    ) {
        $pageController->detalle((int) $coincidencias[1]);

    // 7. GET API: Listar y filtrar reservas.
    } elseif ($ruta === '/api/reservas' && $metodo === 'GET') {
        $apiController->listar();

    // 8. GET API: Consultar una reserva.
    } elseif (
        $metodo === 'GET' &&
        preg_match('#^/api/reservas/([0-9]+)$#', $ruta, $coincidencias)
    ) {
        $apiController->detalle((int) $coincidencias[1]);

    // 9. POST API: Crear una reserva.
    } elseif ($ruta === '/api/reservas' && $metodo === 'POST') {
        $apiController->crear();

    // 10. PATCH API: Confirmar o cancelar una reserva.
    } elseif (
        $metodo === 'PATCH' &&
        preg_match(
            '#^/api/reservas/([0-9]+)/estado$#',
            $ruta,
            $coincidencias
        )
    ) {
        $apiController->cambiarEstado((int) $coincidencias[1]);

    // 11. Método HTTP no permitido en la API.
    } elseif (
        $ruta === '/api/reservas' ||
        preg_match('#^/api/reservas/[0-9]+(?:/estado)?$#', $ruta)
    ) {
        $permitidos = match (true) {
            $ruta === '/api/reservas' => 'GET, POST',
            str_ends_with($ruta, '/estado') => 'PATCH',
            default => 'GET'
        };

        header('Allow: ' . $permitidos);
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(405);

        echo json_encode([
            'error' => 'Método HTTP no permitido.'
        ], JSON_UNESCAPED_UNICODE);

    // 12. Método HTTP no permitido en las páginas HTML.
    } elseif (
        $ruta === '/' ||
        $ruta === '/reservas' ||
        preg_match('#^/reservas/[0-9]+$#', $ruta)
    ) {
        header('Allow: GET');
        header('Content-Type: text/html; charset=utf-8');
        http_response_code(405);

        echo '<h1>405 - Método no permitido</h1>';

    // 13. Ruta no encontrada.
    } else {
        http_response_code(404);

        if ($esApi) {
            header('Content-Type: application/json; charset=utf-8');

            echo json_encode([
                'error' => 'Ruta no encontrada.'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            header('Content-Type: text/html; charset=utf-8');

            echo '<h1>404 - Página no encontrada</h1>';
        }
    }

} catch (Throwable $exception) {
    // Registrar el error sin mostrar información interna.
    error_log((string) $exception);

    http_response_code(500);

    if ($esApi) {
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'error' => 'Error interno del servidor.'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        header('Content-Type: text/html; charset=utf-8');

        echo '<h1>500 - Error interno del servidor</h1>';
    }
}
