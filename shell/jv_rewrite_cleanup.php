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

    /**
     * Run script
     */
    public function run()
    {
        if ($this->getArg('cleanup')) {
            $table = Mage::getResourceModel('core/url_rewrite')->getMainTable();
            $writeAdapter = Mage::getSingleton('core/resource')->getConnection('core_write');
            $sql = sprintf( " SELECT `url_rewrite_id`, `request_path`, `target_path`
                              FROM %s
                              WHERE `options` = 'RP'
                              AND `product_id` IS NOT NULL
                              AND id_path LIKE '%%_%%' ",
                            $writeAdapter->quoteIdentifier($table) );
            /** @var Varien_Db_Statement_Pdo_Mysql $stmt */
            $stmt = $writeAdapter->query( $sql );
            $pregFilter = '/\-\d+/';
            $deleteList = array();
            while ( $row = $stmt->fetch() ) {
                if ( preg_replace($pregFilter,'',$row['request_path']) == preg_replace($pregFilter,'',$row['target_path']) ) {
                    $deleteList[] = $row['url_rewrite_id'];
                }
            }
            if ( empty($deleteList) ) {
                echo "Congratulations, found no records to clean.\n";
            } else {
                printf( "Cleaning up %d records...\n", count( $deleteList ) );
                $chunks = array_chunk( $deleteList, 100 );
                foreach ($chunks as $chunk) {
                    $sql = sprintf(
                        'DELETE FROM %s WHERE `url_rewrite_id` IN (%s)',
                        $writeAdapter->quoteIdentifier( $table ),
                        $writeAdapter->quote( $chunk )
                    );
                    $writeAdapter->query( $sql );
                }
            }
            echo "Done.\n";
        } else {
            echo $this->usageHelp();
        }
    }

    /**
     * Retrieve Usage Help Message
     *
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
}

$shell = new Mage_Shell_RewriteCleanup();
$shell->run();
