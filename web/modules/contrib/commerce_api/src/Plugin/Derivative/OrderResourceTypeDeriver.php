<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class OrderResourceTypeDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * ShippingMethodLinkDeriver constructor.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resourceTypeRepository
   *   The JSON:API resource type repository.
   */
  public function __construct(protected ResourceTypeRepositoryInterface $resourceTypeRepository) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('jsonapi.resource_type.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $resource_types = array_filter($this->resourceTypeRepository->all(), static function (ResourceType $resource_type) {
      return $resource_type->getEntityTypeId() === 'commerce_order';
    });
    return array_reduce($resource_types, static function ($derivative_definitions, ResourceType $resource_type) use ($base_plugin_definition) {
      $derivative_definitions[$resource_type->getTypeName()] = array_merge($base_plugin_definition, [
        'link_context' => [
          'resource_object' => $resource_type->getTypeName(),
        ],
      ]);
      return $derivative_definitions;
    });
  }

}
