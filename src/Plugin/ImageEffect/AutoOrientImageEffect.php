<?php

/**
 * @file
 * Contains \Drupal\image_effects\Plugin\ImageEffect\AutoOrientImageEffect.
 */

namespace Drupal\image_effects\Plugin\ImageEffect;

use Drupal\Core\Image\ImageInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\image\ConfigurableImageEffectBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface;

/**
 * Automatically adjusts the orientation of an image resource.
 *
 * Uses EXIF Orientation tags to determine the image orientation.
 * EXIF: https://en.wikipedia.org/wiki/Exchangeable_image_file_format
 * EXIF orientation tag: http://sylvana.net/jpegcrop/exif_orientation.html
 *
 * Originally contributed to the imagecache_actions module by jonathan_hunt
 * https://drupal.org/user/28976, September 1, 2009.
 *
 * @ImageEffect(
 *   id = "image_effects_auto_orient",
 *   label = @Translation("Automatically correct orientation"),
 *   description = @Translation("Automatically rotates images according to orientation flag set by many phones and digital cameras.")
 * )
 */
class AutoOrientImageEffect extends ConfigurableImageEffectBase implements ContainerFactoryPluginInterface {

  /**
   * The MIME type guessing service.
   *
   * @var \Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface
   */
  protected $mimeTypeGuesser;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs an AutoOrientImageEffect object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface $mime_type_guesser
   *   The MIME type guessing service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger, MimeTypeGuesserInterface $mime_type_guesser, FileSystemInterface $file_system) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger);
    $this->mimeTypeGuesser = $mime_type_guesser;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('image'),
      $container->get('file.mime_type.guesser'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'scan_exif' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    if (!extension_loaded('exif')) {
      // Issue a warning if the PHP EXIF extension is not enabled.
      drupal_set_message($this->t('This image effect requires the PHP EXIF extension to be enabled to work properly.'), 'warning');
    }

    $form['info'] = [
      '#type'  => 'details',
      '#title' => $this->t('Information'),
    ];
    $form['info']['help'] = array(
      '#markup' => "<p>Certain cameras can embed <em>orientation</em> information into image
        files when they save them. This information is embedded in an EXIF tag
        and can be used to rotate images to their correct position for display.
        <em>Not all cameras or images contain this information.</em>
        This process is only useful for images that contain this information,
        whereas for other images it is harmless.
        </p>
        <p>Although most modern browsers do support the orientation tag, the
        information may get lost or become incorrect by other operations.
        So, to support all browsers and prevent rotation errors, it is better to
        start each image style with this effect.
        </p>
        <p>The expected/supported values are:<br/>
        <strong>Tag</strong>: <code>0x0112  Orientation</code>
        </p>
        <ul>
        <li>1 = Horizontal (normal)</li>
        <li>3 = Rotate 180</li>
        <li>6 = Rotate 90 CW</li>
        <li>8 = Rotate 270 CW</li>
        </ul>
        <p>Wikipedia: <a href='https://en.wikipedia.org/wiki/Exchangeable_image_file_format'>Exchangeable image file format</a></p>
      ",
    );

    $form['scan_exif'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Scan image file'),
      '#description' => $this->t('When selected, original image files supporting EXIF data (e.g. JPEG, TIFF) will be scanned to determine styled image orientation and dimensions. This slightly impacts performance, but allows to render more accurate HTML <kbd>&lt;img&gt;</kbd> tags.'),
      '#default_value' => $this->configuration['scan_exif'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['scan_exif'] = $form_state->getValue('scan_exif');
  }

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    // Test to see if EXIF is supported by the image format.
    if (in_array($image->getMimeType(), ['image/jpeg', 'image/tiff'])) {
      // Hand over to toolkit.
      return $image->apply('auto_orient');
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function transformDimensions(array &$dimensions, $uri) {
    if ($dimensions['width'] && $dimensions['height'] && $this->configuration['scan_exif']) {
      // Both dimensions in input, and effect is configured to check the
      // the input file. Check if the file MIME type is one that can have
      // EXIF data, read EXIF data, and determine image orientation.
      $mime_type = $this->mimeTypeGuesser->guess($uri);
      if (in_array($mime_type, ['image/jpeg', 'image/tiff'])) {
        if ($file_path = $this->fileSystem->realpath($uri)) {
          if (!extension_loaded('exif')) {
            // Cannot read EXIF data without the PHP EXIF extension. Set both
            // dimensions to NULL and return.
            $dimensions['width'] = $dimensions['height'] = NULL;
            return;
          }
          if ($exif_data = @exif_read_data($file_path)) {
            $orientation = isset($exif_data['Orientation']) ? $exif_data['Orientation'] : NULL;
            if (in_array($orientation, [5, 6, 7, 8])) {
              $tmp = $dimensions['width'];
              $dimensions['width'] = $dimensions['height'];
              $dimensions['height'] = $tmp;
            }
          }
        }
      }
    }
    else {
      // Either no full dimensions in input, or effect is configured to
      // skip checking the input file. Set both dimensions to NULL.
      $dimensions['width'] = $dimensions['height'] = NULL;
    }
  }

}
