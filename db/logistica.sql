-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 10-06-2025 a las 23:00:10
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `logistica`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asignaciones`
--

CREATE TABLE `asignaciones` (
  `id` int(11) NOT NULL,
  `vehiculo_id` int(11) NOT NULL,
  `conductor_id` int(11) NOT NULL,
  `ruta_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `creado_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `asignaciones`
--

INSERT INTO `asignaciones` (`id`, `vehiculo_id`, `conductor_id`, `ruta_id`, `fecha`, `creado_at`) VALUES
(11, 3, 3, 31, '2025-06-11', '2025-06-10 20:11:47');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `conductores`
--

CREATE TABLE `conductores` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `rut` varchar(12) NOT NULL,
  `fecha_nacimiento` date NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `creado_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `conductores`
--

INSERT INTO `conductores` (`id`, `usuario_id`, `rut`, `fecha_nacimiento`, `telefono`, `direccion`, `creado_at`) VALUES
(1, 2, '11.111.111-1', '1985-07-12', '987654321', 'Calle Falsa 123, Coyhaique', '2025-05-23 05:10:30'),
(2, 3, '22.222.222-2', '1990-03-05', '912345678', 'Av. Lago Carrera 456, Coyhaique', '2025-05-23 05:10:30');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rutas`
--

CREATE TABLE `rutas` (
  `id` int(11) NOT NULL,
  `origen` varchar(100) NOT NULL,
  `destino` varchar(100) NOT NULL,
  `horario_salida` time NOT NULL,
  `horario_llegada` time NOT NULL,
  `distancia_km` decimal(8,2) DEFAULT NULL,
  `creado_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `rutas`
--

INSERT INTO `rutas` (`id`, `origen`, `destino`, `horario_salida`, `horario_llegada`, `distancia_km`, `creado_at`) VALUES
(1, 'Coyhaique', 'Puerto Aysén', '08:00:00', '09:15:00', 75.40, '2025-05-23 05:10:30'),
(3, 'Puerto Aysén', 'Puerto Chacabuco', '13:30:00', '14:30:00', 60.50, '2025-05-23 05:10:30'),
(30, 'Lillo', 'Cerro Castillo', '12:06:00', '14:30:00', NULL, '2025-06-10 20:06:57'),
(31, 'Lillo', 'Cerro Castillo', '12:10:00', '00:00:00', NULL, '2025-06-10 20:11:14');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitudes`
--

CREATE TABLE `solicitudes` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `ruta_id` int(11) NOT NULL,
  `fecha_solicitada` date NOT NULL,
  `horario_salida` time DEFAULT NULL,
  `hora_regreso` time DEFAULT NULL,
  `motivo` varchar(50) NOT NULL DEFAULT 'Salida A Terreno',
  `motivo_otro` varchar(255) DEFAULT NULL,
  `adjunto` varchar(255) DEFAULT NULL,
  `estado` enum('pendiente','confirmada','cancelada') NOT NULL DEFAULT 'pendiente',
  `creado_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `solicitudes`
--

INSERT INTO `solicitudes` (`id`, `usuario_id`, `ruta_id`, `fecha_solicitada`, `horario_salida`, `hora_regreso`, `motivo`, `motivo_otro`, `adjunto`, `estado`, `creado_at`, `actualizado_at`) VALUES
(1, 4, 1, '2025-05-22', NULL, NULL, 'Salida A Terreno', NULL, NULL, 'cancelada', '2025-05-23 05:10:30', '2025-05-29 04:35:52'),
(3, 4, 3, '2025-05-24', NULL, NULL, 'Salida A Terreno', NULL, NULL, 'cancelada', '2025-05-23 05:10:30', '2025-05-23 05:10:30'),
(24, 4, 31, '2025-06-11', '12:10:00', '18:11:00', 'Salida A Terreno', NULL, NULL, 'confirmada', '2025-06-10 20:11:14', '2025-06-10 20:11:47');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('admin','conductor','usuario') NOT NULL DEFAULT 'usuario',
  `creado_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `email`, `password`, `rol`, `creado_at`) VALUES
(1, 'Administrador General', 'admin@logistica.local', '$2y$10$abcdefghijklmnopqrstuv', 'admin', '2025-05-23 05:10:30'),
(2, 'Juan Pérez', 'juan.perez@example.com', '$2y$10$abcdefghijklmnopqrstuv', 'conductor', '2025-05-23 05:10:30'),
(3, 'María González', 'maria.gonzalez@example.com', '$2y$10$abcdefghijklmnopqrstuv', 'conductor', '2025-05-23 05:10:30'),
(4, 'Carlos López', 'carlos.lopez@example.com', '$2y$10$abcdefghijklmnopqrstuv', 'usuario', '2025-05-23 05:10:30'),
(5, 'Ana Torres', 'ana.torres@example.com', '$2y$10$abcdefghijklmnopqrstuv', 'usuario', '2025-05-23 05:10:30');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `vehiculos`
--

CREATE TABLE `vehiculos` (
  `id` int(11) NOT NULL,
  `patente` varchar(10) NOT NULL,
  `marca` varchar(50) NOT NULL,
  `modelo` varchar(50) DEFAULT NULL,
  `anio` year(4) DEFAULT NULL,
  `estado` enum('activo','inactivo','en_mantenimiento','ocupado') NOT NULL DEFAULT 'activo',
  `disponibilidad` enum('disponible','reservado','ocupado') NOT NULL DEFAULT 'disponible',
  `creado_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `vehiculos`
--

INSERT INTO `vehiculos` (`id`, `patente`, `marca`, `modelo`, `anio`, `estado`, `disponibilidad`, `creado_at`) VALUES
(3, 'LRV-301', 'Chevrolet', 'D-Max', '2020', 'activo', 'ocupado', '2025-05-23 05:10:30'),
(4, 'MTB-404', 'Mitsubishi', 'L200', '2017', 'en_mantenimiento', 'reservado', '2025-05-23 05:10:30'),
(7, 'TYT-2022sa', 'wwww', '2222', '2001', 'activo', 'ocupado', '2025-05-30 05:25:37');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `asignaciones`
--
ALTER TABLE `asignaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vehiculo_id` (`vehiculo_id`),
  ADD KEY `conductor_id` (`conductor_id`),
  ADD KEY `ruta_id` (`ruta_id`);

--
-- Indices de la tabla `conductores`
--
ALTER TABLE `conductores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rut` (`rut`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `rutas`
--
ALTER TABLE `rutas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `solicitudes`
--
ALTER TABLE `solicitudes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `ruta_id` (`ruta_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `vehiculos`
--
ALTER TABLE `vehiculos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `patente` (`patente`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `asignaciones`
--
ALTER TABLE `asignaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `conductores`
--
ALTER TABLE `conductores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `rutas`
--
ALTER TABLE `rutas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT de la tabla `solicitudes`
--
ALTER TABLE `solicitudes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `vehiculos`
--
ALTER TABLE `vehiculos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `asignaciones`
--
ALTER TABLE `asignaciones`
  ADD CONSTRAINT `asignaciones_ibfk_1` FOREIGN KEY (`vehiculo_id`) REFERENCES `vehiculos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `asignaciones_ibfk_2` FOREIGN KEY (`conductor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `asignaciones_ibfk_3` FOREIGN KEY (`ruta_id`) REFERENCES `rutas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `conductores`
--
ALTER TABLE `conductores`
  ADD CONSTRAINT `conductores_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `solicitudes`
--
ALTER TABLE `solicitudes`
  ADD CONSTRAINT `solicitudes_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `solicitudes_ibfk_2` FOREIGN KEY (`ruta_id`) REFERENCES `rutas` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
