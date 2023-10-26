<?php

namespace Drupal\geslib\Api;

use Drupal\commerce_product\Entity\Product;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\taxonomy\Entity\Term;
use PDO;

/**
 * GeslibApiDrupalManager
 */
class GeslibApiDrupalManager {
    const GESLIB_LINES_TABLE = 'geslib_lines';
    const GESLIB_LOG_TABLE = 'geslib_log';

    static $geslibLinesKeys = [
		'log_id', // int relation oneToMany with geslib_log
		'geslib_id', // int
		'filename', // string inter000
		'action', // string insert|update|delete
		'entity', // string product | category | author | publisher
		'content', // json
		'queued' // boolean 0|1
	];
    static $geslibLogKeys = [
		'filename', // string inter000
		'start_date', // date
		'end_date', // date
		'lines_count', // int number of lines
		'status', // string waiting | enqueued | processed
	];

    /**
     * geslibApiSanitize
     *
     * @var mixed
     */
    private $geslibApiSanitize;

    /**
     * __construct
     *
     * @return void
     */
    public function __construct( ){
        $this->geslibApiSanitize = new GeslibApiSanitize();
    }
    /**
     * insertLogData
     *
     * @param  mixed $filename
     * @param  mixed $status
     * @param  mixed $linesCount
     * @return void
     */
    public function insertLogData( $filename, $status, $linesCount  ) {
        // 1) Check that this filename has never been stored to geslib_log table

        if($this->isFilenameExists($filename)) {
            return "This file is already in the geslib_log table.";
        } else {
            $geslibLogValues = [
                $filename,
                date('Y-m-d H:i:s'),
                null,
                $linesCount,
                $status
            ];
            try {
                $insertArray = array_combine(self::$geslibLogKeys, $geslibLogValues);
                    \Drupal::database()->insert(self::GESLIB_LOG_TABLE)
                        ->fields( $insertArray)
                        ->execute();
                return "This file has been inserted to the geslib_log table.";
            } catch(\Exception $e) {
                return "This file has not been properly inserted into the database due to an error: ".$e->getMessage();
            }
        }
	}


	/**
     * Check if the filename exists in the wpgeslib_log table.
     *
     * @param string $filename
     * @return bool
     */
    public function isFilenameExists($filename) {
		$table = self::GESLIB_LOG_TABLE;
        $sql = "SELECT COUNT(*)
            FROM {$table}
            WHERE filename = '{$filename}'";
		$result = \Drupal::database()->query(
            $sql
        )->fetchField();
        return $result > 0;
    }

    /**
     * getLogLoggedFile
     *
     * @return string
     */
    public function getLogLoggedFile() :string {
		return \Drupal::database()
                    ->select(self::GESLIB_LOG_TABLE, 'glog')
                    ->fields('glog',['filename'])
                    ->condition('status', 'logged', '=')
                    ->range(0,1)
                    ->execute()
                    ->fetchField();
	}

    /**
     * getLogLoggedId
     *
     * @return int
     */
    public function getGeslibLogLoggedId() :int {
		return \Drupal::database()
                    ->select(self::GESLIB_LOG_TABLE, 'gl')
                    ->fields('gl',['id'])
                    ->condition('status', 'logged', '=')
                    ->range(0,1)
                    ->execute()
                    ->fetchField();
	}

    /**
     * setGeslibLog
     *
     * @param int $log_id
     * @param string $status
     *
     * @return void
     */
    public function setGeslibLog(int $log_id, string $status) {
        $date = ($status == 'processed') ? date('Y-m-d H:i:s'): NULL;
        try {
            \Drupal::database()->update(self::GESLIB_LOG_TABLE)
                ->fields( [
                    'status' => $status,
                    'end_date' => $date,
                    ] )
                ->condition('id', $log_id)
                ->execute();
            \Drupal::messenger()->addError('geslib Logger status to queued');
            \Drupal::logger('geslib')->error('geslib Logger status to queued');
        } catch (\Exception $exception){
            \Drupal::messenger()->addError('Could not set geslib_log queue to 1: '. $exception->getMessage());
            \Drupal::logger('geslib')->error('Could not set geslib_log queue to 1: '.$exception->getMessage());
        }
    }
    /**
     * _readGeslibLinesTable
     *
     * @return void
     */
    private function _readGeslibLinesTable() {
		$query = \Drupal::database()->select(self::GESLIB_LINES_TABLE);
		$results = \Drupal::database()->get_results($query);
        if( count($results) == 0 ) return false;
		foreach( $results as $result ) {
			$this->_storeData(
                $result->type,
                $result->id,
                $result->content );
		}
    }

    /**
     * updateGeslibLines
     *
     * @param  int $geslib_id
     * @param  string $type
     * @param  mixed $content
     * @return string
     */
    public function updateGeslibLines( $geslib_id, $type, $content) :string {
		try {
			\Drupal::database()
                ->update(self::GESLIB_LINES_TABLE)
                ->fields(['content' => $content])
                ->condition('geslib_id', $geslib_id, '=')
                ->condition('entity', $type,'=')
                ->execute();
                return "The " . $type . " item with geslib_id " . $geslib_id ." has been updated";
		} catch( \Exception $e ) {
			return "Un error ha ocurrido al intentar actualizar la tabla". self::GESLIB_LINES_TABLE. " :  ".$e->getMessage() ;
		}
	}

    /**
     * insertData
     * @param array $content_array
     * @param string $action
     * @param int $log_id
     * @param string $entity
     * @return string
     */
    public function insertData(
        array $content_array,
        string $action,
        int $log_id,
        string $entity ) :string {
		try {
            \Drupal::database()
                ->insert(self::GESLIB_LINES_TABLE)
                ->fields([
                            'log_id' => $log_id,
                            'geslib_id' => $content_array['geslib_id'],
                            'action' => $action,
                            'entity' => $entity,
                            'content' => json_encode($content_array),
                            'queued' => 1
                        ])
                ->execute();
                return "The ".$entity." data was successfully inserted to geslib lines";
        } catch(\Exception $e) {
            return "The ".$entity." data could not be inserted to geslib Lines ".$e->getMessage();
        }
	}

    /**
     * getLogId
     *
     * @param  string $filename
     * @return mixed
     */
    public function getLogId( string $filename ) :mixed {
        return \Drupal::database()
                    ->select(self::GESLIB_LOG_TABLE, 't')
                    ->fields('t',['id'])
                    ->condition('t.filename', $filename, '=')
                    ->execute()
                    ->fetchField();
	}

	/**
	 * countRows
     * Count the number of rows in the geslib_log and geslib_lines tables
	 *
	 * @param  string $table
	 * @return void
	 */
	public function countRows($table){
        return \Drupal::database()
                ->select('geslib_' . $table, 't')
                ->countQuery()
                ->execute()
                ->fetchField();
	}

    /**
     * getEditorialsFromGeslibLines
     * Get Editorials from Geslib Lines
     *
     * @return mixed
     */
    public function getEditorialsFromGeslibLines():mixed {
        try {
            return \Drupal::database()
                    ->select(self::GESLIB_LINES_TABLE, 't')
                    ->fields('t')
                    ->condition('entity', 'editorial')
                    ->execute()
                    ->fetchAll();
        } catch (\Exception $exception){
            \Drupal::logger('geslib')->error('Function getEditorialsFromGeslibLines ' . $exception->getMessage());
            return FALSE;
        }
    }

    /**
     * getAuthorsFromGeslibLines
     * Get Authors from Geslib Lines
     *
     * @return mixed
     */
    public function getAuthorsFromGeslibLines():mixed {
        try {
            return \Drupal::database()
                    ->select(self::GESLIB_LINES_TABLE, 't')
                    ->fields('t')
                    ->condition('entity', 'autor')
                    ->execute()
                    ->fetchAllAssoc('entity',\PDO::FETCH_BOTH);
        } catch (\Exception $exception){
            \Drupal::logger('geslib')->error('Function getAuthorsFromGeslibLines ' . $exception->getMessage());
            return FALSE;
        }
    }

    /**
     * _storeData
     *
     * @param  string $type
     * @param  int $geslib_id
     * @param  mixed $content
     * @return void
     */
    private function _storeData( string $type, int $geslib_id, $content ) {
		$store_data=[];
		$function_name = 'store'.$type[0];
		if ( method_exists( $this, $function_name )) {
			$store_data[] = $this->{$function_name}( $geslib_id, $content );
		} else {
			$store_data[] = 'EMPTY';
		}

      	return $store_data;
    }

    public function storeTerm($content, $vocabulary, $sanitize = true) {
        $content = is_string($content) ? json_decode($content, true) : $content;
        $term_name = $sanitize ? $this->geslibApiSanitize->utf8_encode($content['name']) : $content['name'];
        $term_description = $content['description'] ?? $term_name;
        $geslib_id = $content['geslib_id'];

        // Check if the term already exists
        $query = \Drupal::entityQuery('taxonomy_term');
        $query->condition('name', $term_name)
              ->condition('vid', $vocabulary)
              ->accessCheck(FALSE);
        $tids = $query->execute();

        $term = !empty($tids) ? \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load(reset($tids)) : null;

        if (!$term) {
            // Create a new term
            $term = \Drupal\taxonomy\Entity\Term::create([
                'name' => $term_name,
                'vid' => $vocabulary,
                'description' => [
                    'value' => $term_description,
                    'format' => 'basic_html',
                ],
                'geslib_id' => $geslib_id,
            ]);
        } else {
            // Update existing term
            $term->setDescription($term_description);
            $term->set('geslib_id', $geslib_id);
        }

        // Save and validate the term
        $term->save();
        $violations = $term->validate();
        if ($violations) {
            foreach ($violations as $violation) {
                \Drupal::messenger()->addError($violation->getMessage());
                \Drupal::logger('geslib')->error($violation->getMessage());
            }
            return $violations;
        }

        return $term;
    }

    /**
     * storeProducts
     *
     * @return void
     */
    public function storeProducts() {
        // Create a queue for storing products.
        $actions = [
            ['A', 'M'], // Add and Modify
            ['B']       // Delete
        ];
        foreach ($actions as $actionSet) {
		    $query = \Drupal::database()->select( self::GESLIB_LINES_TABLE, 't' )
                        ->fields( 't' )
                        ->condition( 't.action', $actionSet, 'IN' )
                        ->condition( 't.entity', 'product' )
                        ->execute();
            $lines = $query->fetchAll( PDO::FETCH_OBJ );
            var_dump($lines);
            if ( count( $lines ) == 0) return FALSE;

            foreach( $lines as $line ) {
                $item = [
                    'action' => $line->action,
                    'geslib_id' => $line->geslib_id,
                ];

                if ( in_array( $line->action, ['A', 'M'] ) ) {
                    $item['content'] = $line->content;
                }

                \Drupal::queue( 'geslib_manage_product' )->createItem( $item );
            }
	    }
        return $lines;
    }
    /**
     * storeProduct
     *
     * @param  int $geslib_id
     * @param  mixed $content
     * @return void
     */
    public function storeProduct( int $geslib_id, $content ) {
        $geslibApi = new GeslibApi();
        $store = \Drupal::service( 'commerce_store.default_store_resolver' )->resolve();
        $store_id = $store->id();

        $content = json_decode( $content, true );
        var_dump( $content[ 'isbn' ] );
        $ean = $content['ean'];
        $isbn = $content['isbn'];
        $author = $content['author'];
        $año = $content['año_primera_edicion'];

        /**
        * AÑADIR AUTOR
        */
        if(null !== $author) {
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
                } catch(\Exception $exception){
                    $geslibApi->reportThis( 'Impossible to save the author: ' . $exception->getMessage() );
                }
            } else {
                $author_term = Term::load( reset( $author_exists ) );
            }

            // Get the term ID.
            $author_term_id = $author_term->id();
        }

        $num_paginas = $content['num_paginas'];
        $editorial_geslib_id = $content['editorial'];
        $book_name = $content['description'];
        $book_description = $content['sinopsis'] ?? '';
        $book_price = (float)str_replace(',', '.', $content['pvp']);

        $product_storage = \Drupal::entityTypeManager()->getStorage('commerce_product');
        $variation_storage = \Drupal::entityTypeManager()->getStorage('commerce_product_variation');

        $existing_product = $product_storage->loadByProperties(['title' => $book_name]);

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

            return $product;
        } catch ( \Exception $exception ) {
            \Drupal::messenger()->addMessage( 'Impossible to save the product: '.$exception->getMessage() );
            \Drupal::logger('geslib')->notice( 'Impossible to save the product: '.$exception->getMessage() );
        }
    }

    /**
     * _create_slug
     *
     * @param  string $term_name
     * @return string
     */
    private function _create_slug( string $term_name ):string {
		// convert to lowercase
		$term_name = strtolower($term_name);

		// remove punctuation
		$term_name = preg_replace("/[.,:;!?(){}[\]<>%$#@^*+=|~`]/", "", $term_name);

		// replace spaces with underscores
		$term_slug = str_replace(" ", "_", $term_name);

		return $term_slug;
  	}

    /**
     * fetchContent
     *
     * @param  ing $geslib_id
     * @param  string $type
     * @return void
     */
    public function fetchContent( int $geslib_id, string $type ) {

		return \Drupal::database()->select(self::GESLIB_LINES_TABLE,'gl')
                    ->fields('gl',['content'])
                    ->condition('geslib_id', $geslib_id, '=')
                    ->condition('entity', $type, '=')
                    ->range( 0, 1 )
                    ->execute()
                    ->fetchField();
	}

    /**
     * getProductCategoriesFromGeslibLines
     *
     * @return mixed
     */
    public function getProductCategoriesFromGeslibLines() :mixed {
        try {
            return \Drupal::database()
                ->select(self::GESLIB_LINES_TABLE, 't')
                ->fields('t')
                ->condition('entity', 'product_cat')
                ->execute()
                ->fetchAll();
        } catch( \Exception $exception ) {
            \Drupal::logger('geslib')->error( 'Function getProductCategoriesFromGeslibLines' . $exception->getMessage());
            return FALSE;
        }
    }

    /**
     * emptyGeslibLines
     *
     * @return void
     */
    public function emptyGeslibLines(){
        try {
            \Drupal::database()->truncate('geslib_lines')->execute();
        } catch (\Exception $exception){
            \Drupal::messenger()->addError('Could not empty geslib_lines table: ' . $exception->getMessage());
                \Drupal::logger('geslib')->error('Could not empty geslib_lines table: ' . $exception->getMessage());
        }
    }

    /**
     * deleteAllProducts
     *
     * @return void
     */
    public function deleteAllProducts():int {
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
     * @return void
     */
    public function deleteProducts($product_ids, &$context) {
        \Drupal::logger('geslib')->notice('Inside Delete Products');
        var_dump('inside deleteProducts');
        var_dump($product_ids);
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
     * @param  mixed $geslib_id
     * @return void
     */
    public function deleteProduct( int $geslib_id ) {
        // Query to find the product based on the geslib_id field
        $query = \Drupal::entityQuery( 'commerce_product' )
          ->condition( 'field_geslib_id', $geslib_id )
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

    public function deleteProductById( int $product_id ){
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

    public function deleteEditorials() {
        $this->deleteTaxonomy('editorials');
    }
    public function deleteProductCategories() {
        $this->deleteTaxonomy('product_categories');
    }
    /**
     * deleteTaxonomy
     *
     * @param  mixed $taxonomy_name
     * @return void
     */
    private function deleteTaxonomy($taxonomy_name) {
        $terms = \Drupal::entityTypeManager()
                    ->getStorage('taxonomy_term')
                    ->loadTree($taxonomy_name, 0, NULL, TRUE);

        if (empty($terms)) {
            \Drupal::messenger()->addMessage('Terms are already deleted.');
            \Drupal::logger('geslib')->error('Terms are already deleted.');
        }

        foreach ($terms as $term) {
            \Drupal::messenger()->addMessage('Term Deleted: '.$term->id.' - '.$term->title);
            \Drupal::logger('geslib')->error('Term Deleted: '.$term->id.' - '.$term->title);
            $term->delete();
        }

        \Drupal::messenger()->addMessage('All terms in the '.$taxonomy_name.' taxonomy have been deleted.');
        \Drupal::logger('geslib')->error('All terms in the '.$taxonomy_name.' taxonomy have been deleted.');
    }

    public function truncateGeslibLines() {
        $database = \Drupal::database();
        $table_name = 'geslib_lines';
        try {
            $database->truncate( $table_name )->execute();
            \Drupal::messenger()->addMessage(t('@table has been emptied.', [ '@table' => $table_name ]));
            \Drupal::logger('geslib')->error(t('@table has been emptied.', [ '@table' => $table_name ]));
        } catch(\Exception $e) {
            \Drupal::messenger()->addMessage(t('Could not truncate table @table. Make sure the table name is correct.', [ '@table' => $table_name ]) );
            \Drupal::logger('geslib')->error(t('Could not truncate table @table. Make sure the table name is correct.', [ '@table' => $table_name ]) );
        }
    }

    public function fetchLoggedFilesFromDb() {
		return \Drupal::database()
                    ->select( self::GESLIB_LOG_TABLE, 'gl' )
                    ->fields('gl', ['filename', 'status'])
                    ->execute()
                    ->fetchAll(PDO::FETCH_ASSOC);
	}
}