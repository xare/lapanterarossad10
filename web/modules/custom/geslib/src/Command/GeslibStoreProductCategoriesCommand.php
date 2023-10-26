<?php

namespace Drupal\geslib\Command;

use Drush\Commands\DrushCommands;
use Consolidation\AnnotatedCommand\CommandResult;
use Drupal\geslib\Api\GeslibApiStoreData;

/**
 * GeslibStoreProductCategoriesCommand
 */
class GeslibStoreProductCategoriesCommand extends DrushCommands {

   /**
     * Takes Product categories data from the geslib_lines and stores to taxonomies
     * @command geslib:storeProductCategories
     * @alias gsspc
     * @description Takes Product categories data from the geslib_lines and stores to taxonomies
     *
     */

     /**
      * storeEditorials
      *
      * @return void
      */
     public function storeEditorials() {
        $geslibApiStoreData = new GeslibApiStoreData();
        $geslibApiStoreData->storeProductCategories();
        $this->output()->writeln(dt( 'Product categories have been saved.'));
     }
}