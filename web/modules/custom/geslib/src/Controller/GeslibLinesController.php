<?php

namespace Drupal\geslib\Controller;

use Drupal\Core\Controller\ControllerBase;

class GeslibLinesController extends ControllerBase {
    
    public function overview(){
        
        $header = [
            'id' => 'ID',
            'id_log' => 'Log ID',
            'geslib_id' => 'Geslib ID',
            'action' => 'Acción',
            'entity' => 'Entidad',
            'status' => 'Contenido',
            'queued' => 'Queued',
          ];
        $query = \Drupal::database()
                    ->select('geslib_lines', 'g')
                    ->fields('g');  // Replace with the actual fields you want to select

        $table_sort = $query->extend('Drupal\Core\Database\Query\TableSortExtender')
                    ->orderByHeader($header);
        $pager = $table_sort->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                    ->limit(50);  // Number of records per page
        $result = $pager->execute();
        $rows = [];
        foreach ($result as $row) {
            $rows[] = [
                'id' => $row->id,
                'log_id' => $row->log_id,
                'geslib_id' => $row->geslib_id,
                'action' => $row->action,
                'entity' => $row->entity,
                'content' => $row->content,
                'queued' => $row->queued,
            ];
        }
        
        $form['table'] = [
            '#theme' => 'table',
            '#header' => $header,
            '#rows' => $rows,
            '#empty' => $this->t('No logs have been found'),
        ];
    
        $form['pager'] = [
            '#type' => 'pager',
        ];
    
        return $form;
    }
}