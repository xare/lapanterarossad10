<?php

/**
 * @package Drupal\geslib
 */

namespace Drupal\geslib\Plugin;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Database\Database;
use Drupal\Core\Messenger\Messenger;
use Drupal\geslib\Plugin\geslibApi;
use Exception;

class geslibBatches {

  /**
   * Initiate the batch processing
   *
   * @return void
   */

  public function deleteAll() {
    $operations = [
                    'geslib_process_delete_categories',
                    'geslib_process_delete_products',
                    'geslib_process_delete_nodes'
                  ];

    // Add each value to the batch as a separate operation
    $batch_builder = (new BatchBuilder());
    foreach ($operations as $operation) {
      $batch_builder->addOperation( [ geslibBatches::class , $operation ]);
    }
    batch_set($batch_builder->toArray());
    return "inside deleteAll()";
    // Start the batch process
    //

  }

  public static function delete() {
    return true;
  }

  public static function process($operation, $value='') {

      $batch_builder = (new BatchBuilder());
      $batch_builder->addOperation( [ geslibBatches::class , $operation ],[$value]);
      batch_set($batch_builder->toArray());
      return "inside process()";

    // $finish_callback = function() {
    //   $this->finishCallback();
    // };
    // $this->buildBatch(
    //   $operations,
    //   t('Importando artículos de geslib'),
    //   $finish_callback);

  }

  public function geslibOperation($value) {
    return "operation";
  }

  public function buildBatch($operations, $title, $finish_callback) {

    $batch_builder = ( new BatchBuilder() )
      ->setTitle( $title );
      foreach ( $operations as $operation ) {
        $batch_builder->addOperation( $operation );
      }
      $batch_builder->setInitMessage( t( 'Starting the batch process...' ) )
      ->setFinishCallback( $finish_callback )
      ->setErrorMessage( t( 'An error occurred during the batch process.' ) )
      ->setProgressMessage(t( 'Processed @current out of @total.' ) );

      batch_set( $batch_builder->toArray() );
  }
  public function finishCallback() {
    echo "finish callback finished";
  }

  /*********************************************************
   *  PROCESS ITEMS FUNCTIONS
   * *******************************************************/
  /**
 * Generic function to process items in the db
 *
 * @param type $type
 * @param type $context
 */
function geslib_process_items($type, $limit, &$context){
  $connection = Database::getConnection();
  if (!isset($context['sandbox']['progress'])) {
    $query = $connection->select('geslib_lines', 'g')
      ->condition('type', $type);
    $items_db = $query->countQuery()->execute()->fetchField();
    $context['sandbox']['progress'] = 0;
    $context['sandbox']['max'] =  $items_db->rowCount();
    $context['results']['items'][$type] = 0;
  }

  $query = $connection->select('geslib_lines', 'g')
          ->condition('type', $type)
          ->range(0, $limit);

  $lines = $query->execute()->fetchAll();

  foreach ($lines as $line) {
    $item = unserialize($line->content);
    \Drupal::moduleHandler()->invokeAll('geslib_'.$type, $item['action'], $item);
    $connection->delete('geslib_lines')
      ->condition('geslib_id', $line->geslib_id)
      ->condition('type', $type)
      ->execute();
    $context['results']['items'][$type]++;
    $context['sandbox']['progress']++;
  }

  $context['message'] = t("Processed @progress of @max @type items", [
                          '@progress' => $context['sandbox']['progress'],
                          '@max' => $context['sandbox']['max'],
                          '@type' => $type]);

  if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
  }
}

/**
 * Finish callback. Record log of the operations
 *
 * @global type $user
 * @param type $success
 * @param type $results
 * @param type $operations
 */
function geslib_process_finish($success, $results, $operations) {
  if ($success) {
    $total_items = 0;
    foreach ($results['items'] as $type => $count) {
      \Drupal::messenger()->addMessage(t("Created @count @type items", array('@count' => $count, '@type' => $type)));
      $total_items += $count;
    }
    $log = array(
      'start_date' => $results['start_date'],
      'end_date' => time(),
      'imported_file' => $results['inter_file'],
      'items' => $total_items,
      'processed_lines' => $results['processed_lines'],
    );
    //drupal_write_record('geslib_log', $log);
    $database = Database::getConnection();
    $database->insert('geslib_log')
      ->fields($log)
      ->execute();
  }
  else {
    // An error occurred.
    // $operations contains the operations that remained unprocessed.
    $error_operation = reset($operations);
    $message = t('An error occurred while processing %error_operation with arguments: @arguments', [
      '%error_operation' => $error_operation[0],
      '@arguments' => print_r($error_operation[1], TRUE)
    ]);
    \Drupal::messenger()->addError($message);
  }
}

  /***********************************************************
    DELETE FUNCTIONS
  **********************************************************/

  /**
   * Delete all the taxonomies
   * @param type $context
   */
  public static function geslib_process_delete_categories(&$context) {
    $database = \Drupal::database();
    if (!isset($context['sandbox']['progress'])) {
      $terms = $database->select('taxonomy_term_data', 't')
                ->fields('t')
                ->execute();
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = count($terms->fetchAll());
    }

    $limit = 900;

    $terms = $database->select('taxonomy_term_data', 't')->fields('t')->range(0, $limit)->execute()->fetchAll();

    foreach ($terms as $term) {
      //taxonomy_term_delete($term->tid);
      \Drupal::entityTypeManager()->getStorage('taxonomy_term')->delete([$term->id()]);
      /* In case we use dependency injection
      $term_storage = $container->get('entity_type.manager')->getStorage('taxonomy_term');
      $term_storage->delete([$term->id()]);
      */
      $context['sandbox']['progress']++;
    }

    $context['message'] = t("Deleted @progress of @max categories", [
      '@progress' => $context['sandbox']['progress'],
      '@max' => $context['sandbox']['max']
    ]);


    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }

  }

  /**
   * Delete all the products
   * @param type $context
   */
  public static function geslib_process_delete_products(&$context) {
    $database = \Drupal::database();
    if (!isset($context['sandbox']['progress'])) {
      $products = $database->select('commerce_product', 'p')->fields('p')->execute();
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = count($products->fetchAll());
    }

      $limit = 100;

      $products = $database->select('commerce_product', 'p')->fields('p')->range(0, $limit)->execute()->fetchAll();

    foreach ($products as $product){
      //commerce_product_delete($product->product_id);
      $products = \Drupal::entityTypeManager()
                  ->getStorage('commerce_product')
                  ->loadByProperties(['product_id' => $product->product_id]);
      \Drupal::entityTypeManager()
                  ->getStorage('commerce_product')
                  ->delete($products);

      $context['sandbox']['progress']++;
    }

    $context['message'] = t("Deleted @progress of @max products", [
                          '@progress' => $context['sandbox']['progress'],
                          '@max' => $context['sandbox']['max']
                        ]);

    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }

  }

  /**
   * Delete all the nodes
   * @param type $context
   */
  public static function geslib_process_delete_nodes(&$context){
    $database = \Drupal::database();
    if (!isset($context['sandbox']['progress'])) {
      $nodes_db = $database->select('node', 'n')
                  ->fields('n')
                  ->condition('type', 'libro')
                  ->execute();
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = count($nodes_db->fetchAll());
    }

    $limit = 500;

    $nodes = $database->select('node', 'n')->fields('n')->condition('type', 'libro')->range(0, $limit)->execute()->fetchAll();

    foreach($nodes as $node) {
      //node_delete($node->nid);
      $node = \Drupal\node\Entity\Node::load($node->id());
      $node->delete();
      $context['sandbox']['progress'] ++;
    }

    $context['message'] = t("Deleted @progress of @max nodes", array('@progress' => $context['sandbox']['progress'], '@max' => $context['sandbox']['max']));
    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }
  /**
 * Carga el fichero geslib en una tabla de la base de datos
 * para poder procesarlo más tarde
 *
 * @param type $geslib_file_path
 * @param type $context
 */
public static function geslib_process_read_file($geslib_file_path) {

  $geslib_file = file_get_contents($geslib_file_path);
  $geslib_file_array = explode("\n", $geslib_file);
  $context = [];
  if (!isset($context['sandbox']['progress'])) {
    $context['sandbox']['progress'] = 0;
    $context['sandbox']['max'] = count($geslib_file_array);
    $context['results']['processed_lines'] = $context['sandbox']['max'] ;
    $context['results']['start_date'] = DrupalDateTime::createFromTimestamp(time())->getTimestamp();
    $context['results']['inter_file'] = substr($geslib_file_path, strrpos($geslib_file_path, '/') +1);
  }

  $limit = count($geslib_file_array) < 1000 ? count($geslib_file_array): 1000;

  $i = 0;
  $geslib_lines = [];
  while($i < $limit) {
    $line = explode("|", $geslib_file_array[$i]);
    geslibApi::geslib_process_line($line, $geslib_lines);
    unset($geslib_file_array[$i]);
    $i++;
  }
  $database = \Drupal::database();
  foreach ($geslib_lines as $line) {
    if (!isset($line['action'])) {
      $product_db = $database
                    ->select('geslib_lines', 'g')
                    ->condition('geslib_id', $line['geslib_id'])
                    ->condition('type', 'product')
                    ->fields('g')
                    ->execute()
                    ->fetchObject();
      $product = unserialize($product_db->content);

      if ($line['type'] == 'product_author') {
        $product['authors'][] = $line;
      }
      if ($line['type'] == 'description') {
        $product['description'] = $line;
      }
      $product_db->content = serialize($product);
      /* drupal_write_record('geslib_lines', $product_db, array('id')); */
      \Drupal::database()->merge('geslib_lines')
                    ->keys(['id' => $product_db->id ])
                    ->fields([
                      'geslib_id'=>$product_db->geslib_id,
                      'type' => $product_db->type,
                      'inter_file' => $product_db->inter_file,
                      'queued' => $product_db->queued,
                      'content' => $product_db->content
                    ])->execute();
    }
    elseif ($line['action'] != 'delete') {
      $record = array();
      $record['geslib_id'] = $line['geslib_id'];
      $record['type'] = $line['type'];
      $record['inter_file'] = $context['results']['inter_file'];
      $record['content'] = serialize($line);

      try {
        //drupal_write_record('geslib_lines', $record);
        \Drupal::database()->merge('geslib_lines')
                            ->keys(['id' => $record['id']])
                            ->fields([
                              'geslib_id'=>$record['geslib_id'],
                              'type' => $record['type'],
                              'inter_file' => $record['inter_file'],
                              'queued' => $record['queued'],
                              'content' => $record['content']
                            ])->execute();
      }
      catch (Exception $e){
        $logger = \Drupal::logger('geslib');
        $logger->error('Geslib id: @id Type: @type Message: @message', [
          '@id' => $record['geslib_id'],
          '@type' => $record['type'],
          '@message' => $e->getMessage(),
        ]);

        //watchdog('geslib', "Geslib id: @id Type: @type Message: @message", array('@id' => $record['geslib_id'], '@type' => $record['type'], '@message' => $e->getMessage()));
      }
    }
  }

  $context['sandbox']['progress'] += $i;

  $context['message'] = t("Processed @progress of @max lines", array('@progress' => $context['sandbox']['progress'], '@max' => $context['sandbox']['max']));

  file_put_contents($geslib_file_path, implode("\n", $geslib_file_array));

  if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
  }
}

}