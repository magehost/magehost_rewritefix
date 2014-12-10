<?php

/**
 * Observers which fix several things with the 'catalog_url' index.
 * These fixes are developed by Jeroen Vermeulen BVBA.
 */
class JeroenVermeulen_RewriteFix_Model_Observer {

    /**
     * When an URL is hit ending with a number and causes a 404 error, do a 301 redirect to the URL without the number.
     * This helps when you are cleaning up old URLs ending with a number.
     *
     * /category-name/product-name-123       =301=>  /category-name/product-name
     * /category-name/product-name-123/      =301=>  /category-name/product-name/
     * /category-name/product-name-123.html  =301=>  /category-name/product-name.html
     *
     * @param Varien_Event_Observer $observer
     */
    public function controllerActionPredispatchCmsIndexNoRoute( $observer ) {
        /** @var $controllerAction Mage_Cms_IndexController */
        $controllerAction = $observer->getControllerAction();
        $request =  Mage::app()->getRequest();
        $response = Mage::app()->getResponse();
        $originalPath = $request->getOriginalPathInfo();
        $baseUrl = rtrim( Mage::getBaseUrl(), '/' ); // Remove trailing slash
        $currentUrl =  $baseUrl . $originalPath;
        if ( preg_match( '#^([/\w\-]+)\-\d+(\.html|/)?$#', $originalPath, $matches ) ) {
            // URL was ending with a number, let's cut it off and 301 redirect
            $newUrl = $baseUrl . $matches[1];
            if ( isset($matches[2]) ) {
                $newUrl .= $matches[2];
            }
            if ( $currentUrl != $newUrl ) { // Double check to prevent looping
                $response->setRedirect($newUrl, 301);
                $response->sendHeaders();
                $controllerAction->setFlag( '', Mage_Core_Controller_Varien_Action::FLAG_NO_DISPATCH, true );
            }
        }
    }

    /**
     * For stores that have the config setting 'Use Categories Path for Product URLs' set to disabled:
     * clean up records in core_url_rewite which are made for category and product combination URLs.
     *
     * @param Varien_Event_Observer $observer
     */
    public function afterReindexProcessCatalogUrl( $observer ) {
        $cleanForIds = array();
        $stores = Mage::app()->getStores( true );
        $helper = Mage::helper( 'jeroenvermeulen_rewritefix' );
        /** @var Mage_Core_Model_Store $store */
        foreach ( $stores as $store ) {
            if ( ! Mage::getStoreConfigFlag( 'catalog/seo/product_use_categories', $store->getId() ) ) {
                $cleanForIds[] = intval($store->getId());
            }
        }
        if ( !empty($cleanForIds) ) {
            $writeAdapter = Mage::getSingleton('core/resource')->getConnection('core_write');
            $table = Mage::getResourceModel('core/url_rewrite')->getMainTable();
            $sql = sprintf( 'DELETE FROM %s
                             WHERE `store_id` IN (%s)
                             AND `category_id` IS NOT NULL
                             AND `product_id` IS NOT NULL',
                            $writeAdapter->quoteIdentifier($table),
                            $writeAdapter->quote($cleanForIds) );
            $stmt = $writeAdapter->query( $sql );
            $count = $stmt->rowCount();
            if ( $count ) {
                $helper->successMessage( $helper->__( "JV RewriteFix: Cleaned up %d records from '%s' index because '%s' is disabled.",
                                                      $count,
                                                      Mage::helper('catalog')->__("Catalog URL Rewrites"),
                                                      Mage::helper('catalog')->__("Use Categories Path for Product URLs") ) );
            }
        }
    }


    /**
     * This is an observer function for the event 'adminhtml_block_html_before'.
     * If the block is the grid for the "Index Management" we update the description of the "Catalog Search Index"
     *
     * @param Varien_Event_Observer $observer
     */
    public function adminhtmlBlockHtmlBefore( $observer ) {
        $block = $observer->getData( 'block' );
        if (is_a( $block, 'Mage_Index_Block_Adminhtml_Process_Grid' )) {
            /** @var Mage_Index_Block_Adminhtml_Process_Grid $block */
            $collection = $block->getCollection();
            $readAdapter = Mage::getSingleton('core/resource')->getConnection('core_read');
            $table = Mage::getResourceModel('core/url_rewrite')->getMainTable();
            foreach ($collection as $item) {
                /** @var Mage_Index_Model_Process $item */
                if ('catalog_url' == $item->getIndexerCode()) {
                    $select = $readAdapter->select()->from( $table, array('count'=>'COUNT(*)' ) );
                    $count = number_format( $readAdapter->fetchOne( $select ) );
                    $item->setDescription( $item->getDescription() . ' - ' . $block->__('%s records',$count) );
                }
            }
        }
    }
}