<?php

namespace Drupal\geslib\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\geslib\Api\GeslibApiDrupalManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes Tasks for Storing editorials.
 *
 * @QueueWorker(
 *   id = "geslib_manage_editorial",
 *   title = @Translation("Manage Author"),
 *   cron = {"time" = 60}
 * )
 */
class ManageEditorial extends QueueWorkerBase implements ContainerFactoryPluginInterface {

    protected $geslibApiDrupalManager;

    public function __construct(GeslibApiDrupalManager $geslibApiDrupalManager) {
        $this->geslibApiDrupalManager = $geslibApiDrupalManager;
    }

    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        // Here you can load any services from the container and pass them to your constructor.
        return new static(
            $container->get('geslib.api.drupal')
        );
    }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    return $this->geslibApiDrupalManager->storeTerm($data, 'editorial');
  }
}
