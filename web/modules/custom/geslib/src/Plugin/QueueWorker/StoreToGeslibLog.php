<?php
namespace Drupal\geslib\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\geslib\Api\GeslibApiDrupalLogManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes Tasks for Storing to Geslib Log.
 *
 * @QueueWorker(
 *   id = "geslib_store_geslib_log",
 *   title = @Translation("Store to Geslib Log"),
 *   cron = {"time" = 60}
 * )
 */

 class StoreToGeslibLog extends QueueWorkerBase implements ContainerFactoryPluginInterface {

    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        // Here you can load any services from the container and pass them to your constructor.
        return new static(
            $container->get('geslib.api.drupal')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function processItem( $data ) {

        $filename = $data['filename'];
        $status = $data['status'];
        $linesCount = $data['linesCount'];
        $geslibApiDrupalLogManager = new GeslibApiDrupalLogManager;
        $geslibApiDrupalLogManager->insertLogData( $filename, $status, $linesCount );

    }
 }