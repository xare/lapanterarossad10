<?php

namespace Drupal\geslib\Api;

use Drupal\commerce_product\Entity\Product;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\taxonomy\Entity\Term;
use PDO;

/**
 * GeslibApiDrupalProductsManager
 */

class GeslibApiDrupalProductsManager extends GeslibApiDrupalManager
{
    /**
     * storeProducts
     *
     * @return void
     */
    public function storeProducts() {
        $database = \Drupal::database();
        // Define the table names
        $geslibLinesTable = self::GESLIB_LINES_TABLE;
        $geslibApiDrupalQueueManager = new GeslibApiDrupalQueueManager;
        // Define actions
        $actions = ['A', 'M', 'B','stock']; // Add, Modify, Delete
        //foreach ($actions as $actionSet) {
            // Query to select lines based on action and entity
            $query = $database->select($geslibLinesTable, 'gl')
                             ->fields('gl')
                            // ->condition('action', $actionSet)
                             ->condition('entity', 'product');
            $lines = $query->execute()->fetchAll();
            \Drupal::logger('geslib_store_products')->info('The geslib Lines contain at present '. count($lines));
            // Batch processing
            $batch_size = 1000;
            $batch = [];
            if (count($lines) == 0) {
                \Drupal::logger('geslib_store_products')->info('No lines where found on the geslib_lines table');
                return;
            }
            foreach ($lines as $line) {
                \Drupal::logger('geslib_store_products')->info('inside of the lines loop inside of storeproducts GESLIB ID: '. $line->geslib_id.' ACTION: '.$line->action);
                $item = [
                    'geslib_id' => $line->geslib_id,
                    'log_id' => $line->log_id,
                    'data' => $line->content,
                    'type' => 'store_products',
                    'action'=> $line->action,
                ];

                $batch[] = $item;

                if (count($batch) >= $batch_size) {
                    $geslibApiDrupalQueueManager->insertProductsIntoQueue($batch);
                    $batch = [];
                }
            }
            // Process the last batch
            if (!empty($batch)) {
                $geslibApiDrupalQueueManager->insertProductsIntoQueue($batch);
            }
        //}
    }
    /**
     * storeProduct
     * Called from
     * - GeslibApiDrupalProductManager::storeProduct
     * - ManageProduct:processItem
     *
     * @param  int $geslib_id
     * @param  mixed $content
     * @return mixed
     */
    public function storeProduct( int $geslib_id, $content ): mixed {
        $geslibApi = new GeslibApi();
        $store = \Drupal::service( 'commerce_store.default_store_resolver' )
                    ->resolve();
        \Drupal::logger('geslib_store_product')->info('Store products. JSON content: ' . $content );
        $content = json_decode( $content, true );

        \Drupal::logger('geslib_store_product')->info( 'Inside storeProduct. Geslib_id: '
                                    . $geslib_id .
                                    '. Action: ' . $content['action'] );
        // IN CASE EVERYTHING WAS WRONG
        if ($content === NULL ||
            $content == '' ||
            (isset($content['action']) && $content['action'] == 'B')) {
            \Drupal::logger('geslib')->warning('Required fields missing, cannot store product. Action: '. $content['action']);
            if( $content['action'] == 'B' ) $this->deleteProduct($geslib_id);
            return null; // Or handle it as per your requirement
        }

        $ean = $content['ean'];
        $isbn = $content['isbn'];
        $author = $content['author'];
        $año = $content['año_primera_edicion'];
        $peso = $content['peso'];

        /*
         * AÑADIR AUTOR
         */
        if(null !== $author) {
            \Drupal::logger('geslib_store_product')->info("Autor: " .$author);
            $vocabulary = \Drupal\taxonomy\Entity\Vocabulary::load( 'autores' );

            // Assuming $vocabulary is your Vocabulary object and $author is the term name you want to check.
            $author_exists = \Drupal::entityQuery( 'taxonomy_term' )
                            ->condition( 'vid', $vocabulary->id() )
                            ->condition( 'name', $author )
                            ->accessCheck( FALSE )
                            ->execute();
            if (empty($author_exists)) {
                // Create a new author.
                try {
                    $author_term = \Drupal\taxonomy\Entity\Term::create([
                                        'vid' => $vocabulary->id(),
                                        'name' => $author,
                                    ]);
                    // Save the term.
                    $author_term->save();
                    \Drupal::logger('geslib_store_product')->info('Author '.$author.' was succesfully saved in '.$vocabulary->id());
                } catch(\Exception $exception){
                    $geslibApi->reportThis( 'Impossible to save the author: ' . $exception->getMessage() );
                }
            } else {
                $author_term = Term::load( reset( $author_exists ) );
            }

            // Get the term ID.
            $author_term_id = $author_term->id();
            \Drupal::logger('geslib_store_product')->info("Autor Term ID: " .$author_term_id);
        }

        $num_paginas = $content['num_paginas'];
        $editorial_geslib_id = $content['editorial'];
        $book_name = $content['description'];
        if ( empty($book_name) ) {
            \Drupal::logger('geslib_store_product')->warning('Book name is empty, cannot proceed. We exit the store_product function');
            return null;
        }

        $book_description = $content['sinopsis'] ?? '';
        $book_price = (float)str_replace(',', '.', $content['pvp']);

        $product_storage = \Drupal::entityTypeManager()
                            ->getStorage('commerce_product');
        $variation_storage = \Drupal::entityTypeManager()
                            ->getStorage('commerce_product_variation');

        $existing_product = (array) $product_storage
                            ->loadByProperties(['field_geslib_id_producto' => $geslib_id]);
        //$existing_product = $product_storage->loadByProperties(['field_geslib_id_producto' => $geslib_id]);

        if ($existing_product) {
            // If product exists, get an instance of Product for the existing product
            $product = reset($existing_product);
            $variation = $variation_storage->load($product->getVariationIds()[0]);
        } else {
            // If product does not exist, create a new instance of Product and ProductVariation
            $product = $product_storage->create([
              'type' => 'default',
              'stores' => [$store],
              'title' => $book_name,
              'field_ean' => $ean,
              'field_isbn' => $isbn,
              'field_ano_1a_edicion' => $año,
              'field_geslib_id_producto' => $geslib_id,
            ]);

            $variation = $variation_storage->create([
              'type' => 'default',
              'sku' => $ean, // replace with your SKU logic
              'weight'=> $peso,
              'status' => 1,
              'price' => new \Drupal\commerce_price\Price($book_price, 'EUR'), // replace 'USD' with your currency code
            ]);

            $product->addVariation($variation);
        }

        // Set or update product data
        $product->set('body', [
                                'value' => $book_description,
                                'format' => 'full_html' ]);
                                // or whatever text format you like
        $product->set('field_ean', $ean);
        $product->set('field_isbn', $isbn);
        $product->set('field_ano_1a_edicion', $año);
        $product->set('field_geslib_id_producto', $geslib_id);

        if(null !== $author && null !== $author_term_id)
            $product->get('field_autor')->setValue($author_term_id);

        $product->set('field_num_paginas', $num_paginas);

        // Save the product variation and product to the database
        /*
        * AÑADIR EDITORIAL
        */
        if (null !== $editorial_geslib_id) {
            // Load the taxonomy terms by the field value of 'geslib_id'.
            $editorials = \Drupal::entityTypeManager()
                    ->getStorage('taxonomy_term')
                    ->loadByProperties([
                        'vid' => 'editorials',
                        'geslib_id' => $editorial_geslib_id,
                    ]);
            if ( $editorials ) {
                $editorial = reset($editorials);
                // Make sure we got a Term object before trying to get its ID.
                if ($editorial instanceof Term) {
                    $product->set('field_editorial', [
                      'target_id' => $editorial->id(),
                    ]);
                  }
            }
        }
        /*
        * AÑADIR CATEGORIA
        */
        if (!empty($content['categories'])) {
            $category_ids = [];
            foreach ($content['categories'] as $key => $value) {
                $category_geslib_id = intval($key);
               //var_dump("Key: $key, Category ID: $category_geslib_id"); // Add this line to check values
                // Load the term by the geslib_id field
                $categories = \Drupal::entityTypeManager()
                        ->getStorage('taxonomy_term')
                        ->loadByProperties([
                            'vid' => 'product_categories',
                            'geslib_id' => $category_geslib_id
                        ]);
                // If the term is found, retrieve its ID and add to the $category_ids array
                if ($categories) {
                    $category = reset($categories);  // Since loadByProperties returns an array, just get the first result.
                    $category_ids[] = $category->id();
                }
            }

            // Set the term IDs for the product
            if (!empty($category_ids)) {
                $product->set('field_categoria', $category_ids);
            }
        }
        try {
            $variation->save();
            $product->save();
            \Drupal::logger('geslib_store_product')->notice( 'Product '.$geslib_id.' was saved.' );
            return $product;
        } catch ( \Exception $exception ) {
            \Drupal::messenger()->addMessage( 'Impossible to save the product: '.$exception->getMessage() );
            \Drupal::logger('geslib_store_product')->error( 'Impossible to save the product: '.$exception->getMessage() );
        }
    }

    /**
     * deleteAllProducts
     *
     * @return int
     */
    public function deleteAllProducts(): int {
        // Create a queue.
        $queue = \Drupal::queue('geslib_delete_product');
        $product_storage = \Drupal::entityTypeManager()->getStorage('commerce_product');

        $products = $product_storage->getQuery()->accessCheck(FALSE)->execute();
        // Add product IDs to the queue.
        foreach ($products as $product_id) {
            $queue->createItem($product_id);
        }
        return count($products);
    }

    /**
     * deleteProducts
     *
     * @param  mixed $product_ids
     * @param  mixed $context
     * @return bool
     */
    public function deleteProducts($product_ids, &$context): bool {
        \Drupal::logger('geslib')->notice('Inside Delete Products');
        \Drupal::logger('geslib_delete')->notice('Product IDs: @ids', ['@ids' => json_encode($product_ids)]);

        $product_storage = \Drupal::entityTypeManager()
                            ->getStorage('commerce_product');
        $variation_storage = \Drupal::entityTypeManager()
                            ->getStorage('commerce_product_variation');

        foreach($product_ids as $product_id){
            $product = $product_storage->load($product_id);

            if( !$product || !( $product instanceof \Drupal\commerce_product\Entity\ProductInterface) ){
                \Drupal::logger('geslib')->error( "Product not found with product_id: ".$product_id );
                return FALSE;
            }

            try {
                $product->delete();
                $context['results'][] = $product->id();
                $context['message'] = dt('Deleting product @title ',
                                    [
                                        '@title' => $product->get('title')->value,
                                    ]);
                \Drupal::logger('geslib')->info( "Deleting Product: ".$product->get('title')->value );
                return TRUE;
            } catch ( \Exception $exception ) {
                $context['message'] = dt('Unable to delete book: @title ',
                                    [
                                        '@title' => $product->get('title')->value,
                                    ]);
                \Drupal::logger('geslib')->error( "Unable to Delete Product: ".$product->get('title')->value );
                return FALSE;
            }
        }
    }
    /**
     * deleteProduct
     *
     * @param  int $geslib_id
     * @return void
     */
    public function deleteProduct( int $geslib_id ) {
        // Query to find the product based on the geslib_id field
        $query = \Drupal::entityQuery( 'commerce_product' )
          ->condition( 'field_geslib_id_producto', $geslib_id )
          ->accessCheck(FALSE)
          ->range(0, 1);  // Assuming geslib_id is unique, limit to one result

        $result = $query->execute();

        if ( empty( $result ) ) {
            // Log that the product was not found.
            \Drupal::logger('geslib')->warning( 'Product with geslib_id @id not found.', ['@id' => $geslib_id]);
            return FALSE;
        }
        // Get the product ID
        $product_id = reset($result);

        // Load the product entity
        $product = Product::load($product_id);

        if ( !$product ) {
            // Log that no product with the given geslib_id was found.
                \Drupal::logger('geslib')->warning( 'No product with geslib_id @id found.', ['@id' => $geslib_id]);
            return FALSE;
        }
        try {
            // Delete the product
            $product->delete();
        } catch ( EntityStorageException $e ) {
            // Log the exception to watchdog.
            \Drupal::logger('geslib')->error( $e->getMessage() );
            return FALSE;
        }
        return TRUE;
    }


    /**
     * deleteProductById
     *
     * @param  int $product_id
     * @return bool
     */
    public function deleteProductById( int $product_id ): bool{
        $product_storage = \Drupal::entityTypeManager()->getStorage('commerce_product');
        $product = $product_storage->load( $product_id );
        if (!$product) {
            \Drupal::logger( 'geslib' )->error( 'Product did not exist for product_id: ' . $product_id );
            return FALSE;
        }
        try {
            \Drupal::logger( 'geslib' )->notice( 'ProductDeleted: '. $product->get('title')->value );
            $product->delete();
            return TRUE;
        } catch( \Exception $exception ) {
            \Drupal::logger( 'geslib' )->error( 'Unable to delete Product: '. $exception->getMessage() );
            return FALSE;
        }
    }
    /**
     * Updates the stock of a product.
     *
     * @param int $geslib_id
     * @param string $data
     */
    public function stockProduct(int $geslib_id, string $data ): void {
        \Drupal::logger('geslib_stock_product')->info('Inside stockProduct Geslib ID: '.$geslib_id. ' Data: ' .$data );
        $data = json_decode($data, true);
        \Drupal::logger('geslib_stock_product')->info('Inside stockProduct Stock: ' .$data['stock'] );
        $stock = $data['stock'];
        \Drupal::logger('geslib_stock_product')->info('Inside stockProduct Stock2: ' .$stock );
        // Early return if stock is not set or zero
        if ($stock === null || $stock == 0) {
            return;
        }

        // Load the product by custom field 'geslib_id'
        $products = \Drupal::entityTypeManager()
            ->getStorage('commerce_product')
            ->loadByProperties(['field_geslib_id_producto' => $geslib_id]);
        \Drupal::logger('geslib_stock')->info(count($products).' found for geslib_id '.$geslib_id.'.');
        // Assuming 'field_geslib_id' is your custom field that holds geslib_id
        $product = reset($products);

        if ($product) {
            // Assuming 'field_stock' is your stock field on the product entity
            $product->set('field_stock', $stock);

            // Save the product
            try {
                $product->save();
                \Drupal::logger('geslib_stock')->notice('Product with geslib_id '.$geslib_id .' has been saved with stock: ' . $stock);
            } catch (EntityStorageException $e) {
                \Drupal::logger('geslib_stock')->error('Product with geslib_id '.$geslib_id .' has been saved with stock: ' . $stock. ' - '.$e->getMessage());
            }
        } else {
            \Drupal::logger('geslib_stock')->error('Product with geslib_id '.$geslib_id.' not found.');
        }
    }

    /**
     * Get the total number of products (including variations).
     *
     * @return int
     *   The total number of products and variations.
     */
    public function getTotalNumberOfProducts(): int {
        $query = \Drupal::entityQuery('commerce_product')->accessCheck(FALSE);

        // Get the total number of products.
        $total_products = $query->count()->execute();

        // If you need to count product variations separately, you can use a similar query:
        // $variation_query = \Drupal::entityQuery('commerce_product_variation');
        // $total_variations = $variation_query->count()->execute();

        // If you want to include variations in the total count, add them to the total.
        // $total = $total_products + $total_variations;

        return $total_products;
    }

}

