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

        foreach ($products as $product_id) {
            $batchBuilder->addOperation([$this, 'deleteProduct'], [$product_id]);
        }
        //$this->batchBuilder->setBatchStorage($this-_batch_storage);
        //$this->batchBuilder->set('progressive', TRUE);

        //$this->batchBuilder->save();
        $batch = $batchBuilder->toArray();
        //\Drupal::service('batch.manager')->setBatch($batch);
        batch_set($batch);
        drush_backend_batch_process();
        $this->output()->writeln(dt('All products have been deleted.'));
        
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
    public function deleteProduct($product_id, &$context) {
        $product = \Drupal::entityTypeManager()
        ->getStorage('commerce_product')
        ->load($product_id);

        if ($product instanceof \Drupal\commerce_product\Entity\ProductInterface) {
            $product->delete();
        }

        $context['message'] = dt('Deleting product @product_id', ['@product_id' => $product_id]);
    }
}