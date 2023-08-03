<?php

namespace Drupal\opening\plugin\block;

use Drupal\Core\Block\BlockBase;


/**
 * Provides a 'Opening' block.
 *
 * @Block(
 *   id = "opening_block",
 *   admin_label = @Translation("Opening block"),
 * )
 */

class OpeningBlock extends BlockBase {
    /**
    * {@inheritdoc}
    */
    public function build() {

        // Get the current time in the desired format.
    $current_time = \Drupal::service('datetime.time')->getRequestTime();
    $current_time_formatted = \Drupal::service('date.formatter')->format($current_time, 'custom', 'H:i');

    // Check if the library is open.
    $is_library_open = $this->isLibraryOpen($current_time_formatted);

    // Build the sentence based on the library's status and the current time.
    $sentence = $is_library_open ? $this->t('Ahora son las @time, nos puedes encontrar en la librería', ['@time' => $current_time_formatted]) : $this->t('Ahora son las @time, estamos cerradas', ['@time' => $current_time_formatted]);
    $sentence = '<p>' . $sentence . '</p>';
        return [
            '#markup' => $sentence,
        ];
    }

    /**
 * Check if the library is open based on the current time.
 *
 * @param string $current_time
 *   The current time in 'H:i' format.
 *
 * @return bool
 *   TRUE if the library is open, FALSE otherwise.
 */
protected function isLibraryOpen($current_time) {
    // Get the current date.
    $current_date = \Drupal::time()->getCurrentTime();
  
    // Convert the current date to the desired format.
    $current_date_formatted = \Drupal::service('date.formatter')->format($current_date, 'custom', 'd-m');
    
    // Get the library opening exceptions from the configuration.
    $config = \Drupal::config('opening.library_opening_exceptions');
    
    // Check if the current date is an exception.
    if (strpos($config->get('exceptions'), $current_date_formatted) !== false) {
        return false;
    }

    $exceptions = preg_split('/\s+/', $config->get('exceptions'));
    
    // Check if the current day is an exception.
    foreach ( $exceptions as $exception ) {
      if ( $exception == $current_date_formatted ) {
        return false;
      }
    }
  
    // Define the library regular opening hours.
    $opening_time_morning = '10:00';
    $closing_time_morning = '14:00';
    $opening_time_evening = '17:00';
    $closing_time_evening = '21:00';
  
    // Check if the current time falls within the regular opening hours.
    if (
      ($current_time >= $opening_time_morning && $current_time <= $closing_time_morning) ||
      ($current_time >= $opening_time_evening && $current_time <= $closing_time_evening)
    ) {
      return true;
    }
  
    return false;
  }
  

    
}