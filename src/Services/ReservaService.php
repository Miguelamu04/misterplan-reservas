<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Reserva;
use DateTimeImmutable;
use InvalidArgumentException;

class ReservaService
{
    public function __construct(private Reserva $modelo)
    {
    }

    // Validar los datos y crear una nueva reserva.
    public function crear(array $datos): int
    {
        // 1. Comprobar los campos obligatorios.
        $obligatorios = [
            'nombre_huesped',
            'email_huesped',
            'nombre_alojamiento',
            'fecha_entrada',
            'fecha_salida',
            'importe'
        ];

        foreach ($obligatorios as $campo) {
            if (
                !isset($datos[$campo]) ||
                !is_scalar($datos[$campo]) ||
                is_bool($datos[$campo]) ||
                trim((string) $datos[$campo]) === ''
            ) {
                throw new InvalidArgumentException(
                    "El campo $campo es obligatorio."
                );
            }
        }

        // 2. Validar y normalizar los campos de texto.
        $longitudes = [
            'nombre_huesped' => 150,
            'email_huesped' => 254,
            'nombre_alojamiento' => 200
        ];

        foreach ($longitudes as $campo => $maximo) {
            if (!is_string($datos[$campo])) {
                throw new InvalidArgumentException(
                    "El campo $campo debe ser texto."
                );
            }

            $datos[$campo] = trim($datos[$campo]);

            if (
                $datos[$campo] === '' ||
                mb_strlen($datos[$campo]) > $maximo
            ) {
                throw new InvalidArgumentException(
                    "El campo $campo debe contener entre 1 y $maximo caracteres."
                );
            }
        }

        // 3. Comprobar el correo electrónico.
        if (!filter_var($datos['email_huesped'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException(
                'El correo electrónico no es válido.'
            );
        }

        // 4. Validar las fechas.
        $entrada = $this->validarFecha($datos['fecha_entrada']);
        $salida = $this->validarFecha($datos['fecha_salida']);

        if ($salida <= $entrada) {
            throw new InvalidArgumentException(
                'La fecha de salida debe ser posterior a la entrada.'
            );
        }

        // 5. Validar el importe.
        if (
            !is_string($datos['importe']) &&
            !is_int($datos['importe']) &&
            !is_float($datos['importe'])
        ) {
            throw new InvalidArgumentException(
                'El importe debe ser un número válido.'
            );
        }

        if (
            is_float($datos['importe']) &&
            !is_finite($datos['importe'])
        ) {
            throw new InvalidArgumentException(
                'El importe debe ser un número finito.'
            );
        }

        $importe = (string) $datos['importe'];

        // De 0 hasta 99999999.99, con un máximo de dos decimales.
        if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $importe)) {
            throw new InvalidArgumentException(
                'El importe debe ser mayor o igual a cero, no superar 99999999.99 y tener como máximo dos decimales.'
            );
        }

        // 6. Validar las notas opcionales.
        if (isset($datos['notas'])) {
            if (!is_string($datos['notas'])) {
                throw new InvalidArgumentException(
                    'Las notas deben ser texto.'
                );
            }

            $datos['notas'] = trim($datos['notas']);

            if (mb_strlen($datos['notas']) > 5000) {
                throw new InvalidArgumentException(
                    'Las notas no pueden superar los 5000 caracteres.'
                );
            }
        } else {
            $datos['notas'] = null;
        }

        // 7. Normalizar el importe.
        $datos['importe'] = $importe;

        // 8. Crear la reserva mediante el modelo.
        return $this->modelo->crear($datos);
    }

    // Validar los filtros de búsqueda.
    public function validarFiltros(array $filtros): array
    {
        // Comprobar el estado.
        if (isset($filtros['estado'])) {
            if (
                !is_string($filtros['estado']) ||
                !in_array(
                    $filtros['estado'],
                    ['pendiente', 'confirmada', 'cancelada'],
                    true
                )
            ) {
                throw new InvalidArgumentException(
                    'El estado indicado no es válido.'
                );
            }
        }

        // Comprobar las fechas.
        foreach (['fecha_desde', 'fecha_hasta'] as $campo) {
            if (isset($filtros[$campo])) {
                $this->validarFecha($filtros[$campo]);
            }
        }

        // Comprobar el rango de fechas.
        if (
            isset($filtros['fecha_desde'], $filtros['fecha_hasta']) &&
            $filtros['fecha_desde'] > $filtros['fecha_hasta']
        ) {
            throw new InvalidArgumentException(
                'La fecha inicial no puede ser posterior a la final.'
            );
        }

        // Comprobar y normalizar el nombre del huésped.
        if (isset($filtros['huesped'])) {
            if (!is_string($filtros['huesped'])) {
                throw new InvalidArgumentException(
                    'El nombre de búsqueda no es válido.'
                );
            }

            $filtros['huesped'] = trim($filtros['huesped']);

            if (
                $filtros['huesped'] === '' ||
                mb_strlen($filtros['huesped']) > 150
            ) {
                throw new InvalidArgumentException(
                    'El nombre de búsqueda debe contener entre 1 y 150 caracteres.'
                );
            }
        }

        return $filtros;
    }

    // Validar fechas reales en formato YYYY-MM-DD.
    private function validarFecha(mixed $valor): DateTimeImmutable
    {
        if (!is_string($valor)) {
            throw new InvalidArgumentException(
                'La fecha debe tener formato YYYY-MM-DD.'
            );
        }

        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);

        if (!$fecha || $fecha->format('Y-m-d') !== $valor) {
            throw new InvalidArgumentException(
                'La fecha debe tener formato YYYY-MM-DD y ser válida.'
            );
        }

        return $fecha;
    }

    // Validar y solicitar el cambio de estado.
    public function cambiarEstado(int $id, string $estado): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException(
                'El identificador debe ser positivo.'
            );
        }

        if (!in_array($estado, ['confirmada', 'cancelada'], true)) {
            throw new InvalidArgumentException(
                'Solo se puede confirmar o cancelar una reserva.'
            );
        }

        return $this->modelo->cambiarEstado($id, $estado);
    }
}
