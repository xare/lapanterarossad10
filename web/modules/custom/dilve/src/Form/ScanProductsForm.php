<?php

namespace Drupal\dilve\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ScanProductsForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public static function create( ContainerInterface $container ) {
        return new static(
            $container->get('dilve.api.drupal')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'dilve_scan_products_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm ( array $form, FormStateInterface $form_state ) {
        $form['#attached']['library'][] = 'dilve/dilve-library';
        $buttons = [
            'scan_products' => $this->generateButton(
                'Scan Products', 'Scan all products', '::scanProductsAjaxCallback'),
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

    public function scanProductsAjaxCallback() {
        $response = new AjaxResponse();
        $response->addCommand(
            new ReplaceCommand(
                '#outputDiv',
                '<div id="outputDiv">Hello World</div>'));
        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm( array &$form, FormStateInterface $form_state ) {

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