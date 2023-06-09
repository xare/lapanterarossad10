<?php declare(strict_types = 1);

namespace Drupal\Tests\commerce_api\Functional\Cart;

use Drupal\commerce_product\Entity\ProductVariationType;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\jsonapi\Normalizer\HttpExceptionNormalizer;
use Drupal\user\Entity\User;

/**
 * @group commerce_api
 */
final class CartCanonicalResourceTest extends CartResourceTestBase {

  /**
   * Test cart canonical.
   */
  public function testCartCanonical() {
    // Create a cart for another user.
    $anon_cart = $this->cartProvider->createCart('default', $this->store, User::getAnonymousUser());

    $url = Url::fromRoute('commerce_api.carts.canonical', ['commerce_order' => $anon_cart->uuid()], [
      'query' => ['include' => 'order_items,order_items.purchased_entity'],
    ]);

    $response = $this->request('GET', $url, $this->getAuthenticationRequestOptions());
    $this->assertSame(403, $response->getStatusCode());
    $this->assertSame(['application/vnd.api+json'], $response->getHeader('Content-Type'));
    // There should be no body as the cart does not belong to the session.
    $this->assertEquals([
      'jsonapi' => [
        'version' => '1.0',
        'meta' => [
          'links' => [
            'self' => ['href' => 'http://jsonapi.org/format/1.0/'],
          ],
        ],
      ],
      'errors' => [
        [
          'title' => 'Forbidden',
          'status' => '403',
          'detail' => "The following permissions are required: 'view commerce_order' OR 'view default commerce_order'.",
          'links' => [
            'via' => ['href' => $url->setAbsolute()->toString()],
            'info' => ['href' => HttpExceptionNormalizer::getInfoUrl(403)],
          ],
        ],
      ],
    ], Json::decode((string) $response->getBody()));

    // Create a cart for the current user.
    $cart = $this->cartProvider->createCart('default', $this->store, $this->account);
    $order_item = $this->cartManager->addEntity($cart, $this->variation, 5);

    $product_variation_type = ProductVariationType::load('default');

    $url = Url::fromRoute('commerce_api.carts.canonical', ['commerce_order' => $cart->uuid()]);
    $response = $this->request('GET', $url, $this->getAuthenticationRequestOptions());
    $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
    $this->assertSame(['application/vnd.api+json'], $response->getHeader('Content-Type'));
    // There should be no body as the cart does not belong to the session.
    $this->assertEquals([
      'data' => [
        'type' => 'order--default',
        'id' => $cart->uuid(),
        'links' => [
          'self' => ['href' => Url::fromRoute('jsonapi.order--default.individual', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
        ],
        'attributes' => [
          'order_number' => NULL,
          'billing_information' => NULL,
          'shipping_information' => NULL,
          'shipping_method' => NULL,
          'total_price' => [
            'number' => '5000.0',
            'currency_code' => 'USD',
            'formatted' => '$5,000.00',
          ],
          'order_total' => [
            'subtotal' => [
              'number' => '5000.0',
              'currency_code' => 'USD',
              'formatted' => '$5,000.00',
            ],
            'adjustments' => [],
            'total' => [
              'number' => '5000.0',
              'currency_code' => 'USD',
              'formatted' => '$5,000.00',
            ],
          ],
          'email' => $this->account->getEmail(),
          'state' => 'draft',
          'payment_instrument' => NULL,
          'total_paid' => NULL,
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
            'data' => [
              [
                'type' => 'order-item--default',
                'id' => $order_item->uuid(),
                'meta' => [
                  'drupal_internal__target_id' => $order_item->id(),
                ],
              ],
            ],
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
      'included' => [
        [
          'type' => 'order-item--default',
          'id' => $order_item->uuid(),
          'links' => [
            'self' => ['href' => Url::fromRoute('jsonapi.order-item--default.individual', ['entity' => $order_item->uuid()])->setAbsolute()->toString()],
          ],
          'attributes' => [
            'title' => $order_item->label(),
            'quantity' => (int) $order_item->getQuantity(),
            'unit_price' => $order_item->get('unit_price')->first()->getValue() + ['formatted' => '$1,000.00'],
            'total_price' => [
              'number' => '5000.0',
              'currency_code' => 'USD',
              'formatted' => '$5,000.00',
            ],
            'locked' => FALSE,
          ],
          'relationships' => [
            'order_id' => [
              'data' => [
                'type' => 'order--default',
                'id' => $cart->uuid(),
                'meta' => [
                  'drupal_internal__target_id' => $cart->id(),
                ],
              ],
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.order-item--default.order_id.relationship.get', ['entity' => $order_item->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.order-item--default.order_id.related', ['entity' => $order_item->uuid()])->setAbsolute()->toString()],
              ],
            ],
            'purchased_entity' => [
              'data' => [
                'type' => 'product-variation--default',
                'id' => $this->variation->uuid(),
                'meta' => [
                  'drupal_internal__target_id' => $this->variation->id(),
                ],
              ],
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.order-item--default.purchased_entity.relationship.get', ['entity' => $order_item->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.order-item--default.purchased_entity.related', ['entity' => $order_item->uuid()])->setAbsolute()->toString()],
              ],
            ],
          ],
        ],
        [
          'type' => 'product-variation--default',
          'id' => $this->variation->uuid(),
          'links' => [
            'self' => ['href' => Url::fromRoute('jsonapi.product-variation--default.individual', ['entity' => $this->variation->uuid()])->setAbsolute()->toString()],
          ],
          'attributes' => [
            'sku' => $this->variation->getSku(),
            'title' => $this->variation->label(),
            'list_price' => NULL,
            'price' => $this->variation->get('price')->first()->getValue() + ['formatted' => '$1,000.00'],
            'resolved_price' => [
              'number' => '1000.0',
              'currency_code' => 'USD',
              'formatted' => '$1,000.00',
            ],
            'weight' => NULL,
          ],
          'relationships' => [
            'product_variation_type' => [
              'data' => [
                'type' => 'product-variation-type',
                'id' => $product_variation_type->uuid(),
                'meta' => [
                  'drupal_internal__target_id' => $product_variation_type->id(),
                ],
              ],
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.product-variation--default.product_variation_type.relationship.get', ['entity' => $this->variation->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.product-variation--default.product_variation_type.related', ['entity' => $this->variation->uuid()])->setAbsolute()->toString()],
              ],
            ],
            'product_id' => [
              'data' => [
                'type' => 'product--default',
                'id' => $this->variation->getProduct()->uuid(),
                'meta' => [
                  'drupal_internal__target_id' => $this->variation->getProduct()->id(),
                ],
              ],
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.product-variation--default.product_id.relationship.get', ['entity' => $this->variation->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.product-variation--default.product_id.related', ['entity' => $this->variation->uuid()])->setAbsolute()->toString()],
              ],
            ],
          ],
        ],
      ],
    ], Json::decode((string) $response->getBody()));
  }

}
