<?php 
namespace Drupal\custom_routing\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Check if our view's route exists, then increase its priority.
    if ($route = $collection->get('view.productos_por_editorial.page_1')) {
      $route->setOption('_admin_route', TRUE);
    }
  }
}
