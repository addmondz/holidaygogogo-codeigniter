CREATE TABLE IF NOT EXISTS `chat_history_files` (
  `FileID`       INT AUTO_INCREMENT PRIMARY KEY,
  `dedup_key`    VARCHAR(255) NOT NULL,
  `OriginalName` VARCHAR(255) NOT NULL,
  `StoredName`   VARCHAR(255) NOT NULL,
  `Title`        VARCHAR(255) NULL DEFAULT NULL,
  `Status`       CHAR(1) NOT NULL DEFAULT 'Y',
  `CreatedBy`    INT NULL DEFAULT NULL,
  `CreatedAt`    DATETIME NOT NULL,
  KEY `idx_chf_dedup` (`dedup_key`, `Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
