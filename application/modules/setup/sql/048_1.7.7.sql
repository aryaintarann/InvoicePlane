ALTER TABLE `ip_quotes`
    ADD COLUMN `quote_rejection_reason` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `quote_status_id`;
