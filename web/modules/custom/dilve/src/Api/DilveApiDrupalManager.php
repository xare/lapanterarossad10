<?php

namespace Drupal\dilve\Api;

use Drupal\file\Entity\File;

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
    public function fetchAllProducts () {
        $dilveApi = new DilveApi();
        $product_ids = \Drupal::entityQuery('commerce_product')
                    ->accessCheck(FALSE)
                    ->execute();
        count($product_ids);
        $dilveApi->reportThis('Total number of products'.count($product_ids));
        return \Drupal::entityTypeManager()
                        ->getStorage('commerce_product')
                        ->loadMultiple($product_ids);
    }

    public function hasCover( $ean ) {
        $dilveApi = new DilveApi();
        $book = $dilveApi->search($ean);
        var_dump($book['cover_url']);
    }

    public function getProductIds( string $ean ) :array{
        $query = \Drupal::entityQuery( 'commerce_product' )
			->condition( 'field_ean.value', $ean )
			->accessCheck( FALSE );
        return $query->execute();
    }

    public function getExistingFiles( string $destination ):array {
        return \Drupal::entityTypeManager()
										->getStorage('file')
										->loadByProperties(['uri' => $destination]);
    }

    public function createFile(string $uri, string $filename ) {
        $file = File::create([
            'uri' => $uri,
            'uid' => \Drupal::currentUser()->id(),
            'filename' => $filename,
          ]);
          $file->save();
          // Add file usage so the file won't be deleted on the next cron run.
          $file->setPermanent();
          $file->save();
          return $file;
    }
}