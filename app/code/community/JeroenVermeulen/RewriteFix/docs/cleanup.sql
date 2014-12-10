-- Create a backup of your Magento database first.

-- Execute:

DELETE FROM `core_url_rewrite`
WHERE
  `options` = 'RP'
  AND `product_id` IS NOT NULL
  AND
  (
    ( `request_path` REGEXP '\-[[:digit:]]+$'
      AND   SUBSTRING(`request_path`, 1, LENGTH(`request_path`) - 5)
          = SUBSTRING(`target_path`, 1, LENGTH(`target_path`) - 5)
    )
    OR
    ( `request_path` REGEXP '\-[[:digit:]]+\.html?$'
      AND   SUBSTRING_INDEX( SUBSTRING(`request_path`, 1, LENGTH(`request_path`) - 10), '/', -1) )
          = SUBSTRING(`target_path`, 1, LENGTH(`target_path`) - 10)
    )
  )

-- Reindex "Catalog URL Rewrites":
-- $   screen php ~/httpdocs/shell/indexer.php -- --reindex catalog_url