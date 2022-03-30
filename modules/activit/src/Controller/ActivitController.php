<?php

namespace Drupal\activit\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for ActivIT routes.
 */
class ActivitController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build() {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

}
