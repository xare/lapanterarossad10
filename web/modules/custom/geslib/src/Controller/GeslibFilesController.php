<?php

namespace Drupal\geslib\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\geslib\Api\GeslibApiReadFiles;

class GeslibFilesController extends ControllerBase {
    public function overview(){
        $geslibApiReadFiles = new GeslibApiReadFiles;
        $header = [
            'nombre-archivo'=> 'Nombre del archivo',
            'fecha-creacion' => 'Fecha de creación',
            'memoria' => 'Memoria',
            'numero-lineas' => 'Número de lineas',
            'GP4A' => 'GP4 A',
            'GP4M' => 'GP4 M',
            'GP4B' => 'GP4 B',
            '1LA' => '1L A',
            '1LM' => '1L M',
            '1LB' => '1L B',
            '3A' => '3 A',
            '3M' => '3 M',
            '3B' => '3 B',
        ];
        $geslibSettings = \Drupal::config('geslib.settings')->get('geslib_settings');
		$public_files_path = \Drupal::service('file_system')->realpath("public://");
        $mainFolderPath = $public_files_path . '/' . $geslibSettings['geslib_folder_name'].'/';
        $files = glob($mainFolderPath . 'INTER*');
        $rows = [];
        $limit = 10; // Items per page
        $total = count($files); // Total number of files
        $pager_manager = \Drupal::service('pager.manager');
        // Initialize the pager and set the current page
        $current_page = $pager_manager->createPager($total, $limit)->getCurrentPage();
        $offset = $current_page * $limit;
        // Fetch only the subset of files for the current page
        $paged_files = array_slice($files, $offset, $limit);

        foreach( $paged_files as $file ) {
            if( !isset( $file ) || $file === '' ) continue;
            // Get file modification time
            $modTime = filemtime($file);
            // Format the date and time
            $formattedModTime = date('d/m/Y H:i', $modTime);
            // Get file size and format it
            $formattedSize = $this->formatSize(filesize($file));

            $countLines = $geslibApiReadFiles->countLines($file);
            $lineCounts = $geslibApiReadFiles->countLinesWithGP4($file);

            $rows[] = [
                'nombre-archivo' => basename($file),
                'fecha-creacion' => $formattedModTime,
                'memoria' => $formattedSize,
                'numero-lineas' => $countLines,
                'GP4A' => $lineCounts['GP4A'],
                'GP4M' => $lineCounts['GP4M'],
                'GP4B' => $lineCounts['GP4B'],
                '1LA' => $lineCounts['1LA'],
                '1LM' => $lineCounts['1LM'],
                '1LB' => $lineCounts['1LB'],
                '3A' => $lineCounts['3A'],
                '3M' => $lineCounts['3M'],
                '3B' => $lineCounts['3B'],
            ];
        }

        $form['table'] = [
            '#theme' => 'table',
            '#header' => $header,
            '#rows' => $rows,
            '#empty' => $this->t('No logs have been found'),
        ];
        // Add the pager
        $form['pager'] = [
            '#type' => 'pager',
        ];
        return $form;
    }

    private function formatSize($bytes) {
        $types = array( 'B', 'KB', 'MB', 'GB', 'TB' );
        for($i = 0; $bytes >= 1024 && $i < (count($types) - 1); $bytes /= 1024, $i++);
        return( round($bytes, 2) . " " . $types[$i] );
    }
}