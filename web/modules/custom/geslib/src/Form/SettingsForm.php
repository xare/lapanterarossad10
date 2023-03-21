<?php

namespace Drupal\geslib\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure geslib settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'geslib_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['geslib.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* $form['example'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Example'),
      '#default_value' => $this->config('geslib.settings')->get('example'),
    ]; */
    $form['geslib_directory'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Geslib directory'),
      '#default_value' => $this->config('geslib.settings')->get('geslib_directory', 'sites/default/files/geslib'),
    );
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    /* if ($form_state->getValue('example') != 'example') {
      $form_state->setErrorByName('example', $this->t('The value is not correct.'));
    } */

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('geslib.settings')
      ->set('geslib_directory', $form_state->getValue('geslib_directory'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
