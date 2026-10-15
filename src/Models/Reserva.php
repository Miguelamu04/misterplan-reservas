<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use Throwable;
use DomainException;
use InvalidArgumentException;
use RuntimeException;

class Reserva
{
    public function __construct(private PDO $pdo)
    {
    }

    // Obtener reservas aplicando filtros combinables.
    public function listar(array $filtros = []): array
    {
        $sql = "SELECT
                    id,
                    nombre_huesped,
                    email_huesped,
                    nombre_alojamiento,
                    fecha_entrada,
                    fecha_salida,
                    estado,
                    importe,
                    notas,
                    fecha_creacion
                FROM reserva
                WHERE 1 = 1";

        $parametros = [];

        if (!empty($filtros['estado'])) {
            $sql .= " AND estado = :estado";
            $parametros['estado'] = $filtros['estado'];
        }

        // Buscar reservas que coincidan con el período indicado.
        if (!empty($filtros['fecha_desde'])) {
            $sql .= " AND fecha_salida > :fecha_desde";
            $parametros['fecha_desde'] = $filtros['fecha_desde'];
        }

        if (!empty($filtros['fecha_hasta'])) {
            $sql .= " AND fecha_entrada <= :fecha_hasta";
            $parametros['fecha_hasta'] = $filtros['fecha_hasta'];
        }

        if (!empty($filtros['huesped'])) {
            $sql .= " AND nombre_huesped LIKE :huesped ESCAPE '!'";

            // Escapar los caracteres especiales de LIKE.
            $nombre = str_replace(
                ['!', '%', '_'],
                ['!!', '!%', '!_'],
                $filtros['huesped']
            );

            $parametros['huesped'] = '%' . $nombre . '%';
        }

        $sql .= " ORDER BY fecha_creacion DESC, id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parametros);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Buscar una reserva por su identificador.
    public function buscarPorId(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $sql = "SELECT
                    id,
                    nombre_huesped,
                    email_huesped,
                    nombre_alojamiento,
                    fecha_entrada,
                    fecha_salida,
                    estado,
                    importe,
                    notas,
                    fecha_creacion
                FROM reserva
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $reserva = $stmt->fetch(PDO::FETCH_ASSOC);

        return $reserva ?: null;
    }

    // Crear una reserva y registrar su evento inicial.
    public function crear(array $datos): int
    {
        $this->pdo->beginTransaction();

        try {
            $sql = "INSERT INTO reserva (
                        nombre_huesped,
                        email_huesped,
                        nombre_alojamiento,
                        fecha_entrada,
                        fecha_salida,
                        estado,
                        importe,
                        notas
                    ) VALUES (
                        :nombre,
                        :email,
                        :alojamiento,
                        :entrada,
                        :salida,
                        'pendiente',
                        :importe,
                        :notas
                    )";

            $stmt = $this->pdo->prepare($sql);

            $stmt->execute([
                'nombre' => $datos['nombre_huesped'],
                'email' => $datos['email_huesped'],
                'alojamiento' => $datos['nombre_alojamiento'],
                'entrada' => $datos['fecha_entrada'],
                'salida' => $datos['fecha_salida'],
                'importe' => $datos['importe'],
                'notas' => $datos['notas'] ?? null
            ]);

            $reservaId = (int) $this->pdo->lastInsertId();

            $sqlEvento = "INSERT INTO reserva_evento (
                            reserva_id,
                            tipo,
                            descripcion
                        ) VALUES (
                            :reserva_id,
                            'creacion',
                            :descripcion
                        )";

            $stmtEvento = $this->pdo->prepare($sqlEvento);

            $stmtEvento->execute([
                'reserva_id' => $reservaId,
                'descripcion' => 'Reserva creada en estado pendiente'
            ]);

            $this->pdo->commit();

            return $reservaId;

        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    // Confirmar o cancelar una reserva.
    public function cambiarEstado(int $id, string $nuevoEstado): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException(
                'El identificador de la reserva no es válido.'
            );
        }

        if (!in_array($nuevoEstado, ['confirmada', 'cancelada'], true)) {
            throw new InvalidArgumentException(
                'El estado debe ser confirmada o cancelada.'
            );
        }

        $this->pdo->beginTransaction();

        try {
            // Bloquear la fila para evitar cambios simultáneos.
            $sql = "SELECT estado
                    FROM reserva
                    WHERE id = :id
                    FOR UPDATE";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $id]);

            $reserva = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reserva) {
                throw new RuntimeException('La reserva no existe.');
            }

            $estadoActual = $reserva['estado'];

            // No registrar un evento si el estado no cambia.
            if ($estadoActual === $nuevoEstado) {
                $this->pdo->commit();
                return false;
            }

            if ($estadoActual === 'cancelada') {
                throw new DomainException(
                    'Una reserva cancelada no puede modificarse.'
                );
            }

            $sql = "UPDATE reserva
                    SET estado = :estado
                    WHERE id = :id";

            $stmt = $this->pdo->prepare($sql);

            $stmt->execute([
                'estado' => $nuevoEstado,
                'id' => $id
            ]);

            $tipo = match ($nuevoEstado) {
                'confirmada' => 'confirmacion',
                'cancelada' => 'cancelacion'
            };

            $sqlEvento = "INSERT INTO reserva_evento (
                            reserva_id,
                            tipo,
                            descripcion
                        ) VALUES (
                            :reserva_id,
                            :tipo,
                            :descripcion
                        )";

            $stmtEvento = $this->pdo->prepare($sqlEvento);

            $stmtEvento->execute([
                'reserva_id' => $id,
                'tipo' => $tipo,
                'descripcion' =>
                    "Cambio de estado: $estadoActual a $nuevoEstado"
            ]);

            $this->pdo->commit();

            return true;

        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    // Consultar el historial de eventos de una reserva.
    public function obtenerHistorial(int $id): array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException(
                'El identificador de la reserva no es válido.'
            );
        }

        $sql = "SELECT
                    id,
                    reserva_id,
                    tipo,
                    descripcion,
                    fecha_creacion
                FROM reserva_evento
                WHERE reserva_id = :id
                ORDER BY fecha_creacion ASC, id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
