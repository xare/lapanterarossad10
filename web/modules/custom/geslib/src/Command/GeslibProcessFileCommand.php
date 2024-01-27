<?php

namespace Drupal\geslib\Command;

use Drupal\geslib\Api\GeslibApiDrupalLinesManager;
use Drupal\geslib\Api\GeslibApiDrupalLogManager;
use Drupal\geslib\Api\GeslibApiDrupalProductsManager;
use Drupal\geslib\Api\GeslibApiDrupalQueueManager;
use Drupal\geslib\Api\GeslibApiLines;
use Drush\Commands\DrushCommands;

/**
 * Defines a Drush command to execute all processes.
 *
 * @DrushCommands()
 */
class GeslibProcessFileCommand extends DrushCommands {

    /**
     * Undertakes all the process.
     *
     * @command geslib:processFile
     * @alias gspa
     * @description Makes all the process.
     *
     */

     public function processFile( int $log_id ){
        $geslibApiLines = new GeslibApiLines;
        $geslibApiDrupalQueueManager = new GeslibApiDrupalQueueManager;
        $geslibApiDrupalProductsManager = new GeslibApiDrupalProductsManager;
        $geslibApiDrupalLinesManager = new GeslibApiDrupalLinesManager;
        $geslibApiDrupalLogManager = new GeslibApiDrupalLogManager;
        $geslibApiDrupalLogManager->setLogStatus( $log_id, 'queued');
        $stored_lines = $geslibApiLines->storeToLines();
        \Drupal::logger( 'geslib' )
                  ->info( 'Finished processing into queues for lines'. $stored_lines );
        $geslibApiDrupalQueueManager->processFromQueue( 'store_lines' );
        $geslibApiDrupalProductsManager->storeProducts();
        $geslibApiDrupalQueueManager->processFromQueue( 'store_products' );
        $geslibApiDrupalLinesManager->truncateGeslibLines();
        $geslibApiDrupalLogManager->setLogStatus( $log_id, 'processed');
     }
}
