-- Create a backup of your Magento database first.

-- Execute:

DELETE FROM `core_url_rewrite`
WHERE `request_path` REGEXP '\-[[:digit:]]+$'
  AND `options` = 'RP'
  AND `product_id` IS NOT NULL
  AND   SUBSTRING( `request_path`, 1, LENGTH(`request_path`)-5 )
      = SUBSTRING( `target_path`, 1, LENGTH(`target_path`)-5 )

-- Reindex "Catalog URL Rewrites":
-- $   screen ~/httpdocs/shell/indexer.php -- --reindex catalog_url