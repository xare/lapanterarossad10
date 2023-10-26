<?php
namespace Drupal\geslib\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\geslib\Api\GeslibApiLines;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes Tasks for Storing to Geslib Lines.
 *
 * @QueueWorker(
 *   id = "geslib_store_geslib_lines",
 *   title = @Translation("Store to Geslib Lines"),
 *   cron = {"time" = 60}
 * )
 */

 class StoreToGeslibLines extends QueueWorkerBase implements ContainerFactoryPluginInterface {
    protected $geslibApiLines;

    public function __construct(GeslibApiLines $geslibApiLines) {
        $this->geslibApiLines = $geslibApiLines;
    }

    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        // Here you can load any services from the container and pass them to your constructor.
        return new static(
            $container->get('geslib.api.lines')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function processItem( $data ) {
        $log_id = $data['log_id'];
        $line = $data['line'];
        $this->geslibApiLines->readLine( $line, $log_id );

    }
 }