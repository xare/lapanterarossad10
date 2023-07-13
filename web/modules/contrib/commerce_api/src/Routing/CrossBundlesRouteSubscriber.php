<?php

namespace Drupal\commerce_api\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\jsonapi\Routing\Routes;
use ICanBoogie\Inflector;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Rename cross-bundle routes to meet Commerce API route paths.
 */
final class CrossBundlesRouteSubscriber extends RouteSubscriberBase {

  /**
   * The inflector.
   *
   * @var \ICanBoogie\Inflector
   */
  private Inflector $inflector;

  /**
   * Constructs a new CrossBundlesRouteSubscriber object.
   */
  public function __construct() {
    $this->inflector = Inflector::get();
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    foreach ($collection->all() as $route) {
      assert($route instanceof Route);
      if (!$route->hasDefault(Routes::JSON_API_ROUTE_FLAG_KEY) || !$route->hasDefault(Routes::RESOURCE_TYPE_KEY)) {
        continue;
      }
      if ($route->getDefault('_controller') !== Routes::CONTROLLER_SERVICE_NAME . ':getCollection') {
        continue;
      }
      $resource_type = $route->getDefault(Routes::RESOURCE_TYPE_KEY);
      if (strpos($resource_type, 'commerce_') !== 0) {
        continue;
      }
      [$leading, $jsonapi_prefix, $entity_type_id] = explode('/', $route->getPath());
      $pluralized_resource_type = $this->inflector->pluralize((str_replace('commerce_', '', $entity_type_id)));
      $pluralized_resource_type = str_replace('_', '-', $pluralized_resource_type);
      $route->setPath("/$jsonapi_prefix/$pluralized_resource_type");
    }
  }

}
