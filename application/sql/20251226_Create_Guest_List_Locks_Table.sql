-- Create guest_list_locks table for heartbeat-based soft locking
-- This table stores active locks and does NOT auto-delete expired rows

CREATE TABLE IF NOT EXISTS `guest_list_locks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `guest_list_hash` VARCHAR(64) NOT NULL COMMENT 'The gl parameter hash from URL',
  `lock_token` VARCHAR(64) NOT NULL COMMENT 'UUID token for non-logged-in users',
  `lock_owner_type` ENUM('user','guest') NOT NULL DEFAULT 'guest' COMMENT 'user = logged in, guest = anonymous',
  `lock_owner_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'User ID if logged in, NULL for guests',
  `ip_hash` VARCHAR(64) NULL DEFAULT NULL COMMENT 'MD5 hash of IP (secondary signal only)',
  `user_agent_hash` VARCHAR(64) NULL DEFAULT NULL COMMENT 'MD5 hash of User-Agent (secondary signal only)',
  `last_heartbeat_at` DATETIME NOT NULL COMMENT 'Last heartbeat timestamp',
  `lock_expires_at` DATETIME NOT NULL COMMENT 'When the lock expires (10 minutes from creation, or extended)',
  `extension_used` TINYINT(1) DEFAULT 0 COMMENT 'Whether the one-time extension has been used',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_guest_list_hash` (`guest_list_hash`),
  KEY `idx_last_heartbeat` (`last_heartbeat_at`),
  KEY `idx_lock_token` (`lock_token`),
  KEY `idx_owner` (`lock_owner_type`, `lock_owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Guest list editing locks with heartbeat system';

