<?php

namespace Drupal\dilve\Command;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dilve\Api\DilveApi;
use Drupal\dilve\Api\DilveApiDrupalManager;
use Drush\Commands\DrushCommands;

/**
 * Defines a Drush command to scan products from the database and downloads the pictures.
 *
 * @DrushCommands()
 */
/**
 * DilveScanProductsCommand
 */
class DilveScanProductsCommand extends DrushCommands {

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
     * dilveApi
     *
     * @var mixed
     */
    private $dilveApi;
    /**
     * __construct
     *
     * @param  mixed $logger_factory
     * @return void
     */
    public function __construct(LoggerChannelFactoryInterface $logger_factory) {
        $this->drupal = new DilveApiDrupalManager();
        $this->logger_factory = $logger_factory;
        $this->logger = $this->logger_factory->get('dilve');
        $this->dilveApi = new DilveApi();
    }

    /**
     * Scan products from the database and downloads the pictures.
     * @command dilve:scanProducts
     * @alias dlsp
     * @description Scan products from the database and downloads the pictures.
     * @return void
     */
    public function scanProducts() {
        $dilveApi = new DilveApi;
        $dilveApi->scanProducts();
    }
}