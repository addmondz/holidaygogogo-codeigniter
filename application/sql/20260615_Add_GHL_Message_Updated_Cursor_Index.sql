SET @ghl_has_updated_cursor_index := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'ghl_messages'
    AND INDEX_NAME = 'idx_updated_at_id_conversation_date_added'
);

SET @ghl_add_updated_cursor_index_sql := IF(
  @ghl_has_updated_cursor_index = 0,
  'ALTER TABLE `ghl_messages` ADD INDEX `idx_updated_at_id_conversation_date_added` (`updated_at`, `id`, `conversation_id`, `date_added`)',
  'SELECT ''idx_updated_at_id_conversation_date_added already exists'' AS message'
);

PREPARE ghl_add_updated_cursor_index_stmt FROM @ghl_add_updated_cursor_index_sql;
EXECUTE ghl_add_updated_cursor_index_stmt;
DEALLOCATE PREPARE ghl_add_updated_cursor_index_stmt;
