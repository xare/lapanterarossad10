<?php 

namespace Drupal\geslib\Command;

use Drush\Commands\DrushCommands;

/**
 * Defines a Drush command to print "Hello World".
 *
 * @DrushCommands()
 */
class GeslibHelloCommand extends DrushCommands {
    
    /**
     * Prints "Hello World" on the terminal prompt.
     * @command geslib:hello
     * @alias gsh
     * @description Prints "Hello World" on the terminal prompt.
     * 
     */

    public function hello() {
        $this->output()->writeln('Hello World');
    }
}