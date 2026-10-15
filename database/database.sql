-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Versión del servidor:         8.0.46 - MySQL Community Server - GPL
-- SO del servidor:              Win64
-- HeidiSQL Versión:             12.6.0.6765
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Volcando estructura de base de datos para misterplan_reservas
DROP DATABASE IF EXISTS `misterplan_reservas`;
CREATE DATABASE IF NOT EXISTS `misterplan_reservas` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `misterplan_reservas`;

-- Volcando estructura para tabla misterplan_reservas.reserva
DROP TABLE IF EXISTS `reserva`;
CREATE TABLE IF NOT EXISTS `reserva` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre_huesped` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_huesped` varchar(254) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_alojamiento` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_entrada` date NOT NULL,
  `fecha_salida` date NOT NULL,
  `estado` enum('pendiente','confirmada','cancelada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `importe` decimal(10,2) NOT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci,
  `fecha_creacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reserva_estado` (`estado`),
  KEY `idx_reserva_fechas` (`fecha_entrada`,`fecha_salida`),
  KEY `idx_reserva_huesped` (`nombre_huesped`),
  CONSTRAINT `chk_reserva_fechas` CHECK ((`fecha_salida` > `fecha_entrada`)),
  CONSTRAINT `chk_reserva_importe` CHECK ((`importe` >= 0))
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla misterplan_reservas.reserva: ~8 rows (aproximadamente)
INSERT INTO `reserva` (`id`, `nombre_huesped`, `email_huesped`, `nombre_alojamiento`, `fecha_entrada`, `fecha_salida`, `estado`, `importe`, `notas`, `fecha_creacion`) VALUES
	(1, 'Ana García', 'ana.garcia@example.com', 'Hotel Plaza Mayor', '2026-11-03', '2026-11-06', 'confirmada', 345.00, 'Llegada aproximada a las 18:00.', '2026-10-10 10:15:00'),
	(2, 'Miguel López', 'miguel.lopez@example.com', 'Apartamento La Muralla', '2026-11-12', '2026-11-16', 'pendiente', 480.50, NULL, '2026-10-11 09:30:00'),
	(3, 'Laura Sánchez', 'laura.sanchez@example.com', 'Casa Rural El Encinar', '2026-10-22', '2026-10-25', 'cancelada', 270.00, 'Viaje familiar.', '2026-10-08 12:00:00'),
	(4, 'David Fernández', 'david.fernandez@example.com', 'Hostal Centro', '2026-12-01', '2026-12-03', 'confirmada', 145.90, NULL, '2026-10-12 16:45:00'),
	(5, 'Elena Martín', 'elena.martin@example.com', 'Apartamento La Muralla', '2026-11-05', '2026-11-08', 'pendiente', 312.00, 'Solicita habitación tranquila.', '2026-10-13 11:20:00'),
	(6, 'Carlos Ruiz', 'carlos.ruiz@example.com', 'Hotel Plaza Mayor', '2026-12-20', '2026-12-24', 'cancelada', 520.00, NULL, '2026-10-14 08:05:00'),
	(7, 'Carlos Pérez', 'carlos@example.com', 'Hotel Cáceres Centro', '2026-12-10', '2026-12-15', 'cancelada', 350.00, 'Reserva de prueba', '2026-10-15 08:15:29'),
	(8, 'María Fernández', 'maria@example.com', 'Hotel Plaza Mayor', '2026-12-20', '2026-12-23', 'cancelada', 240.00, 'Reserva creada mediante la API', '2026-10-15 08:39:14');

-- Volcando estructura para tabla misterplan_reservas.reserva_evento
DROP TABLE IF EXISTS `reserva_evento`;
CREATE TABLE IF NOT EXISTS `reserva_evento` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reserva_id` bigint unsigned NOT NULL,
  `tipo` enum('creacion','confirmacion','cancelacion') COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_evento_reserva_fecha` (`reserva_id`,`fecha_creacion`,`id`),
  CONSTRAINT `fk_evento_reserva` FOREIGN KEY (`reserva_id`) REFERENCES `reserva` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla misterplan_reservas.reserva_evento: ~16 rows (aproximadamente)
INSERT INTO `reserva_evento` (`id`, `reserva_id`, `tipo`, `descripcion`, `fecha_creacion`) VALUES
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

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
