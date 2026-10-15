<?php

declare(strict_types=1);

use App\Controllers\ReservaController;
use App\Database\Connection;
use App\Models\Reserva;
use App\Services\ReservaService;

require dirname(__DIR__) . '/vendor/autoload.php';

// Cargar las variables de entorno
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();

// Obtener la ruta y el método HTTP
$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$metodo = $_SERVER['REQUEST_METHOD'];

// Configurar respuestas JSON
header('Content-Type: application/json; charset=utf-8');

try {
    // 1. Preparar la conexión y las dependencias
    $pdo = Connection::create();

    $modelo = new Reserva($pdo);
    $servicio = new ReservaService($modelo);
    $controller = new ReservaController($modelo, $servicio);

    // 2. GET: Listar y filtrar reservas
    if ($ruta === '/api/reservas' && $metodo === 'GET') {
        $controller->listar();

    // 3. GET: Consultar el detalle de una reserva
    } elseif (
        $metodo === 'GET' &&
        preg_match('#^/api/reservas/([0-9]+)$#', $ruta, $coincidencias)
    ) {
        $controller->detalle((int) $coincidencias[1]);

    // 4. POST: Crear una reserva
    } elseif ($ruta === '/api/reservas' && $metodo === 'POST') {
        $controller->crear();

    // 5. PATCH: Confirmar o cancelar una reserva
    } elseif (
        $metodo === 'PATCH' &&
        preg_match('#^/api/reservas/([0-9]+)/estado$#', $ruta, $coincidencias)
    ) {
        $controller->cambiarEstado((int) $coincidencias[1]);

    // 6. Método HTTP no permitido
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
        http_response_code(405);

        echo json_encode([
            'error' => 'Método HTTP no permitido.'
        ], JSON_UNESCAPED_UNICODE);

    // 7. Ruta no encontrada
    } else {
        http_response_code(404);

        echo json_encode([
            'error' => 'Ruta no encontrada.'
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Throwable $exception) {
    // Registrar el error sin mostrar detalles internos
    error_log((string) $exception);

    http_response_code(500);

    echo json_encode([
        'error' => 'Error interno del servidor.'
    ], JSON_UNESCAPED_UNICODE);
}
