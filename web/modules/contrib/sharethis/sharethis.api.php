<?php

/**
 * @file
 * Hook provided by sharethis module.
 */

/**
 * Provide a sharethis_render alteration.
 *
 * @param array $attributes
 *   This has url, title, class and display text for the particular
 *   sharethis-button.
 * @param array $data_options
 *   All configuration settings for sharethis.
 * @param string $span_text
 *   The string displayed inside the button.
 */
function hook_sharethis_render_alter(array &$attributes, array &$data_options, string &$span_text) {
  // Change the st_username in attributes.
  $attributes['st_username'] = t('newuser');
  // Change the twitter_handle in data_options.
  $data_options['twitter_handle'] = t('New twitter handle.');
}
