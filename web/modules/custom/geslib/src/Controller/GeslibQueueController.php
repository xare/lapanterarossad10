<?php

namespace Drupal\geslib\Controller;

use Drupal\Core\Controller\ControllerBase;


class GeslibQueueController extends ControllerBase {

    public function overview(){
        $header = [
            'id' => 'ID',
            'log_id' => 'Log ID',
            'geslib_id' => 'Geslib ID',
            'type' => 'Tipo',
            'action' => 'Accion',
            'data' => 'Datos',
          ];
        $query = \Drupal::database()
                    ->select('geslib_queues', 'gq')
                    ->fields('gq');  // Replace with the actual fields you want to select

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
                'type' => $row->type,
                'action' => $row->action,
                'data' => $row->data,
            ];
        }

        $form['table'] = [
            '#theme' => 'table',
            '#header' => $header,
            '#rows' => $rows,
            '#empty' => $this->t('No queues have been found'),
        ];

        $form['pager'] = [
            '#type' => 'pager',
        ];

        return $form;
    }
}
