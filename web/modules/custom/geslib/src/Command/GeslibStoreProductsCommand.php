<?php

namespace Drupal\geslib\Command;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\geslib\Api\GeslibApiDrupalManager;
use Drupal\geslib\Api\GeslibApiDrupalProductsManager;
use Drush\Commands\DrushCommands;

/**
 * GeslibStoreProductsCommand
 */
class GeslibStoreProductsCommand extends DrushCommands {

    /**
     * Stores geslib products to drupal.
     * @command geslib:storeProducts
     * @alias gssp
     * @description Stores geslib products to drupal.
     * @return void
     */
    public function storeProducts() {
        $geslibApiDrupalProductsManager = new GeslibApiDrupalProductsManager;
        $geslibApiDrupalProductsManager->storeProducts();
    }
}