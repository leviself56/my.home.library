-- Adminer 5.4.1 MariaDB 11.8.3-MariaDB-0+deb13u1 from Debian dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `books` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dateAdded` datetime NOT NULL DEFAULT current_timestamp(),
  `isbn` text DEFAULT NULL,
  `inLibrary` int(11) DEFAULT NULL,
  `borrowedBy` text DEFAULT NULL,
  `borrowedByUserId` int(11) DEFAULT NULL,
  `returnBy` date DEFAULT NULL,
  `title` text DEFAULT NULL,
  `author` text DEFAULT NULL,
  `dateCreated_file_ids` text DEFAULT NULL,
  `condition` varchar(32) NOT NULL DEFAULT 'Good',
  PRIMARY KEY (`id`),
  KEY `idx_books_borrowedByUserId` (`borrowedByUserId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `books` (`id`, `dateAdded`, `isbn`, `inLibrary`, `borrowedBy`, `borrowedByUserId`, `returnBy`, `title`, `author`, `dateCreated_file_ids`, `condition`) VALUES
(1,	'2025-12-24 15:49:00',	'9781905574001',	1,	NULL,	NULL,	NULL,	'The Imitation of Christ',	'Thomas A Kempis',	'[1]',	'Good'),
(2,	'2025-12-24 15:49:01',	'91780895553874',	1,	NULL,	NULL,	NULL,	'The Catholic Controversy - A Defense Of The Faith',	'St. Francis De Sales',	'[2]',	'Good'),
(3,	'2025-12-24 15:49:02',	'9781644139400',	1,	NULL,	NULL,	NULL,	'Credo - Compendium of the Catholic Faith',	' Bishop Athanasius Schneider',	'[3]',	'Good');

CREATE TABLE IF NOT EXISTS `checkOuts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bookID` int(11) NOT NULL,
  `outComment` text DEFAULT NULL,
  `inComment` text DEFAULT NULL,
  `receivedBy` text DEFAULT NULL,
  `receivedByUserId` int(11) DEFAULT NULL,
  `receivedDateTime` datetime DEFAULT NULL,
  `in_file_ids` text DEFAULT NULL,
  `created_datetime` datetime NOT NULL DEFAULT current_timestamp(),
  `checkedOutBy` text DEFAULT NULL,
  `checkedOutByUserId` int(11) DEFAULT NULL,
  `dueDate` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_checkOuts_checkedOutByUserId` (`checkedOutByUserId`),
  KEY `idx_checkOuts_receivedByUserId` (`receivedByUserId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_datetime` datetime NOT NULL DEFAULT current_timestamp(),
  `filename` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `files` (`id`, `created_datetime`, `filename`) VALUES
(1,	'2025-12-24 15:58:00',	'file_20251223_041320_e652974f.jpg'),
(2,	'2025-12-24 15:58:01',	'file_20251223_043531_a40fb82d.jpg'),
(3,	'2025-12-24 15:58:02',	'file_8137iL-5-2L._SL1500_.jpg');

CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(64) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT IGNORE INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_datetime`) VALUES
(1, 'swVersion', '1.0', '2025-12-23 20:48:35'),
(2, 'swName', 'my.home.library', '2025-12-23 21:05:07'),
(3, 'swURL', 'https://github.com/leviself56/my.home.library', '2025-12-23 21:35:34');


CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('librarian','user') NOT NULL DEFAULT 'user',
  `created_datetime` datetime NOT NULL DEFAULT current_timestamp(),
  `username` varchar(64) NOT NULL,
  `name` varchar(255) NOT NULL,
  `password` char(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 2025-12-24 05:54:28 UTC
