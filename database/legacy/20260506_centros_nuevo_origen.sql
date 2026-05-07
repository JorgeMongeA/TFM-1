-- Nuevo origen maestro de centros desde Google Sheets.
-- Migracion idempotente: solo anade columnas que no existan.

DROP PROCEDURE IF EXISTS add_centros_column_if_missing;

DELIMITER //

CREATE PROCEDURE add_centros_column_if_missing(
    IN p_column_name VARCHAR(64),
    IN p_column_definition VARCHAR(255),
    IN p_after_column VARCHAR(64)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'centros'
          AND COLUMN_NAME = p_column_name
    ) THEN
        SET @ddl = CONCAT(
            'ALTER TABLE centros ADD COLUMN `',
            REPLACE(p_column_name, '`', '``'),
            '` ',
            p_column_definition,
            IF(p_after_column <> '', CONCAT(' AFTER `', REPLACE(p_after_column, '`', '``'), '`'), '')
        );

        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//

DELIMITER ;

CALL add_centros_column_if_missing('codigo_congregacion', 'VARCHAR(50) NULL', 'ciudad');
CALL add_centros_column_if_missing('congregacion', 'VARCHAR(255) NULL', 'codigo_congregacion');
CALL add_centros_column_if_missing('entrada', 'VARCHAR(20) NULL', 'congregacion');
CALL add_centros_column_if_missing('almacen', 'VARCHAR(50) NULL', 'entrada');
CALL add_centros_column_if_missing('destino', 'VARCHAR(50) NULL', 'almacen');

DROP PROCEDURE IF EXISTS add_centros_column_if_missing;
