<?php 

namespace Drupal\dilve\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class DilveSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dilve_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'dilve.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('dilve.settings');

    $form['dilve_user'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dilve User Name'),
      '#default_value' => $config->get('dilve_user'),
    ];

    $form['dilve_pass'] = [
      '#type' => 'password',
      '#title' => $this->t('Dilve Password'),
      '#default_value' => $config->get('dilve_pass'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('dilve.settings')
      ->set('dilve_user', $form_state->getValue('dilve_user'))
      ->set('dilve_pass', $form_state->getValue('dilve_pass'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}