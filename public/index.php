<?php

declare(strict_types=1);

use App\Controllers\PageController;
use App\Controllers\ReservaController;
use App\Database\Connection;
use App\Models\Reserva;
use App\Services\ReservaService;

require dirname(__DIR__) . '/vendor/autoload.php';

// Cabeceras de seguridad básicas.
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Obtener la ruta y el método HTTP.
$ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!is_string($ruta)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Solicitud no válida.';
    exit;
}

// Permitir archivos estáticos dentro de public/.
// Esta condición se utiliza con el servidor integrado de PHP.
if (PHP_SAPI === 'cli-server') {
    $archivo = realpath(__DIR__ . $ruta);
    $directorioPublico = realpath(__DIR__);

    if (
        $archivo !== false &&
        $directorioPublico !== false &&
        str_starts_with(
            $archivo,
            $directorioPublico . DIRECTORY_SEPARATOR
        ) &&
        is_file($archivo)
    ) {
        return false;
    }
}

// Identificar las peticiones a la API.
$esApi = $ruta === '/api' || str_starts_with($ruta, '/api/');

try {
    // Redirigir la página principal al listado.
    if ($ruta === '/' && $metodo === 'GET') {
        header('Location: /reservas', true, 302);
        exit;
    }

    // Determinar si necesitamos los controladores.
    $esListadoHtml = $ruta === '/reservas';
    $esDetalleHtml = preg_match(
        '#^/reservas/([0-9]+)$#',
        $ruta,
        $coincidenciasHtml
    );

    $esListadoApi = $ruta === '/api/reservas';
    $esDetalleApi = preg_match(
        '#^/api/reservas/([0-9]+)$#',
        $ruta,
        $coincidenciasApi
    );

    $esCambioEstado = preg_match(
        '#^/api/reservas/([0-9]+)/estado$#',
        $ruta,
        $coincidenciasEstado
    );

    // Responder antes de acceder a MySQL si la ruta no existe.
    if (
        !$esListadoHtml &&
        !$esDetalleHtml &&
        !$esListadoApi &&
        !$esDetalleApi &&
        !$esCambioEstado &&
        $ruta !== '/'
    ) {
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

        exit;
    }

    // Comprobar si el método HTTP está permitido.
    $permitidos = match (true) {
        $ruta === '/' => 'GET',
        $esListadoHtml, $esDetalleHtml => 'GET',
        $esListadoApi => 'GET, POST',
        $esDetalleApi => 'GET',
        $esCambioEstado => 'PATCH',
        default => 'GET'
    };

    $metodosPermitidos = array_map(
        'trim',
        explode(',', $permitidos)
    );

    if (!in_array($metodo, $metodosPermitidos, true)) {
        header('Allow: ' . $permitidos);
        http_response_code(405);

        if ($esApi) {
            header('Content-Type: application/json; charset=utf-8');

            echo json_encode([
                'error' => 'Método HTTP no permitido.'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            header('Content-Type: text/html; charset=utf-8');

            echo '<h1>405 - Método no permitido</h1>';
        }

        exit;
    }

    // Crear las dependencias solo para rutas válidas.
    $pdo = Connection::create();

    $modelo = new Reserva($pdo);
    $servicio = new ReservaService($modelo);

    $apiController = new ReservaController($modelo, $servicio);
    $pageController = new PageController($modelo);

    // Rutas HTML.
    if ($esListadoHtml) {
        $pageController->listar();

    } elseif ($esDetalleHtml) {
        $pageController->detalle(
            (int) $coincidenciasHtml[1]
        );

    // Rutas REST.
    } elseif ($esListadoApi && $metodo === 'GET') {
        $apiController->listar();

    } elseif ($esDetalleApi) {
        $apiController->detalle(
            (int) $coincidenciasApi[1]
        );

    } elseif ($esListadoApi && $metodo === 'POST') {
        $apiController->crear();

    } elseif ($esCambioEstado) {
        $apiController->cambiarEstado(
            (int) $coincidenciasEstado[1]
        );
    }

} catch (Throwable $exception) {
    // Registrar los detalles únicamente en el servidor.
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
