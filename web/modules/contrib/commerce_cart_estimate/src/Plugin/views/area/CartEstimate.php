<?php

namespace Drupal\commerce_cart_estimate\Plugin\views\area;

use Drupal\address\AddressInterface;
use Drupal\commerce_cart_estimate\CartEstimateResult;
use Drupal\commerce_cart_estimate\Exception\CartEstimateException;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\PrependCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\area\AreaPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines an area handler for the cart estimate form.
 *
 * @ViewsArea("commerce_cart_estimate")
 */
class CartEstimate extends AreaPluginBase {

  /**
   * The cart estimator.
   *
   * @var \Drupal\commerce_cart_estimate\EstimatorInterface
   */
  protected $cartEstimator;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->messenger = $container->get('messenger');
    $instance->cartEstimator = $container->get('commerce_cart_estimate.estimator');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->logger = $container->get('logger.channel.commerce_cart_estimate');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE) {
    return [];
  }

  /**
   * Gets whether the views form should be shown when the view has no results.
   *
   * @param bool $empty
   *   Whether the view has results.
   *
   * @return bool
   *   TRUE if the views form should be shown, FALSE otherwise.
   */
  public function viewsFormEmpty($empty) {
    return $empty;
  }

  /**
   * Builds the views form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function viewsForm(array &$form, FormStateInterface $form_state) {
    $order_storage = $this->entityTypeManager->getStorage('commerce_order');
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $order_storage->load($this->view->argument['order_id']->getValue());

    // The order is not shippable, stop here.
    if (!$order->hasField('shipments')) {
      return;
    }

    $class = get_class($this);
    $form['cart_estimate_wrapper'] = [
      '#title' => $this->t('Estimate your Shipping'),
      '#type' => 'fieldset',
      '#attached' => [
        'library' => [
          'commerce_cart_estimate/form',
        ],
      ],
      '#attributes' => [
        'class' => ['commerce-cart-estimate-wrapper'],
      ],
      '#after_build' => [
        [$class, 'clearValues'],
      ],
    ];
    $form['cart_estimate_wrapper']['country_code'] = [
      '#title' => $this->t('Country'),
      '#type' => 'address_country',
      '#required' => TRUE,
    ];
    $form['cart_estimate_wrapper']['postal_code'] = [
      '#title' => $this->t('Postal code'),
      '#type' => 'textfield',
      '#size' => 10,
    ];

    $form['cart_estimate_wrapper']['actions'] = ['#type' => 'actions'];
    $form['cart_estimate_wrapper']['actions']['estimate'] = [
      '#value' => $this->t('Estimate'),
      '#type' => 'submit',
      '#cart_estimate_button' => TRUE,
      '#ajax' => [
        'callback' => [$class, 'ajaxRefresh'],
      ],
    ];

    // Display the "clear" button as soon as the estimate button was clicked.
    if ($form_state->get('rated_order')) {
      $form['cart_estimate_wrapper']['actions']['clear'] = [
        '#value' => $this->t('Clear'),
        '#type' => 'submit',
        '#clear_estimate_button' => TRUE,
        '#ajax' => [
          'callback' => [$class, 'ajaxRefresh'],
        ],
      ];
    }

    $triggering_element = $form_state->getTriggeringElement();
    // When the "Clear" estimate button is clicked, don't attempt to set a
    // default postal code & country.
    if ($triggering_element && !empty($triggering_element['#clear_estimate_button'])) {
      return;
    }
    $profiles = $order->collectProfiles();
    if (isset($profiles['shipping']) && !$profiles['shipping']->get('address')->isEmpty()) {
      /** @var \Drupal\address\AddressInterface $address */
      $address = $profiles['shipping']->get('address')->first();
    }
    else {
      // Default the postal code / country code to the store address.
      $address = $order->getStore()->getAddress();
    }

    if ($address instanceof AddressInterface) {
      $form['cart_estimate_wrapper']['postal_code']['#default_value'] = $address->getPostalCode();
      $form['cart_estimate_wrapper']['country_code']['#default_value'] = $address->getCountryCode();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function viewsFormValidate(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    if (empty($triggering_element['#cart_estimate_button'])) {
      if (!empty($triggering_element['#clear_estimate_button'])) {
        $form_state->set('rated_order', NULL);
        $this->messenger()->addMessage($this->t('Estimate successfully cleared.'));
        $form_state->setRebuild(TRUE);
      }
      return;
    }
    $input = $form_state->getUserInput();
    if (empty($input['country_code'])) {
      $form_state->setErrorByName('country_code', $this->t('Please select a country.'));
      return;
    }
    $order_storage = $this->entityTypeManager->getStorage('commerce_order');
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $order_storage->load($this->view->argument['order_id']->getValue());
    $profile = $this->cartEstimator->buildShippingProfile($order, [
      'country_code' => $input['country_code'],
      'postal_code' => !empty($input['postal_code']) ? $input['postal_code'] : NULL,
    ]);

    // Validate the postal code if provided.
    if (!empty($input['postal_code'])) {
      $violations = $profile->validate();
      $violations->filterByFields(array_diff(array_keys($profile->getFieldDefinitions()), ['address']));
      if (count($violations) > 0) {
        foreach ($violations as $violation) {
          if ($violation->getPropertyPath() !== 'address.0.postal_code') {
            continue;
          }
          $form_state->setErrorByName('postal_code', $violation->getMessage());
          return;
        }
      }
    }

    try {
      // Estimate the cart, using a temporary profile built with a partial
      // address.
      $estimate = $this->cartEstimator->estimate($order, $profile);

      if (!$estimate instanceof CartEstimateResult || !$estimate->getRatedOrder()->getAdjustments(['shipping'])) {
        $form_state->setErrorByName('', $this->t('There are no shipping rates available for this address.'));
        return;
      }
      $this->messenger()->addMessage($this->t('Cart successfully estimated.'));
      // Store the rated order in the form state for use in the ajax callback.
      $form_state->set('rated_order', $estimate->getRatedOrder());
      $form_state->setRebuild(TRUE);
    }
    catch (CartEstimateException $e) {
      $form_state->setErrorByName('', $this->t('There are no shipping rates available for this address.'));
      $this->logger->error($e->getMessage());
    }
  }

  /**
   * Ajax callback.
   */
  public static function ajaxRefresh(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $response = new AjaxResponse();
    // Refresh the estimate form.
    $response->addCommand(new ReplaceCommand('[data-drupal-selector="' . $form['cart_estimate_wrapper']['#attributes']['data-drupal-selector'] . '"]', $form['cart_estimate_wrapper']));
    list($base_form_id, $suffix) = explode('--', $form['#id']);
    $order_total_summary_wrapper = sprintf('form[id^="%s"] %s', $base_form_id, 'div[data-drupal-selector="order-total-summary"]');
    $response->addCommand(new PrependCommand('.commerce-cart-estimate-wrapper', ['#type' => 'status_messages']));

    if (empty($triggering_element['#cart_estimate_button'])) {
      // If the "clear" estimate button was clicked, restore the order summary.
      if (!empty($triggering_element['#clear_estimate_button'])) {
        /** @var \Drupal\views\ViewExecutable $view */
        $view = reset($form_state->getBuildInfo()['args']);
        $order_storage = \Drupal::entityTypeManager()->getStorage('commerce_order');
        /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
        $order = $order_storage->load($view->argument['order_id']->getValue());

        $response->addCommand(new HtmlCommand($order_total_summary_wrapper, $order->get('total_price')->view([
          'label' => 'hidden',
          'type' => 'commerce_order_total_summary',
        ])));
      }

      return $response;
    }

    // If the order was successfully rated, replace the order summary with
    // the estimate summary.
    if ($form_state->get('rated_order') instanceof OrderInterface) {
      $rated_order = $form_state->get('rated_order');
      $form_state->set('rated_order', NULL);
      $response->addCommand(new HtmlCommand($order_total_summary_wrapper, [
        '#theme' => 'commerce_cart_estimate_summary',
        '#order_entity' => $rated_order,
      ]));
    }

    return $response;
  }

  /**
   * Clears form input when the clear estimate button is clicked.
   */
  public static function clearValues(array $element, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    if (empty($triggering_element['#clear_estimate_button'])) {
      return $element;
    }
    $user_input = &$form_state->getUserInput();
    $values_to_clear = ['country_code', 'postal_code'];
    $user_input = array_diff_key($user_input, array_combine($values_to_clear, $values_to_clear));
    return $element;
  }

}
