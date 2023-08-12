<?php 

namespace Drupal\geslib\Command;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\geslib\Api\GeslibApiDrupalManager;
use Drush\Commands\DrushCommands;

/**
 * GeslibStoreProductsCommand
 */
class GeslibStoreProductsCommand extends DrushCommands {    
    /**
     * drupal
     *
     * @var mixed
     */
    private $drupal;    
    /**
     * logger_factory
     *
     * @var mixed
     */
    private $logger_factory;
        
    /**
     * __construct
     *
     * @param  mixed $logger_factory
     * @return void
     */
    public function __construct( LoggerChannelFactoryInterface $logger_factory) {
        $this->drupal = new GeslibApiDrupalManager($logger_factory);
    }
    /**
     * Stores geslib products to drupal.
     * @command geslib:storeProducts
     * @alias gssp
     * @description Stores geslib products to drupal.
     * 
     */    
    /**
     * storeProducts
     *
     * @return void
     */
    public function storeProducts() {
        $this->drupal->storeProducts();
    }
}