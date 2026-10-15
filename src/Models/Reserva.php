<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class Reserva
{
    // Propiedades de una reserva
    public int $id;
    public string $nombreHuesped;
    public string $emailHuesped;
    public string $nombreAlojamiento;
    public string $fechaEntrada;
    public string $fechaSalida;
    public string $estado;
    public string $importe;
    public ?string $notas;
    public string $fechaCreacion;

    // Conexión a la base de datos
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Obtener todas las reservas
    
    public function listar(array $filtros = []): array
    {
        $sql = "SELECT * FROM reserva WHERE 1=1";
        $parametros = [];

        // Filtrar por estado
        if (!empty($filtros['estado'])) {
            $sql .= " AND estado = :estado";
            $parametros['estado'] = $filtros['estado'];
        }

        // Filtrar por fecha de entrada
        if (!empty($filtros['fecha_desde'])) {
            $sql .= " AND fecha_salida > :fecha_desde";
            $parametros['fecha_desde'] = $filtros['fecha_desde'];
        }

        // Filtrar por fecha de salida
        if (!empty($filtros['fecha_hasta'])) {
            $sql .= " AND fecha_entrada <= :fecha_hasta";
            $parametros['fecha_hasta'] = $filtros['fecha_hasta'];
        }

        // Buscar por nombre del huésped
        if (!empty($filtros['huesped'])) {
            $sql .= " AND nombre_huesped LIKE :huesped";
            $parametros['huesped'] = '%' . $filtros['huesped'] . '%';
        }

        $sql .= " ORDER BY fecha_creacion DESC, id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parametros);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    // Buscar una reserva por su ID
    public function buscarPorId(int $id): ?array
    {
        $sql = "SELECT * FROM reserva WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $reserva = $stmt->fetch(PDO::FETCH_ASSOC);

        return $reserva ?: null;
    }

    
    public function crear(array $datos): int
    {
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

        $this->pdo->beginTransaction();

        try {
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

        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    
    
    public function cambiarEstado(int $id, string $nuevoEstado): bool
    {
        // 1. Validar los parámetros
        if ($id <= 0) {
            throw new \InvalidArgumentException(
                'El identificador de la reserva no es válido.'
            );
        }

        if (!in_array($nuevoEstado, ['confirmada', 'cancelada'], true)) {
            throw new \InvalidArgumentException(
                'El estado debe ser confirmada o cancelada.'
            );
        }

        // 2. Iniciar la transacción
        $this->pdo->beginTransaction();

        try {
            // 3. Buscar y bloquear la reserva
            $sql = "SELECT estado FROM reserva
                    WHERE id = :id FOR UPDATE";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $id]);

            $reserva = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reserva) {
                throw new \RuntimeException(
                    'La reserva no existe.'
                );
            }

            $estadoActual = $reserva['estado'];

            // 4. Evitar cambios repetidos
            if ($estadoActual === $nuevoEstado) {
                $this->pdo->commit();
                return false;
            }

            // 5. Impedir modificar reservas canceladas
            if ($estadoActual === 'cancelada') {
                throw new \DomainException(
                    'Una reserva cancelada no puede modificarse.'
                );
            }

            // 6. Actualizar el estado
            $sql = "UPDATE reserva
                    SET estado = :estado
                    WHERE id = :id";

            $stmt = $this->pdo->prepare($sql);

            $stmt->execute([
                'estado' => $nuevoEstado,
                'id' => $id
            ]);

            // 7. Determinar el tipo de evento
            $tipo = match ($nuevoEstado) {
                'confirmada' => 'confirmacion',
                'cancelada' => 'cancelacion'
            };

            // 8. Registrar el evento en el historial
            $sql = "INSERT INTO reserva_evento
                        (reserva_id, tipo, descripcion)
                    VALUES
                        (:reserva_id, :tipo, :descripcion)";

            $stmt = $this->pdo->prepare($sql);

            $stmt->execute([
                'reserva_id' => $id,
                'tipo' => $tipo,
                'descripcion' => "Cambio de estado: $estadoActual a $nuevoEstado"
            ]);

            // 9. Confirmar los cambios
            $this->pdo->commit();

            return true;

        } catch (\Throwable $exception) {

            // Deshacer los cambios si ocurre un error
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    
    public function obtenerHistorial(int $id): array
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException(
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

        $stmt->execute([
            'id' => $id
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


}
