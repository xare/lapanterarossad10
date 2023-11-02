<?php

namespace Drupal\selective_better_exposed_filters\Plugin\better_exposed_filters\filter;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Plugin\views\filter\SearchApiFilterTrait;
use Drupal\search_api\Plugin\views\filter\SearchApiOptions;
use Drupal\taxonomy\Plugin\views\filter\TaxonomyIndexTid;
use Drupal\views\Plugin\views\filter\EntityReference;
use Drupal\views\Plugin\views\filter\Bundle;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;

/**
 * Base class for Better exposed filters widget plugins.
 */
abstract class SelectiveFilterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultConfiguration() {
    return [
      'options_show_only_used' => FALSE,
      'options_show_only_used_filtered' => FALSE,
      'options_hide_when_empty' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function buildConfigurationForm(FilterPluginBase $filter, array $settings) {
    $form = [];
    if ($filter->isExposed() && $filter instanceof TaxonomyIndexTid || $filter instanceof EntityReference || $filter instanceof SearchApiOptions || $filter instanceof Bundle) {
      $form['options_show_only_used'] = [
        '#type' => 'checkbox',
        '#title' => t('Show only used items'),
        '#default_value' => !empty($settings['options_show_only_used']),
        '#description' => t('Restrict exposed filter values to those presented in the result set.'),
      ];

      $form['options_show_only_used_filtered'] = [
        '#type' => 'checkbox',
        '#title' => t('Filter items based on filtered result set'),
        '#default_value' => !empty($settings['options_show_only_used_filtered']),
        '#description' => t('Restrict exposed filter values to those presented in the already filtered result set.'),
        '#states' => [
          'visible' => [
            ':input[name="exposed_form_options[bef][filter][' . $filter->field . '][configuration][options_show_only_used]"]' => [
              'checked' => TRUE
            ],
          ],
        ],
      ];

      $form['options_hide_when_empty'] = [
        '#type' => 'checkbox',
        '#title' => t('Hide filter, if no options'),
        '#default_value' => !empty($settings['options_hide_when_empty']),
        '#states' => [
          'visible' => [
            ':input[name="exposed_form_options[bef][filter][' . $filter->field . '][configuration][options_show_only_used]"]' => [
              'checked' => TRUE
            ],
          ],
        ],
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function exposedFormAlter(ViewExecutable &$current_view, FilterPluginBase $filter, array $settings, array &$form, FormStateInterface &$form_state) {
    if ($filter->isExposed() && !empty($settings['options_show_only_used'])) {
      $identifier = $filter->options['is_grouped'] ? $filter->options['group_info']['identifier'] : $filter->options['expose']['identifier'];

      if (empty($current_view->selective_filter)) {
        /** @var \Drupal\views\ViewExecutable $view */
        $view = Views::getView($current_view->id());
        $view->selective_filter = TRUE;
        $view->setArguments($current_view->args);
        $view->setItemsPerPage(0);
        $view->setDisplay($current_view->current_display);
        $view->preExecute();
        // Items_per_page query parameter can override display default
        // Save original query and replace with one without items_per_page
        $query_orig = clone $view->getRequest()->query;
        $view->getRequest()->query->remove('items_per_page');
        $view->execute();
        // Restore items_per_page for main query
        $view->getRequest()->query = $query_orig;

        $element = &$form[$identifier];
        if (!empty($view->result)) {
          $hierarchy = !empty($filter->options['hierarchy']);
          $relationship = $filter->options['relationship'];

          if (in_array(SearchApiFilterTrait::class, class_uses($filter)) || $filter instanceof Bundle) {
            $field_id = $filter->options['field'];
          }
          else {
            $field_id = $filter->definition['field_name'];
          }

          if (in_array(SearchApiFilterTrait::class, class_uses($filter)) || $filter instanceof Bundle) {
            $field_id = $filter->options['field'];

            // For Search API fields find original property path:
            if (in_array(SearchApiFilterTrait::class, class_uses($filter))) {
              $index_fields = $view->getQuery()->getIndex()->getFields();
              if (isset($index_fields[$field_id])) {
                $field_id = $index_fields[$field_id]->getPropertyPath();
              }
            }
          }
          else {
            $field_id = $filter->definition['field_name'];
          }

          $ids = [];
          foreach ($view->result as $row) {
            $entity = $row->_entity;
            if ($relationship != 'none') {
              $entity = $row->_relationship_entities[$relationship] ?? FALSE;
            }
            // Get entity from object.
            if (!isset($entity)) {
              $entity = $row->_object->getEntity();
            }
            if ($entity instanceof TranslatableInterface
              && isset($row->node_field_data_langcode)
              && $entity->hasTranslation($row->node_field_data_langcode)) {
              $entity = $entity->getTranslation($row->node_field_data_langcode);
            }
            if ($entity instanceof FieldableEntityInterface && $entity->hasField($field_id)) {
              $item_values = $entity->get($field_id)->getValue();

              if (!empty($item_values)) {
                foreach ($item_values as $item_value) {
                  $id = $item_value['target_id'];
                  $ids[$id] = $id;

                  if ($hierarchy) {
                    $parents = \Drupal::service('entity_type.manager')
                      ->getStorage("taxonomy_term")
                      ->loadAllParents($id);

                    /** @var \Drupal\taxonomy\TermInterface $term */
                    foreach ($parents as $term) {
                      $ids[$term->id()] = $term->id();
                    }
                  }
                }
              }
            }
          }

          if (!empty($element['#options'])) {
            foreach ($element['#options'] as $key => $option) {
              if ($key === 'All') {
                continue;
              }

              $target_id = $key;
              if (is_object($option) && !empty($option->option)) {
                $target_id = array_keys($option->option);
                $target_id = reset($target_id);
              }
              if (!in_array($target_id, $ids)) {
                unset($element['#options'][$key]);
              }
            }

            if (
              !empty($settings['options_hide_when_empty'])
              && (
                (count($element['#options']) == 1 && isset($element['##options']['All']))
                || empty($element['#options'])
              )
            ) {
              $element['#access'] = FALSE;
            }
          }
        }
        elseif (!empty($settings['options_hide_when_empty'])) {
          $element['#access'] = FALSE;
        }
      }
      else {
        if (!empty($settings['options_show_only_used_filtered'])) {
          $user_input = $form_state->getUserInput();
          if (isset($user_input[$identifier])) {
            unset($user_input[$identifier]);
          }
        }
        else {
          $user_input = [];
        }
        $form_state->setUserInput($user_input);
      }
    }
  }

}
