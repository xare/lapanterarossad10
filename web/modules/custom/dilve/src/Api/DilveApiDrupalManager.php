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
    public function fetchAllProducts ($start, $limit) {
        $query = \Drupal::entityQuery('commerce_product')
                    ->accessCheck(FALSE)
                    ->range($start, $limit);
        $product_ids = $query->execute();

        return \Drupal::entityTypeManager()
                        ->getStorage('commerce_product')
                        ->loadMultiple($product_ids);
    }
}