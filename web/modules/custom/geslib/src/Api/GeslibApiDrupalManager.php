<?php

namespace Drupal\geslib\Api;

use Drupal\geslib\Api\GeslibApiSanitize;

/**
 * GeslibApiDrupalManager
 */
class GeslibApiDrupalManager {
    const GESLIB_LINES_TABLE = 'geslib_lines';
    const GESLIB_LOG_TABLE = 'geslib_log';
    const GESLIB_QUEUES_TABLE = 'geslib_queues';

    static $geslibLinesKeys = [
		'log_id', // int relation oneToMany with geslib_log
		'geslib_id', // int
		'filename', // string inter000
		'action', // string insert|update|delete
		'entity', // string product | category | author | publisher
		'content', // json
		'queued' // boolean 0|1
	];
    static $geslibLogKeys = [
		'filename', // string inter000
		'start_date', // date
		'end_date', // date
		'lines_count', // int number of lines
		'status', // string waiting | enqueued | processed
	];

    /**
     * insertData
     * Called from
     * - GeslibApiLines::processGP4
     * - GeslibApiLines::process1L
     * - GeslibApiLines::process3
     * - GeslibApiLines::processAUT
     * - GeslibApiLines::processB
     *
     * @param array $content_array
     * @param string $action
     * @param int $log_id
     * @param string $entity
     * @return void
     */
    public function insertData(
        array $content_array,
        string $action,
        int $log_id,
        string $entity ): void {
		try {
            \Drupal::database()
                    ->insert(self::GESLIB_LINES_TABLE)
                    ->fields([
                            'log_id' => $log_id,
                            'geslib_id' => $content_array['geslib_id'],
                            'action' => $action,
                            'entity' => $entity,
                            'content' => json_encode($content_array),
                            'queued' => 1
                        ])
                    ->execute();
                \Drupal::logger('geslib_lines_insert_data')
                    ->info("Log_id: ".$log_id.". The " . $entity . " with geslib_id "
                            . $content_array['geslib_id'] . " and action:"
                            . $action . " data was successfully inserted to geslib lines");
        } catch(\Exception $e) {
            \Drupal::logger('geslib_lines_insert_data')
                    ->info("The ".$entity." data could not be inserted to geslib Lines "
                    .$e->getMessage());
        }
	}

	/**
	 * countRows
     * Count the number of rows in the geslib_log and geslib_lines tables
     * Called from GeslibShowTablesCommand
	 *
	 * @param  string $table
	 * @return int|FALSE
	 */
	public function countRows( $table ): int|FALSE {
        return \Drupal::database()
                ->select('geslib_' . $table, 't')
                ->countQuery()
                ->execute()
                ->fetchField();
	}
}