-- Hassan Spot Trading Risk Manager Database Schema (v2.5)
CREATE DATABASE IF NOT EXISTS `hassan_risk_manager` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hassan_risk_manager`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `trades` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `coin_name` VARCHAR(25) NOT NULL,
  `account_balance` DECIMAL(15,2) NOT NULL,
  `risk_percent` DECIMAL(5,2) NOT NULL,
  `entry_price` DECIMAL(18,8) NOT NULL,
  `stop_loss_price` DECIMAL(18,8) NOT NULL,
  `take_profit_price` DECIMAL(18,8) NOT NULL,
  `tp1_price` DECIMAL(18,8) NULL,
  `tp2_price` DECIMAL(18,8) NULL,
  `partial_close_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `remaining_close_percent` DECIMAL(5,2) NOT NULL DEFAULT 100.00,
  `tp1_profit` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `tp2_profit` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `risk_amount` DECIMAL(15,2) NOT NULL,
  `position_size` DECIMAL(24,8) NOT NULL,
  `rr_ratio` DECIMAL(10,2) NOT NULL,
  `potential_profit` DECIMAL(15,2) NOT NULL,
  `potential_loss` DECIMAL(15,2) NOT NULL,
  `status` ENUM('Win', 'Loss', 'Running', 'Partially Closed') NOT NULL DEFAULT 'Running',
  `strategy` VARCHAR(100) DEFAULT NULL,
  `setup_type` VARCHAR(100) DEFAULT NULL,
  `session` ENUM('Asia','London','New York') NOT NULL DEFAULT 'Asia',
  `pre_trade_emotion` ENUM('Confident','Fearful','Neutral','Overconfident') DEFAULT NULL,
  `post_trade_emotion` ENUM('Satisfied','Regret','Frustrated','Disciplined') DEFAULT NULL,
  `trade_notes` TEXT,
  `trade_date` DATE NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_trades_user_date` (`user_id`, `trade_date`),
  KEY `idx_trades_coin` (`coin_name`),
  KEY `idx_trades_strategy` (`user_id`, `strategy`),
  KEY `idx_trades_session` (`user_id`, `session`),
  KEY `idx_trades_status_user` (`user_id`, `status`),
  KEY `idx_trades_pre_emotion` (`user_id`, `pre_trade_emotion`),
  CONSTRAINT `fk_trades_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Upgrade migration for existing v2.4 installations (run once on existing database)
ALTER TABLE `trades`
  ADD COLUMN `tp1_price` DECIMAL(18,8) NULL AFTER `take_profit_price`,
  ADD COLUMN `tp2_price` DECIMAL(18,8) NULL AFTER `tp1_price`,
  ADD COLUMN `partial_close_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER `tp2_price`,
  ADD COLUMN `remaining_close_percent` DECIMAL(5,2) NOT NULL DEFAULT 100.00 AFTER `partial_close_percent`,
  ADD COLUMN `tp1_profit` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `remaining_close_percent`,
  ADD COLUMN `tp2_profit` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `tp1_profit`,
  ADD COLUMN `pre_trade_emotion` ENUM('Confident','Fearful','Neutral','Overconfident') NULL AFTER `session`,
  ADD COLUMN `post_trade_emotion` ENUM('Satisfied','Regret','Frustrated','Disciplined') NULL AFTER `pre_trade_emotion`,
  ADD COLUMN `trade_notes` TEXT NULL AFTER `post_trade_emotion`;

ALTER TABLE `trades`
  MODIFY COLUMN `status` ENUM('Win', 'Loss', 'Running', 'Partially Closed') NOT NULL DEFAULT 'Running';

ALTER TABLE `trades`
  ADD KEY `idx_trades_status_user` (`user_id`, `status`),
  ADD KEY `idx_trades_pre_emotion` (`user_id`, `pre_trade_emotion`);
