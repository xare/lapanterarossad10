<?php 

namespace Drupal\geslib\Command;

use Drush\Commands\DrushCommands;
use Consolidation\AnnotatedCommand\CommandResult;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\geslib\Api\GeslibApiDrupalManager;
use Drupal\geslib\Api\GeslibApiStoreData;

class GeslibStoreEditorialsCommand extends DrushCommands {
   private $logger_factory;
   public function __construct(LoggerChannelFactoryInterface $logger_factory){
      $this->logger_factory = $logger_factory;
   }
     /**
     * Takes editorials data from the geslib_lines and stores to taxonomies
     * @command geslib:storeEditorials
     * @alias gsse
     * @description Takes editorials data from the geslib_lines and stores to taxonomies
     * 
     */

     public function storeEditorials() {
        
        $geslibApiStoreData = new GeslibApiStoreData($this->logger_factory);
        $commandResults = $geslibApiStoreData->storeEditorials();
        $this->output()->writeln(dt( 'Editorials have been saved.'));
     }
}