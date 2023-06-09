<?php

namespace Drupal\commerce_api;

use Drupal\commerce_cart\CartSessionInterface;
use Drupal\Core\TempStore\SharedTempStore;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Decorates the cart session to support cart tokens.
 */
final class CartTokenSession implements CartSessionInterface {

  const HEADER_NAME = 'Commerce-Cart-Token';
  const QUERY_NAME = 'cartToken';

  /**
   * The tempstore service.
   *
   * @var \Drupal\Core\TempStore\SharedTempStore
   */
  private SharedTempStore $tempStore;

  /**
   * Constructs a new CartTokenSession object.
   *
   * @param \Drupal\commerce_cart\CartSessionInterface $inner
   *   The decorated cart session.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory $temp_store_factory
   *   The temp store factory.
   */
  public function __construct(private CartSessionInterface $inner, private RequestStack $requestStack, SharedTempStoreFactory $temp_store_factory) {
    $this->tempStore = $temp_store_factory->get('commerce_api_tokens');
  }

  /**
   * {@inheritdoc}
   */
  public function getCartIds($type = self::ACTIVE) {
    if ($this->getCurrentRequestCartToken() === NULL) {
      return $this->inner->getCartIds($type);
    }
    $data = $this->getTokenCartData();
    return $data[$type];
  }

  /**
   * {@inheritdoc}
   */
  public function addCartId($cart_id, $type = self::ACTIVE) {
    $this->inner->addCartId($cart_id, $type);

    if ($this->getCurrentRequestCartToken() !== NULL) {
      $data = $this->getTokenCartData();
      $ids = $data[$type];
      $ids[] = $cart_id;
      $data[$type] = $ids;
      $this->setTokenCartData($data);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function hasCartId($cart_id, $type = self::ACTIVE) {
    if ($this->getCurrentRequestCartToken() === NULL) {
      return $this->inner->hasCartId($cart_id, $type);
    }
    $data = $this->getTokenCartData();
    $ids = $data[$type];
    return in_array($cart_id, $ids, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteCartId($cart_id, $type = self::ACTIVE) {
    $this->inner->deleteCartId($cart_id, $type);

    if ($this->getCurrentRequestCartToken() !== NULL) {
      $data = $this->getTokenCartData();
      $ids = $data[$type];
      $ids = array_diff($ids, [$cart_id]);
      $data[$type] = $ids;
      $this->setTokenCartData($data);
    }
  }

  /**
   * Get the cart token from the request.
   *
   * @return string
   *   The cart token.
   */
  private function getCurrentRequestCartToken() {
    $request = $this->requestStack->getCurrentRequest();
    assert($request instanceof Request);
    return $request->headers->get(static::HEADER_NAME);
  }

  /**
   * Get the token cart data.
   *
   * @return array
   *   The data.
   */
  private function getTokenCartData() {
    $defaults = [
      static::ACTIVE => [],
      static::COMPLETED => [],
    ];
    $token = $this->getCurrentRequestCartToken();
    if (empty($token)) {
      return $defaults;
    }
    return $this->tempStore->get($token) ?: $defaults;
  }

  /**
   * Set the token cart data.
   *
   * @param array $data
   *   The data.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  private function setTokenCartData(array $data) {
    $token = $this->getCurrentRequestCartToken();
    if (!empty($token)) {
      $this->tempStore->set($token, $data);
    }
  }

}
