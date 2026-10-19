-- Add FULLTEXT search index to products for fuzzy search
ALTER TABLE `commerce_products` ADD FULLTEXT INDEX `ft_com_products_search` (`name`, `sku`);
