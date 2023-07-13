<?php

namespace Drupal\geslib\Api;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\geslib\Api\GeslibApiDrupalManager;

class GeslibApiStoreData {
    private $drupal;
    private $logger_factory;

    public function __construct( LoggerChannelFactoryInterface $logger_factory ) {
        $this->logger_factory = $logger_factory;
        $this->drupal = new GeslibApiDrupalManager($this->logger_factory);
    }

    public function storeProductCategories() {
        $product_categories = $this->drupal->getProductCategoriesFromGeslibLines();
        foreach($product_categories as $product_category) {
            $this->drupal->storeProductCategories($product_category);
        }
    }

    public function storeEditorials() {
        $editorials = $this->drupal->getEditorialsFromGeslibLines();
        foreach($editorials as $editorial) {
            $this->drupal->storeEditorials($editorial);
        }
    }


}