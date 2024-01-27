<?php

namespace Drupal\geslib\Api;

use Drupal\taxonomy\Entity\Term;
use Drupal\geslib\Api\GeslibApiSanitize;

/**
 * GeslibApiDrupalTaxonomyManager
 */

class GeslibApiDrupalTaxonomyManager extends GeslibApiDrupalManager
{

    public function storeTerm( $content, $vocabulary, $sanitize = true ): mixed {
        $geslibApiSanitize = new GeslibApiSanitize;
        \Drupal::logger( 'geslib_store_term' )->info( 'Storing term: ' . $vocabulary );
        $content = is_string($content) ? json_decode($content, true) : $content;
        $term_name = $sanitize ? $geslibApiSanitize->utf8_encode($content['name']) : $content['name'];
        $term_description = $content['description'] ?? $term_name;
        $geslib_id = $content['geslib_id'];

        // Check if the term already exists
        $query = \Drupal::entityQuery('taxonomy_term');
        $query->condition('name', $term_name)
              ->condition('vid', $vocabulary)
              ->accessCheck(FALSE);
        $tids = $query->execute();

        $term = !empty($tids) ? \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load(reset($tids)) : null;

        if (!$term) {
            // Create a new term
            $term = \Drupal\taxonomy\Entity\Term::create([
                'name' => $term_name,
                'vid' => $vocabulary,
                'description' => [
                    'value' => $term_description,
                    'format' => 'basic_html',
                ],
                'geslib_id' => $geslib_id,
            ]);
        } else {
            // Update existing term
            $term->setDescription($term_description);
            $term->set('geslib_id', $geslib_id);
        }

        // Save and validate the term
        try{
            $term->save();
            \Drupal::logger('geslib_store_term')->error('Term was saved'. $term->id().'Term name'. $term_name);
        }catch (\Exception $exception) {
            \Drupal::logger('geslib_store_term')->error('Unable to save the term'. $exception->getMessage());
        }
        $violations = $term->validate();
        if ($violations) {
            foreach ($violations as $violation) {
                \Drupal::messenger()->addError($violation->getMessage());
                \Drupal::logger('geslib_store_term')->error($violation->getMessage());
            }
            return $violations;
        }

        return $term;
    }


    public function deleteEditorials(): void {
        $this->deleteTaxonomy('editorials');
    }
    public function deleteProductCategories(): void {
        $this->deleteTaxonomy('product_categories');
    }
    /**
     * deleteTaxonomy
     *
     * @param  mixed $taxonomy_name
     * @return void
     */
    private function deleteTaxonomy( $taxonomy_name ): void {
        $terms = \Drupal::entityTypeManager()
                    ->getStorage('taxonomy_term')
                    ->loadTree($taxonomy_name, 0, NULL, TRUE);

        if (empty($terms)) {
            \Drupal::messenger()->addMessage('Terms are already deleted.');
            \Drupal::logger('geslib')->error('Terms are already deleted.');
        }

        foreach ($terms as $term) {
            \Drupal::messenger()->addMessage('Term Deleted: '.$term->id.' - '.$term->title);
            \Drupal::logger('geslib')->error('Term Deleted: '.$term->id.' - '.$term->title);
            $term->delete();
        }

        \Drupal::messenger()->addMessage('All terms in the '.$taxonomy_name.' taxonomy have been deleted.');
        \Drupal::logger('geslib')->error('All terms in the '.$taxonomy_name.' taxonomy have been deleted.');
    }
}