<?php

namespace Drupal\geslib\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for geslib routes.
 */
class GeslibController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build() {

    $content = [];
    $content['#markup'] = 'Módulo Geslib';
    return [
      '#theme' => 'geslib_list_page',
      '#content' => $content,
      // '#type' => 'markup',
      // '#markup' => $this->t('Geslib List'),
    ];

    // return $build;
  }

}
