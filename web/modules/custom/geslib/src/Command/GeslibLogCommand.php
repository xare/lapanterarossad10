<?php 

namespace Drupal\geslib\Command;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\geslib\Api\GeslibApiDrupalManager;
use Drush\Commands\DrushCommands;
use Drupal\geslib\Api\GeslibApiReadFiles;

/**
 * Defines a Drush command to read the files/default/geslib folder and store the data in geslib_log.
 *
 * @DrushCommands()
 */
/**
 * GeslibLogCommand
 */
class GeslibLogCommand extends DrushCommands {    
    /**
     * geslibApiReadFiles
     *
     * @var mixed
     */
    private $geslibApiReadFiles;    
    private $drupal;
    private $logger_factory;
    
    /**
     * __construct
     *
     * @param  mixed $logger_factory
     * @return void
     */
    public function __construct( LoggerChannelFactoryInterface $logger_factory ) {
        $this->logger_factory = $logger_factory;
        $this->geslibApiReadFiles = new GeslibApiReadFiles( $this->logger_factory );
        $this->drupal = new GeslibApiDrupalManager( $this->logger_factory );
    }
    /**
     * Stores geslib file data to the database table geslib_log.
     * @command geslib:log
     * @alias gsl
     * @description Stores geslib file data to the database table geslib_log.
     * 
     */
    
    /**
     * log
     *
     * @return void
     */
    public function log() {
        $response = $this->geslibApiReadFiles->readFolder();
        if ( false === $response) $this->output()->writeln('Geslib Log ERROR: No files in the folder');
        else $this->output()->writeln('Geslib Log: The data has been loaded to geslib_log');
    }
}