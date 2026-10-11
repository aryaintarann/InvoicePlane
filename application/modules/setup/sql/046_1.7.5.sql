CREATE TABLE `ip_product_skus` (
    `sku_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sku_code` VARCHAR(191) COLLATE utf8mb4_bin NOT NULL,
    `sku_shipping_cost` DECIMAL(20,2) NOT NULL DEFAULT 0,
    PRIMARY KEY (`sku_id`),
    UNIQUE KEY `sku_code` (`sku_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

ALTER TABLE `ip_products`
    ADD COLUMN `product_sku_id` INT UNSIGNED NULL AFTER `product_sku`,
    ADD INDEX `product_sku_id` (`product_sku_id`);

-- Link legacy codes only when every product using that code agrees on shipping.
-- Conflicting, empty, or oversized codes retain their original data for review.
INSERT INTO `ip_product_skus` (`sku_code`, `sku_shipping_cost`)
SELECT MIN(TRIM(`product_sku`)), MIN(`product_shipping_cost`)
FROM `ip_products`
WHERE TRIM(`product_sku`) <> '' AND CHAR_LENGTH(TRIM(`product_sku`)) <= 191
GROUP BY BINARY TRIM(`product_sku`)
HAVING MIN(`product_shipping_cost`) = MAX(`product_shipping_cost`)
   AND MIN(`product_shipping_cost`) >= 0;

UPDATE `ip_products` AS `product`
INNER JOIN `ip_product_skus` AS `sku`
    ON BINARY TRIM(`product`.`product_sku`) = BINARY `sku`.`sku_code`
   AND `product`.`product_shipping_cost` = `sku`.`sku_shipping_cost`
SET `product`.`product_sku_id` = `sku`.`sku_id`;
