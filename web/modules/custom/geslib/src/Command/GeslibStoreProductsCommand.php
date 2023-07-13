<?php 

namespace Drupal\geslib\Command;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\geslib\Api\GeslibApiDrupalManager;
use Drush\Commands\DrushCommands;

class GeslibStoreProductsCommand extends DrushCommands {
    private $drupal;
    private $logger_factory;
    
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
    public function storeProducts() {
        $this->drupal->storeProducts();
    }
}