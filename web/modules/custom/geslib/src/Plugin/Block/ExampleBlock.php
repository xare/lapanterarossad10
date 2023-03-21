<?php

namespace Drupal\geslib\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "geslib_example",
 *   admin_label = @Translation("Example"),
 *   category = @Translation("geslib")
 * )
 */
class ExampleBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build['content'] = [
      '#markup' => $this->t('It works!'),
    ];
    return $build;
  }

}
