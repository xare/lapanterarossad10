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
     * @return array
     */
    public function fetchAllProducts (): array {
        $dilveApi = new DilveApi();
        $product_ids = \Drupal::entityQuery('commerce_product')
                    ->accessCheck(FALSE)
                    ->execute();

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

    public function createFile(string $uri, string $filename ): mixed {
        $file = File::create([
            'uri' => $uri,
            'uid' => \Drupal::currentUser()->id(),
            'filename' => $filename,
          ]);
          try{
            $file->save();
            // Add file usage so the file won't be deleted on the next cron run.
            $file->setPermanent();
            $file->save();
            \Drupal::logger('dilve')->info('File '
                                        .$filename
                                        .' was saved.');
            return $file;
          } catch(\Exception $exception) {
            \Drupal::logger('dilve')->info('Failed to create the file '
                                        .$filename
                                        .' from url '.$uri
                                        .'. error'. $exception->getMessage());
            return false;
          }
    }

    /**
  	 * set_featured_image_for_product
  	 *
  	 * @param  File $file
  	 * @param  string $ean
  	 * @return void
  	 */
  	function set_featured_image_for_product(File $file, string $ean) {
		$dilveApi = new DilveApi();
		$product_ids = $this->getProductIds($ean);

		foreach ($product_ids as $product_id) {
			$product = \Drupal\commerce_product\Entity\Product::load($product_id);
			try {
				$product->set('field_portada', ['target_id' => $file->id()]);
				$product->save();
				$dilveApi->reportThis('The product with ID @productId, EAN @ean and title @productTitle was correctly saved.','info',
					[
						'@productId' => $product->id,
				  		'@productTitle' => $product->title->value,
				  		'@ean' => $ean
					]
				);
				//In summary, this line of code is registering the usage of a file by a specific entity (in this case, a node of type 'dilve') within the Drupal system. This information can be useful, for example, to track which nodes are using a particular file or to perform cleanup operations when a file is no longer in use.
				\Drupal::service('file.usage')->add($file, 'dilve', 'node', $product_id);
			} catch(\Exception $exception){
				$dilveApi->reportThis('The product was not correctly saved: @exception','error', [ '@exception' => $exception->getMessage() ]);
			}
		}
	}

}