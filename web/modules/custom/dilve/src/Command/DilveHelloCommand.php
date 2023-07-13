<?php 

namespace Drupal\dilve\Command;

use Drush\Commands\DrushCommands;

/**
 * Defines a Drush command to print "Hello World".
 *
 * @DrushCommands()
 */
class DilveHelloCommand extends DrushCommands {
    
    /**
     * Prints "Hello World" on the terminal prompt.
     * @command dilve:hello
     * @alias dlh
     * @description Prints "Hello World" on the terminal prompt.
     * 
     */

    public function hello() {
        $this->output()->writeln('Hello World');
    }
}