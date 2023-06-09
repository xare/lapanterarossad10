<?php declare(strict_types = 1);

namespace Drupal\Tests\commerce_api\Functional\Cart;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\jsonapi\Normalizer\HttpExceptionNormalizer;
use GuzzleHttp\RequestOptions;

/**
 * @group commerce_api
 */
final class CartRemoveItemResourceTest extends CartResourceTestBase {

  /**
   * Test request to delete item from non-existent cart.
   */
  public function testNoCartRemoveItem() {
    $url = Url::fromRoute('commerce_api.carts.remove_item', [
      'commerce_order' => '209c27eb-e5e4-47b3-b3fe-c7aa76dce92f',
    ]);
    $response = $this->request('DELETE', $url, $this->getAuthenticationRequestOptions());
    $this->assertResponseCode(404, $response);
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
          'title' => 'Not Found',
          'status' => '404',
          'detail' => 'The "commerce_order" parameter was not converted for the path "/jsonapi/carts/{commerce_order}/items" (route name: "commerce_api.carts.remove_item")',
          'links' => [
            'info' => ['href' => HttpExceptionNormalizer::getInfoUrl(404)],
            'via' => ['href' => $url->setAbsolute()->toString()],
          ],
        ],
      ],
    ], Json::decode((string) $response->getBody()));
  }

  /**
   * Removes cart items via the REST API.
   */
  public function testRemoveItem() {
    $request_options = $this->getAuthenticationRequestOptions();
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';

    // Failed request to delete item from cart that doesn't belong to the account.
    $not_my_cart = $this->cartProvider->createCart('default', $this->store, $this->createUser());
    $this->assertInstanceOf(OrderInterface::class, $not_my_cart);
    $this->cartManager->addEntity($not_my_cart, $this->variation, 2);
    $this->assertEquals(count($not_my_cart->getItems()), 1);
    $items = $not_my_cart->getItems();
    $not_my_order_item = $items[0];

    $url = Url::fromRoute('commerce_api.carts.remove_item', [
      'commerce_order' => $not_my_cart->uuid(),
    ]);
    $request_options[RequestOptions::BODY] = Json::encode([
      'data' => [
        [
          'type' => 'order-item--default',
          'id' => $not_my_order_item->uuid(),
        ],
      ],
    ]);
    $response = $this->request('DELETE', $url, $request_options);
    $this->assertResponseCode(403, $response);
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
          'detail' => "The following permissions are required: 'update commerce_order' OR 'update default commerce_order'.",
          'links' => [
            'info' => ['href' => HttpExceptionNormalizer::getInfoUrl(403)],
            'via' => ['href' => $url->setAbsolute()->toString()],
          ],
        ],
      ],
    ], Json::decode((string) $response->getBody()));

    // Add a cart that does belong to the account.
    $cart = $this->cartProvider->createCart('default', $this->store, $this->account);
    $this->assertInstanceOf(OrderInterface::class, $cart);
    $this->cartManager->addEntity($cart, $this->variation, 2);
    $this->cartManager->addEntity($cart, $this->variation2, 5);
    $this->assertEquals(count($cart->getItems()), 2);
    [$order_item, $order_item2] = $cart->getItems();

    // Request for order item that does not exist in the cart should fail.
    $url = Url::fromRoute('commerce_api.carts.remove_item', [
      'commerce_order' => $cart->uuid(),
    ]);
    $request_options[RequestOptions::BODY] = Json::encode([
      'data' => [
        [
          'type' => 'order-item--default',
          'id' => $not_my_order_item->uuid(),
        ],
      ],
    ]);
    $response = $this->request('DELETE', $url, $request_options);
    $this->assertResponseCode(422, $response);
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
          'title' => 'Unprocessable Entity',
          'status' => '422',
          'detail' => "Order item {$not_my_order_item->uuid()} does not exist for order {$cart->uuid()}.",
          'links' => [
            'via' => ['href' => $url->setAbsolute()->toString()],
          ],
        ],
      ],
    ], Json::decode((string) $response->getBody()));

    $this->container->get('entity_type.manager')->getStorage('commerce_order')->resetCache([$not_my_cart->id(), $cart->id()]);
    $not_my_cart = Order::load($not_my_cart->id());
    $cart = Order::load($cart->id());

    $this->assertEquals(count($not_my_cart->getItems()), 1);
    $this->assertEquals(count($cart->getItems()), 2);

    // Delete second order item from the cart.
    $url = Url::fromRoute('commerce_api.carts.remove_item', [
      'commerce_order' => $cart->uuid(),
    ]);
    $request_options[RequestOptions::BODY] = Json::encode([
      'data' => [
        [
          'type' => 'order-item--default',
          'id' => $order_item2->uuid(),
        ],
      ],
    ]);
    $this->toggleOrderVersionMismatch();
    $response = $this->request('DELETE', $url, $request_options);
    $this->assertResponseCode(409, $response);
    $this->toggleOrderVersionMismatch();
    $response = $this->request('DELETE', $url, $request_options);
    $this->assertResponseCode(200, $response);
    $data = Json::decode((string) $response->getBody());
    $this->container->get('entity_type.manager')->getStorage('commerce_order')->resetCache([$cart->id()]);
    $cart = Order::load($cart->id());
    $this->assertEquals('order--default', $data['data']['type']);
    $this->assertEquals($cart->uuid(), $data['data']['id']);

    $this->assertEquals(count($cart->getItems()), 1);
    $items = $cart->getItems();
    $remaining_order_item = $items[0];
    $this->assertEquals($order_item->id(), $remaining_order_item->id());

    // Delete remaining order item from the cart.
    $url = Url::fromRoute('commerce_api.carts.remove_item', [
      'commerce_order' => $cart->uuid(),
    ]);
    $request_options[RequestOptions::BODY] = Json::encode([
      'data' => [
        [
          'type' => 'order-item--default',
          'id' => $remaining_order_item->uuid(),
        ],
      ],
    ]);
    $response = $this->request('DELETE', $url, $request_options);
    $this->assertResponseCode(200, $response);
    $data = Json::decode((string) $response->getBody());
    $this->assertEquals('order--default', $data['data']['type']);
    $this->assertEquals($cart->uuid(), $data['data']['id']);
    $this->container->get('entity_type.manager')->getStorage('commerce_order')->resetCache([$cart->id()]);
    $cart = Order::load($cart->id());

    $this->assertEquals(count($cart->getItems()), 0);
  }

}
