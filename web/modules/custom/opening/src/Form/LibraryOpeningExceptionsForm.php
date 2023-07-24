<?php

namespace Drupal\opening\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form for managing library opening exceptions.
 */
class LibraryOpeningExceptionsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'library_opening_exceptions_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['opening.library_opening_exceptions'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('opening.library_opening_exceptions');

    $form['exceptions'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Library Opening Exceptions'),
      '#description' => $this->t('Enter the library opening exceptions in the format: MM-DD'),
      '#default_value' => $config->get('exceptions'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $exceptions = $form_state->getValue('exceptions');


    // Explode the exceptions string into an array using line breaks.
    $exceptions_array = explode("\n", $exceptions);

    // Remove any leading or trailing whitespace from each exception.
    $exceptions_array = array_map('trim', $exceptions_array);

    // Remove empty lines from the exceptions array.
    $exceptions_array = array_filter($exceptions_array);

    // Store the exceptions array in the configuration.
    $this->config('hello_world.library_opening_exceptions')
      ->set('exceptions', $exceptions_array)
      ->save();

    parent::submitForm($form, $form_state);
  }
}
