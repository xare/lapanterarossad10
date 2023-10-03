<?php

namespace Drupal\dilve\Command;

use Drupal\dilve\Api\DilveApi;
use Drupal\dilve\Api\DilveApiDrupalManager;
use Drush\Commands\DrushCommands;

/**
 * Defines a Drush command to print "Hello World".
 *
 * @DrushCommands()
 */
class DilveHasCoverCommand extends DrushCommands {
    /**
     * Prints "Has Cover" on the terminal prompt.
     * @command dilve:hasCover
     * @param string $ean The EAN of the product.
     * @alias dlhc
     * @description Prints "Has Cover" on the terminal prompt.
     *
     */

     public function hasCover( string $ean ) {
        $dilveApi = new DilveApi();
        $dilveApiDrupalManager = new DilveApiDrupalManager();
        $product_ids = $dilveApiDrupalManager->getProductIds( $ean );
        // Check if any product is found.
        if (empty($product_ids)) {
            $message = "No product found with EAN: " . $ean;
            $dilveApi->messageThis( $message, 'error' );
            $dilveApi->fileThis( $message, 'error' );
            return false;
        }
        // Load the first product found.
        $product = \Drupal\commerce_product\Entity\Product::load(reset($product_ids));
        // Check if the product has the 'field_portada' field.
        if ( !$product->hasField('field_portada') ) {
            $message = "Product does not have the 'field_portada' field.";
            $dilveApi->messageThis( $message, 'error' );
            $dilveApi->fileThis( $message, 'error' );
            return false;
        }
        // Get the values of the 'field_portada' field.
        $field_portada_values = $product->get( 'field_portada' )->getValue();

        // Check if the field has any values.
        if (empty($field_portada_values)) {
            $message = "Field 'field_portada' is empty.";
            $dilveApi->messageThis( $message, 'error' );
            $dilveApi->fileThis( $message, 'error' );
            return false;
        }
        // Get the target_id from the first item.
        $first_item = reset($field_portada_values);
        $target_id = $first_item['target_id'];

        // Check if the target_id is empty.
        if (empty($target_id)) {
            $message= "Target ID is empty.";
            $dilveApi->messageThis( $message, 'error' );
            $dilveApi->fileThis( $message, 'error' );
            return false;
        }
        // Load the file entity using the target_id.
        $file = \Drupal\file\Entity\File::load($target_id);

        // Check if the file entity is found.
        if (!$file) {
            $message ="File entity not found with ID: " . $target_id;
            $dilveApi->messageThis( $message, 'error' );
            $dilveApi->fileThis( $message, 'error' );
            return false;
        }
        // Get the file URI.
        $file_uri = $file->getFileUri();

        // Check if the file exists at the given path.
        if (file_exists($file_uri)) {
            $messages = [
                "File exists at path: " . $file_uri,
                "Field 'field_portada' Value: " . $target_id,
                "File Path: " . $file_uri
            ];
            foreach ($messages as $message) {
                $dilveApi->messageThis($message);
                $dilveApi->fileThis($message);
            }
            return true;
        } else {
            $message = "File does not exist at path: " . $file_uri;
            $dilveApi->messageThis($message);
            return false;
        }
    }


}