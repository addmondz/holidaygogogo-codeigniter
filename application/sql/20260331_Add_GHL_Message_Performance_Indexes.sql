SET @ghl_message_time_col := (
  SELECT CASE
    WHEN COUNT(*) > 0 THEN 'timestamp'
    ELSE 'date_added'
  END
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'ghl_messages'
    AND COLUMN_NAME = 'timestamp'
);

SET @ghl_sql_1 := CONCAT(
  'ALTER TABLE `ghl_messages` ADD INDEX `idx_',
  @ghl_message_time_col,
  '_id_conversation` (`',
  @ghl_message_time_col,
  '`, `id`, `conversation_id`)'
);

PREPARE ghl_stmt_1 FROM @ghl_sql_1;
EXECUTE ghl_stmt_1;
DEALLOCATE PREPARE ghl_stmt_1;

SET @ghl_sql_2 := CONCAT(
  'ALTER TABLE `ghl_messages` ADD INDEX `idx_conversation_',
  @ghl_message_time_col,
  '_id` (`conversation_id`, `',
  @ghl_message_time_col,
  '`, `id`)'
);

PREPARE ghl_stmt_2 FROM @ghl_sql_2;
EXECUTE ghl_stmt_2;
DEALLOCATE PREPARE ghl_stmt_2;
