<?php

namespace Drupal\geslib\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\geslib\Api\GeslibApiDrupalManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements the StoreProducts form.
 */
class StoreProductsForm extends FormBase {

  /**
   * The geslib drupal manager.
   *
   * @var \Drupal\geslib\Api\GeslibApiDrupalManager
   */
  protected $drupalManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(GeslibApiDrupalManager $drupal_manager) {
    $this->drupalManager = $drupal_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('geslib.drupal_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'geslib_store_products_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run Store Products'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Trigger the storeProducts method.
    $this->drupalManager->storeProducts();
    $this->drupalManager->setGeslibLogQueued();
    $this->drupalManager->emptyGeslibLines();
    $this->messenger()->addMessage($this->t('Products stored successfully.'));
  }
}
