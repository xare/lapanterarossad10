<?php

namespace Drupal\geslib\Api;

use PDO;

/**
 * GeslibApiDrupalLogManager
 */

class GeslibApiDrupalLogManager extends GeslibApiDrupalManager
{
    /**
     * insertLogData
     * Insert to geslib_log data
     *
     * @param  string $filename
     * @param  string $status
     * @param  int $linesCount
     * @return bool
     */
    public function insertLogData( string $filename, string $status, int $linesCount ): bool {
        if( $this->isFilenameExists( $filename ) ) {
            \Drupal::logger('geslib_log')->warning("This file is already in the geslib_log table.");
            return TRUE;
        } else {
            $geslibLogValues = [
                $filename,
                date('Y-m-d H:i:s'),
                null,
                $linesCount,
                $status
            ];
            try {
                \Drupal::database()->insert( self::GESLIB_LOG_TABLE )
                        ->fields( array_combine( self::$geslibLogKeys, $geslibLogValues ) )
                        ->execute();
                \Drupal::logger('geslib_log')->info("This file has been inserted to the geslib_log table.");
                return TRUE;
            } catch(\Exception $e) {
                \Drupal::logger('geslib_log')->error("This file has not been properly inserted into the database due to an error: ".$e->getMessage());
                return FALSE;
            }
        }
	}
    /**
     * isFilenameExists
     * Check if the filename exists in the wpgeslib_log table.
     * - called from GeslibApiReadFiles
     * - called from GeslibApiDrupalLogManager
     *
     * @param string $filename
     * @return bool
     */
    public function isFilenameExists( string $filename ): bool {
        $query = \Drupal::database()
                    ->select(self::GESLIB_LOG_TABLE, 't');
        $query->fields('t', ['id'] );
        $query->condition( 'filename', $filename, '=' );
        $query->addExpression('COUNT(*)');
        return (bool) $query->countQuery()->execute()->fetchfield() > 0;
    }
    /**
     * getLogLoggedFile
     * - called from geslibApiDrupalLogManager
     *
     * @return string
     */
    public function getLogLoggedFile(): string {
		return (string) \Drupal::database()
                    ->select(self::GESLIB_LOG_TABLE, 'glog')
                    ->fields('glog',['filename'])
                    ->condition('status', 'logged', '=')
                    ->range(0,1)
                    ->execute()
                    ->fetchField();
	}

    /**
     * getLogLoggedId
     * - called from geslibApiDrupalLogManager
     * - called from storeProductsForm
     *
     * @return int
     */
    public function getLogLoggedId(): int {
		return \Drupal::database()
                    ->select(self::GESLIB_LOG_TABLE, 'glog')
                    ->fields('glog',['id'])
                    ->condition('status', 'logged', '=')
                    ->range(0,1)
                    ->execute()
                    ->fetchField();
	}
    /**
     * getLogQueuedFilename
     * - called from GeslibApiLines
     * - called from GeslibAjaxStatistics
     * - called from StoreProductsForm
     *
     * @return string
     */
    public function getLogQueuedFilename(): string {
		$response = \Drupal::database()
                    ->select(self::GESLIB_LOG_TABLE, 'glog')
                    ->fields('glog',['filename'])
                    ->condition('status', 'queued', '=')
                    ->range(0,1)
                    ->execute()
                    ->fetchField();
        return (string) ($response == null || $response == '' ) ? 'No File': $response;
	}

    /**
     * isQueued
     * Called from:
     * - GeslibApiDrupalLogManager
     * - StoreProductsForm
     * - geslib.module
     * - GeslibProcessAllCommand
     *
     * @return bool
     */
    public function isQueued(): bool {
        return (bool) \Drupal::database()
            ->select(self::GESLIB_LOG_TABLE, 'glog')
            ->fields('glog',['id'])
            ->condition('status', 'queued', '=')
            ->range(0,1)
            ->execute()
            ->fetchField();
    }

    /**
     * getLogId
     * - called from GeslibApiLines
     * @param  string $filename
     * @return int
     */
    public function getLogId( string $filename ): int {
        return (int) \Drupal::database()
                    ->select(self::GESLIB_LOG_TABLE, 't')
                    ->fields('t',['id'])
                    ->condition('t.filename', $filename, '=')
                    ->execute()
                    ->fetchField();
	}
    /**
     * Updates the status of a log entry.
     * Called from:
     * - geslib.module
     * - GeslibProcessAllCommand
     * - GeslibApiDrupalLogManager
     * - StoreProductsForm
     * - GeslibProcessFileCommand
     *
     * @param int $log_id
     * @param string $status
     * @return bool
     */
    public function setLogStatus(int $log_id, string $status): bool {
        $fields = ['status' => $status];
        if( $status == 'processed' ) $fields['end_date'] = date('Y-m-d H:i:s');
        try {
            \Drupal::logger('geslib_log')->info( 'Geslib_log entry was updated. Log Id: '. $log_id );
            return (bool) \Drupal::database()->update( self::GESLIB_LOG_TABLE )
                ->fields( $fields )
                ->condition( 'id', $log_id, '=' )
                ->execute();
        } catch (\Exception $exception) {
            \Drupal::logger('geslib_log')->error('Unable to update the row. Message: @message', ['@message' => $exception->getMessage()]);
            return FALSE;
        }
    }

    /**
     * fetchLoggedFilesFromDb
     * Called from:
     * - GeslibApiDrupalLogManager
     * - GeslibApiReadFiles
     *
     * @return array
     */
    public function fetchLoggedFilesFromDb(): array {
		return (array) \Drupal::database()
                    ->select( self::GESLIB_LOG_TABLE, 'gl' )
                    ->fields('gl', ['filename', 'status'])
                    ->execute()
                    ->fetchAll(PDO::FETCH_ASSOC);
	}

    /**
     * countGeslibLog
     * Called from:
     * - GeslibApiDrupalLogManager
     * - GeslibAjaxStatistics
     * - StoreProductsForm
     *
     * @return int
     */
    public function countGeslibLog(): int {
        $query = \Drupal::database()->select(self::GESLIB_LOG_TABLE, 'gl');
        $query->addExpression('COUNT(*)');
        return (int) $query->execute()->fetchField();
    }
    /**
     * checkLoggedStatus
     * Checks if there is at least one "logged" status in geslib_log table.
     * Called from:
     * - GeslibApiDrupalLogManager
     * - StoreProductsForm
     * - geslib.module
     * - GeslibProcessAllCommand
     *
     * @return bool
     *   Returns true if there is at least one row with status "logged",
     *   false otherwise.
     */
    function checkLoggedStatus(): bool {
        // Build the query to check for at least one row with status "logged".
        return (bool)  \Drupal::database()->select(self::GESLIB_LOG_TABLE, 'gl')
                            ->fields('gl', ['status'])
                            ->condition('status', 'logged')
                            ->orderBy('id', 'ASC')
                            ->range(0, 1)
                            ->execute()
                            ->fetchField();
    }

    /**
	 * countGeslibLogStatus
     * Called by:
     * - GeslibApiDrupalLogManager
     * - GeslibAjaxStatistics
     * - StoreProductsForm
	 *
	 * @param  string $status
	 * @return int
	 */
	public function countGeslibLogStatus( string $status ): int {
        return (int) \Drupal::database()->select(self::GESLIB_LOG_TABLE, 'gl')
            ->fields('gl', ['status'])
            ->condition('status', $status)
            ->countQuery()->execute()->fetchField();
	}

    /**
	 * setLogTableToLogged
	 * Sets the status of all rows in the geslib_log table to "logged".
     * Called by:
     * - StoreProductsForm
	 *
	 * @return bool
	 */
	public function setLogTableToLogged(): bool {
		try {
			return (bool) \Drupal::database()->update(self::GESLIB_LOG_TABLE)
                       ->fields(['status' => 'logged'])
                       ->execute();
		} catch (\Exception $exception) {
			\Drupal::logger('geslib_log')
                    ->error('Could not change '.self::GESLIB_LOG_TABLE
                            .' to logged: '.$exception->getMessage() );
			return false;
		}
	}

    /**
     * getFilename
     * Called by:
     * - GeslibApiLines
     *
     * @param  int $log_id
     * @return string
     */
    public function getFilename( int $log_id ): string {
        return (string) \Drupal::database()
                ->select(self::GESLIB_LOG_TABLE, 'gl')
                ->fields('gl',['filename'])
                ->condition('id',$log_id)
                ->execute()
                ->fetchField();
    }

}