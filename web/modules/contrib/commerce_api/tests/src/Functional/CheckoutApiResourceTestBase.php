<?php

namespace Drupal\Tests\commerce_api\Functional;

use Drupal\commerce_order\Entity\OrderType;
use Drupal\commerce_price\Comparator\NumberComparator;
use Drupal\commerce_price\Comparator\PriceComparator;
use Drupal\commerce_product\Entity\ProductVariationType;
use Drupal\commerce_store\StoreCreationTrait;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\jsonapi\Functional\JsonApiRequestTestTrait;
use Psr\Http\Message\ResponseInterface;
use SebastianBergmann\Comparator\Factory as PhpUnitComparatorFactory;

abstract class CheckoutApiResourceTestBase extends BrowserTestBase {

  use StoreCreationTrait;
  use JsonApiRequestTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The store entity.
   *
   * @var \Drupal\commerce_store\Entity\Store
   */
  protected $store;

  /**
   * A variation to add to carts.
   *
   * @var \Drupal\commerce_product\Entity\ProductVariation
   */
  protected $variation;

  /**
   * A second variation to add to carts.
   *
   * @var \Drupal\commerce_product\Entity\ProductVariation
   */
  protected $variation2;

  /**
   * The account to use for authentication.
   *
   * @var null|\Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'basic_auth',
    'commerce_payment',
    'commerce_payment_example',
    'commerce_promotion',
    'commerce_shipping',
    'jsonapi_resources',
    'commerce_api',
    'commerce_api_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $factory = PhpUnitComparatorFactory::getInstance();
    $factory->register(new NumberComparator());
    $factory->register(new PriceComparator());

    $this->store = $this->createStore();

    /** @var \Drupal\commerce_product\Entity\ProductVariationTypeInterface $product_variation_type */
    $product_variation_type = ProductVariationType::load('default');
    $product_variation_type->setGenerateTitle(FALSE);
    $product_variation_type->save();
    // Install the variation trait.
    $trait_manager = $this->container->get('plugin.manager.commerce_entity_trait');
    $trait = $trait_manager->createInstance('purchasable_entity_shippable');
    $trait_manager->installTrait($trait, 'commerce_product_variation', 'default');

    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
    $order_type = OrderType::load('default');
    $order_type->setThirdPartySetting('commerce_shipping', 'shipment_type', 'default');
    $order_type->save();
    // Create the order field.
    $field_definition = commerce_shipping_build_shipment_field_definition($order_type->id());
    $this->container->get('commerce.configurable_field_manager')->createField($field_definition);

    // Create a product variation.
    $this->variation = $this->createEntity('commerce_product_variation', [
      'type' => 'default',
      'sku' => $this->randomMachineName(),
      'price' => [
        'number' => 1000,
        'currency_code' => 'USD',
      ],
    ]);

    // Create a second product variation.
    $this->variation2 = $this->createEntity('commerce_product_variation', [
      'type' => 'default',
      'sku' => $this->randomMachineName(),
      'price' => [
        'number' => 500,
        'currency_code' => 'USD',
      ],
    ]);

    // We need a product too otherwise tests complain about the missing
    // backreference.
    $this->createEntity('commerce_product', [
      'type' => 'default',
      'title' => $this->randomMachineName(),
      'stores' => [$this->store],
      'variations' => [$this->variation, $this->variation2],
    ]);
    // Create an account, which tests will use. Also ensure the @current_user
    // service this account, to ensure certain access check logic in tests works
    // as expected.
    $this->account = $this->createUser();
    $this->container->get('current_user')->setAccount($this->account);

    $this->config('jsonapi.settings')->set('read_only', FALSE)->save(TRUE);

    $this->createEntity('commerce_payment_gateway', [
      'id' => 'example',
      'label' => 'Example',
      'plugin' => 'example_offsite_redirect',
    ]);

    $shipping_method = $this->createEntity('commerce_shipping_method', [
      'stores' => $this->store->id(),
      'name' => 'Example',
      'plugin' => [
        'target_plugin_id' => 'flat_rate',
        'target_plugin_configuration' => [
          'rate_label' => 'Flat rate',
          'rate_amount' => [
            'number' => '5',
            'currency_code' => 'USD',
          ],
        ],
      ],
      'status' => TRUE,
      'weight' => 1,
    ]);

    $another_shipping_method = $this->createEntity('commerce_shipping_method', [
      'stores' => $this->store->id(),
      'name' => 'Another shipping method',
      'plugin' => [
        'target_plugin_id' => 'flat_rate',
        'target_plugin_configuration' => [
          'rate_label' => 'Flat rate',
          'rate_amount' => [
            'number' => '20',
            'currency_code' => 'USD',
          ],
        ],
      ],
      'status' => TRUE,
      'weight' => 0,
    ]);
    \Drupal::service('router.builder')->rebuild();
  }

  /**
   * Creates a new entity.
   *
   * @param string $entity_type
   *   The entity type to be created.
   * @param array $values
   *   An array of settings.
   *   Example: 'id' => 'foo'.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   A new entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createEntity($entity_type, array $values): EntityInterface {
    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->container->get('entity_type.manager')->getStorage($entity_type);
    $entity = $storage->create($values);
    $entity->save();
    // The newly saved entity isn't identical to a loaded one, and would fail
    // comparisons.
    $entity = $storage->load($entity->id());

    return $entity;
  }

  /**
   * Returns Guzzle request options for authentication.
   *
   * @return array
   *   Guzzle request options to use for authentication.
   *
   * @see \GuzzleHttp\ClientInterface::request()
   */
  protected function getAuthenticationRequestOptions(): array {
    return [
      'headers' => [
        'Authorization' => 'Basic ' . base64_encode($this->account->name->value . ':' . $this->account->passRaw),
      ],
    ];
  }

  /**
   * Asserts the response code for a response.
   *
   * @param int $expected_status_code
   *   The expected response code.
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The response.
   */
  protected function assertResponseCode($expected_status_code, ResponseInterface $response): void {
    $this->assertSame($expected_status_code, $response->getStatusCode(), var_export(Json::decode((string) $response->getBody()), TRUE));
  }

  /**
   * Gets the payment options meta.
   *
   * @return array
   *   The payment options meta.
   */
  protected static function getPaymentOptionsMeta() : array {
    return [
      [
        'id' => 'example',
        'label' => 'Example',
        'payment_gateway_id' => 'example',
        'payment_method_id' => NULL,
        'payment_method_type_id' => NULL,
      ],
    ];
  }

  /**
   * Get the shipping methods relationship.
   *
   * @return array
   *   The relationship.
   */
  protected static function getShippingMethodsRelationship() {
    return [
      [
        'id' => '2--default',
        'shipping_method_id' => '2',
        'service' => [
          'id' => 'default',
          'label' => 'Flat rate',
        ],
        'original_amount' => [
          'number' => '20',
          'currency_code' => 'USD',
          'formatted' => '$20.00',
        ],
        'amount' => [
          'number' => '20',
          'currency_code' => 'USD',
          'formatted' => '$20.00',
        ],
        'delivery_date' => NULL,
        'description' => NULL,
        'data' => [],
      ],
      [
        'id' => '1--default',
        'shipping_method_id' => '1',
        'service' => [
          'id' => 'default',
          'label' => 'Flat rate',
        ],
        'original_amount' => [
          'number' => '5',
          'currency_code' => 'USD',
          'formatted' => '$5.00',
        ],
        'amount' => [
          'number' => '5',
          'currency_code' => 'USD',
          'formatted' => '$5.00',
        ],
        'delivery_date' => NULL,
        'description' => NULL,
        'data' => [],
      ],
    ];
  }

  /**
   * Helper method to toggle the order version mismatch exception.
   *
   * When calling this method the first time, this will cause the test order
   * subscriber to artificially trigger an order version mismatch exception.
   * When this is called a second time, this behavior is disabled.
   *
   * @see \Drupal\commerce_api_test\EventSubscriber\OrderSubscriber
   */
  protected function toggleOrderVersionMismatch() {
    /** @var \Drupal\Core\State\StateInterface $state */
    $state = $this->container->get('state');
    $state->set('trigger_order_version_mismatch', !$state->get('trigger_order_version_mismatch', FALSE));
  }

}
