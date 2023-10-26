<?php

namespace Drupal\dilve\Command;

use Drush\Commands\DrushCommands;
use Drupal\file\Entity\File;
use Drupal\commerce_product\Entity\Product;
use Drupal\dilve\Api\DilveApi;

/**
 * A Drush command file.
 *
 * @package Drupal\your_module\Commands
 */
class DilveRemoveOneByOneCommand extends DrushCommands {

  /**
   * Removes all 1x1 images and sets a default image for products.
   *
   * @command dilve:remove-one-by-one-images
   * @aliases droboi
   */
    public function removeOneByOneImages() {
        $dilveApi = new DilveApi;
        $query = \Drupal::entityQuery('file')->accessCheck(FALSE);
        $fids = $query->execute();
        $default_fid = 1642; // Replace with the fid of your default image.

        foreach ($fids as $fid) {
            $file = File::load($fid);
            $uri = $file->getFileUri();
            $real_path = \Drupal::service('file_system')->realpath($uri);
            list($width, $height) = getimagesize($real_path);

            if ($width < 2 ) {
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
                $file->delete();
                $dilveApi->reportThis("Deleted file with ID: $fid and URI: $uri");
            }
        }
  }
}
