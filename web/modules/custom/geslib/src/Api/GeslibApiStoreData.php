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
        $geslibApiDrupalTaxonomyManager = new GeslibApiDrupalTaxonomyManager();
        $geslibApiDrupalLinesManager = new GeslibApiDrupalLinesManager();
        $product_categories =  $geslibApiDrupalLinesManager->getProductCategoriesFromGeslibLines();
        foreach($product_categories as $product_category) {
            if(!$product_category || !isset($product_category['name']) || $product_category['name'] == null ) {
                \Drupal::logger('geslib_store_categories')->warning('Category´s name was missing for '. var_export($product_category, TRUE));
                continue;
            }
            \Drupal::logger('geslib_store_categories')->info('Categoría:' .$product_category['name'].', Geslib ID' .$product_category['geslib_id']);
            $geslibApiDrupalTaxonomyManager->storeTerm($product_category, 'product_categories');
        }
    }

    /**
     * storeEditorials
     *
     * @return void
     */
    public function storeEditorials() {
        \Drupal::logger('geslib_store_editorial')->info('Editorial');
        $geslibApiDrupalTaxonomyManager = new GeslibApiDrupalTaxonomyManager;
        $geslibApiDrupalLinesManager = new GeslibApiDrupalLinesManager;
        $editorials = $geslibApiDrupalLinesManager->getEditorialsFromGeslibLines();
        foreach($editorials as $editorial) {
            if( !$editorial || !isset($editorial['name']) || $editorial['name'] == null ) {
                \Drupal::logger('geslib_store_editorials')->warning('Editorial´s name was missing for '. var_export($editorial, TRUE));
                continue;
            }
            \Drupal::logger('geslib_store_editorial')->info('Editorial:' .$editorial['name'].', Geslib ID' .$editorial['geslib_id']);
            $geslibApiDrupalTaxonomyManager->storeTerm($editorial, 'editorials');
        }
    }

    /**
     * storeAuthors
     *
     * @return void
     */
    public function storeAuthors() {
        $geslibApiDrupalTaxonomyManager = new GeslibApiDrupalTaxonomyManager;
        $geslibApiDrupalLinesManager = new GeslibApiDrupalLinesManager;
        $authors = $geslibApiDrupalLinesManager->getAuthorsFromGeslibLines();

        foreach($authors as $author) {
            if(!$author || !isset($author['name']) || $author['name'] == null ){
                \Drupal::logger('geslib_store_authors')->warning('Author´s name was missing for '. var_export($author, TRUE));
             continue;
            }
            \Drupal::logger('geslib_store_authors')->info('Autor:' .$author['name'].', Geslib ID' .$author['geslib_id']);
            $geslibApiDrupalTaxonomyManager->storeTerm($author, 'autores', true);
        }
    }

}