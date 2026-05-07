SET @db_name = DATABASE();

SET @sql_add_stock_procesado = (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db_name
              AND TABLE_NAME = 'pedidos'
              AND COLUMN_NAME = 'stock_procesado'
        ),
        'SELECT 1',
        'ALTER TABLE pedidos ADD COLUMN stock_procesado TINYINT(1) NOT NULL DEFAULT 0 AFTER fecha_ultima_gestion'
    )
);
PREPARE stmt_add_stock_procesado FROM @sql_add_stock_procesado;
EXECUTE stmt_add_stock_procesado;
DEALLOCATE PREPARE stmt_add_stock_procesado;

SET @sql_add_fecha_stock_procesado = (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db_name
              AND TABLE_NAME = 'pedidos'
              AND COLUMN_NAME = 'fecha_stock_procesado'
        ),
        'SELECT 1',
        'ALTER TABLE pedidos ADD COLUMN fecha_stock_procesado DATETIME NULL AFTER stock_procesado'
    )
);
PREPARE stmt_add_fecha_stock_procesado FROM @sql_add_fecha_stock_procesado;
EXECUTE stmt_add_fecha_stock_procesado;
DEALLOCATE PREPARE stmt_add_fecha_stock_procesado;

SET @sql_add_index_stock_procesado = (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = @db_name
              AND TABLE_NAME = 'pedidos'
              AND INDEX_NAME = 'idx_pedidos_stock_procesado'
        ),
        'SELECT 1',
        'ALTER TABLE pedidos ADD INDEX idx_pedidos_stock_procesado (stock_procesado)'
    )
);
PREPARE stmt_add_index_stock_procesado FROM @sql_add_index_stock_procesado;
EXECUTE stmt_add_index_stock_procesado;
DEALLOCATE PREPARE stmt_add_index_stock_procesado;
