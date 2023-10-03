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

        while ($products = $this->drupal->fetchAllProducts($start)){
            $this->logger->notice('Processed ' . count($products) . ' products.');
            $this->output()->writeln('Processed ' . count($products) . ' products.');
            foreach($products as $product){
                $ean = $product->get('field_ean')->value;
                if (!$ean) {
                    $this->dilveApi->messageThis('No EAN set for product variation ID: ' . $product->id());
                    continue;
                }
                $book = $this->dilveApi->search($ean);
                if(is_array($book)) {
                    $message = 'Title - ' . $book['title'] . '('.$book['author'][0]['name'].') - '.$book['isbn'];
                    $this->dilveApi->messageThis($message);
                    $this->dilveApi->fileThis($message);
				    \Drupal::logger('dilve')->notice($message);
                }
                if($book && isset($book['cover_url'])) {
                    $message = 'Download from: '.$book['cover_url'];
                    $this->dilveApi->messageThis($message);
                    $this->dilveApi->fileThis($message);

                    $file = $this->dilveApi->create_cover( $book['cover_url'], $ean.'.jpg');
                    if ( !$file  ) {
                        $message = 'Failed to create cover image for EAN: ' . $ean;
                        $this->dilveApi->messageThis( $message );
                        $this->dilveApi->fileThis( $message );
                        continue;
                    }
                    $this->dilveApi->set_featured_image_for_product( $file, $ean );
                    $message = "Success to create cover image for EAN: " . $ean ;
                    $this->dilveApi->messageThis( $message );
                    $this->dilveApi->fileThis( $message );
                }
                $message = 'EAN: ' . $ean;
                $this->dilveApi->messageThis( $message );
                $this->dilveApi->fileThis( $message );
            }
            // Increment the starting position for the next batch.
            $start += $limit;
        }
        $message = 'Finished scanning all products.';
        $this->dilveApi->messageThis( $message );
        $this->dilveApi->fileThis( $message );
    }
}