<?php

namespace Drupal\commerce_api\EventSubscriber;

use Drupal\commerce_cart\CartSessionInterface;
use Drupal\commerce_api\CartTokenSession;
use Drupal\Core\TempStore\SharedTempStore;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Cart token subscriber.
 *
 * This subscriber provides two pieces of functionality.
 *
 * On response, it ensures the Vary header contains the cart token header. This
 * handles browser and reverse proxy caching handling.
 *
 * On request, it checks if the cart token query parameter is available. This
 * ensures cart data is passed to the user's session. For example, a user that
 * created a cart from a decoupled application but visits checkout using the
 * cart token to finish order purchased.
 */
final class CartTokenSubscriber implements EventSubscriberInterface {

  /**
   * The tempstore service.
   *
   * @var \Drupal\Core\TempStore\SharedTempStore
   */
  private SharedTempStore $tempStore;

  /**
   * Constructs a new CartTokenSubscriber object.
   *
   * @param \Drupal\commerce_cart\CartSessionInterface $cartSession
   *   The cart session.
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory $temp_store_factory
   *   The temp store factory.
   */
  public function __construct(private CartSessionInterface $cartSession, SharedTempStoreFactory $temp_store_factory) {
    $this->tempStore = $temp_store_factory->get('commerce_api_tokens');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    // Run before router_listener so we execute before access checks, and before
    // dynamic_page_cache so we can populate a session. The ensures proper
    // access to CheckoutController.
    $events[KernelEvents::REQUEST][] = ['onRequest', 100];

    $events[KernelEvents::RESPONSE][] = ['onResponse', -10];
    return $events;
  }

  /**
   * Loads the token cart data and resets it to the session.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event, which contains the current request.
   */
  public function onRequest(RequestEvent $event) {
    $cart_token = $event->getRequest()->query->get(CartTokenSession::QUERY_NAME);
    if ($cart_token) {
      $token_cart_data = $this->tempStore->get($cart_token);
      foreach ([CartSessionInterface::ACTIVE, CartSessionInterface::COMPLETED] as $cart_type) {
        if (isset($token_cart_data[$cart_type]) && is_array($token_cart_data[$cart_type])) {
          foreach ($token_cart_data[$cart_type] as $token_cart_datum) {
            $this->cartSession->addCartId($token_cart_datum, $cart_type);
          }
        }
      }
    }
  }

  /**
   * Ensures the Vary header contains the cart token header name.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function onResponse(ResponseEvent $event) {
    if (!$event->isMainRequest()) {
      return;
    }
    $request = $event->getRequest();
    if ($request->headers->has(CartTokenSession::HEADER_NAME)) {
      $response = $event->getResponse();
      // The Vary header gets mangled with CORS.
      // @see https://www.drupal.org/project/commerce_api/issues/3116590
      $vary = array_filter($response->getVary());
      $vary[] = CartTokenSession::HEADER_NAME;
      $response->setVary(implode(', ', $vary));
    }
  }

}
