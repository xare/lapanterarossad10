<?php declare(strict_types = 1);

namespace Drupal\commerce_api\EventSubscriber;

use Drupal\commerce_order\Event\OrderEvents;
use Drupal\commerce_order\Event\OrderProfilesEvent;
use Drupal\commerce_shipping\ShippingOrderManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\profile\Entity\ProfileInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ShippingProfileSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new ShippingProfileSubscriber object.
   *
   * @param \Drupal\commerce_shipping\ShippingOrderManagerInterface $shippingOrderManager
   *   The shipping order manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(protected ShippingOrderManagerInterface $shippingOrderManager, protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      // Run after Shipping's normal subscriber.
      OrderEvents::ORDER_PROFILES => ['onProfiles', -100],
    ];
  }

  /**
   * Ensures there is a shipping profile.
   *
   * @param \Drupal\commerce_order\Event\OrderProfilesEvent $event
   *   The event.
   */
  public function onProfiles(OrderProfilesEvent $event) {
    if (!$event->hasProfile('shipping')) {
      $order = $event->getOrder();
      $shipping_profile_id = $order->getData('shipping_profile_id');

      $shipping_profile = NULL;
      if ($shipping_profile_id !== NULL) {
        $profile_storage = $this->entityTypeManager->getStorage('profile');
        $shipping_profile = $profile_storage->load($shipping_profile_id);
      }
      elseif ($order->getData('shipping_profile')) {
        $shipping_profile = $order->getData('shipping_profile');
      }
      if (!$shipping_profile instanceof ProfileInterface) {
        $shipping_profile = $this->shippingOrderManager->createProfile($order);
      }

      $event->addProfile('shipping', $shipping_profile);
    }
  }

}
