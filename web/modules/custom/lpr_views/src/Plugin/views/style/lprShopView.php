<?php

namespace Drupal\lpr_views\Plugin\views\style;

use Drupal\core\form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;

/**
 * Style plugin to render a list of commerce plugin's items
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "lpr_shop_view",
 *   title = @Translation("LPR SHOP VIEW"),
 *   help = @Translation("Render a list of commerce plugin's items."),
 *   theme = "views_view_lpr_shop_view",
 *   display_types = { "normal" }
 * )
 */

 class lprShopView extends StylePluginBase {
    /**
    * {@inheritdoc}
    */
    protected function defineOptions() {
        $options = parent::defineOptions();
        //$options['path'] = array('default' => 'lpr_shop_view');
        return $options;
    }

    /**
    * {@inheritdoc}
    */
    public function buildOptionsForm(&$form, FormStateInterface $form_state) {
        parent::buildOptionsForm($form, $form_state);
    
        // Path prefix for LPR links.
        /* $form['path'] = array(
          '#type' => 'textfield',
          '#title' => t('Link path'),
          '#default_value' => (isset($this->options['path'])) ? $this->options['path'] : 'lpr',
          '#description' => t('Path prefix for each LPR link, eg. example.com<strong>/lpr/</strong>2015/10.'),
        ); */
            
        // Extra CSS classes.
        /* $form['classes'] = array(
          '#type' => 'textfield',
          '#title' => t('CSS classes'),
          '#default_value' => (isset($this->options['classes'])) ? $this->options['classes'] : 'lpr_view',
          '#description' => t('CSS classes for further customization of this LPR page.'),
        ); */
      }
    
    }
    