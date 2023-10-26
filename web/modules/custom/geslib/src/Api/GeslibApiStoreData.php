<?php

namespace Drupal\geslib\Api;

use Drupal\geslib\Api\GeslibApiDrupalManager;

/**
 * GeslibApiStoreData
 */
class GeslibApiStoreData {

    /**
     * storeProductCategories
     *
     * @return void
     */
    public function storeProductCategories() {
        $geslibApiDrupalManager = new GeslibApiDrupalManager();
        $product_categories = $geslibApiDrupalManager->getProductCategoriesFromGeslibLines();
        foreach($product_categories as $product_category) {
            \Drupal::queue( 'geslib_manage_editorial' )->createItem( $product_category );
            //$geslibApiDrupalManager->storeTerm($product_category, 'product_categories');
        }
    }

    /**
     * storeEditorials
     *
     * @return void
     */
    public function storeEditorials() {
        $geslibApiDrupalManager = new GeslibApiDrupalManager();
        $editorials = $geslibApiDrupalManager->getEditorialsFromGeslibLines();
        foreach($editorials as $editorial) {
            \Drupal::queue( 'geslib_manage_editorial' )->createItem( $editorial );
            //$geslibApiDrupalManager->storeTerm($editorial, 'editorials');
        }
    }

    /**
     * storeAuthors
     *
     * @return void
     */
    public function storeAuthors() {
        $geslibApiDrupalManager = new GeslibApiDrupalManager();
        $authors = $geslibApiDrupalManager->getAuthorsFromGeslibLines();
        var_dump($authors);
        foreach($authors as $author) {
            \Drupal::queue( 'geslib_manage_author' )->createItem( $author );
        }
    }

}