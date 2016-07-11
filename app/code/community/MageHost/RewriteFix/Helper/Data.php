<?php

/**
 * This class must exist to make translations work
 */
class MageHost_RewriteFix_Helper_Data extends Mage_Core_Helper_Abstract {

    /**
     * @param string $message
     */
    public function successMessage( $message ) {
        if ( null === Mage::app()->getRequest()->getControllerName() ) {
            // Shell script
            echo $message . "\n";
        } else {
            Mage::getSingleton( 'adminhtml/session' )->addSuccess( $message );
        }
    }

}