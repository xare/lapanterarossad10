<?php

namespace Drupal\geslib\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\geslib\Api\GeslibApiDrupalManager;
use Drupal\geslib\Api\GeslibApiLines;
use Drupal\geslib\Api\GeslibApiReadFiles;
use Drupal\geslib\Api\GeslibApiStoreData;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\dilve\Api\DilveApiDrupalManager;
use Drupal\geslib\Api\GeslibApiDrupalLinesManager;
use Drupal\geslib\Api\GeslibApiDrupalLogManager;
use Drupal\geslib\Api\GeslibApiDrupalProductsManager;
use Drupal\geslib\Api\GeslibApiDrupalQueueManager;
use Symfony\Component\HttpFoundation\JsonResponse;

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
  public function buildForm( array $form, FormStateInterface $form_state ) {

    $form['#attached']['library'][] = 'geslib/geslib-library';
    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => -10,
    ];
    $geslibApiDbManager = new GeslibApiDrupalManager;
    $geslibApiDrupalLogManager = new GeslibApiDrupalLogManager;
    $geslibApiDrupalLinesManager = new GeslibApiDrupalLinesManager;
    $geslibApiDrupalQueueManager = new GeslibApiDrupalQueueManager;
    $geslibApiDrupalProductsManager = new GeslibApiDrupalProductsManager;
    $geslibReadFiles = new GeslibApiReadFiles;
    $getTotalNumberOfProducts = $geslibApiDrupalProductsManager->getTotalNumberOfProducts();
    $countFilesInFolder = $geslibReadFiles->countFilesInFolder();
    $form['preButtonsHtml'] = [
      '#type' => 'markup',
      '#markup' => '
      <ul class="geslib-statistics">
        <li>Archivos geslib en la carpeta:<br />
            <strong data-target="total-files"> '.$countFilesInFolder.'</strong>
        </li>
         <li>Archivos registrados en la tabla logs:<br />
            <strong data-target="total-logs"> '.$geslibApiDrupalLogManager->countGeslibLog().'</strong>
            <br />
            Logged:
            <strong data-target="geslib-log-logged">
                '. $geslibApiDrupalLogManager->countGeslibLogStatus('logged') .'
            </strong>
            <br />
            Queued:
            <strong data-target="geslib-log-queued">
                '. $geslibApiDrupalLogManager->countGeslibLogStatus('queued') .'
            </strong>
            <br />
            Dilve:
            <strong data-target="geslib-log-dilve">
               '. $geslibApiDrupalLogManager->countGeslibLogStatus('dilve') .'
            </strong>
            <br />
            Processed:
            <strong data-target="geslib-log-processed">
               '. $geslibApiDrupalLogManager->countGeslibLogStatus('processed') .'
            </strong>

            </li>
        <li>Archivo en la cola de procesamiento:<br />
            <strong data-target="queued-filename"> '.$geslibApiDrupalLogManager->getLogQueuedFilename().'</strong>
        </li>
        <li>Elementos en la cola de proceso de las lineas:<br />
            <strong data-target="total-queue-lines"> '.$geslibApiDrupalQueueManager->getQueueCount('geslib_store_geslib_lines').'</strong><br />
            GP4A: <strong data-target="total-queue-lines-products"> '.$geslibApiDrupalQueueManager->countProductsInGeslibLinesQueue().'</strong>
            Título: <strong data-target="latest-queue-lines-gp4a"> '.$geslibApiDrupalQueueManager->getLastProductInGeslibLinesQueue().'</strong>
            <br />
            AUTA: <strong data-target="total-queue-lines-authors"> '.$geslibApiDrupalQueueManager->countAuthorsInGeslibLinesQueue().'</strong>
            Título: <strong data-target="latest-queue-lines-auta"> '.$geslibApiDrupalQueueManager->getLastAuthorInGeslibLinesQueue().'</strong>
            <br />
            1L|A: <strong data-target="total-queue-lines-editorials"> '.$geslibApiDrupalQueueManager->countEditorialsInGeslibLinesQueue().'</strong>
            Título: <strong data-target="latest-queue-lines-1la"> '.$geslibApiDrupalQueueManager->getLastEditorialInGeslibLinesQueue().'</strong>
        </li>
        <li>Archivos registrados en la tabla lines:<br />
            <strong data-target="total-lines"> '.$geslibApiDrupalLinesManager->countGeslibLines().'</strong>
        </li>
        <li>Elementos en la cola de proceso de los productos:<br />
            <strong data-target="total-queue-products"> '.$geslibApiDrupalQueueManager->getQueueCount('geslib_manage_product').'</strong>
        </li>
        <li>Productos almacenados:<br />
            <strong data-target="total-products"> '.$getTotalNumberOfProducts.' </strong>
        </li>
    </ul>
      ',
      '#weight' => 50
    ];

    $buttons = [
      'hello_world' => $this->generateButton(
        '0. Hello World', 'Print Hello World', '::helloWorldAjaxCallback'),
      'check_files' => $this->generateButton(
        '0. Check Files', 'Print Check Files', '::checkFilesAjaxCallback'),
      'store_log' => $this->generateButton(
        '1. Log geslib file', 'Logs the geslib file', '::storeLogAjaxCallback'),
      'store_lines' => $this->generateButton(
        '2. Send to Geslib Lines', 'Sends data to geslib lines', '::storeLinesAjaxCallback'),
      'store_editorials' => $this->generateButton(
        '3. Store Editorials', 'Stores editorial information', '::storeEditorialsAjaxCallback'),
      'store_authors' => $this->generateButton(
        '4. Store Authors', 'Stores author information', '::storeAuthorsAjaxCallback'),
      'store_product_categories' => $this->generateButton(
        '5. Store Product Categories', 'Stores product categories', '::storeProductCategoriesAjaxCallback'),
      'store_products' => $this->generateButton(
        '6. Run Store Products',
        'Runs the product storing process',
        '::storeProductsAjaxCallback'),
      'set_log_to_logged' => $this->generateButton(
        '7. Set to logged',
        'Hace que las lineas almacenadas en geslib_log pasen a estado de resgistradas',
        '::setLogToLoggedAjaxCallback'
      ),
      'truncate_lines' => $this->generateButton(
        'X0. Truncate Geslib Lines', 'Runs the product storing process', '::truncateGeslibLinesAjaxCallback'),
      'delete_editorials' => $this->generateButton(
        'X1. Borrar Editoriales', 'Borra todas las editoriales', '::deleteEditorialsAjaxCallback'),
      'delete_product_categories' => $this->generateButton(
        'X2. Borrar categorias de productos', 'Borrar todas las categorías', '::deleteProductCategoriesAjaxCallback'),
      'delete_products' => $this->generateButton(
        'X3. Borrar Productos', 'Deletes all products', '::deleteProductsAjaxCallback'),
  ];

    foreach ($buttons as $button_id => $button_data) {
      $form['actions'][$button_id] = [
        '#type' => 'submit',
        '#value' => $this->t($button_data['label']),
        '#button_type' => (strpos($button_id,'delete_') === 0 )? 'danger' : 'primary',
        //'#submit' => ["::submit" . ucfirst(str_replace('_', '', $button_id))],
        '#ajax'=> $button_data['ajax'],
        '#prefix' => '<div class="vertical-button">',
        //'#suffix' => '<span class="button-text">' . $this->t($button_data['text']) . '</span></div>',
        '#suffix' => '</div>',
        '#weight' => -10,
      ];
    }
    $form['output_div'] = [
      '#type' => 'markup',
      '#markup' => '<div class="terminal" data-container="geslib_products">
      <div id="outputDiv"></div></div>',
      '#weight' => 50
    ];
    return $form;
  }

  public function helloWorldAjaxCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#outputDiv', '<div id="outputDiv">Hello World</div>'));
    return $response;
  }
  public function checkFilesAjaxCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $geslibApiReadFiles = new GeslibApiReadFiles();

    $fileHtml = array_reduce($geslibApiReadFiles->listFilesInFolder(), function($carry, $file) {
      $filename = is_object($file) ? $file->filename : $file['filename'];
      $status = is_object($file) ? $file->status : $file['status'];
      return $carry . '<li>' . $filename . ' (' . $status . ')</li>';
    }, '');

    $html = '<div id="outputDiv">'
              . '<p> Check files </p>'
              . '<ul>'
                . $fileHtml
              . '</ul>'
            . '</div>';


    // Invoke a custom JavaScript function and pass the data as an argument
    $response->addCommand( new ReplaceCommand( '#outputDiv', $html ) );

    return $response;
  }
  public function storeLogAjaxCallback(array &$form, FormStateInterface $form_state) {
    $geslibReadFiles = new GeslibApiReadFiles();
    $geslibReadFiles->readFolder();
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#outputDiv', '<div id="outputDiv">File Logged</div>'));
    return $response;
  }

  public function storeLinesAjaxCallback(array &$form, FormStateInterface $form_state) {
    $geslibApiLines = new GeslibApiLines;
    $message = $geslibApiLines->storeToLines();

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#outputDiv', '<div id="outputDiv">Store Lines'.$message.'</div>'));
    return $response;
  }

  public function storeEditorialsAjaxCallback(array &$form, FormStateInterface $form_state) {
    $geslibApiStoreData = new GeslibApiStoreData();
    $geslibApiStoreData->storeEditorials();
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#outputDiv', '<div id="outputDiv">Store editorials</div>'));
    return $response;
  }

  public function storeAuthorsAjaxCallback(array &$form, FormStateInterface $form_state) {
    $geslibApiStoreData = new GeslibApiStoreData();
    $geslibApiStoreData->storeAuthors();
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#outputDiv', '<div id="outputDiv">Store authors</div>'));
    return $response;
  }

  public function storeProductCategoriesAjaxCallback(array &$form, FormStateInterface $form_state) {
    $geslibApiStoreData = new GeslibApiStoreData();
    $geslibApiStoreData->storeProductCategories();
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#outputDiv', '<div id="outputDiv">Store product categories</div>'));
    return $response;
  }

  /**
   * {@inheritdoc}
   */

  public function storeProductsAjaxCallback(array &$form, FormStateInterface $form_state) {
    $geslibApiDrupalManager = new GeslibApiDrupalManager;
    $geslibApiDrupalLogManager = new GeslibApiDrupalLogManager;
    $geslibApiDrupalLinesManager = new GeslibApiDrupalLinesManager;
    $geslibApiDrupalProductsManager = new GeslibApiDrupalProductsManager;
    $geslibApiDrupalQueueManager = new GeslibApiDrupalQueueManager;
    $geslibApiReadFiles = new GeslibApiReadFiles();
    $geslibApiLines = new GeslibApiLines();
    $geslibApiStoreData = new GeslibApiStoreData();
    \Drupal::logger('geslib')
        ->notice('Empieza el proceso de importación' );
    // Insert files into the geslib_log table.
    $geslibApiReadFiles->readFolder();

    // Check Logged Status: Returns true if there is at least one row with status "logged", false otherwise
    while ( $geslibApiDrupalLogManager->checkLoggedStatus() ) {
      $log_id = $geslibApiDrupalLogManager->getLogLoggedId();
      if( $log_id > 0 ) {
        \Drupal::logger('geslib_log')
                ->info('ACTUAL LOG: ' . $log_id );
        //
        if ( !$geslibApiDrupalLogManager->isQueued() ){
          \Drupal::logger('geslib_log')
                ->info('There is no queued row, so we set log id'. $log_id. ' to queued.' );
          $geslibApiDrupalLogManager->setLogStatus( $log_id, 'queued' );
        } else {
          \Drupal::logger('geslib_log')
                ->info('There is a queued log file: ' . $log_id );
                // We remove whatever unfinished store_lines queue was left so we can start again.
          $geslibApiDrupalQueueManager->deleteItemsFromQueue( 'store_lines' );
        }
        // Takes the queued filename gets the $log_id and stores each item into the geslib_lines table.
        $stored_lines = $geslibApiLines->storeToLines();
        \Drupal::logger( 'geslib' )
                ->info( 'Finished processing into queues for lines'. $stored_lines );

        $geslibApiDrupalQueueManager->processFromQueue( 'store_lines' );
        $geslibApiStoreData->storeAuthors();
        $geslibApiStoreData->storeEditorials();
        // Assuming you have a method to get queued tasks
        \Drupal::logger( 'geslib_queues' )
              ->notice( 'Finished processing from queues for lines' );
        $geslibApiDrupalProductsManager->storeProducts();
        \Drupal::logger( 'geslibgeslib_queues' )
              ->info( 'Finished storing product info to store_products queue.' );
        $geslibApiDrupalQueueManager->processFromQueue( 'store_products' );
        $geslibApiDrupalLinesManager->truncateGeslibLines();
        $geslibApiDrupalLogManager->setLogStatus( $log_id, 'processed');
      }
    }
    // in the case all the logged files had been processed but maybe some queued row would be orphaned
    if ( $geslibApiDrupalLogManager->isQueued() != FALSE ) {
        $log_id = $geslibApiDrupalLogManager->isQueued();
        $geslibApiLines->storeToLines();
        \Drupal::logger( 'geslib' )
                ->notice( 'Finished processing into queues for lines' );
        $geslibApiDrupalQueueManager->processFromQueue( 'store_lines' );
        $geslibApiStoreData->storeAuthors();
        $geslibApiStoreData->storeEditorials();
        // Assuming you have a method to get queued tasks
        \Drupal::logger( 'geslib' )
              ->notice( 'Finished processing from queues for lines' );
        $geslibApiDrupalProductsManager->storeProducts();
        \Drupal::logger( 'geslib' )
              ->notice( 'Finished storing product info to store_products queue.' );
        $geslibApiDrupalQueueManager->processFromQueue( 'store_products' );
        $geslibApiDrupalLinesManager->truncateGeslibLines();
        $geslibApiDrupalLogManager->setLogStatus( $log_id, 'processed');
    }

    //$dilveApiDrupalManager->scanProducts();
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#outputDiv', '<div id="outputDiv">
    The Store Products process has been queued.</div>'));
    return $response;
  }

  public function setLogToLoggedAjaxCallback(array &$form, FormStateInterface $form_state) {
    $geslibApiDrupalLogManager = new GeslibApiDrupalLogManager;
    $geslibApiDrupalLogManager->setLogTableToLogged();
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#outputDiv', '<div id="outputDiv">
    La tabla Geslib Log ha sido reinicializada.</div>'));
    return $response;
  }

  public function truncateGeslibLinesAjaxCallback(array &$form, FormStateInterface $form_state) {
    $geslibApiDrupalLinesManager = new GeslibApiDrupalLinesManager;
    $geslibApiDrupalLinesManager->truncateGeslibLines();
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#outputDiv', '<div id="outputDiv">Geslib Lines table emptied.</div>'));
    return $response;
  }

  public function deleteProductsAjaxCallback(array &$form, FormStateInterface $form_state) {
    $geslibApiDrupalProductsManager = new GeslibApiDrupalProductsManager;
    $numberOfProducts = $geslibApiDrupalProductsManager->deleteAllProducts();
    $response = new AjaxResponse();

    $response->addCommand(new ReplaceCommand('#outputDiv', '<div id="outputDiv">'.$numberOfProducts.' products queued for deletion</div>'));
    return $response;
  }

  public function deleteEditorialsAjaxCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#outputDiv', '<div id="outputDiv">delete editorials</div>'));
    return $response;
  }

  public function deleteProductCategoriesAjaxCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $response->addCommand(new ReplaceCommand('#outputDiv', '<div id="outputDiv">delete categories</div>'));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This is intentionally empty because we have custom submit handlers for our buttons.
  }

  function generateButton($label, $text, $callback) {
    $defaultAjax = [
        'callback' => $callback,
        'wrapper' => 'outputDiv',
        'method' => 'replace',
        'effect' => 'fade',
        'disable-refocus' => TRUE,
        'event' => 'click',
        'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Verifying entry...'),
        ],
    ];

    return [
        'label' => $label,
        'text' => $text,
        'ajax' => $defaultAjax,
    ];
  }
}
