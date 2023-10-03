<?php

namespace Drupal\dilve\Command;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dilve\Api\DilveApi;
use Drupal\dilve\Api\DilveApiDrupalManager;
use Drush\Commands\DrushCommands;

/**
 * DilveScanProductsCommand
 * Defines a Drush command to scan one product on the base of its ean number from the database and downloads the picture.
 *
 * @DrushCommands()
 *
 */

 class DilveScanProductCommand extends DrushCommands {

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
     * @command dilve:scanProduct
     * @param string $ean The EAN of the product.
     * @alias dlspr
     * @description Scan products from the database and downloads the pictures.
     * @return void
     */
    public function scanProduct( $ean ) {
        //Here I need a product give the value of $ean which corresponds to a field called field_ean associated to a Commerce product
        $this->dilveApi->messageThis( 'EAN: ' . $ean);
        $book = $this->dilveApi->search( $ean );

        if( $book && isset( $book['cover_url'] ) ) {
            $file = $this->dilveApi->create_cover( $book['cover_url'], $ean.'.jpg');
            if ( !$file  ) {
                $message = 'Failed to create cover image for EAN: ' . $ean ;
                $this->dilveApi->messageThis( $message, 'error' );
                $this->dilveApi->fileThis( $message, 'error' );
                return;
            }
            $this->dilveApi->set_featured_image_for_product( $file, $ean );
            $this->dilveApi->messageThis( 'Success to create cover image for EAN: ' . $ean);
        }
    }
 }