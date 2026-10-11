ALTER TABLE `ip_products`
    ADD COLUMN `product_shipping_cost` DECIMAL(20,2) NOT NULL DEFAULT 0 AFTER `purchase_price`;
