<?php

namespace Drupal\dilve\Api;

/**
 * DilveApiDrupalManager
 */
class DilveApiDrupalManager {    
    /**
     * fetchAllProducts
     *
     * @param  mixed $start
     * @param  mixed $limit
     * @return void
     */
    public function fetchAllProducts ($start, $limit=0) {
        $product_ids = \Drupal::entityQuery('commerce_product')
                    ->accessCheck(FALSE)
                    ->execute();
                    //->range($start, $limit);

        return \Drupal::entityTypeManager()
                        ->getStorage('commerce_product')
                        ->loadMultiple($product_ids);
    }
}