<?php

namespace Drupal\dilve\Api;

class DilveApiDrupalManager {
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