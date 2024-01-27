<?php

namespace Drupal\geslib\Api;

use Drupal\Core\Batch\BatchBuilder;

/**
 * @package GeslibApiDrupalQueueManager
 */

class GeslibApiDrupalQueueManager extends GeslibApiDrupalManager
{
    /**
     * insertLinesIntoQueue
     * Takes a $batch multidimensional array with elements' properties:
     * - (string) type = 'store_lines'
     * - (int) geslib_id
     * - (string) data = $line ( a line in the INTER*** file)
     * - (string) action = A | M | B | stock
     * - (int) log_id
     *
     * Called by:
     * - GeslibApiLines
     *
     *
     * @param  array $batch
     * @return bool
     */
    public function insertLinesIntoQueue( array $batch ): bool {
        \Drupal::logger('geslib_queue')->info('received batch size:' . count($batch));
		foreach ($batch as $item) {
			try {
				\Drupal::database()
                ->insert(self::GESLIB_QUEUES_TABLE)
                ->fields($item)
                ->execute();
                \Drupal::logger('geslib_queue_insert_lines')
                            ->notice('Inserted into geslib_queues table, type: '
                                    . $item['type'] ." geslib_id: "
                                    . $item['geslib_id']." data: "
                                    . $item['data'] );
                return true;
			} catch( \Exception $exception ) {
				// Logging the exception. Drupal has a different logging mechanism.
                \Drupal::logger('geslib_queue_insert_lines')->error(
                    "ERROR: Not inserted into geslib_queues table.".
                    $exception->getMessage());
                return false;
            }
		}
	}

    /**
     * Inserts products into a queue and deletes specific entries from another table.
     *
     * @param array $batch
     *   An array of items to process.
     * @return void
     */
    public function insertProductsIntoQueue( array $batch ): void {
        $database = \Drupal::database();

        foreach ($batch as $item) {
            try {
                // Inserting data into the queue table.
                $database->insert(self::GESLIB_QUEUES_TABLE)
                    ->fields($item)
                    ->execute();
                try {
                    // Deleting specific records from the lines table.
                    $database->delete(self::GESLIB_LINES_TABLE)
                        ->condition('geslib_id', $item['geslib_id'], '=')
                        ->condition('log_id', $item['log_id'], '=')
                        ->condition('entity', 'product', '=')
                        ->execute();
                } catch (\Exception $exception) {
                    \Drupal::logger('geslib_lines')->error('Error al intentar borrar un producto de la tabla de geslib_lines ' . $exception->getMessage());
                }
            } catch (\Exception $exception) {
                \Drupal::logger('geslib_lines')->error('Error al intentar insertar un producto en la tabla de geslib_queue ' .$exception->getMessage());
            }
        }
    }

    /**
     * Deletes an item from the queue.
     *

     * @param int $id
     *
     * @return void
     */
    public function deleteItemFromQueue( int $id ): void {
        try {
            // Delete query using Drupal's Database API
            \Drupal::database()->delete(self::GESLIB_QUEUES_TABLE)
                    ->condition('id', $id)
                    ->execute();

            \Drupal::logger('geslib_queue')->notice("Deleted task: {$id}");
        } catch (\Exception $exception) {
            \Drupal::logger('geslib_queue')->error("Failed to delete task: Type{$id} :".$exception->getMessage());
        }
    }

    /**
     * deleteItemsFromQueue
     *
     * @param  string $type
     * @return bool
     */
    public function deleteItemsFromQueue( string $type ): bool {
        try {
            // Delete query using Drupal's Database API
            \Drupal::database()->delete(self::GESLIB_QUEUES_TABLE)
                    ->condition('type', $type)
                    ->execute();
            \Drupal::logger('geslib')->notice("Deleted task: Type {$type}");
            return TRUE;
        } catch (\Exception $exception) {
            \Drupal::logger('geslib')->error("Failed to delete task: Type {$type} :".$exception->getMessage());
            return FALSE;
        }
    }

    /**
	 * processBatchStoreLines
     * Extracts 10 rows from geslib_queues type='store_lines'
     * And applies geslibApiLines->readLine on each.
     * The readLine function is called by GeslibApiLines and converts the line into an object.
	 *
	 * @param  int $batchSize
	 * @return void
	 */
	public function processBatchStoreLines( int $batchSize = 10 ): void {
        $queueArray = $this->getBatchFromQueue( $batchSize, 'store_lines' );
        // If there are no tasks, exit the function.
        $geslibApiLines = new GeslibApiLines;
        foreach ($queueArray as $queueObject) {
            \Drupal::logger('geslib_queue_store_lines')->info('Log ID '.$queueObject->log_id . ' - line '.$queueObject->data.' - id '.$queueObject->id);
            $geslibApiLines->readLine( $queueObject->data, $queueObject->log_id, $queueObject->id );
        }
    }

    /**
     * getBatchFromQueue
     * Retrieves a batch of records from the geslib_queue table for a given type.
     *
     * @param int $batchSize
     * @param string $type
     * @return array
     */
    public function getBatchFromQueue(int $batchSize, string $type): array {
        // Query to select a batch of records
        $query = \Drupal::database()
            ->select(self::GESLIB_QUEUES_TABLE, 'gq')
            ->fields('gq')
            ->condition('type', $type)
            ->range(0, $batchSize)
            ->execute();

        return (array) $query->fetchAll();
    }

    /**
     * processBatchStoreProducts
     *
     * @param  int $batchSize
     * @return void
     */
    public function processBatchStoreProducts( int $batchSize = 10 ): void {
		$queue = $this->getBatchFromQueue( $batchSize, 'store_products' );
        \Drupal::logger('geslib_store_products')->info('Processing Store Products: queue length: '.count($queue));
        $geslibApiDrupalProductsManager = new GeslibApiDrupalProductsManager;
		foreach ( $queue as $task ) {
            \Drupal::logger('geslib_store_products')->info('Task Action: ' . $task->action );
            if( $task->action == 'stock') {
				$geslibApiDrupalProductsManager->stockProduct($task->geslib_id, $task->data);
			} else if( $task->action == 'B') {
				$geslibApiDrupalProductsManager->deleteProduct( $task->geslib_id );
            } else {
				$geslibApiDrupalProductsManager->storeProduct( $task->geslib_id, $task->data );
			}
			$this->deleteItemFromQueue( $task->id );
		}
	}

    /**
	 * getQueuedTasks
	 *
	 * @param  string $type
	 * @return array
	 */
	public function getQueuedTasks( string $type): array {
        $query = \Drupal::database()
                    ->select( self::GESLIB_QUEUES_TABLE, 'gq' )
                    ->condition( 'type', $type, '=' )
                    ->execute();
        return $query->fetchAll( );
	}

    /**
     * countGeslibQueue
     * Counts the number of entries in the geslib queue for a given type.
     *
     * @param string $type
     * @return int
     */
    public function countGeslibQueue( string $type ): int {
        // Query to count the number of entries of a specific type
        $query = \Drupal::database()
        ->select(self::GESLIB_QUEUES_TABLE, 'gq');
        $query->fields('gq',['id']);
        $query->addExpression('COUNT(*)');
        $query->condition( 'type', $type );
        return (int)  $query->countQuery()->execute()->fetchField();
    }

    /**
	 * processFromQueue
     * Called from:
     * - GeslibProcessAllCommand::processAll()
     * - GeslibProcessFileCommand::processFile()
     * - StoreProductsForm::storeProductsAjaxCallback()
	 *
	 * @param  string $type
	 * @return void
	 */

     public function processFromQueue( string $type ) {
        \Drupal::logger('geslib_queue')->info('Inside Process From Queue type: '. $type);
        // Transform the type to the method name format
        if( $type == 'store_lines' ) {
            // Process the queue items
            do {
                $this->processBatchStoreLines( 200 );
                // Count remaining items in the queue
                $queue_count = $this->countGeslibQueue($type);
                \Drupal::logger('geslib_'.$type)->info('Remain '.$queue_count. ' items in Queue type: '. $type);
            } while ($queue_count > 0);
        }
        if( $type == 'store_products' ) {
			// Select tasks of type 'store_products' that are pending
			do {
				$this->processBatchStoreProducts( 200 );
				$queue_count = $this->countGeslibQueue($type);
                \Drupal::logger('geslib_'.$type)->info('Remain '.$queue_count. ' items in Queue type: '. $type);
			} while( $queue_count > 0);
		}
    }

    /**
     * getQueueCount
     * Called by:
     * - GeslibAjaxStatistics::ajaxStatistics
     * - StoreProductsForm::buildForm
     *
     * @param  string $queue_name
     * @return int
     */
    public function getQueueCount( string $queue_name ): int {
        $connection = \Drupal::database();
        $query = $connection->select(self::GESLIB_QUEUES_TABLE, 'gq');
        $query->fields('gq',['type']);
        $query->condition('type', $queue_name);

        try {
            $result = $query->countQuery()->execute()->fetchfield();
            return $result;
        } catch (\Exception $e) {
            \Drupal::logger('geslib')->error($e->getMessage());
            return 10; // Or handle the exception as needed
        }
    }

    /**
     * countProductsInGeslibLinesQueue
     *
     * @return int
     */
    public function countProductsInGeslibLinesQueue(): int  {
        $query = \Drupal::database()
        ->select(self::GESLIB_QUEUES_TABLE, 't');
        $query->fields('t',['id']);
        $query->addExpression('COUNT(*)');
        $query->condition('type', 'store_lines', '=');
        $query->condition('data', 'GP4|A%', 'LIKE');
        return (int) $query->countQuery()->execute()->fetchfield();
    }
    /**
     * countAuthorsInGeslibLinesQueue
     *
     * @return int
     */
    public function countAuthorsInGeslibLinesQueue(): int  {
        $query = \Drupal::database()
        ->select(self::GESLIB_QUEUES_TABLE, 't');
        $query->fields('t',['id']);
        $query->addExpression('COUNT(*)');
        $query->condition('type', 'store_lines', '=');
        $query->condition('data', 'AUT|A%', 'LIKE');
        return (int) $query->countQuery()->execute()->fetchfield();
    }
    /**
     * countEditorialsInGeslibLinesQueue
     *
     * @return int
     */
    public function countEditorialsInGeslibLinesQueue(): int  {
        $query = \Drupal::database()
        ->select(self::GESLIB_QUEUES_TABLE, 't');
        $query->fields('t',['id']);
        $query->addExpression('COUNT(*)');
        $query->condition('type', 'store_lines', '=');
        $query->condition('data', '1L|A%', 'LIKE');
        return (int) $query->countQuery()->execute()->fetchfield();
    }

    /**
     * getLastProductInGeslibLinesQueue
     *
     * @return string|false
     */
    public function getLastProductInGeslibLinesQueue(): string|false {
        // Construct the query
        $query = \Drupal::database()->select( self::GESLIB_QUEUES_TABLE, 'g')
        ->fields( 'g', ['data'] )
        ->condition( 'g.type', 'store_lines' )
        ->condition( 'g.data', 'GP4|A%', 'LIKE' )
        ->orderBy( 'g.id', 'DESC' )
        ->range(0, 1);

        // Execute the query
        $result = $query->execute();

        // Extract the desired substring if a row is found
        if ( !$row = $result->fetchAssoc() ) return false;

        $lineArray = explode('|', $row['data']);
        if ( !isset( $lineArray[3] ) )  return false;

        return $lineArray[3];

    }

    /**
     * getLastAuthorInGeslibLinesQueue
     *
     * @return string|false
     */
    public function getLastAuthorInGeslibLinesQueue(): string|false {
        // Construct the query
        $query = \Drupal::database()->select(self::GESLIB_QUEUES_TABLE, 'g')
        ->fields( 'g', ['data'] )
        ->condition( 'g.type', 'store_lines' )
        ->condition( 'g.data', 'AUT|A%', 'LIKE' )
        ->orderBy( 'g.id', 'DESC' )
        ->range(0, 1);

        // Execute the query
        $result = $query->execute();

        // Extract the desired substring if a row is found
        if ( !$row = $result->fetchAssoc()) return false;
        $lineArray = explode('|', $row['data']);
        if ( !isset($lineArray[3]) )  return false;

        return (string) $lineArray[3];

    }

    /**
     * getLastAuthorInGeslibLinesQueue
     *
     * @return string|false
     */
    public function getLastEditorialInGeslibLinesQueue(): string|false {
        // Construct the query
        $query = \Drupal::database()->select(self::GESLIB_QUEUES_TABLE, 'g')
        ->fields( 'g', ['data'] )
        ->condition( 'g.type', 'store_lines' )
        ->condition( 'g.data', '1L|A%', 'LIKE' )
        ->orderBy( 'g.id', 'DESC' )
        ->range(0, 1);

        // Execute the query
        $result = $query->execute();

        // Extract the desired substring if a row is found
        if ( !$row = $result->fetchAssoc()) return false;
        $lineArray = explode('|', $row['data']);
        if ( !isset($lineArray[3]) )  return false;

        return (string) $lineArray[3];

    }

    /**
     * Retrieves the queue ID for a given type, log ID, and geslib ID.
     *
     * @param string $type The type parameter
     * @param string $log_id The log ID parameter
     * @param string $geslib_id The geslib ID parameter
     * @return string The queue ID
     */
    public function getQueueId( string $type, string $log_id, string $geslib_id ): int {
        $query = \Drupal::database()->select( self::GESLIB_QUEUES_TABLE, 'gq' )
        ->fields( 'gq', ['id'] )
        ->condition( 'gq.type', $type )
        ->condition( 'gq.log_id', $log_id )
        ->condition( 'gq.geslib_id', $geslib_id )
        ->orderBy( 'gq.id', 'DESC' )
        ->range(0, 1);

        return (int) $query->execute()->fetchField();
    }

}