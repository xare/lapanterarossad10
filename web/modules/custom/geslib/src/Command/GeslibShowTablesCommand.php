<?php 

namespace Drupal\geslib\Command;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Commands\DrushCommands;
use Consolidation\AnnotatedCommand\CommandResult;
use Drupal\geslib\Api\GeslibApiDrupalManager;

class GeslibShowTablesCommand extends DrushCommands {
    /**
     * Prints a the number of lines of geslib log and geslib lines
     * @command geslib:showTables
     * @alias gsde
     * @description Prints a the number of lines of geslib log and geslib lines
     * 
     */

     public function showTables() {
        $geslibApiDrupal = new GeslibApiDrupalManager();
        $tables = ['log', 'lines'];
        foreach ( $tables as $table ) {
            $this->output()
                    ->writeln(dt( '@table table contains @countRows lines.', 
                                [ '@table' => $table, 
                                '@countRows' => $geslibApiDrupal->countRows($table) ]));
        }
        return CommandResult::exitCode(0);
     }
}