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
    public function __construct(
        GeslibApiDrupalManager $drupal_manager,
        LoggerChannelFactoryInterface $logger_factory) {
        $this->drupal = $drupal_manager;
        $this->logger_factory = $logger_factory;
    }

    /**
     * Stores geslib products to drupal.
     * @command geslib:storeProducts
     * @alias gssp
     * @description Stores geslib products to drupal.
     * @return void
     */
    public function storeProducts() {
        $log_id = $this->drupal->getGeslibLogged();
        $this->drupal->setGeslibLog($log_id, 'queued');
        $this->drupal->storeProducts();
        $this->drupal->setGeslibLog($log_id, 'processed');
        $this->drupal->emptyGeslibLines();
        //Once the last line has been read now we will have to delete all rows from geslib_lines and set geslib_log state to queued.

    }
}