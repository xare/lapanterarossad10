<?php

namespace Drupal\commerce_api\Resource;

use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_api\EntityResourceShim;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_order\Exception\OrderVersionMismatchException;
use Drupal\commerce_order\OrderItemStorageInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\jsonapi\Exception\UnprocessableHttpEntityException;
use Drupal\jsonapi\JsonApiResource\ResourceIdentifier;
use Drupal\jsonapi\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class CartRemoveItemResource extends CartResourceBase {

  /**
   * Constructs a new CartRemoveItemResource object.
   *
   * @param \Drupal\commerce_cart\CartProviderInterface $cartProvider
   *   The cart provider.
   * @param \Drupal\commerce_cart\CartManagerInterface $cartManager
   *   The cart manager.
   * @param \Drupal\commerce_api\EntityResourceShim $inner
   *   The JSON:API controller shim.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(protected CartProviderInterface $cartProvider, CartManagerInterface $cartManager, protected EntityResourceShim $inner, protected Connection $connection) {
    parent::__construct($cartProvider, $cartManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_cart.cart_provider'),
      $container->get('commerce_cart.cart_manager'),
      $container->get('commerce_api.jsonapi_controller_shim'),
      $container->get('database')
    );
  }

  /**
   * DELETE an order item from a cart.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order.
   * @param array $_order_item_resource_types
   *   An array order item resource types.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function process(Request $request, OrderInterface $commerce_order, array $_order_item_resource_types = []): ResourceResponse {
    $resource_type = $this->getGeneralizedOrderResourceType($_order_item_resource_types);
    $order_item_storage = $this->entityTypeManager->getStorage('commerce_order_item');
    assert($order_item_storage instanceof OrderItemStorageInterface);

    /** @var \Drupal\jsonapi\JsonApiResource\ResourceIdentifier[] $resource_identifiers */
    $resource_identifiers = $this->inner->deserialize($resource_type, $request, ResourceIdentifier::class, 'order_items');
    $transaction = $this->connection->startTransaction();
    try {
      foreach ($resource_identifiers as $resource_identifier) {
        $order_item = $order_item_storage->loadByProperties(['uuid' => $resource_identifier->getId()]);
        $order_item = reset($order_item);
        if (!$order_item instanceof OrderItemInterface || !$commerce_order->hasItem($order_item)) {
          throw new UnprocessableEntityHttpException("Order item {$resource_identifier->getId()} does not exist for order {$commerce_order->uuid()}.");
        }
        $this->cartManager->removeOrderItem($commerce_order, $order_item, FALSE);
      }
    }
    catch (UnprocessableHttpEntityException $e) {
      throw $e;
    }
    finally {
      try {
        $commerce_order->save();
      }
      catch (EntityStorageException $exception) {
        if ($exception->getPrevious() instanceof OrderVersionMismatchException) {
          $transaction->rollBack();
          throw new ConflictHttpException($exception->getMessage(), $exception);
        }
        throw $exception;
      }
    }

    $top_level_data = $this->createIndividualDataFromEntity($commerce_order);
    return $this->createJsonapiResponse($top_level_data, $request);
  }

}
