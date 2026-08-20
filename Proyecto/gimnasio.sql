-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 26-05-2026 a las 18:27:05
-- Versión del servidor: 8.0.31
-- Versión de PHP: 8.0.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `gimnasio`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asistencia`
--

DROP TABLE IF EXISTS `asistencia`;
CREATE TABLE IF NOT EXISTS `asistencia` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` int UNSIGNED NOT NULL,
  `fecha` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `asistencia`
--

INSERT INTO `asistencia` (`id`, `usuario_id`, `fecha`) VALUES
(1, 4, '2026-06-11'),
(2, 4, '2026-06-12'),
(3, 4, '2026-06-14'),
(4, 4, '2026-06-15'),
(5, 4, '2026-06-16'),
(6, 5, '2026-06-04'),
(7, 5, '2026-06-06'),
(8, 5, '2026-06-08'),
(9, 5, '2026-06-11');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rutinas`
--

DROP TABLE IF EXISTS `rutinas`;
CREATE TABLE IF NOT EXISTS `rutinas` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `nivel` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `rutinas`
--

INSERT INTO `rutinas` (`id`, `nombre`, `descripcion`, `nivel`) VALUES
(1, 'Gluteo', 'Hip Trust 10-12\r\nPeso Muerto \r\nFemoral Acostado', 'Principiante'),
(2, 'Hombro', 'Polea lateral 10b\r\nPolea frontal 2 manos c/cuerda 10\r\nAperturas en banco inclinado (hacia atras) 15', 'Avanzado');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rutinas_asignadas`
--

DROP TABLE IF EXISTS `rutinas_asignadas`;
CREATE TABLE IF NOT EXISTS `rutinas_asignadas` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` int UNSIGNED NOT NULL,
  `rutina_id` int UNSIGNED NOT NULL,
  `asignada_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `rutina_id` (`rutina_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `Nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  `Rol` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  `Correo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  `Clave` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  `Tipo_Suscripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `Nombre`, `Rol`, `Correo`, `Clave`, `Tipo_Suscripcion`) VALUES
(1, 'DONSALAMI', 'admin', 'd0nsalami117@gmail.com', '$2y$10$KJzhKJ57ItpcQQR68rrf5ewe4QAs0s6oL9lVF5Q5QIP5L8WIDXpTu', 'admin'),
(2, 'ragatha', 'usuario', 'ragdoll123@gmail.com', '$2y$10$Fi7vy42AjF9YMVNeftrbMOuNI1MJzwxS4Pu5Xt4TPRS10SsCVlIOe', 'Básico'),
(3, 'Pomni', 'usuario', 'Pomi123@gmail.com', '$2y$10$j.RVvDG0ObM6OaTt/LjNzOnpp.nKkCihIADiSTCcOINyxknUAnl/2', 'Premium'),
(4, 'Kinger', 'admin', 'KingCA@gmail.com', '$2y$10$caZ6sz7X7.T2YqN1FpBR6uFHTGRC43j9pZ5Yv6IouZ3cm5wwfmU06', 'Básico'),
(5, 'Jax', 'usuario', 'JaxT0y@gmail.com', '$2y$10$qiXsYSyiG/LidVh.9qkzAOVQM4yIv.ykyGIRp5XwynniPlpKEjike', 'Básico');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
