<?php

/**
 * @file
 * Contains \Drupal\image_effects\Form\SettingsForm.
 */

namespace Drupal\image_effects\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\image_effects\Plugin\ImageEffectsPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Main image_effects settings admin form.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManager
   */
  protected $streamWrapperManager;

  /**
   * The color selector plugin manager.
   *
   * @var \Drupal\image_effects\Plugin\ImageEffectsPluginManager
   */
  protected $colorManager;

  /**
   * Constructs the class for image_effects settings form.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManager $stream_wrapper_manager
   *   The stream wrapper manager.
   * @param \Drupal\image_effects\Plugin\ImageEffectsPluginManager $color_plugin_manager
   *   The color selector plugin manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StreamWrapperManager $stream_wrapper_manager, ImageEffectsPluginManager $color_plugin_manager) {
    parent::__construct($config_factory);
    $this->colorManager = $color_plugin_manager;
    $this->streamWrapperManager = $stream_wrapper_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('stream_wrapper_manager'),
      $container->get('plugin.manager.image_effects.color_selector')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'image_effects_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['image_effects.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('image_effects.settings');

    $ajaxing = (bool) $form_state->getValues();

    // Color selector plugin.
    $color_plugin_id = $ajaxing ? $form_state->getValue(['settings', 'color_selector', 'plugin_id']) : $config->get('color_selector.plugin_id');
    $color_plugin = $this->colorManager->getPlugin($color_plugin_id);
    if ($ajaxing && $form_state->hasValue(['settings', 'color_selector', 'plugin_settings'])) {
      $color_plugin->setConfiguration($form_state->getValue(['settings', 'color_selector', 'plugin_settings']));
    }

    // AJAX messages
    $form['ajax_messages'] = array(
      '#type' => 'container',
      '#attributes' => [
        'id' => 'image-effects-ajax-messages',
      ],
    );

    // AJAX settings.
    $ajax_settings = ['callback' => [$this, 'processAjax']];

    // Main part of settings form.
    $form['settings'] = array(
      '#type' => 'container',
      '#tree' => TRUE,
      '#attributes' => [
        'id' => 'image-effects-settings-main',
      ],
    );

    // Color selector.
    $form['settings']['color_selector'] = array(
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Color selector'),
      '#tree' => TRUE,
    );
    $form['settings']['color_selector']['plugin_id'] = array(
      '#type' => 'radios',
      '#options' => $this->colorManager->getPluginOptions(),
      '#default_value' => $color_plugin->getPluginId(),
      '#required' => TRUE,
      '#ajax'  => $ajax_settings,
    );
    $form['settings']['color_selector']['plugin_settings'] = $color_plugin->buildConfigurationForm(array(), $form_state, $ajax_settings);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) { }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $config = $this->config('image_effects.settings');

    // Color plugin.
    $color_plugin = $this->colorManager->getPlugin($form_state->getValue(['settings', 'color_selector', 'plugin_id']));
    if ($form_state->hasValue(['settings', 'color_selector', 'plugin_settings'])) {
      $color_plugin->setConfiguration($form_state->getValue(['settings', 'color_selector', 'plugin_settings']));
    }
    $config
      ->set('color_selector.plugin_id', $color_plugin->getPluginId())
      ->set('color_selector.plugin_settings.' . $color_plugin->getPluginId(), $color_plugin->getConfiguration());

    $config->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * AJAX callback.
   */
  public function processAjax($form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $status_messages = array('#type' => 'status_messages');
    $response->addCommand(new HtmlCommand('#image-effects-ajax-messages', $status_messages));
    $response->addCommand(new HtmlCommand('#image-effects-settings-main', $form['settings']));
    return $response;
  }

}