<?php

// Fix for running via modman symlink
if ( !empty($_SERVER['SCRIPT_FILENAME']) ) {
    set_include_path( get_include_path() . PATH_SEPARATOR . dirname($_SERVER['SCRIPT_FILENAME']) );
}

require_once 'abstract.php';

/**
 * URL Rewrite Cleanup Shell Script
 */
class Mage_Shell_RewriteCleanup extends Mage_Shell_Abstract
{
    /** @var Magento_Db_Adapter_Pdo_Mysql */
    protected $writeAdapter;
    /** @var Magento_Db_Adapter_Pdo_Mysql */
    protected $readAdapter;
    /** @var string */
    protected $table;
    /** @var string */
    protected $quotedTable;
    // You can lower these chunk sizes if you don't have much memory.
    /** @var int */
    protected $selectChunkSize = 100000;
    /** @var int */
    protected $deleteChunkSize = 5000;

    /** 
     * Constructor, prepare database stuff.
     */
    public function _construct() {
        $this->readAdapter = Mage::getSingleton('core/resource')->getConnection('core_read');
        $this->writeAdapter = Mage::getSingleton('core/resource')->getConnection('core_write');
        $this->table = Mage::getResourceModel('core/url_rewrite')->getMainTable();
        $this->quotedTable = $this->readAdapter->quoteIdentifier($this->table);
    }

    /**
     * Run script
     * @throws Zend_Db_Statement_Exception
     */
    public function run()
    {
        if ( $this->getArg('cleanup') ) {
            echo "==== MageHost RewriteFix - https://MagentoHosting.pro ====\n\n";
            echo "Checking for unnecessary product URLs with category path...\n";
            $dummyObserver = new Varien_Event_Observer();
            Mage::getSingleton('magehost_rewritefix/observer')->afterReindexProcessCatalogUrl($dummyObserver);

            echo "\nChecking if we can cleanup rewrites which only add/remove '-[number]' in the URL...\n";

            // Process select and deletes in chunks to prevent "Allowed memory size" error.
            $deleteCount = 0;
            $maxSql = sprintf("SELECT MAX(url_rewrite_id) as `max` FROM %s", $this->quotedTable);
            $maxRewriteId = $this->readAdapter->raw_fetchRow( $maxSql, 'max' );
            $selectChunks = ceil( $maxRewriteId / $this->selectChunkSize );

            for ( $chunkNr=0; $chunkNr < $selectChunks; $chunkNr++ ) {
                $sql = sprintf( " SELECT `url_rewrite_id`, `request_path`, `target_path`
                              FROM %s
                              WHERE url_rewrite_id >= %d AND url_rewrite_id < %d
                              AND `options` = 'RP'
                              AND `product_id` IS NOT NULL
                              AND id_path LIKE '%%\_%%' ",
                    $this->quotedTable,
                    $chunkNr * $this->selectChunkSize,
                    $chunkNr * $this->selectChunkSize + $this->selectChunkSize );
                /** @var Varien_Db_Statement_Pdo_Mysql $stmt */
                $stmt = $this->readAdapter->query( $sql );
                $pregFilter = '/\-\d+(\.html)?$/';
                $deleteList = array();
                while ( $row = $stmt->fetch() ) {
                    if ( preg_replace($pregFilter,'$1',$row['request_path']) == preg_replace($pregFilter,'$1',$row['target_path']) ) {
                        $deleteList[] = intval( $row['url_rewrite_id'] );
                    }
                    if ( $this->deleteChunkSize <= count($deleteList) ) {
                        $deleteCount += $this->cleanRewrites( $deleteList );
                        $deleteList = array();
                    }
                }
                $deleteCount += $this->cleanRewrites( $deleteList );
            }

            if ( $deleteCount ) {
                printf( "\nCleaned up %d records.\n", $deleteCount );
            } else {
                echo "Found no records to clean.\n";
            }
            echo "\nDone.\n";
        } else {
            echo $this->usageHelp();
        }
    }

    /**
     * Retrieve Usage Help Message
     */
    public function usageHelp()
    {
        return <<<USAGE

WARNING: Use at your own risk. Create a database backup first.
This script will remove entries from the catalog_url index containing which
contain -[number] and point to a URL which is the same except for the number.

Usage:  php -f mh_rewrite_cleanup.php -- [options]
        php -f mh_rewrite_cleanup.php -- cleanup

  cleanup           Cleanup Catalog URL index
  help              This help

USAGE;
    }

    /**
     * Delete a list of ids from the `core_url_rewrite` table
     *
     * @param $deleteList int[] - List of ids to delete
     * @return int              - Nr of ids deleted
     */
    protected function cleanRewrites( $deleteList ) {
        $count = 0;
        if ( !empty($deleteList) ) {
            $chunks = array_chunk( $deleteList, 100 );
            foreach ($chunks as $chunk) {
                $sql = sprintf( 'DELETE FROM %s WHERE `url_rewrite_id` IN (%s)',
                                $this->writeAdapter->quoteIdentifier( $this->table ),
                                $this->writeAdapter->quote( $chunk ) );
                $stmt = $this->writeAdapter->query( $sql );
                $count += $stmt->rowCount();
                $stmt->closeCursor();
            }
            echo ".";
            flush();
        }
        return $count;
    }

}

$shell = new Mage_Shell_RewriteCleanup();
$shell->run();
