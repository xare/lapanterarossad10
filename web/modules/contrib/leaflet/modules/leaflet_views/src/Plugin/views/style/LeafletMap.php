<?php

namespace Drupal\leaflet_views\Plugin\views\style;

use Drupal\search_api\Plugin\views\ResultRow as SearchApiResultRow;
use Drupal\views\ResultRow;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\leaflet_views\Controller\LeafletAjaxPopupController;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Entity\Index;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\views\ViewExecutable;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Leaflet\LeafletService;
use Drupal\Component\Utility\Html;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\leaflet\LeafletSettingsElementsTrait;
use Drupal\views\Plugin\views\PluginBase;
use Drupal\views\Views;

/**
 * Style plugin to render a View output as a Leaflet map.
 *
 * @ingroup views_style_plugins
 *
 * Attributes set below end up in the $this->definition[] array.
 *
 * @ViewsStyle(
 *   id = "leaflet_map",
 *   title = @Translation("Leaflet Map"),
 *   help = @Translation("Displays a View as a Leaflet map."),
 *   display_types = {"normal"},
 *   theme = "leaflet-map"
 * )
 */
class LeafletMap extends StylePluginBase implements ContainerFactoryPluginInterface {

  use LeafletSettingsElementsTrait;

  /**
   * The Default Settings.
   *
   * @var array
   */
  protected $defaultSettings;

  /**
   * The Entity source property.
   *
   * @var string
   */
  protected $entitySource;

  /**
   * The Entity type property.
   *
   * @var string
   */
  protected $entityType;

  /**
   * The Entity Info service property.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityInfo;

  /**
   * {@inheritdoc}
   */
  protected $usesFields = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;

  /**
   * The Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  /**
   * The Entity Field manager service property.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The Entity Display Repository service property.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplay;

  /**
   * Current user service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;


  /**
   * The Renderer service property.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $renderer;

  /**
   * The module handler to invoke the alter hook.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Leaflet service.
   *
   * @var \Drupal\Leaflet\LeafletService
   */
  protected $leafletService;

  /**
   * The Link generator Service.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
   */
  protected $link;

  /**
   * The list of fields added to the view.
   *
   * @var array
   */
  protected $viewFields = [];

  /**
   * Field type plugin manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $fieldTypeManager;

  /**
   * Constructs a LeafletMap style instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display
   *   The entity display manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Leaflet\LeafletService $leaflet_service
   *   The Leaflet service.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The Link Generator service.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_manager
   *   The field type plugin manager service.
   */
  public function __construct(
    array $configuration,
          $plugin_id,
          $plugin_definition,
    EntityTypeManagerInterface $entity_manager,
    EntityFieldManagerInterface $entity_field_manager,
    EntityDisplayRepositoryInterface $entity_display,
    AccountInterface $current_user,
    MessengerInterface $messenger,
    RendererInterface $renderer,
    ModuleHandlerInterface $module_handler,
    LeafletService $leaflet_service,
    LinkGeneratorInterface $link_generator,
    FieldTypePluginManagerInterface $field_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->defaultSettings = self::getDefaultSettings();
    $this->entityManager = $entity_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityDisplay = $entity_display;
    $this->currentUser = $current_user;
    $this->messenger = $messenger;
    $this->renderer = $renderer;
    $this->moduleHandler = $module_handler;
    $this->leafletService = $leaflet_service;
    $this->link = $link_generator;
    $this->fieldTypeManager = $field_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('entity_display.repository'),
      $container->get('current_user'),
      $container->get('messenger'),
      $container->get('renderer'),
      $container->get('module_handler'),
      $container->get('leaflet.service'),
      $container->get('link_generator'),
      $container->get('plugin.manager.field.field_type')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    // We want to allow view editors to select which entity out of a
    // possible set they want to use to pass to the MapThemer plugin. Long term
    // it would probably be better not to pass an entity to MapThemer plugin and
    // instead pass the result row.
    if (!empty($options['entity_source']) && $options['entity_source'] != '__base_table') {
      $handler = $this->displayHandler->getHandler('relationship', $options['entity_source']);
      $this->entitySource = $options['entity_source'];

      $data = Views::viewsData();
      if (($table = $data->get($handler->definition['base'])) && !empty($table['table']['entity type'])) {
        try {
          $this->entityInfo = $this->entityManager->getDefinition($table['table']['entity type']);
          $this->entityType = $this->entityInfo->id();
        }
        catch (\Exception $e) {
          watchdog_exception('geofield_map', $e);
        }
      }
    }
    else {
      $this->entitySource = '__base_table';

      // For later use, set entity info related to the View's base table.
      $base_tables = array_keys($view->getBaseTables());
      $base_table = reset($base_tables);
      if ($this->entityInfo = $view->getBaseEntityType()) {
        $this->entityType = $this->entityInfo->id();
        return;
      }

      // Eventually try to set entity type & info from base table suffix
      // (i.e. Search API views).
      if (!isset($this->entityType) && $this->moduleHandler->moduleExists('search_api')) {
        $index_id = substr($base_table, 17);
        $index = Index::load($index_id);
        foreach ($index->getDatasources() as $datasource) {
          if ($datasource instanceof DatasourceInterface) {
            $this->entityType = $datasource->getEntityTypeId();
            try {
              $this->entityInfo = $this->entityManager->getDefinition($this->entityType);
            }
            catch (\Exception $e) {
              watchdog_exception('leaflet', $e);
            }
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldValue($index, $field) {
    $result = $this->view->result[$index];

    if ($result instanceof SearchApiResultRow) {
      $search_api_field = $result->_item->getField($field, FALSE);
      if ($search_api_field !== NULL) {
        $values = $search_api_field->getValues();
      }

      if (!empty($values)) {
        foreach ($values as $key => $value) {
          [$lat, $lon] = explode(',', $value);
          $values[$key] = sprintf('POINT(%s %s)', $lon, $lat);
        }
        return $values;
      }
    }

    // Check and return values coming from normal View or Search Api View,
    // or return NULL Otherwise.
    if (isset($this->view->field[$field]) &&
      ($this->view->result[$index] instanceof ResultRow || $this->view->result[$index] instanceof SearchApiResultRow)
    ) {
      return $this->view->field[$field]->getValue($this->view->result[$index]);
    }
    else {
      return NULL;
    }
  }

  /**
   * Get a list of fields and a sublist of geo data fields in this view.
   *
   * @return array
   *   Available data sources.
   */
  protected function getAvailableDataSources() {
    $fields_geo_data = [];

    /** @var \Drupal\views\Plugin\views\ViewsHandlerInterface $handler) */
    foreach ($this->displayHandler->getHandlers('field') as $field_id => $handler) {
      $label = $handler->adminLabel() ?: $field_id;
      $this->viewFields[$field_id] = $label;
      if (is_a($handler, '\Drupal\views\Plugin\views\field\EntityField')) {
        /** @var \Drupal\views\Plugin\views\field\EntityField $handler */
        try {
          $entity_type = $handler->getEntityType();
        }
        catch (\Exception $e) {
          $entity_type = NULL;
        }
        $field_storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type);
        if (array_key_exists($handler->definition['field_name'], $field_storage_definitions)) {
          $field_storage_definition = $field_storage_definitions[$handler->definition['field_name']];
          $type = $field_storage_definition->getType();
          try {
            $definition = $this->fieldTypeManager->getDefinition($type);
            if (is_a($definition['class'], '\Drupal\geofield\Plugin\Field\FieldType\GeofieldItem', TRUE)) {
              $fields_geo_data[$field_id] = $label;
            }
          }
          catch (\Exception $e) {
            watchdog_exception("Leaflet Map - Get Available data sources", $e);
          }
        }
      }
    }

    return $fields_geo_data;
  }

  /**
   * Get options for the available entity sources.
   *
   * Entity source controls which entity gets passed to the MapThemer plugin. If
   * not set it will always default to the view base entity.
   *
   * @return array
   *   The entity sources list.
   */
  protected function getAvailableEntitySources() {
    if ($base_entity_type = $this->view->getBaseEntityType()) {
      $label = $base_entity_type->getLabel();
    }
    else {
      // Fallback to the base table key.
      $base_tables = array_keys($this->view->getBaseTables());
      // A view without a base table should never happen (just in case).
      $label = $base_tables[0] ?? $this->t('Unknown');
    }

    $options = [
      '__base_table' => new TranslatableMarkup('View Base Entity (@entity_type)', [
        '@entity_type' => $label,
      ]),
    ];

    $data = Views::viewsData();
    /** @var \Drupal\views\Plugin\views\HandlerBase $handler */
    foreach ($this->displayHandler->getHandlers('relationship') as $relationship_id => $handler) {
      if (($table = $data->get($handler->definition['base'])) && !empty($table['table']['entity type'])) {
        try {
          $entity_type = $this->entityManager->getDefinition($table['table']['entity type']);
        }
        catch (\Exception $e) {
          $entity_type = NULL;
        }
        $options[$relationship_id] = new TranslatableMarkup('@relationship (@entity_type)', [
          '@relationship' => $handler->adminLabel(),
          '@entity_type' => $entity_type->getLabel(),
        ]);
      }
    }

    return $options;
  }

  /**
   * Get the entity info of the entity source.
   *
   * @param string $source
   *   The Source identifier.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   The entity type.
   */
  protected function getEntitySourceEntityInfo($source) {
    if (!empty($source) && ($source != '__base_table')) {
      $handler = $this->displayHandler->getHandler('relationship', $source);

      $data = Views::viewsData();
      if (($table = $data->get($handler->definition['base'])) && !empty($table['table']['entity type'])) {
        try {
          return $this->entityManager->getDefinition($table['table']['entity type']);
        }
        catch (\Exception $e) {
        }
      }
    }

    return $this->view->getBaseEntityType();
  }

  /**
   * {@inheritdoc}
   */
  public function evenEmpty() {
    // Render map even if there is no data.
    return TRUE;
  }

  /**
   * Get the Layers options List from the Grouping Field Settings.
   *
   * @param \Drupal\leaflet_views\Plugin\views\style\LeafletMap $view_style
   *   The LeafletMap View style.
   * @param string $grouping_field_value
   *   The grouping field value.
   * @param bool $grouping_rendered_value
   *   The grouping rendered flag.
   *
   * @return array|string[]
   *   The group Overlays definitions list.
   */
  public static function getOverlaysOptions(LeafletMap $view_style, string $grouping_field_value, bool $grouping_rendered_value = FALSE) {
    $overlays = [];
    if ($view_style->view->execute()) {
      // Group the rows according to the grouping instructions, if specified.
      $view_results_groups = $view_style->renderGrouping(
        $view_style->view->result,
        $grouping_field_value,
        $grouping_rendered_value,
      );
      $overlays = ['none' => ' - none - '];
      foreach ($view_results_groups as $group_label => $view_results_group) {
        $group_label = str_replace(["\n", "\r"], "", strip_tags($group_label));
        // Add a Layer Option only if there is a group label value not empty.
        if (!empty($group_label)) {
          $overlays[$group_label] = $group_label;
        }
      }
    }
    return $overlays;
  }

  /**
   * Provide a new group 0 overlays_options on the AJAX call.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The replacing group 0 overlays_options form element.
   */
  public static function updateGrouping0OverlaysOptionsAjax(array $form, FormStateInterface $form_state): array {
    return $form["options"]["style_options"]["grouping"][0]["overlays_options"];
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {

    // If data source changed then apply the changes.
    if ($form_state->get('entity_source')) {
      $this->options['entity_source'] = $form_state->get('entity_source');
      $this->entityInfo = $this->getEntitySourceEntityInfo($this->options['entity_source']);
      $this->entityType = $this->entityInfo->id();
      $this->entitySource = $this->options['entity_source'];
    }

    // Build the Parent Form.
    parent::buildOptionsForm($form, $form_state);

    $form['#attached'] = [
      'library' => [
        'leaflet/general',
      ],
    ];

    // Customise for this Leaflet View Style the "grouping" section.
    $form["grouping"] = [
      '#type' => 'details',
      '#title' => $this->t("Leaflet Grouping"),
      0 => $form["grouping"][0],
    ];

    $form["grouping"][0]["field"]["#title"] = $this->t('Grouping field');
    $form["grouping"][0]["field"]["#description"] = $this->t("You may optionally specify a field by which to group the Leaflet Map Features by Overlayers, whose visibility could be managed throughout the Leaflet Map Layers Control.<br>Leave blank to not group");
    unset($form["grouping"][0]["rendered_strip"]);

    $form["grouping"][0]["field"]['#ajax'] = [
      'callback' => __CLASS__ . '::updateGrouping0OverlaysOptionsAjax',
      'wrapper' => 'grouping-0-overlays_options-fieldset',
      'event' => 'change',
    ];

    $form["grouping"][0]["rendered"]['#ajax'] = [
      'callback' => __CLASS__ . '::updateGrouping0OverlaysOptionsAjax',
      'wrapper' => 'grouping-0-overlays_options-fieldset',
      'event' => 'change',
    ];

    // Unset/remove the Grouping Field n.2.
    // as we don't support it in Leaflet View style map, at the moment.
    unset($form["grouping"][1]);

    // Get a sublist of geo data fields in the view.
    $fields_geo_data = $this->getAvailableDataSources();

    // Overlay Options settings section.
    $form["grouping"][0]['overlays_options'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Layers options'),
      '#attributes' => ['id' => 'grouping-0-overlays_options-fieldset'],
    ];

    // Extract the Layers options depending on the form state.
    $grouping_0_field = $form_state->getUserInput()['style_options']['grouping'][0]['field'] ?? $form["grouping"][0]["field"]["#default_value"];
    $grouping_0_rendered_option = isset($form_state->getUserInput()['style_options']['grouping'][0]) ? ($form_state->getUserInput()['style_options']['grouping'][0]['rendered'] ?? FALSE) : $form["grouping"][0]["rendered"]["#default_value"];
    $overlays_options = self::getOverlaysOptions($this, $grouping_0_field, $grouping_0_rendered_option);

    // Disabled Layers.
    $form["grouping"][0]['overlays_options']['disabled_overlays'] = count($overlays_options) > 1 ? [
      '#type' => 'select',
      '#title' => $this->t('Disabled Layers'),
      '#description' => $this->t('Choose the Layers that should start as disabled / switched off'),
      '#options' => $overlays_options,
      '#default_value' => $this->options["grouping"][0]['overlays_options']['disabled_overlays'],
      // The #validated setting to TRUE skips the "An illegal choice has been
      // detected" error message after Ajax refresh.
      '#validated' => TRUE,
      '#required' => FALSE,
      '#multiple' => TRUE,
      '#size' => count($overlays_options) < 10 ? count($overlays_options) + 1 : 10,
      '#states' => [
        'invisible' => [
          ':input[name="style_options[grouping][0][field]"]' => ['value' => ''],
        ],
      ],
    ] : [
      '#type' => 'hidden',
      '#value' => [],
    ];

    // Disabled Layers.
    $form["grouping"][0]['overlays_options']['hidden_overlays_controls'] = count($overlays_options) > 1 ? [
      '#type' => 'select',
      '#title' => $this->t('Hidden Layers Controls'),
      '#description' => $this->t('Choose the Layers that will not appear in the Layers Control'),
      '#options' => $overlays_options,
      '#default_value' => $this->options["grouping"][0]['overlays_options']['hidden_overlays_controls'],
      // The #validated setting to TRUE skips the "An illegal choice has been
      // detected" error message after Ajax refresh.
      '#validated' => TRUE,
      '#required' => FALSE,
      '#multiple' => TRUE,
      '#size' => count($overlays_options) < 10 ? count($overlays_options) + 1 : 10,
      '#states' => [
        'invisible' => [
          ':input[name="style_options[grouping][0][field]"]' => ['value' => ''],
        ],
      ],
    ] : [
      '#type' => 'hidden',
      '#value' => [],
    ];

    // Check whether we have a geo data field we can work with.
    if (!count($fields_geo_data)) {
      $form['error'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Please add at least one Geofield to the View and come back here to set it as Data Source.'),
        '#attributes' => [
          'class' => ['leaflet-warning'],
        ],
      ];
      return;
    }

    $wrapper_id = 'leaflet-map-views-style-options-form-wrapper';
    $form['#prefix'] = '<div id="' . $wrapper_id . '">';
    $form['#suffix'] = '</div>';

    // Map preset.
    $form['data_source'] = [
      '#type' => 'select',
      '#title' => $this->t('Data Source'),
      '#description' => $this->t('Which Geofield(s) contains geodata you want to map?'),
      '#options' => $fields_geo_data,
      '#default_value' => $this->options['data_source'],
      '#required' => TRUE,
      '#multiple' => TRUE,
      '#size' => count($fields_geo_data),
    ];

    // Get the possible entity sources.
    $entity_sources = $this->getAvailableEntitySources();

    // If there is only one entity source it will be the base entity, so don't
    // show the element to avoid confusing people.
    if (count($entity_sources) == 1) {
      $form['entity_source'] = [
        '#type' => 'value',
        '#value' => key($entity_sources),
      ];
    }
    else {
      $form['entity_source'] = [
        '#type' => 'select',
        '#title' => new TranslatableMarkup('Entity Source'),
        '#description' => new TranslatableMarkup('Select which Entity should be used as Leaflet Mapping base Entity.<br><u>Leave as "View Base Entity" to rely on default Views behaviour, and don\'t specifically needed otherwise</u>.'),
        '#options' => $entity_sources,
        '#default_value' => !empty($this->options['entity_source']) ? $this->options['entity_source'] : '__base_table',
        '#ajax' => [
          'wrapper' => $wrapper_id,
          'callback' => [static::class, 'optionsFormEntitySourceSubmitAjax'],
          'trigger_as' => ['name' => 'entity_source_submit'],
        ],
      ];
      $form['entity_source_submit'] = [
        '#type' => 'submit',
        '#value' => new TranslatableMarkup('Update Entity Source'),
        '#name' => 'entity_source_submit',
        '#submit' => [
          [static::class, 'optionsFormEntitySourceSubmit'],
        ],
        '#validate' => [],
        '#limit_validation_errors' => [
          ['style_options', 'entity_source'],
        ],
        '#attributes' => [
          'class' => ['js-hide'],
        ],
        '#ajax' => [
          'wrapper' => $wrapper_id,
          'callback' => [static::class, 'optionsFormEntitySourceSubmitAjax'],
        ],
      ];
    }

    // Set Leaflet Tooltip Element.
    $this->setTooltipElement($form, $this->options, $this->viewFields);

    // Set Simple Tooltip.
    $form['name_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Simple Tooltip'),
      '#description' => $this->t('Choose the field which will appear as as Simple Tooltip on mouse over each Leaflet feature.'),
      '#options' => array_merge(['' => ' - None - '], $this->viewFields),
      '#default_value' => $this->options['name_field'],
      '#states' => [
        'visible' => [
          'select[name="style_options[leaflet_tooltip][value]' => ['value' => ''],
        ],
      ],
    ];

    // Get the human-readable labels for the entity view modes.
    $view_mode_options = [];
    foreach ($this->entityDisplay->getViewModes($this->entityType) as $key => $view_mode) {
      $view_mode_options[$key] = $view_mode['label'];
    }

    // Set Leaflet Popup Element.
    $this->setPopupElement($form, $this->options, $this->viewFields, $this->entityType, $view_mode_options);

    // Generate the Leaflet Map General Settings.
    $this->generateMapGeneralSettings($form, $this->options);

    // Set the FitBoundsOptions Element.
    $this->setFitBoundsOptionsElement($form, $this->options);

    // Generate the Leaflet Map Reset Control.
    $this->setResetMapViewControl($form, $this->options);

    // Generate the Leaflet Map Scale Control.
    $this->setMapScaleControl($form, $this->options);

    // Generate the Leaflet Map Position Form Element.
    $form['map_position'] = $this->generateMapPositionElement($this->options['map_position']);

    // Generate the Leaflet Map weight/zIndex Form Element.
    $form['weight'] = $this->generateWeightElement($this->options['weight']);

    // Generate Icon form element.
    $icon_options = $this->options['icon'];
    $form['icon'] = $this->generateIconFormElement($icon_options);

    // Set Map Marker Cluster Element.
    $this->setMapMarkerclusterElement($form, $this->options, $this->viewFields);

    // Set Fullscreen Element.
    $this->setFullscreenElement($form, $this->options);

    // Set Map Geometries Options Element.
    $this->setMapPathOptionsElement($form, $this->options);

    // Set the Feature Additional Properties Element.
    $this->setFeatureAdditionalPropertiesElement($form, $this->options);

    // Set Locate User Position Control Element.
    $this->setLocateControl($form, $this->options);

    // Set Map Geocoder Control Element, if the Geocoder Module exists,
    // otherwise output a tip on Geocoder Module Integration.
    $this->setGeocoderMapControl($form, $this->options);

    // Set Map Lazy Load Element.
    $this->setMapLazyLoad($form, $this->options);

    unset($form["#pre_render"]);

  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    parent::validateOptionsForm($form, $form_state);

    $style_options = $form_state->getValue('style_options');
    if (!empty($style_options['height']) && (!is_numeric($style_options['height']) || $style_options['height'] <= 0)) {
      $form_state->setError($form['height'], $this->t('Map height needs to be a positive number.'));
    }
  }

  /**
   * Submit to update the data source.
   *
   * @param array $form
   *   The Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The Form state.
   */
  public static function optionsFormEntitySourceSubmit(array $form, FormStateInterface $form_state) {
    $parents = $form_state->getTriggeringElement()['#parents'];
    array_pop($parents);
    array_push($parents, 'entity_source');

    // Set the data source selected in the form state and rebuild the form.
    $form_state->set('entity_source', $form_state->getValue($parents));
    $form_state->setRebuild(TRUE);
  }

  /**
   * Ajax callback to reload the options form after data source change.
   *
   * This allows the entityType which can be affected by which source
   * is selected to alter the form.
   *
   * @param array $form
   *   The Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The Form state.
   *
   * @return mixed
   *   The returned result.
   */
  public static function optionsFormEntitySourceSubmitAjax(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $array_parents = $triggering_element['#array_parents'];
    array_pop($array_parents);

    return NestedArray::getValue($form, $array_parents);
  }

  /**
   * Renders the View.
   */
  public function render() {
    $features_groups = [];
    $element = [];

    // Collect bubbleable metadata when doing early rendering.
    $build_for_bubbleable_metadata = [];

    // Always render the map, otherwise ...
    $leaflet_map_style = !isset($this->options['leaflet_map']) ? $this->options['map'] : $this->options['leaflet_map'];
    $map = leaflet_map_get_info($leaflet_map_style);

    // Set Map additional map Settings.
    $this->setAdditionalMapOptions($map, $this->options);

    // Add a specific map id.
    $map['id'] = Html::getUniqueId("leaflet_map_view_" . $this->view->id() . '_' . $this->view->current_display);

    // Define the list of geofields set as source of Leaflet View geodata,
    // with backword compatibility with the previous version (8.1.22) when only
    // one Geofield was possible as geodata source.
    $geofield_names = is_array($this->options['data_source']) ? $this->options['data_source'] : [$this->options['data_source']];

    // If the Geofield field is null, output a warning
    // to the Geofield Map administrator.
    if (empty($geofield_names) && $this->currentUser->hasPermission('configure geofield_map')) {
      $element = [
        '#markup' => '<div class="geofield-map-warning">' . $this->t("The Geofield field has not been correctly set for this View. <br>Add at least one Geofield to the View and set it as Data Source in the Geofield Google Map View Display Settings.") . "</div>",
        '#attached' => [
          'library' => ['leaflet/general'],
        ],
      ];
    }

    if (!empty($geofield_names) && (!empty($this->view->result) || !$this->options['hide_empty_map'])) {
      $this->renderFields($this->view->result);

      // Group the rows according to the grouping instructions, if specified.
      $view_results_groups = $this->renderGrouping(
        $this->view->result,
        $this->options['grouping'],
        TRUE
      );

      foreach ($view_results_groups as $group_label => $view_results_group) {
        $features_group = [];
        // Sanitize the Group Label from Tags and invisible characters.
        $group_label = str_replace(["\n", "\r"], "", strip_tags($group_label));

        // Iterate on each geofields set as source of Leaflet View geodata.
        foreach ($geofield_names as $geofield_name) {

          if (isset($this->view->field[$geofield_name])) {

            foreach ($view_results_group['rows'] as $id => $result) {

              // For proper processing make sure the geofield_value is created
              // as an array, also if single value.
              $geofield_value = $this->view->field[$geofield_name] ? (array) $this->getFieldValue($id, $geofield_name) : [];

              // Allow other modules to add/alter the $geofield_value
              // and the $map.
              $leaflet_view_geofield_value_alter_context = [
                'leaflet_map_style' => $leaflet_map_style,
                'result' => $result,
                'leaflet_view_style' => $this,
              ];
              $this->moduleHandler->alter('leaflet_map_view_geofield_value', $geofield_value, $map, $leaflet_view_geofield_value_alter_context);

              if (!empty($geofield_value)) {
                $features = $this->leafletService->leafletProcessGeofield($geofield_value);

                $entity_id = NULL;
                $entity_type = NULL;
                $entity_language = NULL;
                if (!empty($result->_entity)) {
                  // Entity API provides a plain entity object.
                  $entity = $result->_entity;
                  $entity_id = $entity->id();
                  $entity_type = $entity->getEntityTypeId();
                  $entity_language = $entity->language()->getId();
                }
                elseif (isset($result->_object)) {
                  // Search API provides a TypedData EntityAdapter.
                  $entity_adapter = $result->_object;
                  if ($entity_adapter instanceof EntityAdapter) {
                    $entity = $entity_adapter->getValue();
                    $entity_id = $entity->id();
                    $entity_type = $entity->getEntityTypeId();
                    $entity_language = $entity->language()->getId();
                  }
                }
                elseif ($result instanceof SearchApiResultRow) {
                  $id = $result->_item->getId();
                  $search_api_id_parts = explode(':', $result->_item->getId());
                  $id_parts = explode('/', $search_api_id_parts[1]);
                  $entity_id = $id_parts[1] ?? NULL;
                  $entity_type = $id_parts[0] ?? NULL;
                  $entity_language = $search_api_id_parts[2] ?? NULL;
                }

                // Render the entity with the selected view mode.
                if (!empty($entity_id) && !empty($entity_type)) {
                  // Get and set (if not set) the Geofield cardinality.
                  /** @var \Drupal\Core\Field\FieldItemList $geofield_entity */
                  if (!isset($map['geofield_cardinality']) && isset($entity)) {
                    try {
                      $geofield_entity = $entity->get($geofield_name);
                      $map['geofield_cardinality'] = $geofield_entity->getFieldDefinition()
                        ->getFieldStorageDefinition()
                        ->getCardinality();
                    }
                    catch (\Exception $e) {
                      // In case of exception it means that $geofield_name field
                      // is not directly related to the $entity and might be the
                      // case of a geofield exposed through a relationship.
                      // In this case it is complicated to get the geofield
                      // related entity, so apply a more general case of
                      // multiple/infinite geofield_cardinality.
                      // @see: https://www.drupal.org/project/leaflet/issues/3048089
                      $map['geofield_cardinality'] = -1;
                    }
                  }
                  else {
                    $map['geofield_cardinality'] = -1;
                  }

                  $entity_type_langcode_attribute = $entity_type . '_field_data_langcode';

                  $view = $this->view;

                  // Set the langcode to be used for rendering the entity.
                  $rendering_language = $view->display_handler->getOption('rendering_language');
                  $dynamic_renderers = [
                    '***LANGUAGE_entity_translation***' => 'TranslationLanguageRenderer',
                    '***LANGUAGE_entity_default***' => 'DefaultLanguageRenderer',
                  ];
                  if (isset($dynamic_renderers[$rendering_language])) {
                    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
                    $langcode = $result->$entity_type_langcode_attribute ?? $entity_language;
                  }
                  else {
                    if (strpos($rendering_language, '***LANGUAGE_') !== FALSE) {
                      $langcode = PluginBase::queryLanguageSubstitutions()[$rendering_language];
                    }
                    else {
                      // Specific langcode set.
                      $langcode = $rendering_language;
                    }
                  }

                  // Define the Popup source and Popup view mode with backward
                  // compatibility with Leaflet release < 2.x.
                  $popup_source = !empty($this->options['description_field']) ? $this->options['description_field'] : ($this->options['leaflet_popup']['value'] ?? '');
                  $popup_view_mode = !empty($this->options['view_mode']) ? $this->options['view_mode'] : $this->options['leaflet_popup']['view_mode'];

                  switch ($popup_source) {
                    case '#rendered_entity':
                      $build = $this->entityManager->getViewBuilder($entity_type)
                        ->view($entity, $popup_view_mode, $langcode);
                      $render_context = new RenderContext();
                      $popup_content = $this->renderer->executeInRenderContext($render_context, function () use (&$build) {
                        return $this->renderer->render($build, TRUE);
                      });
                      if (!$render_context->isEmpty()) {
                        $render_context->update($build_for_bubbleable_metadata);
                      }
                      break;

                    case '#rendered_entity_ajax':
                      $parameters = [
                        'entity_type' => $entity_type,
                        'entity' => $entity_id,
                        'view_mode' => $popup_view_mode,
                        'langcode' => $langcode,
                      ];
                      $url = Url::fromRoute('leaflet_views.ajax_popup', $parameters);
                      $popup_content = sprintf('<div class="leaflet-ajax-popup" data-leaflet-ajax-popup="%s" %s></div>',
                        $url->toString(), LeafletAjaxPopupController::getPopupIdentifierAttribute($entity_type, $entity_id, $this->options['leaflet_popup']['view_mode'], $langcode));
                      $map['settings']['ajaxPoup'] = TRUE;
                      break;

                    case '#rendered_view_fields':
                      // Normal rendering via view/row fields
                      // (with labels options, formatters, classes, etc.).
                      $render_row = [
                        "markup" => $this->view->rowPlugin->render($result),
                      ];
                      $popup_content = $this->renderer->renderPlain($render_row);
                      break;

                    default:
                      // Row rendering of single specified field value (without
                      // labels).
                      $popup_content = !empty($popup_source) ? $this->rendered_fields[$result->index][$popup_source] : '';
                  }

                  // Eventually merge map icon definition
                  // from hook_leaflet_map_info.
                  if (!empty($map['icon'])) {
                    $this->options['icon'] = $this->options['icon'] ?: [];

                    // Remove empty icon options so that they might be replaced
                    // by the ones set by the hook_leaflet_map_info.
                    foreach ($this->options['icon'] as $k => $icon_option) {
                      if (empty($icon_option) || (is_array($icon_option) && $this->leafletService->multipleEmpty($icon_option))) {
                        unset($this->options['icon'][$k]);
                      }
                    }
                    $this->options['icon'] = array_replace($map['icon'], $this->options['icon']);
                  }

                  // Define possible tokens.
                  $tokens = [];
                  foreach ($this->rendered_fields[$result->index] as $field_name => $field_value) {
                    $tokens[$field_name] = $field_value;
                  }

                  $icon_type = $this->options['icon']['iconType'] ?? 'marker';

                  // Relates each result feature with additional properties.
                  foreach ($features as &$feature) {

                    // Attach pop-ups if we have a description field.
                    // Add its entity id, so it might be referenced from
                    // outside.
                    $feature['entity_id'] = $entity_id;

                    // Generate the weight feature property
                    // (falls back to natural result ordering).
                    $feature['weight'] = !empty($this->options['weight']) ? intval(str_replace([
                      "\n",
                      "\r",
                    ], "", $this->viewsTokenReplace($this->options['weight'], $tokens))) : $id;

                    // Attach pop-ups if we have a description field.
                    if (!empty($popup_content)) {
                      $feature['popup']['value'] = $popup_content;
                      $feature['popup']['options'] = $this->options['leaflet_popup'] ? $this->options['leaflet_popup']['options'] : NULL;
                    }

                    // Attach tooltip data (value & options),
                    // if tooltip value is not empty.
                    if (!empty($this->options['leaflet_tooltip']['value'])) {
                      $feature['tooltip'] = $this->options['leaflet_tooltip'];
                      // Decode any entities because JS will encode them again,
                      // and we don't want double encoding.
                      $feature['tooltip']['value'] = !empty($this->options['leaflet_tooltip']['value']) ? Html::decodeEntities(($this->rendered_fields[$result->index][$this->options['leaflet_tooltip']['value']])) : '';
                    }
                    // Otherwise eventually attach simple title tooltip.
                    elseif ($this->options['name_field']) {
                      // Decode any entities because JS will encode them again,
                      // and we don't want double encoding.
                      $feature['title'] = !empty($this->options['name_field']) ? Html::decodeEntities(($this->rendered_fields[$result->index][$this->options['name_field']])) : '';
                    }

                    // Eventually set the custom Marker icon (DivIcon, Icon Url
                    // or Circle Marker).
                    if ($feature['type'] === 'point' && isset($this->options['icon'])) {
                      // Set Feature Icon properties.
                      $feature['icon'] = $this->options['icon'];

                      // Transforms Icon Options that support Replacement
                      // Patterns/Tokens.
                      if (!empty($this->options["icon"]["iconSize"]["x"])) {
                        $feature['icon']["iconSize"]["x"] = $this->viewsTokenReplace($this->options["icon"]["iconSize"]["x"], $tokens);
                      }
                      if (!empty($this->options["icon"]["iconSize"]["y"])) {
                        $feature['icon']["iconSize"]["y"] = $this->viewsTokenReplace($this->options["icon"]["iconSize"]["y"], $tokens);
                      }
                      if (!empty($this->options["icon"]["shadowSize"]["x"])) {
                        $feature['icon']["shadowSize"]["x"] = $this->viewsTokenReplace($this->options["icon"]["shadowSize"]["x"], $tokens);
                      }
                      if (!empty($this->options["icon"]["shadowSize"]["y"])) {
                        $feature['icon']["shadowSize"]["y"] = $this->viewsTokenReplace($this->options["icon"]["shadowSize"]["y"], $tokens);
                      }

                      switch ($icon_type) {
                        case 'html':
                          $feature['icon']['html'] = str_replace([
                            "\n",
                            "\r",
                          ], "", $this->viewsTokenReplace($this->options['icon']['html'], $tokens));
                          $feature['icon']['html_class'] = $this->options['icon']['html_class'];
                          break;

                        case 'circle_marker':
                          $feature['icon']['options'] = str_replace([
                            "\n",
                            "\r",
                          ], "", $this->viewsTokenReplace($this->options['icon']['circle_marker_options'], $tokens));
                          break;

                        default:
                          // Apply Token Replacements to iconUrl & shadowUrl.
                          if (!empty($this->options['icon']['iconUrl'])) {
                            $feature['icon']['iconUrl'] = str_replace([
                              "\n",
                              "\r",
                            ], "", $this->viewsTokenReplace($this->options['icon']['iconUrl'], $tokens));
                            // Generate correct Absolute iconUrl & shadowUrl,
                            // if not external.
                            if (!empty($feature['icon']['iconUrl'])) {
                              $feature['icon']['iconUrl'] = $this->leafletService->generateAbsoluteString($feature['icon']['iconUrl']);
                            }
                          }
                          if (!empty($this->options['icon']['shadowUrl'])) {
                            $feature['icon']['shadowUrl'] = str_replace([
                              "\n",
                              "\r",
                            ], "", $this->viewsTokenReplace($this->options['icon']['shadowUrl'], $tokens));
                            if (!empty($feature['icon']['shadowUrl'])) {
                              $feature['icon']['shadowUrl'] = $this->leafletService->generateAbsoluteString($feature['icon']['shadowUrl']);
                            }
                          }

                          // Set Feature IconSize and ShadowSize to the IconUrl
                          // or ShadowUrl Image sizes (if empty or invalid).
                          $this->leafletService->setFeatureIconSizesIfEmptyOrInvalid($feature);

                          break;
                      }
                    }

                    // Associate dynamic path properties (token based) to each
                    // feature, in case of not point.
                    if ($feature['type'] !== 'point') {
                      $feature['path'] = str_replace([
                        "\n",
                        "\r",
                      ], "", $this->viewsTokenReplace($this->options['path'], $tokens));
                    }

                    // Associate dynamic className property (token based) to
                    // icon.
                    $feature['icon']['className'] = !empty($this->options['icon']['className']) ? str_replace([
                      "\n",
                      "\r",
                    ], "", $this->viewsTokenReplace($this->options['icon']['className'], $tokens)) : '';

                    // Add Feature additional Properties (if present).
                    if (!empty($this->options['feature_properties']['values'])) {
                      $feature['properties'] = str_replace([
                        "\n",
                        "\r",
                      ], "", $this->viewsTokenReplace($this->options['feature_properties']['values'], $tokens));
                    }

                    // Add eventually the Marker Cluster Exclude Flag.
                    if ($this->options['leaflet_markercluster'] && $this->options['leaflet_markercluster']['control'] && !empty($this->options['leaflet_markercluster']['excluded'])) {
                      $feature['markercluster_excluded'] = !empty(str_replace([
                        "\n",
                        "\r",
                      ], "", strip_tags($this->rendered_fields[$result->index][$this->options['leaflet_markercluster']['excluded']])));
                    }

                    // Eventually Add the belonging Group Label/Name to each
                    // Feature, for possible based logics.
                    if (count($view_results_groups) > 1) {
                      $feature['group_label'] = $group_label;
                    }

                    // Allow modules to adjust the single feature (marker).
                    $this->moduleHandler->alter('leaflet_views_feature', $feature, $result, $this->view->rowPlugin);
                  }
                }

                // Generate a single Features Group as incremental Features.
                $features_group = array_merge($features_group, $features);
              }
            }
          }
        }

        // Order the data features based on the 'weight' element.
        uasort($features_group, [
          'Drupal\Component\Utility\SortArray',
          'sortByWeightElement',
        ]
        );

        // Generate Features Groups in case of Grouping.
        if (count($view_results_groups) > 1) {
          // Generate the Features Group.
          $group = [
            'group' => count($view_results_groups) > 1,
            'group_label' => $group_label,
            'disabled' => FALSE,
            'features' => $features_group,
            'weight' => 1,
          ];

          if (isset($this->options["grouping"][0]) && !empty($this->options["grouping"][0]["overlays_options"]["hidden_overlays_controls"])) {
            $group['group_label'] = !array_key_exists($group_label, $this->options["grouping"][0]["overlays_options"]["hidden_overlays_controls"]) ? $group_label : NULL;
          }

          if (isset($this->options["grouping"][0]) && !empty($this->options["grouping"][0]["overlays_options"]["disabled_overlays"])) {
            $group['disabled'] = array_key_exists($group_label, $this->options["grouping"][0]["overlays_options"]["disabled_overlays"]);
          }

          // Allow modules to adjust the single features group.
          $this->moduleHandler->alter('leaflet_views_features_group', $group, $this);

          // Add the Group to the Features Groups array/list.
          $features_groups[] = $group;
        }
      }

      // Order the data features based on the 'weight' element.
      if (isset($features_group) && count($features_group) > 1) {
        // Order the data features groups based on the 'weight' element.
        uasort($features_group, [
          'Drupal\Component\Utility\SortArray',
          'sortByWeightElement',
        ]);
      }

      // Define the Js Settings.
      // Features is defined as Features Groups or single Features in case of a
      // single Features Group (no Grouping active)
      $js_settings = [
        'map' => $map,
        'features' => count($view_results_groups) > 1 ? $features_groups : ($features_group ?? []),
      ];

      // Allow other modules to add/alter the map js settings.
      $this->moduleHandler->alter('leaflet_map_view_style', $js_settings, $this);

      $map_height = !empty($this->options['height']) ? $this->options['height'] . $this->options['height_unit'] : '';
      $element = $this->leafletService->leafletRenderMap($js_settings['map'], $js_settings['features'], $map_height);

      // Add the Core Drupal Ajax library for Ajax Popups.
      if (isset($map['settings']['ajaxPoup']) && $map['settings']['ajaxPoup']) {
        $build_for_bubbleable_metadata['#attached']['library'][] = 'core/drupal.ajax';
      }
      BubbleableMetadata::createFromRenderArray($element)
        ->merge(BubbleableMetadata::createFromRenderArray($build_for_bubbleable_metadata))
        ->applyTo($element);
    }
    return $element;
  }

  /**
   * Set default options.
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['data_source'] = ['default' => ''];
    $options['entity_source'] = ['default' => '__base_table'];
    $options['name_field'] = ['default' => ''];

    $leaflet_map_default_settings = [];
    foreach (self::getDefaultSettings() as $k => $setting) {
      $leaflet_map_default_settings[$k] = ['default' => $setting];
    }
    return $options + $leaflet_map_default_settings;
  }

}
