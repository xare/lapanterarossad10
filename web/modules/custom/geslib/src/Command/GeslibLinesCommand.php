<?php 

namespace Drupal\geslib\Command;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\geslib\Api\GeslibApiDrupalManager;
use Drupal\geslib\Api\GeslibApiLines;
use Drush\Commands\DrushCommands;
use Drupal\geslib\Api\GeslibApiReadFiles;
use Drupal\geslib\Api\GeslibApiLog;

/**
 * Defines a Drush command to read the files/default/geslib folder and store the data in geslib_log.
 *
 * @DrushCommands()
 */
class GeslibLinesCommand extends DrushCommands {
    private $logger_factory;
    public function __construct( LoggerChannelFactoryInterface $logger_factory ) {
        $this->logger_factory = $logger_factory;
    }
   /*  private $geslibApiReadFiles;    
    private $drupal;
    public function __construct() {
        $this->geslibApiReadFiles = new GeslibApiReadFiles();
        $this->drupal = new GeslibApiDrupalManager();
    } */
    /**
     * Stores geslib file data to the database table geslib_log.
     * @command geslib:lines
     * @alias gsl
     * @description Stores geslib file data to the database table geslib_log.
     * 
     */

    public function lines() {
        $geslibApiLog = new GeslibApiLog($this->logger_factory);
        $geslibApiLines = new GeslibApiLines($this->logger_factory);
        $geslibApiDb = new GeslibApiDrupalManager($this->logger_factory);
        $this->output()->writeln($geslibApiLines->storeToLines());

        $this->output()->writeln('Geslib Lines: The data has been loaded to geslib_lines');
    }
}