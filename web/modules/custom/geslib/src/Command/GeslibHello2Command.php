<?php

namespace Drupal\geslib\Command;

use Drush\Commands\DrushCommands;

/**
 * Defines a Drush command to print "Hello World".
 *
 * @DrushCommands()
 */
class GeslibHello2Command extends DrushCommands {

    /**
     * Prints "Hello World" on the terminal prompt.
     * @command geslib:hello2
     * @alias gsh2
     * @description Prints "Hello World" on the terminal prompt.
     *
     */

    public function hello() {
        $this->output()->writeln('Hello World');
    }
}