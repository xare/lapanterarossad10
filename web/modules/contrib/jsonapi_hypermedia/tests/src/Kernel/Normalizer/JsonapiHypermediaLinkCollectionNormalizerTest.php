<?php

namespace Drupal\Tests\jsonapi_hypermedia\Kernel\Normalizer;

use Drupal\Component\Assertion\Inspector;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Url;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\JsonApiResource\Link;
use Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\jsonapi\JsonApiResource\NullIncludedData;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\Normalizer\Value\CacheableNormalization;
use Drupal\KernelTests\KernelTestBase;

/**
 * Test the link collection normalizer that replaces the core normalizer.
 *
 * @group jsonapi_hypermedia
 *
 * @internal
 */
final class JsonapiHypermediaLinkCollectionNormalizerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'jsonapi',
    'serialization',
    'jsonapi_hypermedia',
  ];

  /**
   * Tests link collection normalization.
   */
  public function testNormalize() {
    // A single link with a key that matches its link relation type.
    $this->assertSame([
      'self' => [
        'href' => 'https://jsonapi.org',
      ],
    ], $this->getNormalization($this->getTestLinkCollection([
      'self' => new Link(new CacheableMetadata(), Url::fromUri('https://jsonapi.org'), 'self'),
    ])));

    // A single link with a key that's different from its link relation type.
    $this->assertSame([
      'self' => [
        'href' => 'https://jsonapi.org',
        'meta' => [
          'linkParams' => [
            'rel' => ['describedby'],
          ],
        ],
      ],
    ], $this->getNormalization($this->getTestLinkCollection([
      'self' => new Link(new CacheableMetadata(), Url::fromUri('https://jsonapi.org'), 'describedby'),
    ])));

    // Two links with a matching keys and matching link relation types.
    $link_collection = $this->getTestLinkCollection([
      'self' => new Link(new CacheableMetadata(), Url::fromUri('https://jsonapi.org'), 'self'),
    ]);
    $link_collection = $link_collection->withLink('self', new Link(new CacheableMetadata(), Url::fromUri('https://jsonapi.org'), 'self'));
    $this->assertSame([
      'self' => [
        'href' => 'https://jsonapi.org',
      ],
    ], $this->getNormalization($link_collection));

    // Two links with a matching keys but different link relation types.
    $link_collection = $this->getTestLinkCollection([
      'self' => new Link(new CacheableMetadata(), Url::fromUri('https://jsonapi.org'), 'self'),
    ]);
    $link_collection = $link_collection->withLink('self', new Link(new CacheableMetadata(), Url::fromUri('https://jsonapi.org'), 'describedby'));
    $this->assertSame([
      'self' => [
        'href' => 'https://jsonapi.org',
        'meta' => [
          'linkParams' => [
            'rel' => ['self', 'describedby'],
          ],
        ],
      ],
    ], $this->getNormalization($link_collection));

    // Two links with a matching keys and matching link relation types and
    // target attributes.
    $link_collection = $this->getTestLinkCollection([
      'self' => new Link(new CacheableMetadata(), Url::fromUri('https://jsonapi.org'), 'self', ['foo' => 'bar']),
    ]);
    $link_collection = $link_collection->withLink('self', new Link(new CacheableMetadata(), Url::fromUri('https://jsonapi.org'), 'self', ['foo' => 'bar']));
    $actual = $this->getNormalization($link_collection);
    $this->assertCount(1, $actual, var_export($actual, TRUE));
    $this->assertSame([
      'self' => [
        'href' => 'https://jsonapi.org',
        'meta' => [
          'linkParams' => [
            'foo' => 'bar',
          ],
        ],
      ],
    ], $actual);

    // Two links with a matching keys and matching link relation types, but
    // different target attributes.
    $link_collection = $this->getTestLinkCollection([
      'self' => new Link(new CacheableMetadata(), Url::fromUri('https://jsonapi.org'), 'self', ['foo' => 'bar']),
    ]);
    $link_collection = $link_collection->withLink('self', new Link(new CacheableMetadata(), Url::fromUri('https://jsonapi.org'), 'self', ['foo' => 'baz']));
    $actual = $this->getNormalization($link_collection);
    $this->assertCount(2, $actual, var_export($actual, TRUE));
    $normalized_keys = array_keys($actual);
    $this->assertTrue(array_reduce($normalized_keys, function ($bool, $key) {
      return $bool ? strpos($key, 'self--') === 0 : FALSE;
    }, TRUE), var_export($actual, TRUE));
    $this->assertSame([
      'href' => 'https://jsonapi.org',
      'meta' => [
        'linkParams' => [
          'foo' => 'bar',
        ],
      ],
    ], $actual[$normalized_keys[0]]);
    $this->assertSame([
      'href' => 'https://jsonapi.org',
      'meta' => [
        'linkParams' => [
          'foo' => 'baz',
        ],
      ],
    ], $actual[$normalized_keys[1]]);

    // Two links with different keys and link relation types that match their
    // keys.
    $this->assertSame([
      'related' => [
        'href' => 'https://jsonapi.org',
      ],
      'self' => [
        'href' => 'https://jsonapi.org',
      ],
    ], $this->getNormalization($this->getTestLinkCollection([
      'self' => new Link(new CacheableMetadata(), Url::fromUri('https://jsonapi.org'), 'self'),
      'related' => new Link(new CacheableMetadata(), Url::fromUri('https://jsonapi.org'), 'related'),
    ])));

    // Two links with different keys and link relation types that match their
    // sibling's keys.
    $this->assertSame([
      'related' => [
        'href' => 'https://jsonapi.org',
        'meta' => [
          'linkParams' => [
            'rel' => ['self'],
          ],
        ],
      ],
      'self' => [
        'href' => 'https://jsonapi.org',
        'meta' => [
          'linkParams' => [
            'rel' => ['related'],
          ],
        ],
      ],
    ], $this->getNormalization($this->getTestLinkCollection([
      'self' => new Link(new CacheableMetadata(), Url::fromUri('https://jsonapi.org'), 'related'),
      'related' => new Link(new CacheableMetadata(), Url::fromUri('https://jsonapi.org'), 'self'),
    ])));
  }

  /**
   * Tests that this module works with consumer_image_styles.
   */
  public function testConsumerImageStylesCompatibility(): void {
    $modules_available = $this->container->get('extension.list.module')
      ->getAllAvailableInfo();

    if (array_key_exists('consumer_image_styles', $modules_available)) {
      $this->enableModules(['consumers', 'consumer_image_styles']);
      // Ensure we have an up-to-date container.
      $this->container = $this->container->get('kernel')->rebuildContainer();
      $this->testNormalize();
    }
    else {
      $this->markTestSkipped('Cannot test compatibility with consumer_image_styles because it is not available.');
    }
  }

  /**
   * Gets a normalized array using the SUT.
   */
  protected function getNormalization(LinkCollection $link_collection) {
    $normalization = $this->container->get('jsonapi.serializer')
      ->normalize(['link_collection' => $link_collection], 'api_json', []);
    $this->assertIsArray($normalization);
    $this->assertArrayHasKey('link_collection', $normalization);
    $this->assertInstanceOf(CacheableNormalization::class, $normalization['link_collection']);
    return $normalization['link_collection']->getNormalization();
  }

  /**
   * Creates a link collection with which to test normalization.
   */
  protected function getTestLinkCollection(array $links) {
    Inspector::assertAllObjects($links, Link::class);
    $dummy_link_context = new JsonApiDocumentTopLevel(new ResourceObjectData([]), new NullIncludedData(), new LinkCollection($links));
    return $dummy_link_context->getLinks();
  }

}
