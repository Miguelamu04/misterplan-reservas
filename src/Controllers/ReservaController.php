<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Reserva;
use App\Services\ReservaService;

class ReservaController
{
    public function __construct(
        private Reserva $modelo,
        private ReservaService $servicio
    ) {
    }

    // Listar y filtrar reservas
    
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

        } catch (\InvalidArgumentException $exception) {
            $this->responder([
                'error' => $exception->getMessage()
            ], 400);
        }
    }


    // Obtener el detalle de una reserva
    public function detalle(int $id): void
    {
        $reserva = $this->modelo->buscarPorId($id);

        if ($reserva === null) {
            $this->responder(
                ['error' => 'Reserva no encontrada.'],
                404
            );
            return;
        }

        $reserva['historial'] = $this->modelo->obtenerHistorial($id);

        $this->responder($reserva);
    }

    
    public function cambiarEstado(int $id): void
    {
        try {
            // Leer el JSON recibido
            $datos = json_decode(
                file_get_contents('php://input'),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            // Comprobar que contiene un estado válido
            if (
                !is_array($datos) ||
                !isset($datos['estado']) ||
                !is_string($datos['estado'])
            ) {
                $this->responder([
                    'error' => 'Debes indicar un estado válido.'
                ], 400);
                return;
            }

            // Comprobar que existe la reserva
            if ($this->modelo->buscarPorId($id) === null) {
                $this->responder([
                    'error' => 'Reserva no encontrada.'
                ], 404);
                return;
            }

            // Actualizar mediante el servicio
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

        } catch (\JsonException $exception) {
            $this->responder([
                'error' => 'El JSON enviado no es válido.'
            ], 400);

        
        } catch (\InvalidArgumentException $exception) {
            // Datos incorrectos
            $this->responder([
                'error' => $exception->getMessage()
            ], 400);

        } catch (\DomainException $exception) {
            // Conflicto con el estado actual
            $this->responder([
                'error' => $exception->getMessage()
            ], 409);
        }
    }


    // Enviar respuesta JSON
    private function responder(array $datos, int $codigo = 200): void
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(
            $datos,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }

    
    public function crear(): void
    {
        // Leer el cuerpo de la petición
        $contenido = file_get_contents('php://input');

        try {
            $datos = json_decode(
                $contenido,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $exception) {
            $this->responder([
                'error' => 'El JSON enviado no es válido.'
            ], 400);
            return;
        }

        // Comprobar que recibimos un objeto JSON
        if (!is_array($datos) || array_is_list($datos)) {
            $this->responder([
                'error' => 'Debes enviar un objeto JSON.'
            ], 400);
            return;
        }

        try {
            // Crear la reserva mediante el servicio
            $id = $this->servicio->crear($datos);

            $this->responder([
                'mensaje' => 'Reserva creada correctamente.',
                'id' => $id
            ], 201);

        } catch (\InvalidArgumentException $exception) {
            $this->responder([
                'error' => $exception->getMessage()
            ], 400);
        }
    }

}
