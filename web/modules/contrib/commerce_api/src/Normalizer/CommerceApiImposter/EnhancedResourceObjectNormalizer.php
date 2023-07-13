<?php declare(strict_types = 1);

namespace Drupal\jsonapi\Normalizer\CommerceApiImposter;

use Drupal\commerce_api\Events\CollectResourceObjectMetaEvent;
use Drupal\commerce_api\Events\JsonapiEvents;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\jsonapi\Normalizer\ResourceObjectNormalizer;
use Drupal\jsonapi\Normalizer\Value\CacheableNormalization;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @todo remove after https://www.drupal.org/project/drupal/issues/3100732
 * @todo remove after https://www.drupal.org/project/drupal/issues/3125777
 */
final class EnhancedResourceObjectNormalizer extends ResourceObjectNormalizer {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private EventDispatcherInterface $eventDispatcher;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  private RendererInterface $renderer;

  /**
   * Set the event dispatcher.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function setEventDispatcher(EventDispatcherInterface $event_dispatcher) {
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * Set the renderer.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function setRenderer(RendererInterface $renderer) {
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []): array|string|int|float|bool|\ArrayObject|NULL {
    $parent_normalization = parent::normalize($object, $format, $context);
    assert($parent_normalization instanceof CacheableNormalization);
    $altered_normalization = $parent_normalization->getNormalization();
    $event = new CollectResourceObjectMetaEvent($object, $context);
    $render_context = new RenderContext();
    $this->renderer->executeInRenderContext($render_context, function () use ($event) {
      $this->eventDispatcher->dispatch($event, JsonapiEvents::COLLECT_RESOURCE_OBJECT_META);
    });
    $altered_normalization['meta'] = $this->serializer->normalize($event->getMeta(), $format, $context);
    if (!$render_context->isEmpty()) {
      $parent_normalization->withCacheableDependency($render_context->pop());
    }
    return new CacheableNormalization($parent_normalization, array_filter($altered_normalization));
  }

  /**
   * {@inheritdoc}
   */
  protected function serializeField($field, array $context, $format) {
    // We need to check if this is a typed data object that should be
    // serialized. The parent field handles FieldItemListInterface but not other
    // generic typed data objects. If this is any form of typed data outside
    // beyond a field, we normalize it.
    if (!($field instanceof FieldItemListInterface) && $field instanceof TypedDataInterface) {
      return CacheableNormalization::permanent($this->serializer->normalize($field, $format, $context));
    }
    return parent::serializeField($field, $context, $format);
  }

}
