<?php

namespace Drupal\geslib\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\geslib\Api\GeslibApiDrupalManager;
use Drupal\geslib\Api\GeslibApiDrupalProductsManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes Tasks for Storing Products.
 *
 * @QueueWorker(
 *   id = "geslib_manage_product",
 *   title = @Translation("Manage Product"),
 *   cron = {"time" = 60}
 * )
 */
class ManageProduct extends QueueWorkerBase implements ContainerFactoryPluginInterface {

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
    $geslibApiDrupalProductManager = new GeslibApiDrupalProductsManager;
    if ($data['action'] == 'A' || $data['action'] == 'M') {
        $geslibApiDrupalProductManager->storeProduct( $data['geslib_id'], $data['content']);
    } elseif ($data['action'] == 'B') {
        $geslibApiDrupalProductManager->deleteProduct( $data['geslib_id'] );
    }
  }
}
