<?php

namespace Drupal\geslib\Command;

use Drupal\dilve\Api\DilveApiDrupalManager;
use Drupal\geslib\Api\GeslibApiDrupalLinesManager;
use Drupal\geslib\Api\GeslibApiDrupalLogManager;
use Symfony\Component\Console\Command\Command;
use Drupal\geslib\Api\GeslibApiDrupalManager;
use Drupal\geslib\Api\GeslibApiDrupalProductsManager;
use Drupal\geslib\Api\GeslibApiDrupalQueueManager;
use Drupal\geslib\Api\GeslibApiReadFiles;
use Drupal\geslib\Api\GeslibApiLines;
use Drupal\geslib\Api\GeslibApiStoreData;
use Drush\Commands\DrushCommands;

/**
 * Defines a Drush command to execute all processes.
 *
 * @DrushCommands()
 */
class GeslibProcessAllCommand extends DrushCommands {

    /**
     * Undertakes all the process.
     *
     * @command geslib:processAll
     * @alias gspa
     * @description Makes all the process.
     *
     */

    public function processAll() {
        $geslibApiDrupalManager = new GeslibApiDrupalManager();
        $geslibApiDrupalLogManager = new GeslibApiDrupalLogManager;
        $geslibApiDrupalLinesManager = new GeslibApiDrupalLinesManager;
        $geslibApiDrupalProductsManager = new GeslibApiDrupalProductsManager;
        $geslibApiDrupalQueueManager = new GeslibApiDrupalQueueManager;
        $geslibApiReadFiles = new GeslibApiReadFiles();
        $geslibApiLines = new GeslibApiLines();
        $geslibApiStoreData = new GeslibApiStoreData;
        $geslibApiReadFiles->readFolder();
         // Check Logged Status: Returns true if there is at least one row with status "logged", false otherwise
    while ( $geslibApiDrupalLogManager->checkLoggedStatus() ) {
        $log_id = $geslibApiDrupalLogManager->getLogLoggedId();
        if ( $log_id > 0 ) {
          \Drupal::logger('geslib_main')
                  ->info('ACTUAL LOG: ' . $log_id );
          if ( !$geslibApiDrupalLogManager->isQueued() ){
            \Drupal::logger('geslib_main')
                  ->info('There is no queued row, so we set log id'. $log_id. ' to queued.' );
            $geslibApiDrupalLogManager->setLogStatus( $log_id, 'queued' );
          } else {
            \Drupal::logger('geslib_main')
                  ->info('There is a queued log file: ' . $log_id );
            $geslibApiDrupalQueueManager->deleteItemsFromQueue( 'store_lines' );
          }
          // Takes the queued filename gets the $log_id and stores each item into the geslib_queue table.
          $stored_lines = $geslibApiLines->storeToLines();
          \Drupal::logger( 'geslib_main' )
                  ->info( 'Finished processing into queues for lines'. $stored_lines );

          $geslibApiDrupalQueueManager->processFromQueue( 'store_lines' );
          // Once we have processed all the store_queues(type=store_lines) we can delete them.
          \Drupal::logger( 'geslib_main' )
          ->info( 'After processing the store_lines queue we start with the authors. Log id ' . $log_id );
          $geslibApiStoreData->storeAuthors();
          \Drupal::logger( 'geslib_main' )
          ->info( 'After processing the store_lines queue we start with the editorials. Log id ' . $log_id );
          $geslibApiStoreData->storeEditorials();
          // Assuming you have a method to get queued tasks
          \Drupal::logger( 'geslib_queues' )
                ->notice( 'Finished processing from queues for lines' );
          $geslibApiDrupalProductsManager->storeProducts();
          \Drupal::logger( 'geslibgeslib_queues' )
                ->info( 'Finished storing product info to store_products queue.' );

          $geslibApiDrupalQueueManager->processFromQueue( 'store_products' );
          $geslibApiDrupalLinesManager->truncateGeslibLines();
          $geslibApiDrupalLogManager->setLogStatus( $log_id, 'processed');
        }
      }
      // in the case all the logged files had been processed but maybe some queued row would be orphaned
      if ( $geslibApiDrupalLogManager->isQueued() != FALSE ) {
          $log_id = $geslibApiDrupalLogManager->isQueued();
          $geslibApiLines->storeToLines();
          \Drupal::logger( 'geslib' )
                  ->notice( 'Finished processing into queues for lines' );
          $geslibApiDrupalQueueManager->processFromQueue( 'store_lines' );
          $geslibApiStoreData->storeAuthors();
          $geslibApiStoreData->storeEditorials();
          // Assuming you have a method to get queued tasks
          \Drupal::logger( 'geslib' )
                ->notice( 'Finished processing from queues for lines' );
          $geslibApiDrupalProductsManager->storeProducts();
          \Drupal::logger( 'geslib' )
                ->notice( 'Finished storing product info to store_products queue.' );
          $geslibApiDrupalQueueManager->processFromQueue( 'store_products' );
          $geslibApiDrupalLinesManager->truncateGeslibLines();
          $geslibApiDrupalLogManager->setLogStatus( $log_id, 'processed');
      }

        $this->output()->writeln('All Geslib tasks have been processed.');

        return Command::SUCCESS;
    }

}

