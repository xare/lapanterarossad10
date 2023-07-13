<?php
/**
 * @package Drupal\geslib
 */
namespace Drupal\geslib\Form;

use Drupal;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Url;
use Drupal\geslib\Plugin\geslibBatches;

/**
 * Configure geslib settings for this site.
 */
class GeslibFirstImportForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'geslib_first_import_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['geslib.first.import.form'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $geslib_dir =  $this->config( 'geslib.settings' )->get( 'geslib_directory', 'sites/default/files/geslib' );
    $base_path = Drupal::root() . DIRECTORY_SEPARATOR;
    if($geslib_dir === null) {
      $geslib_dir = 'sites/default/files/geslib';
    }
    $geslib_dir = str_replace('/', DIRECTORY_SEPARATOR, $geslib_dir);
    $geslib_dir = $base_path . $geslib_dir;

  if ( !file_exists( $geslib_dir ) ) {
    mkdir( $geslib_dir );
  }
  $files = scandir( $geslib_dir );
  $options = [];
  foreach ( $files as $file ) {
    if ( strpos( $file , '.') !== 0 AND $file != '..'){
      $options[$geslib_dir.'/'.$file] = $file;
    }
  }

    $form['geslib_file'] = array(
      '#type' => 'select',
      '#title' => 'Geslib File',
      '#options' => $options,
    );
    $form['geslib_debugger'] = array(
      '#type' => 'textfield',
      '#title' => 'Debugger',
      '#default_value' => $this->config('geslib.first.import.form')->get('geslib_debugger', 'debugger'),
    );

    $form['operations'] = array(
      '#type' => 'fieldset',
    );

    $form['operations']['geslib_process_delete_all'] = array(
      '#type' => 'checkbox',
      '#title' => 'Delete All',
      '#default_value' => 0,
    );
    $form['operations']['geslib_process_read_file'] = array(
      '#type' => 'checkbox',
      '#title' => 'Read file',
      '#default_value' => 0,
    );
    $form['operations']['geslib_process_categories'] = array(
      '#type' => 'checkbox',
      '#title' => 'Process categories',
      '#default_value' => 0,
    );
    $form['operations']['geslib_process_publishers'] = array(
      '#type' => 'checkbox',
      '#title' => 'Process publishers',
      '#default_value' => 0,
    );
    $form['operations']['geslib_process_authors'] = array(
      '#type' => 'checkbox',
      '#title' => 'Process authors',
      '#default_value' => 0,
    );
    $form['operations']['geslib_process_items'] = array(
      '#type' => 'checkbox',
      '#title' => 'Process books',
      '#default_value' => 0,
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => 'Submit',
    );
    $form['borrar items'] = array(
      '#type' => 'submit',
      '#value' => 'Borrar todo',
    );
    return parent::buildForm($form, $form_state);
  }
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $output = 'The content of geslib_debugger is '. $form_state->getValue( 'geslib_debugger' );
    $gestlibBatches = new geslibBatches;
    $operations = [];
    $values = $form_state->getValues();

    if ($form_state->getValue('geslib_process_read_file') == 1 ){
      geslibBatches::process('geslib_process_read_file', $form_state->getValue( 'geslib_file'));
    }
      //   $operations[] = [ 'geslib_process_read_file', [ $values[ 'geslib_file' ] ]];
      // }
      // if ($value['geslib_process_process_categories']){
      //   $operations[] = [ 'geslib_process_items', [ 'category' , 25]];
      // }
      // if ($value['geslib_process_publishers']){
      //   $operations[] = [ 'geslib_process_items', [ 'publisher' , 50]];
      // }
      // if ($value['geslib_process_authors']){
      //   $operations[] = [ 'geslib_process_items', [ 'author' , 50]];
      // }
      // if ($value['geslib_process_items']){
      //   $operations[] = [ 'geslib_process_items', [ 'product' , 50]];
      // }
    //}
    $this->messenger()->addMessage($output);
    //

    // $form_state->setRebuild();
    //   $form['output'] = [
    //     '#type' => 'markup',
    //     '#markup' => $form_state->getValue( 'geslib_debugger' ),
    //   ];
    // if ($form_state->getValue( 'geslib_debugger' ) == 'Borrar') {
    //   $output = $output .' and we are inside Borrar';
    //   $geslibBatches = $gestlibBatches->deleteAll();
    //   $output = ' '.$geslibBatches;
    //   $this->messenger()->addMessage($output);

      //;
    // } else {
    //   $output = $output .' and we are NOT inside Borrar';
    //   $geslibBatches = $gestlibBatches->process();
    //   $output = $output .' '.$geslibBatches;
    //   $this->messenger()->addMessage($output);
    //   $this->config('geslib.first.import.form')
    // ->set('geslib_debugger', 'Fuera de borrado')
    // ->save();
    //   $output = "No has pedido un borrado";
    //   $form['output'] = [
    //     '#type' => 'markup',
    //     '#markup' => $output,
    //   ];

      //$gestlibBatches->process($form_state->getValues());
    // }
    // $this->config('geslib.first.import.form')
    //   ->set('geslib_debugger', $form_state->getValue( 'geslib_debugger' ))
    //   ->save();

  parent::submitForm( $form, $form_state );
}


}