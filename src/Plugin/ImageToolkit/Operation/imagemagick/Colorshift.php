<?php

/**
 * @file
 * Contains \Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick\Colorshift.
 */

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\image_effects\Plugin\ImageToolkit\Operation\ColorshiftTrait;
use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;

/**
 * Defines ImageMagick Colorshift operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_imagemagick_colorshift",
 *   toolkit = "imagemagick",
 *   operation = "colorshift",
 *   label = @Translation("Colorshift"),
 *   description = @Translation("Shift image colors.")
 * )
 */
class Colorshift extends ImagemagickImageToolkitOperationBase {

  use ColorshiftTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $this->getToolkit()->addArgument("-fill " . $this->getToolkit()->escapeShellArg($arguments['RGB']) . " -colorize 50%");
    return TRUE;
  }

}