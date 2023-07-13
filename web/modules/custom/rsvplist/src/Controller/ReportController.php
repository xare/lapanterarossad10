<?php 

namespace Drupal\rsvplist\Controller;

use Drupal\Core\Controller\ControllerBase;

class ReportController extends ControllerBase {

    protected function load() {
        try {
            /** @var \Drupal\Core\Database\Connection $database */
            $database = \Drupal::database();
            
            $select_query = $database->select( 'rsvplist', 'r' );
            $select_query->join( 'users_field_data', 'u', 'r.uid = u.uid' );
            $select_query->join( 'node_field_data', 'n', 'r.nid = n.nid' );
            $select_query->addField( 'u', 'name', 'username' );
            $select_query->addField( 'n', 'title' );
            $select_query->addField( 'r', 'email' );
            $entries = $select_query->execute()->fetchAll( \PDO::FETCH_ASSOC );
            return $entries;
        } catch(\Exception $e) {
            \Drupal::messenger()->addMessage( t( 'Unable to reach the database: @error',['@error'=>$e->getMessage()] ) );
            return NULL;
        }
    }

    public function report() {
        $content = [];
        $content['message'] = [
            '#markup' => t('Below is a list of all events RSVPs including username, email address, and the name of the event they will be attending.'),
        ];

        $headers = [
            t('Username'),
            t('Event'),
            t('Email')
        ];

        $table_rows = $this->load();

        $content['table'] = [
            '#type' => 'table',
            '#header' => $headers,
            '#rows' => $table_rows,
            '#empty' => t('No entries available'),
        ];

        $content['#cache']['max_age'] = 0; //Cache inmediately invalidated and the report is updated in 0 seconds.

        return $content;
    }
}