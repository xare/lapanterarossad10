<?php

namespace Drupal\geslib\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\geslib\Api\GeslibApiDrupalManager;
use Drupal\geslib\Api\GeslibApiLines;
use Drupal\geslib\Api\GeslibApiReadFiles;
use Drupal\geslib\Api\GeslibApiStoreData;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements the StoreProducts form.
 */
class StoreProductsForm extends FormBase {


  /**
   * {@inheritdoc}
   */
  public function __construct(
      protected GeslibApiDrupalManager $drupalManager,
      protected GeslibApiReadFiles $geslibApiReadFiles,
      protected GeslibApiLines $geslibApiLines,
      protected GeslibApiStoreData $geslibApiStoreData ) {
    $this->drupalManager = $drupalManager;
    $this->geslibApiReadFiles = $geslibApiReadFiles;
    $this->geslibApiLines = $geslibApiLines;
    $this->geslibApiStoreData = $geslibApiStoreData;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('geslib.api.drupal'),
      $container->get('geslib.api.readFiles'),
      $container->get('geslib.api.lines'),
      $container->get('geslib.api.storeData'),
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
    // GeslibLog button
    $form['actions']['store_log'] = [
      '#type' => 'submit',
      '#value' => $this->t('1. Log geslib file'),
      '#button_type' => 'primary',
      '#submit' => ['::submit2Log'],  
    ];

    // GeslibLines button
    $form['actions']['store_lines'] = [
      '#type' => 'submit',
      '#value' => $this->t('2. Send to Geslib Lines'),
      '#button_type' => 'primary',
      '#submit' => ['::submit2Lines'],  
    ];
    // GeslibLines button
    $form['actions']['store_editorials'] = [
      '#type' => 'submit',
      '#value' => $this->t('3. Store Editorials'),
      '#button_type' => 'primary',
      '#submit' => ['::submitStoreEditorials'],  
    ];

    $form['actions']['store_product_categories'] = [
      '#type' => 'submit',
      '#value' => $this->t('4. Store Product Categories'),
      '#button_type' => 'primary',
      '#submit' => ['::submitStoreProductCategories'],  
    ];

    // First button
    $form['actions']['store_products'] = [
      '#type' => 'submit',
      '#value' => $this->t('5. Run Store Products'),
      '#button_type' => 'primary',
      '#submit' => ['::submitStoreProducts'],  
    ];
    // Second button
    $form['actions']['delete_products'] = [
      '#type' => 'submit',
      '#value' => $this->t('X. Delete Products'),
      '#button_type' => 'danger',
      '#submit' => ['::submitDeleteProducts'],  // Custom submit handler for this button
    ];
    return $form;
  }


  public function submit2Log( array &$form, FormStateInterface $form_state ) {
    try {
      $response = $this->geslibApiReadFiles->readFolder();
      $this->messenger()->addMessage($this->t( 'Geslib Log: The data has been loaded to geslib_log' ));
      \Drupal::logger('geslib')->notice('Geslib Log: The data has been loaded to geslib_log');

      // Redirect to a custom route (controller)
      $form_state->setRedirect('geslib.admin.log');

    } catch (\Exception $exception) {
      $this->messenger()->addMessage($this->t( 'Geslib Log ERROR: No files in the folder: ' .$exception->getMessage() ));
      \Drupal::logger('geslib')->notice('Geslib Log ERROR: No files in the folder');
    }
  }

  public function submit2Lines( array &$form, FormStateInterface $form_state ) {

    try {
      $this->geslibApiLines->storeToLines();
      $this->drupalManager->setGeslibLogQueued();
      $this->messenger()->addMessage($this->t( 'Success saving to geslib_lines' ));
      \Drupal::logger('geslib')->notice( 'Success saving to geslib_lines ' );
      $form_state->setRedirect('geslib.admin.lines');

    } catch ( \Exception $exception ) {

      $this->messenger()->addMessage( $this->t( 'Error saving to geslib_lines ' .$exception->getMessage() ) );
      \Drupal::logger('geslib')->notice( 'Error saving to geslib_lines ' .$exception->getMessage() );

    }
  }

  public function submitStoreEditorials( array &$form, FormStateInterface $form_state ) { 
      try {
        $this->geslibApiStoreData->storeEditorials();
        $this->messenger()->addMessage($this->t( ' ' ) );
        \Drupal::logger('geslib')->notice( ' ' );
      } catch ( \Exception $exception ) {
        $this->messenger()->addMessage($this->t( ' ' .$exception->getMessage() ) );
        \Drupal::logger('geslib')->notice( ' ' .$exception->getMessage() );
      }
  }

  /**
   * {@inheritdoc}
   */
  public function submitStoreProducts( array &$form, FormStateInterface $form_state ) {
    // Debug: Starting the process
    $this->messenger()->addMessage( $this->t( 'Starting the storeProducts function.' ) );
    \Drupal::logger('geslib')->notice('Starting the storeProducts function.');
    $result1 = $this->drupalManager->storeProducts();
    // Debug: After storeProducts
    $this->messenger()->addMessage($this->t('storeProducts function complete. Result: @result', [ '@result' => print_r( $result1, TRUE)]));
    \Drupal::logger('geslib')->notice('storeProducts function complete.');
    
    $this->drupalManager->setGeslibLogQueued();
    $this->messenger()->addMessage($this->t( 'setGeslibLogQueued function complete.' ));
    \Drupal::logger('geslib')->notice( 'setGeslibLogQueued function complete.' );
    $this->drupalManager->emptyGeslibLines();
    $this->messenger()->addMessage($this->t( 'emptyGeslibLines function complete.' ));
    \Drupal::logger('geslib')->notice('emptyGeslibLines function complete.');
    
    $this->messenger()->addMessage($this->t('Products stored successfully.'));
  }

  public function submitDeleteProducts(array &$form, FormStateInterface $form_state) {
    // Logic for deleting products
    $this->drupalManager->deleteProducts();
    $this->messenger()->addMessage($this->t('Products deleted successfully.'));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This is intentionally empty because we have custom submit handlers for our buttons.
  }
}
