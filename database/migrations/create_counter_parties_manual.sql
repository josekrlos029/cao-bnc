-- Script SQL manual para crear la tabla counter_parties
-- Ejecutar este script directamente en la base de datos si la migración no funciona

CREATE TABLE IF NOT EXISTS `counter_parties` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `exchange` varchar(50) NOT NULL DEFAULT 'binance',
  `counter_party` varchar(255) DEFAULT NULL,
  `merchant_no` varchar(255) DEFAULT NULL,
  `counter_party_dni` varchar(255) DEFAULT NULL,
  `dni_type` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `counter_parties_user_id_exchange_counter_party_unique` (`user_id`,`exchange`,`counter_party`),
  KEY `counter_parties_user_id_exchange_merchant_no_index` (`user_id`,`exchange`,`merchant_no`),
  KEY `counter_parties_user_id_foreign` (`user_id`),
  CONSTRAINT `counter_parties_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

