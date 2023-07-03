<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Events;

use Drupal\Component\EventDispatcher\Event;
use Drupal\jsonapi\JsonApiResource\Relationship;

/**
 * Event to collect meta for a Relationship object.
 *
 * @todo remove after https://www.drupal.org/project/drupal/issues/3100732
 */
final class CollectRelationshipMetaEvent extends Event {

  /**
   * The meta data.
   *
   * @var array
   */
  private array $meta = [];

  /**
   * Constructs a new CollectRelationshipMetaEvent object.
   *
   * @param \Drupal\jsonapi\JsonApiResource\Relationship $relationship
   *   The resource object.
   * @param array $context
   *   The context.
   */
  public function __construct(private Relationship $relationship, private array $context) {}

  /**
   * Get the relationship.
   *
   * @return \Drupal\jsonapi\JsonApiResource\Relationship
   *   The resource object.
   */
  public function getRelationship(): Relationship {
    return $this->relationship;
  }

  /**
   * Get the context.
   *
   * @return array
   *   The context.
   */
  public function getContext(): array {
    return $this->context;
  }

  /**
   * Get the meta data.
   *
   * @return array
   *   The meta data.
   */
  public function getMeta(): array {
    return $this->meta;
  }

  /**
   * Set the meta data.
   *
   * @param array $meta
   *   The meta data.
   */
  public function setMeta(array $meta): void {
    $this->meta = $meta;
  }

}
