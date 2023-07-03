<?php 

namespace Drupal\rsvplist;

use Drupal\Core\Database\Connection;
use Drupal\node\Entity\Node;

class EnablerService {

    protected $database_connection;
    public function __construct(Connection $connection){
        $this->database_connection = $connection;
    }

    public function isEnabled(Node &$node) {
        if($node->isNew()){
            return FALSE;
        } 
        try {
            $results = $this->database_connection
                            ->select( 'rsvplist_enabled', 're' )
                            ->fields( 're', ['nid'] )
                            ->condition( 'nid', $node->id() )
                            ->execute();
            return !(empty($results->fetchCol()));
        } catch(\Exception $e) {
            \Drupal::messenger()->addError(
                t( 'Unable to determine RSVP settings at this time. Please try again') 
            );
            return NULL;
        }
    }

     public function setEnabled(Node $node) {
        try{
            if(!($this->isEnabled($node))) {
                $this->database_connection
                    ->insert('rsvplist_enabled')
                    ->fields(['nid'])
                    ->values([$node->id()])
                    ->execute();
            }
            \Drupal::messenger()->addError(
                t('The rspv form has been enabled for this form.')
            );
        } catch(\Exception $e) {
            \Drupal::messenger()->addError(
                t('Unable to save RSVP settings at this time. Please try again')
            );
        }
     }

     /**
      * Deletes RSVP enabled settings.
      * @param Node $node
      */
     public function deleteEnabled(Node $node) {
        try{
            $delete = $this->database_connection->delete('rsvplist_enabled');
            $delete->condition('nid',$node->id());
            $delete->execute();
            \Drupal::messenger()->addError(
                t('The rspv form has been disabled for this form.')
            );
        } catch(\Exception $e) {
            \Drupal::messenger()->addError(
                t('Unable to save RSVP settings at this time. Please try again')
            );
            return NULL;
        }
     }
}