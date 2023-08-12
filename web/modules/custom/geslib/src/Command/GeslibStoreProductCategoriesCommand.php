<?php 

namespace Drupal\geslib\Command;

use Drush\Commands\DrushCommands;
use Consolidation\AnnotatedCommand\CommandResult;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\geslib\Api\GeslibApiDrupalManager;
use Drupal\geslib\Api\GeslibApiStoreData;

/**
 * GeslibStoreProductCategoriesCommand
 */
class GeslibStoreProductCategoriesCommand extends DrushCommands {   
   /**
    * logger_factory
    *
    * @var mixed
    */
   private $logger_factory;   
   /**
    * __construct
    *
    * @param  mixed $logger_factory
    * @return void
    */
   public function __construct(LoggerChannelFactoryInterface $logger_factory){
      $this->logger_factory = $logger_factory;
   }
   
   /**
     * Takes Product categories data from the geslib_lines and stores to taxonomies
     * @command geslib:storeProductCategories
     * @alias gsse
     * @description Takes Product categories data from the geslib_lines and stores to taxonomies
     * 
     */
     
     /**
      * storeEditorials
      *
      * @return void
      */
     public function storeEditorials() {
        $geslibApiStoreData = new GeslibApiStoreData($this->logger_factory);
        $geslibApiStoreData->storeProductCategories();
        $this->output()->writeln(dt( 'Product categories have been saved.'));
     }
}