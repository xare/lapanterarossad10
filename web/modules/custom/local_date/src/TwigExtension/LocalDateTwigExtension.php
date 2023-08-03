<?php

namespace Drupal\local_date\TwigExtension;

use Drupal\Core\Datetime\DrupalDateTime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class LocalDateTwigExtension extends AbstractExtension {

  public function getFilters() {
    return [
      new TwigFilter('format_date_custom', [$this, 'formatDateCustom']),
    ];
  }

  public function formatDateCustom($date, $format) {
    \Drupal::logger('local_date')->notice('Input date: @date', ['@date' => $date]);
    if (null === $date || $date == '' ){
      $date = date('Y-m-d\TH:i:s');
    }  
    $date = DrupalDateTime::createFromFormat('Y-m-d\TH:i:s', $date);
    if ($date) {
        return \Drupal::service('date.formatter')->format($date->getTimestamp(), 'custom', $format);
    } else {
      \Drupal::logger('local_date')->warning('Invalid date format: @date', ['@date' => $date]);
      return 'Invalid date format';  // You can handle invalid format case as per your need
    }
  }
}
