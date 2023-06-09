<?php declare(strict_types = 1);

namespace Drupal\Tests\commerce_api\Functional\Cart;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;

/**
 * @group commerce_api
 */
final class CartClearResourceTest extends CartResourceTestBase {

  /**
   * Tests clearing a cart.
   */
  public function testCartClear() {
    // Create a cart for the current user.
    $cart = $this->cartProvider->createCart('default', $this->store, $this->account);
    $this->cartManager->addEntity($cart, $this->variation, 5);

    $url = Url::fromRoute('commerce_api.carts.collection');
    $response = $this->request('GET', $url, $this->getAuthenticationRequestOptions());
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(['application/vnd.api+json'], $response->getHeader('Content-Type'));

    $this->assertTrue($response->hasHeader('X-Drupal-Dynamic-Cache'));
    $this->assertSame('UNCACHEABLE', $response->getHeader('X-Drupal-Dynamic-Cache')[0]);
    $this->assertSame([
      'commerce_order:1',
      'commerce_order_item:1',
      'commerce_product:1',
      'commerce_product_variation:1',
      'http_response',
    ], explode(' ', $response->getHeader('X-Drupal-Cache-Tags')[0]));

    $this->assertNotEquals([
      'data' => [
        [
          'type' => 'order--default',
          'id' => $cart->uuid(),
          'links' => [
            'self' => ['href' => Url::fromRoute('jsonapi.order--default.individual', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
          ],
          'attributes' => [
            'order_number' => NULL,
            'total_price' => NULL,
          ],
          'relationships' => [
            'store_id' => [
              'data' => [
                'type' => 'store--online',
                'id' => $this->store->uuid(),
              ],
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.order--default.store_id.relationship.get', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.order--default.store_id.related', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
              ],
            ],
            'order_items' => [
              'data' => [],
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.order--default.order_items.relationship.get', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.order--default.order_items.related', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
              ],
            ],
          ],
        ],
      ],
      'jsonapi' => [
        'version' => '1.0',
        'meta' => [
          'links' => [
            'self' => ['href' => 'http://jsonapi.org/format/1.0/'],
          ],
        ],
      ],
      'links' => [
        'self' => ['href' => $url->setAbsolute()->toString()],
      ],
    ], Json::decode((string) $response->getBody()));

    // Trigger an order version mismatch exception to test the response.
    $url = Url::fromRoute('commerce_api.carts.clear', ['commerce_order' => $cart->uuid()]);
    $this->toggleOrderVersionMismatch();
    $response = $this->request('DELETE', $url, $this->getAuthenticationRequestOptions());
    $this->toggleOrderVersionMismatch();
    $this->assertSame(409, $response->getStatusCode(), (string) $response->getBody());
    $response = $this->request('DELETE', $url, $this->getAuthenticationRequestOptions());
    $this->assertSame(204, $response->getStatusCode(), (string) $response->getBody());

    $order_storage = $this->container->get('entity_type.manager')->getStorage('commerce_order');
    $order_storage->resetCache([$cart->id()]);
    $cart = $order_storage->load($cart->id());
    $this->assertEquals(count($cart->getItems()), 0);

    $url = Url::fromRoute('commerce_api.carts.collection', [], [
      // 'query' => ['include' => 'order_items,order_items.purchased_entity'],
    ]);
    $response = $this->request('GET', $url, $this->getAuthenticationRequestOptions());
    $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
    $this->assertSame(['application/vnd.api+json'], $response->getHeader('Content-Type'));

    $this->assertTrue($response->hasHeader('X-Drupal-Dynamic-Cache'));
    $this->assertSame('UNCACHEABLE', $response->getHeader('X-Drupal-Dynamic-Cache')[0]);
    $this->assertSame(['commerce_order:1', 'http_response'], explode(' ', $response->getHeader('X-Drupal-Cache-Tags')[0]));
    $this->assertEquals([
      'data' => [
        [
          'type' => 'order--default',
          'id' => $cart->uuid(),
          'links' => [
            'self' => ['href' => Url::fromRoute('jsonapi.order--default.individual', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
          ],
          'attributes' => [
            'order_number' => NULL,
            'total_price' => NULL,
            'order_total' => [
              'subtotal' => [
                'number' => NULL,
                'currency_code' => NULL,
                'formatted' => NULL,
              ],
              'adjustments' => [],
              'total' => [
                'number' => NULL,
                'currency_code' => NULL,
                'formatted' => NULL,
              ],
            ],
            'billing_information' => NULL,
            'shipping_information' => NULL,
            'shipping_method' => NULL,
            'email' => $this->account->getEmail(),
            'state' => 'draft',
            'total_paid' => NULL,
            'payment_instrument' => NULL,
          ],
          'relationships' => [
            'store_id' => [
              'data' => [
                'type' => 'store--online',
                'id' => $this->store->uuid(),
                'meta' => [
                  'drupal_internal__target_id' => $this->store->id(),
                ],
              ],
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.order--default.store_id.relationship.get', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.order--default.store_id.related', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
              ],
            ],
            'order_items' => [
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.order--default.order_items.relationship.get', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.order--default.order_items.related', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
              ],
            ],
            'coupons' => [
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.order--default.coupons.relationship.get', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.order--default.coupons.related', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
              ],
            ],
            'shipments' => [
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.order--default.shipments.relationship.get', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.order--default.shipments.related', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
              ],
            ],
          ],
          'meta' => [
            'constraints' => [
              [
                'required' => [
                  'detail' => 'This value should not be null.',
                  'source' => ['pointer' => 'billing_profile'],
                ],
              ],
              [
                'required' => [
                  'detail' => 'This value should not be null.',
                  'source' => ['pointer' => 'shipping_information'],
                ],
              ],
            ],
          ],
        ],
      ],
      'jsonapi' => [
        'version' => '1.0',
        'meta' => [
          'links' => [
            'self' => ['href' => 'http://jsonapi.org/format/1.0/'],
          ],
        ],
      ],
      'links' => [
        'self' => ['href' => $url->setAbsolute()->toString()],
      ],
    ], Json::decode((string) $response->getBody()));
  }

}
