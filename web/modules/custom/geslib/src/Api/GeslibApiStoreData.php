<?php

namespace Drupal\geslib\Api;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\geslib\Api\GeslibApiDrupalManager;

/**
 * GeslibApiStoreData
 */
class GeslibApiStoreData {    
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
     * __construct
     *
     * @param  mixed $logger_factory
     * @return void
     */
    public function __construct( LoggerChannelFactoryInterface $logger_factory ) {
        $this->logger_factory = $logger_factory;
        $this->drupal = new GeslibApiDrupalManager($this->logger_factory);
    }
    
    /**
     * storeProductCategories
     *
     * @return void
     */
    public function storeProductCategories() {
        $product_categories = $this->drupal->getProductCategoriesFromGeslibLines();
        foreach($product_categories as $product_category) {
            $this->drupal->storeProductCategories($product_category);
        }
    }
    
    /**
     * storeEditorials
     *
     * @return void
     */
    public function storeEditorials() {
        $editorials = $this->drupal->getEditorialsFromGeslibLines();
        foreach($editorials as $editorial) {
            $this->drupal->storeEditorials($editorial);
        }
    }

}