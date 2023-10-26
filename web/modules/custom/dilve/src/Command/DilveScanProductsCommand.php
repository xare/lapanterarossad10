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
        $start = 0;
        $limit = 100; //number of products to process at a time.
        $products = $this->drupal->fetchAllProducts();
        $counter = 0;
        $this->dilveApi->reportThis( ' Processed ' . count($products) . ' products.' );
        foreach( $products as $product ) {
            $ean = $product->get('field_ean')->value;
            if (!$ean) {
                $this->dilveApi->reportThis($counter .' - No EAN set for product variation ID: ' . $product->id());
                continue;
            }
            $book = $this->dilveApi->search( $ean );
            if(is_array($book)) {
                $this->dilveApi->reportThis($counter .' Title - ' . $book['title'] . '('.$book['author'][0]['name'].') - '.$book['isbn']);
            }
            if($book && isset($book['cover_url'])) {
                $this->dilveApi->reportThis('Download from: '.$book['cover_url']);

                $file = $this->dilveApi->create_cover( $book['cover_url'], $ean.'.jpg');
                if ( !$file  ) {
                    $this->dilveApi->reportThis( $counter .' Failed to create cover image for EAN: ' . $ean );
                    continue;
                }
                $this->dilveApi->set_featured_image_for_product( $file, $ean );
                $this->dilveApi->reportThis( $counter ." Success to create cover image for EAN: " . $ean );
            }
            //$this->dilveApi->reportThis( 'EAN: ' . $ean );
            $counter++;
        }
        $this->dilveApi->reportThis( 'Finished scanning all products.' );
    }
}