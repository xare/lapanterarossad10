<?php 
namespace Drupal\rewrite_url\TwigExtension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class CustomFilters extends AbstractExtension {

  /**
   * Gets a list of filters to add to the existing list.
   *
   * @return array
   */
  public function getFilters() {
    return [
      new TwigFilter('clean_url', [$this, 'cleanUrl']),
    ];
  }

  /**
   * Replaces spaces, slashes, and dashes with underscores in a string.
   *
   * @param string $string
   *   The input string.
   *
   * @return string
   *   The cleaned string.
   */
  public function cleanUrl($string) {
    return preg_replace('/[\/\-\s]+/', '-', $string);
  }
}
