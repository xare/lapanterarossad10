<?php

/**
 * @file
 * Theme for LPR views.
 */
function template_preprocess_views_view_lpr_views(&$variables) {
  // View options set by user.
  $options = $variables['view']->style_plugin->options;
  $variables['options'] = $options;
}