<?php

namespace Drupal\geslib\Command;

use Consolidation\AnnotatedCommand\CommandResult;
use Drupal\taxonomy\Entity\Term;
use Drush\Commands\DrushCommands;

/**
 * Defines a Drush command to delete Editorials defined as taxonomies.
 *
 * @DrushCommands()
 */

class GeslibDeleteTaxonomyCommand extends DrushCommands {

    /**
     * Defines a Drush command to fully delete the terms of a given taxonomy.
     * @command geslib:deleteTaxonomy
     * @alias gsde
     * @description Defines a Drush command to fully delete the terms of a given taxonomy.
     * 
     */

    public function deleteTaxonomy($taxonomy_name) {
        $terms = \Drupal::entityTypeManager()
                    ->getStorage('taxonomy_term')
                    ->loadTree($taxonomy_name, 0, NULL, TRUE);

        if (empty($terms)) {
            $this->output()->writeln(dt('No terms found in the @taxonomy taxonomy.', ['@taxonomy' => $taxonomy_name]));
            return CommandResult::exitCode(0);
        }

        foreach ($terms as $term) {
            $term = Term::load($term->tid);
            $term->delete();
        }

        $this->output()->writeln(dt('All terms in the @taxonomy taxonomy have been deleted.', ['@taxonomy' => $taxonomy_name]));

        return CommandResult::exitCode(0);
    }

}