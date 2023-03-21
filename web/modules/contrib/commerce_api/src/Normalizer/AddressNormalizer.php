<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Normalizer;

use Drupal\commerce_api\Plugin\DataType\Address;
use Drupal\serialization\Normalizer\NormalizerBase;

final class AddressNormalizer extends NormalizerBase {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = Address::class;

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    assert($object instanceof Address);
    return array_filter($object->getValue());
  }

}
