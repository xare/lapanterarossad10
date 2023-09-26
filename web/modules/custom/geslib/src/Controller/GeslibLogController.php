<?php

namespace Drupal\geslib\Controller;

use Drupal\Core\Controller\ControllerBase;

class GeslibLogController extends ControllerBase {
    
    public function overview() {
        $header = [
            'id' => 'ID',
            'filename' => 'Filename',
            'start_date' => 'Start Date',
            'end_date' => 'End Date',
            'status' => 'Status',
            'lines_count' => 'Lines',
          ];
        $query = \Drupal::database()
                    ->select('geslib_log', 'g')
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
                'filename' => $row->filename,
                'start_date' => $row->start_date,
                'end_date' => $row->end_date,
                'status' => $row->status,
                'lines_count' => $row->lines_count,
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