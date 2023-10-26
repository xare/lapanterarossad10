<?php

namespace Drupal\dilve\Command;

use Drush\Commands\DrushCommands;
use Drupal\file\Entity\File;
use Drupal\commerce_product\Entity\Product;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\dilve\Api\DilveApi;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\HttpKernel\Log\Logger;

/**
 * A Drush command file.
 *
 * @package Drupal\dilve\Commands
 */
class DilveRemoveFailingPortadasCommand extends DrushCommands {

  public function __construct(private StreamWrapperManagerInterface $stream_wrapper_manager, private LoggerChannelFactory $logger_factory) {
    $this->stream_wrapper_manager = $stream_wrapper_manager;
    $this->logger_factory = $logger_factory;
  }

  /**
   * Removes all failing images and sets a default image for products.
   *
   * @command dilve:remove-failing-images
   * @aliases drfi
   */
  public function removeFailingImages() {
    $dilveApi = new DilveApi;
    $query = \Drupal::entityQuery('file')->accessCheck(FALSE);
    $fids = $query->execute();
    $default_fid = 16742; // Replace with the fid of your default image.
    $httpClient = \Drupal::httpClient();

    foreach ($fids as $fid) {
        $file = File::load($fid);
        $uri = $file->getFileUri();
        $wrapper = $this->stream_wrapper_manager->getViaUri($uri);
        $url = $wrapper->getExternalUrl();
        $pos = strpos($url, 'default');
        $url = ($pos !== false) ? substr_replace($url, 'lapanterarossa', $pos, strlen('default')) : $url;

        try {
            $response = $httpClient->get($url);
            $statusCode = $response->getStatusCode();
        } catch (RequestException $e) {
            $statusCode = 500;
        }
        if($statusCode===404) {
          $dilveApi->reportThis('404 URL:'.$url.', STATUS CODE: '. $statusCode);
        }

        if ($statusCode === 500 || $statusCode === 404 ) {
            $dilveApi->reportThis('AFTER CATCH URL:'.$url.', STATUS CODE: '. $statusCode);
            // Find products that use this file in field_portada.
            $product_ids = \Drupal::entityQuery('commerce_product')
            ->condition('field_portada.target_id', $fid)
            ->accessCheck(FALSE)
            ->execute();

            // Update those products to use the default image.
            foreach ($product_ids as $product_id) {
                $product = Product::load($product_id);
                $product->set('field_portada', ['target_id' => $default_fid]);
                $product->save();
            }

        // Delete the file.
        //$file->delete();
        $this->logger()->notice("Could delete faulty file with ID: $fid and URI: $uri");
      }
    }
  }
}
