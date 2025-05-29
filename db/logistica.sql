-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 29-05-2025 a las 10:20:36
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
(1, 1, 2, 1, '2025-05-22', '2025-05-23 05:10:30'),
(2, 3, 3, 2, '2025-05-23', '2025-05-23 05:10:30'),
(3, 1, 2, 3, '2025-05-24', '2025-05-23 05:10:30'),
(5, 2, 3, 7, '2025-06-04', '2025-05-29 06:19:54'),
(6, 4, 2, 14, '2025-06-04', '2025-05-29 06:21:12');

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
(2, 'Coyhaique', 'Puerto Chacabuco', '10:00:00', '12:00:00', 140.00, '2025-05-23 05:10:30'),
(3, 'Puerto Aysén', 'Puerto Chacabuco', '13:30:00', '14:30:00', 60.50, '2025-05-23 05:10:30'),
(4, 'Coyhaique', 'Santiago', '06:30:00', '00:00:00', NULL, '2025-05-23 07:56:48'),
(5, 'Coyhaique', 'Cochrane', '06:40:00', '00:00:00', NULL, '2025-05-29 04:36:52'),
(6, 'Puerto Aysén', 'Cochrane', '05:40:00', '00:00:00', NULL, '2025-05-29 04:38:47'),
(7, 'Puerto Aysén', 'Coyhaique', '06:30:00', '00:00:00', NULL, '2025-05-29 04:43:11'),
(8, 'Puerto Aysén', 'Puerto Chacabuco', '06:30:00', '00:00:00', NULL, '2025-05-29 04:43:40'),
(9, 'Puerto Aysén', 'Santiago', '04:50:00', '00:00:00', NULL, '2025-05-29 04:48:22'),
(10, 'Coyhaique', 'Cochrane', '06:30:00', '00:00:00', NULL, '2025-05-29 05:01:18'),
(11, 'Coyhaique', 'Puerto Chacabuco', '06:30:00', '00:00:00', NULL, '2025-05-29 05:07:22'),
(12, 'Coyhaique', 'Santiago', '12:30:00', '00:00:00', NULL, '2025-05-29 05:41:22'),
(13, 'Coyhaique', 'Puerto Aysén', '13:20:00', '00:00:00', NULL, '2025-05-29 06:04:32'),
(14, 'Coyhaique', 'Cochrane', '12:30:00', '00:00:00', NULL, '2025-05-29 06:20:50');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitudes`
--

CREATE TABLE `solicitudes` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `ruta_id` int(11) NOT NULL,
  `fecha_solicitada` date NOT NULL,
  `estado` enum('pendiente','confirmada','cancelada') NOT NULL DEFAULT 'pendiente',
  `creado_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `solicitudes`
--

INSERT INTO `solicitudes` (`id`, `usuario_id`, `ruta_id`, `fecha_solicitada`, `estado`, `creado_at`, `actualizado_at`) VALUES
(1, 4, 1, '2025-05-22', 'cancelada', '2025-05-23 05:10:30', '2025-05-29 04:35:52'),
(2, 5, 2, '2025-05-23', 'confirmada', '2025-05-23 05:10:30', '2025-05-23 05:10:30'),
(3, 4, 3, '2025-05-24', 'cancelada', '2025-05-23 05:10:30', '2025-05-23 05:10:30'),
(4, 4, 6, '2025-06-02', 'cancelada', '2025-05-29 04:41:55', '2025-05-29 04:42:20'),
(5, 4, 8, '2025-06-05', 'cancelada', '2025-05-29 04:43:40', '2025-05-29 04:44:30'),
(6, 4, 9, '2025-06-01', 'cancelada', '2025-05-29 04:48:22', '2025-05-29 04:48:27'),
(7, 4, 10, '2025-06-03', 'cancelada', '2025-05-29 05:01:18', '2025-05-29 05:01:38'),
(8, 4, 11, '2025-06-08', 'confirmada', '2025-05-29 05:07:22', '2025-05-29 05:29:03'),
(9, 4, 11, '2025-05-31', 'cancelada', '2025-05-29 05:30:00', '2025-05-29 05:30:17'),
(10, 5, 12, '2025-06-12', 'confirmada', '2025-05-29 05:41:22', '2025-05-29 05:55:29'),
(11, 5, 13, '2025-06-04', 'confirmada', '2025-05-29 06:04:32', '2025-05-29 06:04:45'),
(12, 5, 7, '2025-06-04', 'confirmada', '2025-05-29 06:19:42', '2025-05-29 06:19:54'),
(13, 5, 14, '2025-06-04', 'confirmada', '2025-05-29 06:20:50', '2025-05-29 06:21:12');

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
(1, 'CPX-101', 'Toyota', 'Hilux', '2018', 'activo', 'disponible', '2025-05-23 05:10:30'),
(2, 'DSQ-202', 'Ford', 'Ranger', '2020', 'en_mantenimiento', 'disponible', '2025-05-23 05:10:30'),
(3, 'LRV-303', 'Chevrolet', 'D-Max', '2019', 'activo', 'ocupado', '2025-05-23 05:10:30'),
(4, 'MTB-404', 'Mitsubishi', 'L200', '2017', 'inactivo', 'disponible', '2025-05-23 05:10:30');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `conductores`
--
ALTER TABLE `conductores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `rutas`
--
ALTER TABLE `rutas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `solicitudes`
--
ALTER TABLE `solicitudes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `vehiculos`
--
ALTER TABLE `vehiculos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
