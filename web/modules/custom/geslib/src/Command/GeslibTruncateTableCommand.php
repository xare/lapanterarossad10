<?php 

namespace Drupal\geslib\Command;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandResult;
use Drush\Commands\DrushCommands;

/**
 * Defines a Drush command to empty the tables geslib_log or geslib_lines.
 *
 * @DrushCommands()
 */
class GeslibTruncateTableCommand extends DrushCommands {
    
    /**
     * Defines a Drush command to empty the tables geslib_log or geslib_lines.
     * @command geslib:truncateTable
     * @alias gstt
     * @description Defines a Drush command to empty the tables geslib_log or geslib_lines.
     * 
     */

    public function truncateTable($table_suffix) {
        $database = \Drupal::database();
        $table_name = 'geslib_'.$table_suffix;
        try {
            $database->truncate( $table_name )->execute();
            $this->output()->writeln(dt('@table has been emptied.', [ '@table' => $table_name ]));
        } catch(\Exception $e) {
            $this->output()->writeln(dt('Could not truncate table @table. Make sure the table name is correct.', [ '@table' => $table_name ]));
        }
        return CommandResult::exitCode(0);
        
    }
}