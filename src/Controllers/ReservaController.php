<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Reserva;
use App\Services\ReservaService;
use DomainException;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

class ReservaController
{
    public function __construct(
        private Reserva $modelo,
        private ReservaService $servicio
    ) {
    }

    // GET /api/reservas
    // Listar y filtrar reservas.
    public function listar(): void
    {
        $filtros = [
            'estado' => $_GET['estado'] ?? null,
            'fecha_desde' => $_GET['fecha_desde'] ?? null,
            'fecha_hasta' => $_GET['fecha_hasta'] ?? null,
            'huesped' => $_GET['huesped'] ?? null
        ];

        try {
            $filtros = $this->servicio->validarFiltros($filtros);

            $reservas = $this->modelo->listar($filtros);

            $this->responder($reservas);

        } catch (InvalidArgumentException $exception) {
            $this->responder([
                'error' => $exception->getMessage()
            ], 400);
        }
    }

    // GET /api/reservas/{id}
    // Obtener el detalle y su historial.
    public function detalle(int $id): void
    {
        $reserva = $this->modelo->buscarPorId($id);

        if ($reserva === null) {
            $this->responder([
                'error' => 'Reserva no encontrada.'
            ], 404);
            return;
        }

        $reserva['historial'] = $this->modelo->obtenerHistorial($id);

        $this->responder($reserva);
    }

    // POST /api/reservas
    // Crear una reserva.
    public function crear(): void
    {
        try {
            $datos = $this->leerJson();

            $id = $this->servicio->crear($datos);

            header('Location: /api/reservas/' . $id);

            $this->responder([
                'mensaje' => 'Reserva creada correctamente.',
                'id' => $id
            ], 201);

        } catch (JsonException $exception) {
            $this->responder([
                'error' => 'El JSON enviado no es válido.'
            ], 400);

        } catch (InvalidArgumentException $exception) {
            $this->responder([
                'error' => $exception->getMessage()
            ], 400);
        }
    }

    // PATCH /api/reservas/{id}/estado
    // Confirmar o cancelar una reserva.
    public function cambiarEstado(int $id): void
    {
        try {
            $datos = $this->leerJson();

            if (
                !isset($datos['estado']) ||
                !is_string($datos['estado'])
            ) {
                throw new InvalidArgumentException(
                    'Debes indicar un estado válido.'
                );
            }

            // El modelo comprueba la existencia de la reserva
            // dentro de la transacción mediante FOR UPDATE.
            $modificada = $this->servicio->cambiarEstado(
                $id,
                $datos['estado']
            );

            $this->responder([
                'mensaje' => $modificada
                    ? 'Estado actualizado correctamente.'
                    : 'La reserva ya tenía ese estado.',
                'reserva' => $this->modelo->buscarPorId($id)
            ]);

        } catch (JsonException $exception) {
            $this->responder([
                'error' => 'El JSON enviado no es válido.'
            ], 400);

        } catch (InvalidArgumentException $exception) {
            $this->responder([
                'error' => $exception->getMessage()
            ], 400);

        } catch (DomainException $exception) {
            $this->responder([
                'error' => $exception->getMessage()
            ], 409);

        } catch (RuntimeException $exception) {
            // El modelo utiliza este mensaje cuando no
            // encuentra una reserva durante la actualización.
            if ($exception->getMessage() !== 'La reserva no existe.') {
                throw $exception;
            }

            $this->responder([
                'error' => 'Reserva no encontrada.'
            ], 404);
        }
    }

    // Leer y validar el cuerpo JSON de la petición.
    private function leerJson(): array
    {
        $contenido = file_get_contents('php://input');

        $datos = json_decode(
            $contenido,
            false,
            512,
            JSON_THROW_ON_ERROR
        );

        // Exigir un objeto JSON, no una lista ni otro valor.
        if (!$datos instanceof \stdClass) {
            throw new InvalidArgumentException(
                'Debes enviar un objeto JSON.'
            );
        }

        return (array) $datos;
    }

    // Enviar una respuesta JSON con su código HTTP.
    private function responder(array $datos, int $codigo = 200): void
    {
        http_response_code($codigo);

        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(
            $datos,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }
}
