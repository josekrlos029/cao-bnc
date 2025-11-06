-- Migración manual para agregar campo exchange a la tabla transactions
-- Ejecuta este SQL directamente en tu base de datos si la migración automática no funciona

-- Agregar columna exchange
ALTER TABLE `transactions` 
ADD COLUMN `exchange` VARCHAR(50) NOT NULL DEFAULT 'binance' AFTER `transaction_type`;

-- Agregar índice para mejorar consultas por exchange
ALTER TABLE `transactions` 
ADD INDEX `transactions_exchange_index` (`exchange`);

-- Actualizar transacciones existentes que no tengan exchange (por si acaso)
UPDATE `transactions` SET `exchange` = 'binance' WHERE `exchange` IS NULL OR `exchange` = '';

