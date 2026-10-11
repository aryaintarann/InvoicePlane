ALTER TABLE `ip_clients`
    ADD COLUMN `client_whatsapp_consent` TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE `ip_invoices`
    ADD COLUMN `invoice_whatsapp_issued_at` DATETIME NULL DEFAULT NULL;

-- Existing published invoices must not produce an initial notification.
UPDATE `ip_invoices` SET `invoice_whatsapp_issued_at` = UTC_TIMESTAMP()
WHERE `invoice_status_id` <> 1;

CREATE TABLE `ip_notifications` (
    `notification_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_key` VARCHAR(191) NOT NULL,
    `event_type` VARCHAR(20) NOT NULL,
    `invoice_id` INT NOT NULL,
    `payment_id` INT NULL,
    `recipient` VARCHAR(20) NULL,
    `reminder_date` DATE NULL,
    `payment_amount` DECIMAL(20,2) NULL,
    `payment_balance` DECIMAL(20,2) NULL,
    `scheduled_at` DATETIME NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'queued',
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `claimed_at` DATETIME NULL,
    `provider_id` VARCHAR(191) NULL,
    `failure_reason` VARCHAR(100) NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `retried_by` INT NULL,
    PRIMARY KEY (`notification_id`),
    UNIQUE KEY `event_key` (`event_key`),
    KEY `pending_jobs` (`status`, `scheduled_at`, `notification_id`),
    KEY `invoice_history` (`invoice_id`, `notification_id`),
    KEY `worker_lease` (`status`, `claimed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
