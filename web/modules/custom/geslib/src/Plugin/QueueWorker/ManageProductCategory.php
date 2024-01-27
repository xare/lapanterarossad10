<?php

namespace Drupal\geslib\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\geslib\Api\GeslibApiDrupalManager;
use Drupal\geslib\Api\GeslibApiDrupalTaxonomyManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes Tasks for Storing product Categories.
 *
 * @QueueWorker(
 *   id = "geslib_manage_product_categories",
 *   title = @Translation("Manage ProductCategory"),
 *   cron = {"time" = 60}
 * )
 */
class ManageProductCategories extends QueueWorkerBase implements ContainerFactoryPluginInterface {

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
    $geslibApiDrupalTaxonomyManager = new GeslibApiDrupalTaxonomyManager;
    return $geslibApiDrupalTaxonomyManager->storeTerm($data, 'product_categories');
  }
}
