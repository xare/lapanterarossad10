<?php

namespace Drupal\geslib\Api;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;


use PDO;

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

    private $database;
    private $geslibApiSanitize;
    protected $logger;
    private $output;
    public function __construct( LoggerChannelFactoryInterface $logger_factory ){
        $this->database = \Drupal::database();
        $this->geslibApiSanitize = new GeslibApiSanitize();
        $this->logger = $logger_factory->get('geslib');
    }
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

    public function insert2geslibLines( $data_array ) {
        try{
		    $this->database
                    ->insert( self::GESLIB_LINES_TABLE)
                    ->fields($data_array)
                    ->execute();
            return "the line was inserted in ".self::GESLIB_LINES_TABLE;
        } catch(\Exception $e) {
            return "An error happened while trying to insert a line in ".self::GESLIB_LINES_TABLE. ". The error is " . $e->getMessage() . ".";
        }
	}

    public function _readGeslibLinesTable() {
		$table_name = self::GESLIB_LINES_TABLE;
		$query = $this->database->select($table_name);
		$results = $this->database->get_results($query);
		
		foreach( $results as $result ) {
			$this->_storeData($result->type, $result->id, $result->content);
		}
    }

    public function updateGeslibLines( $geslib_id, $type, $content){
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

    public function insertProductData($content_array, $action, $log_id) {
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
                    return "The product data was successfully inserte to geslib lines";
                } catch(\Exception $e) {
                    return "The product data could not be inserted to geslib Lines ".$e->getMessage();
                }
	}

    public function getLogId($filename){
        return $this->database
                    ->select(self::GESLIB_LOG_TABLE, 't')
                    ->fields('t',['id'])
                    ->condition('t.filename', $filename, '=')
                    ->execute()
                    ->fetchField();
	}

    /** 
	 * Count the number of rows in the geslib_log and geslib_lines tables
	 */

	public function countRows($table){
        return $this->database
                ->select('geslib_' . $table, 't')
                ->countQuery()
                ->execute()
                ->fetchField();
	}

    /**
     * Get Editorials from Geslib Lines
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

    

    private function _storeData( $type, $geslib_id, $content ) {
		$store_data=[];
		$function_name = 'store'.$type[0];
		if (method_exists($this, $function_name)) {
			$store_data[] = $this->{$function_name}($geslib_id,$content);
		} else {
			$store_data[] = 'EMPTY';
		}
      
      	return $store_data;
    }

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
        $limit = 100;
		foreach($product_geslib_lines as $product_geslib_line) {
            if( $i < $limit )
			    $this->storeProduct($product_geslib_line->geslib_id, $product_geslib_line->content);
            else
                return;
            $i++;
        }
	}
    public function storeProduct($geslib_id, $content) {
        $store = \Drupal::service('commerce_store.default_store_resolver')->resolve();
        $store_id = $store->id();

        $content = json_decode($content, true);
        $ean = $content['ean'];
        $author = $content['author'];
        if(null !== $author) {
            $vocabulary = \Drupal\taxonomy\Entity\Vocabulary::load('autores');
            
            // Assuming $vocabulary is your Vocabulary object and $author is the term name you want to check.
            $term_exists = \Drupal::entityQuery('taxonomy_term')
                            ->condition('vid', $vocabulary->id())
                            ->condition('name', $author)
                            ->accessCheck(FALSE)
                            ->execute();
            if (empty($term_exists)) {
                // Create a new term.
                $author_term = \Drupal\taxonomy\Entity\Term::create([
                                    'vid' => $vocabulary->id(),
                                    'name' => $author,
                                ]);
                // Save the term.
                $author_term->save();
            } else {
                $author_term = Term::load(reset($term_exists));
            }
        
            // Get the term ID.
            $author_term_id = $author_term->id();
            var_dump($author_term_id);
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
        if (null !== $editorial_geslib_id) {
            $product->set('field_editorial', [
                                                'target_id' => $editorial_geslib_id]);
        }

        if (!empty($content['categories'])) {
            $category_ids = [];
            foreach ($content['categories'] as $key => $value) {
                $category_id = intval($key);
                var_dump("Key: $key, Category ID: $category_id"); // Add this line to check values
                $category_ids[] = $category_id;
            }

            $product->set('field_categoria', $category_ids);
        }

        $variation->save();
        $product->save();

        // Assign the product to the editorial taxonomy term
        // This assumes that the taxonomy term ID is stored in $editorial_geslib_id, adjust if needed
        
    
        // Assign categories
       
        //$this->output->writeln("Stored Product - Title: $book_name, EAN: $ean");
        return $product;
    }
    
    private function _create_slug($term_name) {
		// convert to lowercase
		$term_name = strtolower($term_name);
	  
		// remove punctuation
		$term_name = preg_replace("/[.,:;!?(){}[\]<>%$#@^*+=|~`]/", "", $term_name);
	  
		// replace spaces with underscores
		$term_slug = str_replace(" ", "_", $term_name);
	  
		return $term_slug;
  	}

    public function fetchContent($geslib_id, $type) {

		return $this->database->select(self::GESLIB_LINES_TABLE,'gl')
                    ->fields('gl',['content'])
                    ->condition('geslib_id', $geslib_id, '=')
                    ->condition('entity', $type, '=')
                    ->range( 0, 1 )
                    ->execute()
                    ->fetchField();
	}
    
    public function getProductCategoriesFromGeslibLines() {
        return $this->database
                ->select(self::GESLIB_LINES_TABLE, 't')
                ->fields('t')
                ->condition('entity', 'product_cat')
                ->execute()
                ->fetchAll();
    }

    public function storeProductCategories($product_category) {
        $term_name = $this->geslibApiSanitize->utf8_encode($product_category->content);
    
        // Load the taxonomy term by name from the 'product_categories' vocabulary
        $terms = \Drupal::entityTypeManager()
                ->getStorage('taxonomy_term')
                ->loadByProperties([
                    'name' => $term_name,
                    'vid' => 'product_categories',
            ]);
    
        if (empty($terms)) {
            // Create a new term
            $term = \Drupal\taxonomy\Entity\Term::create([
                'name' => $term_name,
                'vid' => 'product_categories',
                'description' => [
                    'value' => 'Imported category',
                    'format' => 'plain_text',
                ],
                // You may need to adjust this if the pathauto module is installed
                'path' => [
                    'alias' => '/' . strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', \Drupal::transliteration()->transliterate($term_name, 'en'))),
                ],
                'field_geslib_id' => $product_category->geslib_id,
            ]);
    
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
    

}