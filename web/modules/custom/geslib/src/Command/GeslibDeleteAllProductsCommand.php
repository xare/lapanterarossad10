<?php

namespace Drupal\geslib\Command;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandResult;
use Drush\Commands\DrushCommands;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Batch\BatchStorageInterface;

/**
 * Defines a Drush command to delete all products from a drupal commerce.
 *
 * @DrushCommands()
 */
class GeslibDeleteAllProductsCommand extends DrushCommands  {

    /**
     * Defines a Drush command to delete all products from a drupal commerce.
     * @command geslib:deleteAllProducts
     * @alias gsdap
     * @description Defines a Drush command to delete all products from a drupal commerce.
     * 
     */

     public function deleteAllProducts() {
        // Bootstrap Drupal.
        
        $product_storage = \Drupal::entityTypeManager()
                        ->getStorage( 'commerce_product' );
        
        $batchBuilder = new BatchBuilder();
        $batchBuilder->setTitle( 'Deleting Commerce products' );
        $batchBuilder->setFinishCallback([ $this, 'deleteAllProductsFinish' ]);
        $batchBuilder->setInitMessage( 'Starting deletion of Commerce products...' );
        $batchBuilder->setProgressMessage( 'Deleting Commerce products %percentage% completed.' );
        $batchBuilder->setErrorMessage( 'An error occurred while deleting Commerce products.' );
        $products = $product_storage->getQuery()->accessCheck(FALSE)->execute();

        $batch_operation = '';
         // Determine how many products to delete at once.
        $batchSize = 100;
        $productsChunks = array_chunk($products, $batchSize);
        foreach ($productsChunks as $productsChunk) {
            $batchBuilder->addOperation([$this, 'deleteProducts'], [$productsChunk]);
        }
      
        $batch = $batchBuilder->toArray();
        batch_set($batch);
        drush_backend_batch_process();
        
     }

    /**
    * Batch finish callback for deleting all Commerce products.
    */
    public function deleteAllProductsFinish($success, $results, $operations) {
        $this->output()->getFormatter()->setDecorated(false);
        if ($success) {
            $this->output()->writeln(dt('All Commerce products deleted successfully.'));
        } else {
            $this->output()->writeln(dt('An error occurred while deleting Commerce products.'));
        }
    }

    /**
    * Batch operation for deleting a Commerce product.
    */
    public function deleteProducts($product_ids, &$context) {
        $product_storage = \Drupal::entityTypeManager()
                            ->getStorage('commerce_product');
        $variation_storage = \Drupal::entityTypeManager()
                            ->getStorage('commerce_product_variation');

        foreach($product_ids as $product_id){
            $product = $product_storage->load($product_id);
            if ($product instanceof \Drupal\commerce_product\Entity\ProductInterface) {
                $variation_id = $product->getDefaultVariation()->id();
                $variation = $variation_storage->load($variation_id);
                $product->delete();
                $context['message'] = dt('Deleting product @title with EAN: @ean.', 
                                        [
                                            '@title' => $product->get('title')->value,
                                            '@ean' => $variation->get('field_ean')->value,
                                        ]);
            }
        }
        $context['message'] = dt('Deleting products...');
        //$this->output()->writeln(dt('Deleting product @product_id', ['@product_id' => $product_id]));
        //$context['message'] = dt('Deleting product @product_id', ['@product_id' => $product_id]);
    }
}