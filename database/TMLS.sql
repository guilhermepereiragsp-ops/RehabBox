-- --------------------------------------------------------
-- Anfitrião:                    127.0.0.1
-- Versão do servidor:           8.0.30 - MySQL Community Server - GPL
-- SO do servidor:               Win64
-- HeidiSQL Versão:              12.1.0.6537
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- A despejar estrutura da base de dados para tmts
CREATE DATABASE IF NOT EXISTS `tmts` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `tmts`;

-- A despejar estrutura para tabela tmts.blockedips
CREATE TABLE IF NOT EXISTS `blockedips` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `blocked_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `blocked_until` datetime NOT NULL,
  `reason` varchar(255) DEFAULT 'Demasiadas tentativas falhadas',
  `blocked_by` enum('auto','admin') DEFAULT 'auto',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ip` (`ip_address`),
  KEY `idx_until` (`blocked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- A despejar dados para tabela tmts.blockedips: ~0 rows (aproximadamente)

-- A despejar estrutura para tabela tmts.colorgame
CREATE TABLE IF NOT EXISTS `colorgame` (
  `id` int NOT NULL AUTO_INCREMENT,
  `game_id` int NOT NULL,
  `ronda` int NOT NULL,
  `cor_acendeu` varchar(10) NOT NULL,
  `cor_clicada` varchar(10) DEFAULT NULL,
  `acertou` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `game_id` (`game_id`),
  CONSTRAINT `colorgame_ibfk_1` FOREIGN KEY (`game_id`) REFERENCES `game` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- A despejar dados para tabela tmts.colorgame: ~72 rows (aproximadamente)
INSERT INTO `colorgame` (`id`, `game_id`, `ronda`, `cor_acendeu`, `cor_clicada`, `acertou`) VALUES
	(1, 1, 1, 'AZUL', 'VERDE', 0),
	(2, 1, 2, 'AZUL', 'VERDE', 0),
	(3, 1, 3, 'VERMELHO', 'VERMELHO', 1),
	(4, 1, 4, 'VERDE', 'AZUL', 1),
	(5, 1, 5, 'AZUL', 'VERMELHO', 0),
	(6, 1, 6, 'VERDE', 'VERDE', 1),
	(7, 1, 7, 'VERMELHO', 'VERDE', 0),
	(8, 1, 8, 'AZUL', 'VERMELHO', 0),
	(9, 4, 1, 'VERDE', 'VERDE', 1),
	(10, 4, 2, 'AZUL', 'VERMELHO', 0),
	(11, 4, 3, 'VERMELHO', 'VERMELHO', 1),
	(12, 4, 4, 'VERDE', 'VERDE', 1),
	(13, 4, 5, 'AZUL', 'VERMELHO', 0),
	(14, 4, 6, 'VERMELHO', 'VERMELHO', 1),
	(15, 4, 7, 'VERDE', 'VERDE', 1),
	(16, 4, 8, 'AZUL', 'VERMELHO', 0),
	(17, 10, 1, 'AZUL', 'AZUL', 1),
	(18, 10, 2, 'VERMELHO', 'VERMELHO', 1),
	(19, 10, 3, 'VERDE', 'VERDE', 1),
	(20, 10, 4, 'VERDE', 'VERDE', 1),
	(21, 10, 5, 'VERDE', 'VERDE', 1),
	(22, 10, 6, 'VERDE', 'VERDE', 1),
	(23, 10, 7, 'VERDE', 'VERDE', 1),
	(24, 10, 8, 'VERMELHO', 'AZUL', 0),
	(25, 13, 1, 'VERMELHO', 'VERMELHO', 1),
	(26, 13, 2, 'VERMELHO', 'AZUL', 0),
	(27, 13, 3, 'VERDE', 'VERMELHO', 0),
	(28, 13, 4, 'VERDE', 'AZUL', 0),
	(29, 13, 5, 'VERDE', 'VERMELHO', 0),
	(30, 13, 6, 'VERMELHO', 'AZUL', 0),
	(31, 13, 7, 'VERMELHO', 'VERMELHO', 1),
	(32, 13, 8, 'VERMELHO', 'VERMELHO', 1),
	(33, 16, 1, 'AZUL', 'AZUL', 1),
	(34, 16, 2, 'AZUL', 'VERMELHO', 0),
	(35, 16, 3, 'AZUL', 'VERDE', 0),
	(36, 16, 4, 'VERMELHO', 'VERMELHO', 1),
	(37, 16, 5, 'VERDE', 'AZUL', 0),
	(38, 16, 6, 'AZUL', 'AZUL', 1),
	(39, 16, 7, 'VERDE', 'VERMELHO', 0),
	(40, 16, 8, 'AZUL', 'VERMELHO', 0),
	(41, 19, 1, 'AZUL', 'AZUL', 1),
	(42, 19, 2, 'AZUL', 'VERMELHO', 0),
	(43, 19, 3, 'VERDE', 'VERDE', 1),
	(44, 19, 4, 'VERDE', 'VERDE', 1),
	(45, 19, 5, 'VERMELHO', 'AZUL', 0),
	(46, 19, 6, 'VERDE', 'VERDE', 1),
	(47, 19, 7, 'VERDE', 'AZUL', 0),
	(48, 19, 8, 'VERMELHO', 'VERMELHO', 1),
	(49, 22, 1, 'VERMELHO', 'VERMELHO', 1),
	(50, 22, 2, 'VERDE', 'AZUL', 0),
	(51, 22, 3, 'AZUL', 'AZUL', 1),
	(52, 22, 4, 'VERMELHO', 'VERMELHO', 1),
	(53, 22, 5, 'AZUL', 'AZUL', 1),
	(54, 22, 6, 'VERDE', 'VERDE', 1),
	(55, 22, 7, 'VERMELHO', 'VERMELHO', 1),
	(56, 22, 8, 'VERDE', 'VERDE', 1),
	(57, 25, 1, 'VERMELHO', 'VERMELHO', 1),
	(58, 25, 2, 'VERMELHO', 'NENHUM', 0),
	(59, 25, 3, 'AZUL', 'AZUL', 1),
	(60, 25, 4, 'AZUL', 'VERMELHO', 0),
	(61, 25, 5, 'VERDE', 'VERDE', 1),
	(62, 25, 6, 'VERDE', 'AZUL', 0),
	(63, 25, 7, 'AZUL', 'AZUL', 1),
	(64, 25, 8, 'VERMELHO', 'VERMELHO', 1),
	(65, 28, 1, 'AZUL', 'AZUL', 1),
	(66, 28, 2, 'VERMELHO', 'VERMELHO', 1),
	(67, 28, 3, 'VERDE', 'VERDE', 1),
	(68, 28, 4, 'VERMELHO', 'AZUL', 0),
	(69, 28, 5, 'VERMELHO', 'VERMELHO', 1),
	(70, 28, 6, 'AZUL', 'AZUL', 1),
	(71, 28, 7, 'VERDE', 'AZUL', 0),
	(72, 28, 8, 'VERDE', 'VERDE', 1);

-- A despejar estrutura para tabela tmts.consentrevocationrequest
CREATE TABLE IF NOT EXISTS `consentrevocationrequest` (
  `id` int NOT NULL AUTO_INCREMENT,
  `patient_id` int NOT NULL,
  `request_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `reason` text,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `handled_by` int DEFAULT NULL,
  `handled_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `handled_by` (`handled_by`),
  CONSTRAINT `consentrevocationrequest_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`id`) ON DELETE CASCADE,
  CONSTRAINT `consentrevocationrequest_ibfk_2` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- A despejar dados para tabela tmts.consentrevocationrequest: ~1 rows (aproximadamente)
INSERT INTO `consentrevocationrequest` (`id`, `patient_id`, `request_date`, `reason`, `status`, `handled_by`, `handled_at`) VALUES
	(1, 3, '2026-06-16 14:57:48', NULL, 'rejected', 1, '2026-06-16 16:47:58');

-- A despejar estrutura para tabela tmts.doctor
CREATE TABLE IF NOT EXISTS `doctor` (
  `id` int NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `doctor_ibfk_1` FOREIGN KEY (`id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- A despejar dados para tabela tmts.doctor: ~1 rows (aproximadamente)
INSERT INTO `doctor` (`id`) VALUES
	(2);

-- A despejar estrutura para tabela tmts.evaluation
CREATE TABLE IF NOT EXISTS `evaluation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `patient_id` int NOT NULL,
  `doctor_id` int NOT NULL,
  `ev_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `description` text,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  CONSTRAINT `evaluation_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`id`) ON DELETE CASCADE,
  CONSTRAINT `evaluation_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctor` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- A despejar dados para tabela tmts.evaluation: ~4 rows (aproximadamente)
INSERT INTO `evaluation` (`id`, `patient_id`, `doctor_id`, `ev_date`, `description`) VALUES
	(1, 3, 2, '2026-06-16 00:00:00', 'Avaliação inicial da paciente'),
	(2, 3, 2, '2026-06-16 10:28:33', 'Manter nível de dificuldade. Aumentar dificuldade na próxima sessão. Necessita mais prática. Excelente desempenho.'),
	(3, 3, 2, '2026-06-16 11:56:58', 'Necessita mais prática. Sinais de fadiga observados. Manter nível de dificuldade.'),
	(6, 3, 2, '2026-06-16 16:43:27', 'Aumentar dificuldade na próxima sessão. Necessita mais prática. Manter nível de dificuldade. Boa evolução.');

-- A despejar estrutura para tabela tmts.game
CREATE TABLE IF NOT EXISTS `game` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `difficulty` int DEFAULT NULL,
  `final_score` int DEFAULT NULL,
  `game_group_id` int DEFAULT NULL,
  `patient_id` int NOT NULL,
  `duration_minutes` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `fk_game_session` (`game_group_id`),
  CONSTRAINT `fk_game_session` FOREIGN KEY (`game_group_id`) REFERENCES `gamesession` (`id`) ON DELETE CASCADE,
  CONSTRAINT `game_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- A despejar dados para tabela tmts.game: ~30 rows (aproximadamente)
INSERT INTO `game` (`id`, `name`, `date`, `difficulty`, `final_score`, `game_group_id`, `patient_id`, `duration_minutes`) VALUES
	(1, 'Botoes', '2026-06-11', 1, 62, 1, 3, 1),
	(2, 'Obstaculos', '2026-06-11', 1, 75, 1, 3, 1),
	(3, 'Touch', '2026-06-11', 1, 50, 1, 3, 1),
	(4, 'Botoes', '2026-06-11', 1, 50, 2, 3, 1),
	(5, 'Obstaculos', '2026-06-11', 1, 57, 2, 3, 1),
	(6, 'Touch', '2026-06-11', 1, 50, 2, 3, 1),
	(7, 'Botoes', '2026-06-16', 1, 50, 8, 3, 1),
	(8, 'Obstaculos', '2026-06-16', 1, 57, 8, 3, 1),
	(9, 'Touch', '2026-06-16', 1, 42, 8, 3, 1),
	(10, 'Botoes', '2026-06-16', 1, 87, 10, 3, 1),
	(11, 'Obstaculos', '2026-06-16', 1, 100, 10, 3, 1),
	(12, 'Touch', '2026-06-16', 1, 85, 10, 3, 1),
	(13, 'Botoes', '2026-06-16', 1, 37, 12, 3, 1),
	(14, 'Obstaculos', '2026-06-16', 1, 88, 12, 3, 1),
	(15, 'Touch', '2026-06-16', 1, 85, 12, 3, 1),
	(16, 'Botoes', '2026-06-16', 1, 37, 13, 3, 1),
	(17, 'Obstaculos', '2026-06-16', 1, 57, 13, 3, 1),
	(18, 'Touch', '2026-06-16', 1, 33, 13, 3, 1),
	(19, 'Botoes', '2026-06-16', 1, 62, 14, 3, 1),
	(20, 'Obstaculos', '2026-06-16', 1, 42, 14, 3, 1),
	(21, 'Touch', '2026-06-16', 1, 71, 14, 3, 1),
	(22, 'Botoes', '2026-06-16', 1, 87, 15, 3, 1),
	(23, 'Obstaculos', '2026-06-16', 1, 57, 15, 3, 1),
	(24, 'Touch', '2026-06-16', 1, 83, 15, 3, 1),
	(25, 'Botoes', '2026-06-16', 3, 62, 16, 3, 1),
	(26, 'Obstaculos', '2026-06-16', 3, 22, 16, 3, 1),
	(27, 'Touch', '2026-06-16', 3, 80, 16, 3, 1),
	(28, 'Botoes', '2026-06-16', 2, 75, 17, 3, 1),
	(29, 'Obstaculos', '2026-06-16', 2, 75, 17, 3, 1),
	(30, 'Touch', '2026-06-16', 2, 75, 17, 3, 1);

-- A despejar estrutura para tabela tmts.gamesession
CREATE TABLE IF NOT EXISTS `gamesession` (
  `id` int NOT NULL AUTO_INCREMENT,
  `patient_id` int NOT NULL,
  `session_date` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_gamesession_patient` (`patient_id`),
  CONSTRAINT `fk_gamesession_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- A despejar dados para tabela tmts.gamesession: ~17 rows (aproximadamente)
INSERT INTO `gamesession` (`id`, `patient_id`, `session_date`) VALUES
	(1, 3, '2026-06-11 16:17:06'),
	(2, 3, '2026-06-11 16:19:43'),
	(3, 3, '2026-06-11 16:22:28'),
	(4, 3, '2026-06-11 16:23:17'),
	(5, 3, '2026-06-11 16:27:37'),
	(6, 3, '2026-06-11 16:29:32'),
	(7, 3, '2026-06-11 16:34:42'),
	(8, 3, '2026-06-16 15:36:01'),
	(9, 3, '2026-06-16 15:39:33'),
	(10, 3, '2026-06-16 15:53:06'),
	(11, 3, '2026-06-16 15:56:39'),
	(12, 3, '2026-06-16 16:02:56'),
	(13, 3, '2026-06-16 16:06:45'),
	(14, 3, '2026-06-16 16:11:14'),
	(15, 3, '2026-06-16 16:15:57'),
	(16, 3, '2026-06-16 16:21:38'),
	(17, 3, '2026-06-16 16:28:25');

-- A despejar estrutura para tabela tmts.loginlogs
CREATE TABLE IF NOT EXISTS `loginlogs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `email_attempted` varchar(100) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text,
  `success` tinyint(1) NOT NULL DEFAULT '0',
  `failure_reason` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_ip_success_date` (`ip_address`,`success`,`created_at`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `loginlogs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- A despejar dados para tabela tmts.loginlogs: ~32 rows (aproximadamente)
INSERT INTO `loginlogs` (`id`, `user_id`, `email_attempted`, `ip_address`, `user_agent`, `success`, `failure_reason`, `created_at`) VALUES
	(1, 1, 'admin@tmts.pt', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 0, 'Password incorrecta', '2026-06-16 09:47:08'),
	(2, 3, 'maria.costa@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 0, 'Password incorrecta', '2026-06-16 09:48:15'),
	(3, 3, 'maria.costa@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, NULL, '2026-06-16 09:48:52'),
	(4, 3, 'maria.costa@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, NULL, '2026-06-16 09:49:17'),
	(5, 3, 'maria.costa@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, NULL, '2026-06-16 09:56:42'),
	(6, 3, 'maria.costa@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, NULL, '2026-06-16 10:05:20'),
	(7, 2, 'joao.silva@tmts.pt', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 0, 'Password incorrecta', '2026-06-16 10:26:45'),
	(8, 2, 'joao.silva@tmts.pt', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, NULL, '2026-06-16 10:28:10'),
	(9, 3, 'maria.costa@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, NULL, '2026-06-16 10:29:41'),
	(10, 3, 'maria.costa@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, NULL, '2026-06-16 10:30:11'),
	(11, 3, 'maria.costa@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, NULL, '2026-06-16 10:30:34'),
	(12, 3, 'maria.costa@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, NULL, '2026-06-16 10:38:59'),
	(13, 3, 'maria.costa@gmail.com', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, NULL, '2026-06-16 11:41:11'),
	(14, 3, 'maria.costa@gmail.com', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, NULL, '2026-06-16 11:41:44'),
	(15, 3, 'maria.costa@gmail.com', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, NULL, '2026-06-16 11:55:28'),
	(16, 2, 'joao.silva@tmts.pt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, NULL, '2026-06-16 11:56:37'),
	(17, 3, 'maria.costa@gmail.com', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, NULL, '2026-06-16 11:57:24'),
	(18, 3, 'maria.costa@gmail.com', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, NULL, '2026-06-16 14:24:57'),
	(19, 1, 'admin@tmts.pt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, NULL, '2026-06-16 14:25:48'),
	(20, 1, 'admin@tmts.pt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, NULL, '2026-06-16 14:34:04'),
	(21, 3, 'maria.costa@gmail.com', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, NULL, '2026-06-16 14:37:46'),
	(22, 1, 'admin@tmts.pt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, NULL, '2026-06-16 14:42:04'),
	(23, 3, 'maria.costa@gmail.com', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, NULL, '2026-06-16 14:43:07'),
	(24, 1, 'admin@tmts.pt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, NULL, '2026-06-16 14:59:01'),
	(25, 2, 'joao.silva@tmts.pt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, NULL, '2026-06-16 15:41:01'),
	(26, 3, 'maria.costa@gmail.com', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, NULL, '2026-06-16 16:46:08'),
	(27, 1, 'admin@tmts.pt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, NULL, '2026-06-16 16:47:39'),
	(28, NULL, 'pereirao@gmail.com', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 0, 'Password incorrecta', '2026-06-16 16:50:28'),
	(29, 1, 'admin@tmts.pt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, NULL, '2026-06-16 16:51:18'),
	(30, NULL, 'pereirao@gmail.com', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, NULL, '2026-06-16 16:57:10'),
	(31, 1, 'admin@tmts.pt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, NULL, '2026-06-16 16:58:11'),
	(32, 1, 'admin@tmts.pt', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, NULL, '2026-06-17 10:47:09');

-- A despejar estrutura para tabela tmts.mistakes
CREATE TABLE IF NOT EXISTS `mistakes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `timestamp` datetime DEFAULT CURRENT_TIMESTAMP,
  `description` text,
  `game_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `game_id` (`game_id`),
  CONSTRAINT `mistakes_ibfk_1` FOREIGN KEY (`game_id`) REFERENCES `game` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=85 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- A despejar dados para tabela tmts.mistakes: ~84 rows (aproximadamente)
INSERT INTO `mistakes` (`id`, `timestamp`, `description`, `game_id`) VALUES
	(1, '2026-06-11 16:17:29', 'Ronda 4 Erro Cor', 1),
	(2, '2026-06-11 16:17:34', 'Ronda 5 Erro Cor', 1),
	(3, '2026-06-11 16:17:47', 'Ronda 7 Timeout', 1),
	(4, '2026-06-11 16:18:23', 'Colisao', 2),
	(5, '2026-06-11 16:18:29', 'Colisao', 2),
	(6, '2026-06-11 16:18:49', 'Muito Cedo - Batida 1', 3),
	(7, '2026-06-11 16:18:57', 'Muito Cedo - Batida 3', 3),
	(8, '2026-06-11 16:19:17', 'Timeout - Batida 6', 3),
	(9, '2026-06-11 16:35:02', 'Ronda 2 Erro Cor', 4),
	(10, '2026-06-11 16:35:23', 'Ronda 5 Erro Cor', 4),
	(11, '2026-06-11 16:35:32', 'Ronda 6 Erro Cor', 4),
	(12, '2026-06-11 16:35:40', 'Ronda 7 Erro Cor', 4),
	(13, '2026-06-11 16:36:14', 'Colisao', 5),
	(14, '2026-06-11 16:36:20', 'Colisao', 5),
	(15, '2026-06-11 16:36:26', 'Colisao', 5),
	(16, '2026-06-11 16:36:54', 'Muito Cedo - Batida 1', 6),
	(17, '2026-06-11 16:36:57', 'Muito Cedo - Batida 2', 6),
	(18, '2026-06-11 16:37:07', 'Muito Cedo - Batida 4', 6),
	(19, '2026-06-11 16:37:19', 'Muito Cedo - Batida 7', 6),
	(20, '2026-06-16 15:36:35', 'Ronda 5 Erro Cor', 7),
	(21, '2026-06-16 15:36:43', 'Ronda 6 Erro Cor', 7),
	(22, '2026-06-16 15:36:51', 'Ronda 7 Erro Cor', 7),
	(23, '2026-06-16 15:36:59', 'Ronda 8 Erro Cor', 7),
	(24, '2026-06-16 15:37:59', 'Colisao', 8),
	(25, '2026-06-16 15:38:12', 'Colisao', 8),
	(26, '2026-06-16 15:38:21', 'Colisao', 8),
	(27, '2026-06-16 15:38:38', 'Muito Cedo - Batida 1', 9),
	(28, '2026-06-16 15:38:43', 'Muito Cedo - Batida 2', 9),
	(29, '2026-06-16 15:38:51', 'Muito Cedo - Batida 4', 9),
	(30, '2026-06-16 15:39:08', 'Timeout - Batida 7', 9),
	(31, '2026-06-16 15:54:40', 'Ronda 8 Erro Cor', 10),
	(32, '2026-06-16 15:55:47', 'Muito Cedo - Batida 1', 12),
	(33, '2026-06-16 16:03:20', 'Ronda 2 Erro Cor', 13),
	(34, '2026-06-16 16:03:28', 'Ronda 3 Erro Cor', 13),
	(35, '2026-06-16 16:03:38', 'Ronda 4 Erro Cor', 13),
	(36, '2026-06-16 16:03:47', 'Ronda 5 Erro Cor', 13),
	(37, '2026-06-16 16:03:54', 'Ronda 6 Erro Cor', 13),
	(38, '2026-06-16 16:04:39', 'Colisao', 14),
	(39, '2026-06-16 16:05:13', 'Muito Cedo - Batida 1', 15),
	(40, '2026-06-16 16:07:10', 'Ronda 2 Erro Cor', 16),
	(41, '2026-06-16 16:07:18', 'Ronda 3 Erro Cor', 16),
	(42, '2026-06-16 16:07:32', 'Ronda 5 Erro Cor', 16),
	(43, '2026-06-16 16:07:48', 'Ronda 7 Erro Cor', 16),
	(44, '2026-06-16 16:07:55', 'Ronda 8 Erro Cor', 16),
	(45, '2026-06-16 16:08:31', 'Colisao', 17),
	(46, '2026-06-16 16:08:37', 'Colisao', 17),
	(47, '2026-06-16 16:08:42', 'Colisao', 17),
	(48, '2026-06-16 16:09:00', 'Muito Cedo - Batida 1', 18),
	(49, '2026-06-16 16:09:05', 'Muito Cedo - Batida 2', 18),
	(50, '2026-06-16 16:09:24', 'Timeout - Batida 5', 18),
	(51, '2026-06-16 16:09:27', 'Muito Cedo - Batida 6', 18),
	(52, '2026-06-16 16:11:33', 'Ronda 2 Erro Cor', 19),
	(53, '2026-06-16 16:11:52', 'Ronda 5 Erro Cor', 19),
	(54, '2026-06-16 16:12:07', 'Ronda 7 Erro Cor', 19),
	(55, '2026-06-16 16:12:36', 'Colisao', 20),
	(56, '2026-06-16 16:12:45', 'Colisao', 20),
	(57, '2026-06-16 16:12:51', 'Colisao', 20),
	(58, '2026-06-16 16:13:03', 'Colisao', 20),
	(59, '2026-06-16 16:13:24', 'Muito Cedo - Batida 1', 21),
	(60, '2026-06-16 16:13:28', 'Muito Cedo - Batida 2', 21),
	(61, '2026-06-16 16:16:17', 'Ronda 2 Erro Cor', 22),
	(62, '2026-06-16 16:18:11', 'Colisao', 23),
	(63, '2026-06-16 16:18:17', 'Colisao', 23),
	(64, '2026-06-16 16:18:27', 'Colisao', 23),
	(65, '2026-06-16 16:18:48', 'Muito Cedo - Batida 1', 24),
	(66, '2026-06-16 16:21:58', 'Ronda 2 Timeout', 25),
	(67, '2026-06-16 16:22:11', 'Ronda 4 Erro Cor', 25),
	(68, '2026-06-16 16:22:23', 'Ronda 6 Erro Cor', 25),
	(69, '2026-06-16 16:22:52', 'Colisao', 26),
	(70, '2026-06-16 16:22:55', 'Colisao', 26),
	(71, '2026-06-16 16:22:59', 'Colisao', 26),
	(72, '2026-06-16 16:23:04', 'Colisao', 26),
	(73, '2026-06-16 16:23:08', 'Colisao', 26),
	(74, '2026-06-16 16:23:14', 'Colisao', 26),
	(75, '2026-06-16 16:23:18', 'Colisao', 26),
	(76, '2026-06-16 16:23:35', 'Muito Cedo - Batida 1', 27),
	(77, '2026-06-16 16:23:57', 'Muito Cedo - Batida 8', 27),
	(78, '2026-06-16 16:29:00', 'Ronda 4 Erro Cor', 28),
	(79, '2026-06-16 16:29:18', 'Ronda 7 Erro Cor', 28),
	(80, '2026-06-16 16:29:49', 'Colisao', 29),
	(81, '2026-06-16 16:29:59', 'Colisao', 29),
	(82, '2026-06-16 16:30:12', 'Colisao', 29),
	(83, '2026-06-16 16:30:45', 'Muito Cedo - Batida 3', 30),
	(84, '2026-06-16 16:31:05', 'Muito Cedo - Batida 8', 30);

-- A despejar estrutura para tabela tmts.patient
CREATE TABLE IF NOT EXISTS `patient` (
  `id` int NOT NULL,
  `disease_status` varchar(100) DEFAULT NULL,
  `difficulty` int DEFAULT '1',
  `doctor_id` int DEFAULT NULL,
  `last_eval_check` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `doctor_id` (`doctor_id`),
  CONSTRAINT `patient_ibfk_1` FOREIGN KEY (`id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `patient_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctor` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- A despejar dados para tabela tmts.patient: ~2 rows (aproximadamente)
INSERT INTO `patient` (`id`, `disease_status`, `difficulty`, `doctor_id`, `last_eval_check`) VALUES
	(3, 'Parkinson estadio inicial', 1, 2, '2026-06-16 16:46:19'),
	(4, 'Parkinson estadio inicial', 2, 2, NULL);

-- A despejar estrutura para tabela tmts.pendingregistrations
CREATE TABLE IF NOT EXISTS `pendingregistrations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `requested_role` enum('patient','doctor') NOT NULL,
  `justification` text,
  `submitted_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `pendingregistrations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- A despejar dados para tabela tmts.pendingregistrations: ~0 rows (aproximadamente)

-- A despejar estrutura para tabela tmts.users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','doctor','patient','pending') NOT NULL DEFAULT 'pending',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- A despejar dados para tabela tmts.users: ~4 rows (aproximadamente)
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `is_active`) VALUES
	(1, 'Administrador', 'admin@tmts.pt', '$2y$10$zsB8aHSbUi8T3DcwICjMe.RdxC30JiJr63RFACoI2a0B90mtYTRce', 'admin', 1),
	(2, 'Dr. João Silva', 'joao.silva@tmts.pt', '$2y$10$ISSL7WkpC0gsT1aupK7LAu0vQRBFyha2WeuNcXdel.8oOz6TZMoF2', 'doctor', 1),
	(3, 'Maria Fernanda Costa', 'maria.costa@gmail.com', '$2y$10$6YSygXF4p7xeQNKrtFdZaehIFiOMAPJaXOr7J.ix.ibeuSm4BVffu', 'patient', 1),
	(4, 'Rui Pinto', 'rui.pinto@gmail.com', '$2y$10$rcqYh/ggKLbmkQtDhKECLOqzvbTGEbgdehLlgVorp8RjSGBmsOnUO', 'patient', 1);

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
