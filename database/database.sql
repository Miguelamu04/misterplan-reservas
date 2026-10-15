-- MisterPlan - Prueba tecnica Desarrollador/a Junior PHP
-- MySQL 8.0+ / MariaDB compatible con CHECK
-- ADVERTENCIA: este script REEMPLAZA las tablas reserva_evento y reserva
-- y sus datos si ya existen en la base de datos misterplan_reservas.
-- No elimina otras tablas ni la base de datos completa.
-- Importar en una base de datos de prueba o tras realizar una copia de seguridad.

CREATE DATABASE IF NOT EXISTS `misterplan_reservas`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `misterplan_reservas`;

-- Eliminar antes la tabla hija para respetar la clave foranea.
DROP TABLE IF EXISTS `reserva_evento`;
DROP TABLE IF EXISTS `reserva`;

-- Tabla principal de reservas.
CREATE TABLE `reserva` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre_huesped` VARCHAR(150) NOT NULL,
    `email_huesped` VARCHAR(254) NOT NULL,
    `nombre_alojamiento` VARCHAR(200) NOT NULL,
    `fecha_entrada` DATE NOT NULL,
    `fecha_salida` DATE NOT NULL,
    `estado` ENUM('pendiente', 'confirmada', 'cancelada') NOT NULL DEFAULT 'pendiente',
    `importe` DECIMAL(10,2) NOT NULL,
    `notas` TEXT NULL,
    `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_reserva_estado` (`estado`),
    KEY `idx_reserva_fechas` (`fecha_entrada`, `fecha_salida`),
    KEY `idx_reserva_huesped` (`nombre_huesped`),
    CONSTRAINT `chk_reserva_fechas` CHECK (`fecha_salida` > `fecha_entrada`),
    CONSTRAINT `chk_reserva_importe` CHECK (`importe` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historial de eventos de cada reserva.
CREATE TABLE `reserva_evento` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reserva_id` BIGINT UNSIGNED NOT NULL,
    `tipo` ENUM('creacion', 'confirmacion', 'cancelacion') NOT NULL,
    `descripcion` VARCHAR(255) NOT NULL,
    `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_evento_reserva_fecha` (`reserva_id`, `fecha_creacion`, `id`),
    CONSTRAINT `fk_evento_reserva`
        FOREIGN KEY (`reserva_id`) REFERENCES `reserva` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos de ejemplo: 8 reservas en diferentes estados.
INSERT INTO `reserva`
    (`id`, `nombre_huesped`, `email_huesped`, `nombre_alojamiento`, `fecha_entrada`, `fecha_salida`, `estado`, `importe`, `notas`, `fecha_creacion`)
VALUES
    (1, 'Ana García', 'ana.garcia@example.com', 'Hotel Plaza Mayor', '2026-11-03', '2026-11-06', 'confirmada', 345.00, 'Llegada aproximada a las 18:00.', '2026-10-10 10:15:00'),
    (2, 'Miguel López', 'miguel.lopez@example.com', 'Apartamento La Muralla', '2026-11-12', '2026-11-16', 'pendiente', 480.50, NULL, '2026-10-11 09:30:00'),
    (3, 'Laura Sánchez', 'laura.sanchez@example.com', 'Casa Rural El Encinar', '2026-10-22', '2026-10-25', 'cancelada', 270.00, 'Viaje familiar.', '2026-10-08 12:00:00'),
    (4, 'David Fernández', 'david.fernandez@example.com', 'Hostal Centro', '2026-12-01', '2026-12-03', 'confirmada', 145.90, NULL, '2026-10-12 16:45:00'),
    (5, 'Elena Martín', 'elena.martin@example.com', 'Apartamento La Muralla', '2026-11-05', '2026-11-08', 'pendiente', 312.00, 'Solicita habitación tranquila.', '2026-10-13 11:20:00'),
    (6, 'Carlos Ruiz', 'carlos.ruiz@example.com', 'Hotel Plaza Mayor', '2026-12-20', '2026-12-24', 'cancelada', 520.00, NULL, '2026-10-14 08:05:00'),
    (7, 'Carlos Pérez', 'carlos@example.com', 'Hotel Cáceres Centro', '2026-12-10', '2026-12-15', 'cancelada', 350.00, 'Reserva de prueba', '2026-10-15 08:15:29'),
    (8, 'María Fernández', 'maria@example.com', 'Hotel Plaza Mayor', '2026-12-20', '2026-12-23', 'cancelada', 240.00, 'Reserva creada mediante la API', '2026-10-15 08:39:14');

-- Historial de ejemplo: 16 eventos, todos vinculados a reservas existentes.
INSERT INTO `reserva_evento`
    (`id`, `reserva_id`, `tipo`, `descripcion`, `fecha_creacion`)
VALUES
    (1, 1, 'creacion', 'Reserva creada en estado pendiente.', '2026-10-10 10:15:00'),
    (2, 1, 'confirmacion', 'Reserva confirmada.', '2026-10-10 10:20:00'),
    (3, 2, 'creacion', 'Reserva creada en estado pendiente.', '2026-10-11 09:30:00'),
    (4, 3, 'creacion', 'Reserva creada en estado pendiente.', '2026-10-08 12:00:00'),
    (5, 3, 'cancelacion', 'Reserva cancelada.', '2026-10-09 14:00:00'),
    (6, 4, 'creacion', 'Reserva creada en estado pendiente.', '2026-10-12 16:45:00'),
    (7, 4, 'confirmacion', 'Reserva confirmada.', '2026-10-12 16:47:00'),
    (8, 5, 'creacion', 'Reserva creada en estado pendiente.', '2026-10-13 11:20:00'),
    (9, 6, 'creacion', 'Reserva creada en estado pendiente.', '2026-10-14 08:05:00'),
    (10, 6, 'cancelacion', 'Reserva cancelada.', '2026-10-14 12:25:00'),
    (11, 7, 'creacion', 'Reserva creada en estado pendiente', '2026-10-15 08:15:29'),
    (12, 7, 'confirmacion', 'Cambio de estado: pendiente a confirmada', '2026-10-15 08:22:22'),
    (13, 7, 'cancelacion', 'Cambio de estado: confirmada a cancelada', '2026-10-15 08:23:11'),
    (14, 8, 'creacion', 'Reserva creada en estado pendiente', '2026-10-15 08:39:14'),
    (15, 8, 'confirmacion', 'Cambio de estado: pendiente a confirmada', '2026-10-15 12:41:19'),
    (16, 8, 'cancelacion', 'Cambio de estado: confirmada a cancelada', '2026-10-15 12:42:20');

-- Verificacion opcional tras importar:
-- SELECT COUNT(*) AS total_reservas FROM reserva;        -- 8
-- SELECT COUNT(*) AS total_eventos FROM reserva_evento;  -- 16
