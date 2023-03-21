<?php declare(strict_types=1);

namespace Drupal\commerce_api\Normalizer;

use Drupal\commerce_shipping\Plugin\DataType\ShipmentItem as ShipmentItemDataType;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\serialization\Normalizer\NormalizerBase;

class ShipmentItemNormalizer extends NormalizerBase {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = ShipmentItemDataType::class;

  /**
   * Constructs a new ShipmentItemNormalizer object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    assert($object instanceof ShipmentItemDataType);
    $shipment_item = $object->toArray();
    $order_item = $this->entityTypeManager->getStorage('commerce_order_item')->load($shipment_item['order_item_id']);
    $shipment_item['order_item_id'] = $order_item ? $order_item->uuid() : NULL;
    return $shipment_item;
  }

}
