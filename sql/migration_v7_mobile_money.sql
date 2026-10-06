-- ======================================================
-- EduSuivi - Migration v7 : Mobile Money Transactions
-- À exécuter dans phpMyAdmin ou via la console MySQL
-- ======================================================

-- Table de suivi des transactions Mobile Money
CREATE TABLE IF NOT EXISTS `mobile_money_transactions` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id`      INT UNSIGNED NOT NULL,
    `parent_id`       INT UNSIGNED NOT NULL,
    `operator`        ENUM('mpesa','airtel','orange','africell') NOT NULL,
    `phone`           VARCHAR(20) NOT NULL,
    `amount`          DECIMAL(15,2) NOT NULL,
    `currency`        VARCHAR(5) NOT NULL DEFAULT 'CDF',
    `reference`       VARCHAR(80) NOT NULL UNIQUE,
    `provider_ref`    VARCHAR(120) DEFAULT NULL COMMENT 'Référence retournée par l''opérateur',
    `status`          ENUM('pending','completed','failed','cancelled','refunded') NOT NULL DEFAULT 'pending',
    `status_message`  VARCHAR(255) DEFAULT NULL,
    `webhook_data`    JSON DEFAULT NULL COMMENT 'Payload du webhook opérateur',
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `completed_at`    DATETIME DEFAULT NULL,

    INDEX `idx_student`  (`student_id`),
    INDEX `idx_parent`   (`parent_id`),
    INDEX `idx_operator` (`operator`),
    INDEX `idx_status`   (`status`),
    INDEX `idx_ref`      (`reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Suivi des transactions Mobile Money EduSuivi';

-- Ajouter les colonnes Mobile Money dans fee_payments si pas encore présentes
ALTER TABLE `fee_payments`
    ADD COLUMN IF NOT EXISTS `mm_transaction_id` INT UNSIGNED DEFAULT NULL
        COMMENT 'Lien vers mobile_money_transactions.id'
        AFTER `receipt_number`,
    ADD COLUMN IF NOT EXISTS `mm_provider_ref` VARCHAR(120) DEFAULT NULL
        COMMENT 'Référence opérateur'
        AFTER `mm_transaction_id`;

-- Vue pratique pour l'admin : paiements Mobile Money en attente
CREATE OR REPLACE VIEW `v_mm_pending_payments` AS
SELECT
    mmt.id,
    mmt.reference,
    mmt.operator,
    mmt.phone,
    mmt.amount,
    mmt.status,
    mmt.created_at,
    CONCAT(s.first_name, ' ', s.last_name) AS student_name,
    CONCAT(u.first_name, ' ', u.last_name) AS parent_name
FROM mobile_money_transactions mmt
JOIN students s ON s.id = mmt.student_id
JOIN users    u ON u.id = mmt.parent_id
WHERE mmt.status = 'pending'
ORDER BY mmt.created_at DESC;
