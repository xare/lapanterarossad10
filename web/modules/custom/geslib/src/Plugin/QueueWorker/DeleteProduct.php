<?php
namespace Drupal\geslib\Plugin\QueueWorker;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\geslib\Api\GeslibApiDrupalManager;

/**
 * Processes Tasks for Deleting products.
 *
 * @QueueWorker(
 *   id = "geslib_delete_product",
 *   title = @Translation("Delete Product"),
 *   cron = {"time" = 60}
 * )
 */
class DeleteProduct extends QueueWorkerBase implements ContainerFactoryPluginInterface {
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
    public function processItem( $data ) {
        $this->geslibApiDrupalManager->deleteProductById( $data );
    }

}
