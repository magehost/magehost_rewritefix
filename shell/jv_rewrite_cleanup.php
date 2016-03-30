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
    protected $writeAdapter;
    protected $table;

    /** 
     * Constructor, prepare database stuff.
     */
    public function _construct() {
        $this->writeAdapter = Mage::getSingleton('core/resource')->getConnection('core_write');
        $this->writeAdapter->getConnection()->setAttribute( PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false );        
        $this->table = Mage::getResourceModel('core/url_rewrite')->getMainTable();
    }

    /**
     * Run script
     */
    public function run()
    {
        if ( $this->getArg('cleanup') ) {
            echo "Checking for unnecessary product URLs with category path...\n";
            $dummyObserver = new Varien_Event_Observer();
            Mage::getSingleton('jeroenvermeulen_rewritefix/observer')->afterReindexProcessCatalogUrl($dummyObserver);

            echo "\nChecking if we can cleanup rewrites which only add/remove '-[number]' in the URL...\n";
            $sql = sprintf( " SELECT `url_rewrite_id`, `request_path`, `target_path`
                              FROM %s
                              WHERE `options` = 'RP'
                              AND `product_id` IS NOT NULL
                              AND id_path LIKE '%%\_%%' ",
                            $this->writeAdapter->quoteIdentifier($this->table) );
            /** @var Varien_Db_Statement_Pdo_Mysql $stmt */
            $stmt = $this->writeAdapter->query( $sql );
            $pregFilter = '/\-\d+(\.html)?$/';
            $deleteCount = 0;
            $deleteList = array();
            while ( $row = $stmt->fetch() ) {
                if ( preg_replace($pregFilter,'$1',$row['request_path']) == preg_replace($pregFilter,'$1',$row['target_path']) ) {
                    $deleteList[] = intval( $row['url_rewrite_id'] );
                }
                if ( 5000 <= count($deleteList) ) {
                    $deleteCount += $this->cleanRewrites( $deleteList );
                    $deleteList = array();
                }
            }
            $deleteCount += $this->cleanRewrites( $deleteList );
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

Usage:  php -f jv_rewrite_cleanup.php -- [options]
        php -f jv_rewrite_cleanup.php -- cleanup

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
        $count = count( $deleteList );
        if ( $count ) {
            $chunks = array_chunk( $deleteList, 100 );
            foreach ($chunks as $chunk) {
                $sql = sprintf( 'DELETE FROM %s WHERE `url_rewrite_id` IN (%s)',
                                $this->writeAdapter->quoteIdentifier( $this->table ),
                                $this->writeAdapter->quote( $chunk ) );
                $this->writeAdapter->query( $sql );
            }
            echo ".";
            flush();
        }
        return $count;
    }

}

$shell = new Mage_Shell_RewriteCleanup();
$shell->run();
