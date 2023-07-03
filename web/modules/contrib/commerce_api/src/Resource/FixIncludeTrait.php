<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Resource;

use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\Request;

trait FixIncludeTrait {

  /**
   * Fixes the includes parameter to ensure order_item.
   *
   * @todo determine if to remove, allow people to include if they want.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   */
  protected function fixOrderInclude(Request $request) {
    if (Settings::get('commerce_api_fix_order_includes', TRUE)) {
      $include = $request->query->get('include');
      $request->query->set('include', $include . (empty($include) ? '' : ',') . 'order_items,order_items.purchased_entity');
    }
  }

}
