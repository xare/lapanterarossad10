<?php

namespace Drupal\geslib\Api;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\taxonomy\Entity\Vocabulary;
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
     * database
     *
     * @var mixed
     */
    private $database;    
    /**
     * geslibApiSanitize
     *
     * @var mixed
     */
    private $geslibApiSanitize;    
    /**
     * logger
     *
     * @var mixed
     */
    protected $logger;    
    /**
     * output
     *
     * @var mixed
     */
    private $output;    
    /**
     * __construct
     *
     * @param  mixed $logger_factory
     * @return void
     */
    public function __construct( LoggerChannelFactoryInterface $logger_factory ){
        $this->database = \Drupal::database();
        $this->geslibApiSanitize = new GeslibApiSanitize();
        $this->logger = $logger_factory->get('geslib');
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
                    $this->database->insert(self::GESLIB_LOG_TABLE)
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
		$result = $this->database->query(
            $sql
        )->fetchField();
        return $result > 0;
    }
    
    /**
     * getLogQueuedFile
     *
     * @return void
     */
    public function getLogQueuedFile() {
		$table_name = self::GESLIB_LOG_TABLE;
		
		return $this->database
                    ->select($table_name, 'gl')
                    ->fields('gl',['filename'])
                    ->condition('status', 'logged', '=')
                    ->range(0,1)
                    ->execute()
                    ->fetchField();
	}
    
    /**
     * insert2geslibLines
     *
     * @param  array $data_array
     * @return string
     */
    public function insert2geslibLines( array $data_array ):string {
        try{
		    $this->database
                    ->insert( self::GESLIB_LINES_TABLE )
                    ->fields($data_array)
                    ->execute();
            return "the line was inserted in ".self::GESLIB_LINES_TABLE;
        } catch(\Exception $e) {
            return "An error happened while trying to insert a line in ".self::GESLIB_LINES_TABLE. ". The error is " . $e->getMessage() . ".";
        }
	}
    
    /**
     * _readGeslibLinesTable
     *
     * @return void
     */
    private function _readGeslibLinesTable() {
		$table_name = self::GESLIB_LINES_TABLE;
		$query = $this->database->select($table_name);
		$results = $this->database->get_results($query);
		
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
     * @return mixed
     */
    public function updateGeslibLines( $geslib_id, $type, $content):mixed {
		try {
			$this->database
                ->update(self::GESLIB_LINES_TABLE)
                ->fields(['content' => $content])
                ->condition('geslib_id', $geslib_id, '=')
                ->condition('entity', $type,'=')
                ->execute();
                return "The " . $type . " item with geslib_id " . $geslib_id ." has been updated";
		} catch( \Exception $e ) {
			echo "Un error ha ocurrido al intentar actualizar la tabla". self::GESLIB_LINES_TABLE. " :  ".$e->getMessage() ;
		}
	}
    
    /**
     * insertProductData
     *
     * @param  array $content_array
     * @param  string $action
     * @param  int $log_id
     * @return mixed
     */
    public function insertProductData(array $content_array, string $action, int $log_id) :mixed {
		try{
            $this->database
                ->insert(self::GESLIB_LINES_TABLE)
                ->fields([
                            'log_id' => $log_id,
                            'geslib_id' => $content_array['geslib_id'],
                            'action' => $action,
                            'entity' => 'product',
                            'content' => json_encode($content_array),
                            'queued' => 1
                        ])
                ->execute();
                    return "The product data was successfully inserted to geslib lines";
                } catch(\Exception $e) {
                    return "The product data could not be inserted to geslib Lines ".$e->getMessage();
                }
	}
    
    /**
     * getLogId
     *
     * @param  string $filename
     * @return void
     */
    public function getLogId( string $filename ) {
        return $this->database
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
        return $this->database
                ->select('geslib_' . $table, 't')
                ->countQuery()
                ->execute()
                ->fetchField();
	}
    
    /**
     * getEditorialsFromGeslibLines
     * Get Editorials from Geslib Lines
     *
     * @return void
     */
    public function getEditorialsFromGeslibLines() {

        // A prefix is not needed as Drupal automatically adds it to all table names.
        return $this->database
                ->select(self::GESLIB_LINES_TABLE, 't')
                ->fields('t')
                ->condition('entity', 'editorial')
                ->execute()
                ->fetchAll();
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
    
    /**
     * storeProductCategories
     *
     * @param  mixed $product_category
     * @return void
     */
    public function storeProductCategories($product_category) {
        $term_name = $this->geslibApiSanitize->utf8_encode($product_category->content);
        $term_description = $term_name;
        $geslib_id = $product_category->geslib_id;
        // Load the taxonomy term by name from the 'product_categories' vocabulary
        $terms = \Drupal::entityTypeManager()
                ->getStorage('taxonomy_term')
                ->loadByProperties([
                    'name' => $term_name,
                    'vid' => 'product_categories',
                ]);
    
        if (empty($terms)) {
            // Create a new term
            $term = \Drupal\taxonomy\Entity\Term::create( [
                'name' => $term_name,
                'vid' => 'product_categories',
                'description' => [
                    'value' => 'Imported category',
                    'format' => 'plain_text',
                ],
                // You may need to adjust this if the pathauto module is installed
                'path' => [
                    'alias' => '/' . strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', \Drupal::transliteration()->transliterate($term_name, 'es'))),
                ],
                'geslib_id' => $product_category->geslib_id,
            ] );
    
            // Validate the term
            $violations = $term->validate();
    
            if ($violations->count() > 0) {
                // Handle error here
                foreach ($violations as $violation) {
                    \Drupal::messenger()->addError($violation->getMessage());
                }
    
                return null;
            } else {
                // Save the term
                $term->save();
    
                return $term;
            }
        } else {
            \Drupal::messenger()->addMessage(t('Category already exists'));
            
            return null;
        }
    }
    
    /**
     * storeEditorials
     *
     * @param  mixed $editorial
     * @return void
     */
    public function storeEditorials( $editorial ) {
        $term_name = $this->geslibApiSanitize->utf8_encode($editorial->content);
        $term_description = $term_name;
        $geslib_id = $editorial->geslib_id;
    
        // Check if the term already exists
        $query = \Drupal::entityQuery('taxonomy_term');
        $query->condition('name', $term_name)
              ->condition('vid', 'editorials')
              ->accessCheck(FALSE);  // add this line
        $tids = $query->execute();
    
        if (!empty($tids)) {
            // If term exists, update it
            $term = \Drupal::entityTypeManager()
                        ->getStorage('taxonomy_term')
                        ->load(reset($tids)); // add this line
            $term->setDescription($term_description);
            $term->set('geslib_id', $geslib_id); // assuming field_geslib_id is your custom field
        } else {
            // Otherwise, create a new term
            $term = \Drupal\taxonomy\Entity\Term::create([
              'name' => $term_name,
              'vid' => 'editorials',
              'description' => [
                'value' => $term_description,
                'format' => 'basic_html', // or whatever text format you like
              ],
              'geslib_id' => $geslib_id, // assuming field_geslib_id is your custom field
            ]);
        }    
        // Save the term
        $term->save();
        $violations = $term->validate();
        if ($violations) {
            // Handle errors here
            foreach ($violations as $violation) {
                \Drupal::messenger()->addError($violation->getMessage());
                $this->logger->error($violation->getMessage());
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
		$table = self::GESLIB_LINES_TABLE;

		$query = $this->database
                    ->select($table, 't')
                    ->fields('t')
                    ->condition('t.action', 'A')
                    ->condition('t.entity','product')
                    ->execute();
        $product_geslib_lines = $query->fetchAll(PDO::FETCH_OBJ);
        $i = 0;
        $limit = count($product_geslib_lines);
		foreach($product_geslib_lines as $product_geslib_line) {
            if( $i < $limit )
			    $this->storeProduct($product_geslib_line->geslib_id, $product_geslib_line->content);
            else
                return;
            $i++;
        }
	}
    
    /**
     * storeProduct
     *
     * @param  int $geslib_id
     * @param  mixed $content
     * @return void
     */
    public function storeProduct( int $geslib_id, $content ) {
        $store = \Drupal::service('commerce_store.default_store_resolver')->resolve();
        $store_id = $store->id();

        $content = json_decode($content, true);
        $ean = $content['ean'];
        $author = $content['author'];

        /*
        * AÑADIR AUTOR
        */
        if(null !== $author) {
            $vocabulary = \Drupal\taxonomy\Entity\Vocabulary::load('autores');
            
            // Assuming $vocabulary is your Vocabulary object and $author is the term name you want to check.
            $author_exists = \Drupal::entityQuery('taxonomy_term')
                            ->condition('vid', $vocabulary->id())
                            ->condition('name', $author)
                            ->accessCheck(FALSE)
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
                    \Drupal::messenger()->addMessage( 'Impossible to save the author: '.$exception->getMessage() );
                    \Drupal::logger('geslib')->notice( 'Impossible to save the author: '.$exception->getMessage() );
                }
            } else {
                $author_term = Term::load(reset($author_exists));
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
                                'format' => 'full_html']); 
                                // or whatever text format you like
        $product->set('field_ean', $ean);

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
                      'target_id' => $term->id(),
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
                var_dump("Key: $key, Category ID: $category_geslib_id"); // Add this line to check values
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

		return $this->database->select(self::GESLIB_LINES_TABLE,'gl')
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
     * @return void
     */
    public function getProductCategoriesFromGeslibLines() {
        return $this->database
                ->select(self::GESLIB_LINES_TABLE, 't')
                ->fields('t')
                ->condition('entity', 'product_cat')
                ->execute()
                ->fetchAll();
    }
    
    /**
     * emptyGeslibLines
     *
     * @return void
     */
    public function emptyGeslibLines(){
        try {
            $this->database->truncate('geslib_lines')->execute();
        } catch (\Exception $exception){
            \Drupal::messenger()->addError('Could not empty geslib_lines table: ' . $exception->getMessage());
                $this->logger->error('Could not empty geslib_lines table: ' . $exception->getMessage());
        }
    }
    
    /**
     * setGeslibLogQueued
     *
     * @return void
     */
    public function setGeslibLogQueued() {
        try {
            $this->database->update('geslib_log')
                ->fields( [ 
                    'status' => 'queued',
                    'end_date' => date('Y-m-d H:i:s'),
                    ] )
                ->condition('status', 'logged')
                ->execute();
            \Drupal::messenger()->addError('geslib Logger status to queued');
            $this->logger->error('geslib Logger status to queued');
        } catch (\Exception $exception){
            \Drupal::messenger()->addError('Could not set geslib_log queue to 1: '. $exception->getMessage());
            $this->logger->error('Could not set geslib_log queue to 1: '.$exception->getMessage());
        }
    }

    public function deleteProducts() {
         // Bootstrap Drupal.
        
         $product_storage = \Drupal::entityTypeManager()
         ->getStorage( 'commerce_product' );

        $batchBuilder = new BatchBuilder();
            $batchBuilder->setTitle( 'Deleting Commerce products' );
            $batchBuilder->setFinishCallback( [ $this, 'deleteAllProductsFinish' ] );
            $batchBuilder->setInitMessage( 'Starting deletion of Commerce products...' );
            $batchBuilder->setProgressMessage( 'Deleting Commerce products %percentage% completed.' );
            $batchBuilder->setErrorMessage( 'An error occurred while deleting Commerce products.' );
        $products = $product_storage->getQuery()->accessCheck(FALSE)->execute();

        $batch_operation = '';
        // Determine how many products to delete at once.
        $batchSize = 100;
        $productsChunks = array_chunk($products, $batchSize);
        foreach ($productsChunks as $productsChunk) {
            $batchBuilder->addOperation([$this, 'deleteProducts'], [$productsChunk]);
        }

        $batch = $batchBuilder->toArray();
        batch_set($batch);
        drush_backend_batch_process();
    }

}