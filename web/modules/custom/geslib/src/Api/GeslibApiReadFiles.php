<?php

namespace Drupal\geslib\Api;

use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\geslib\Api\GeslibApiDrupalManager;
use ZipArchive;

//

/**
 * @package GeslibApiReadFiles
 * This class contains all the functions necessary to read the contents in the geslib folder
 * and store them to the logs table.
 *
 *
 */

class GeslibApiReadFiles {

	private string $mainFolderPath;
    private string $histoFolderPath;
	private array $geslibSettings;

    /**
     * __construct
     * - Cast the configuration to an array.
	 * - Retrieve the real path of the public files directory and ensure it's a string.
	 * - Construct the main folder path. Consider checking if 'geslib_folder_name' exists in the settings.
	 * - Append 'HISTO/' to the mainFolderPath.
	 *
     * @return void
     */
    public function __construct( ) {

		$this->geslibSettings = (array) \Drupal::config('geslib.settings')->get('geslib_settings');
		$public_files_path = (string) \Drupal::service('file_system')->realpath("public://");
        $this->mainFolderPath = (string) $public_files_path . '/' . $this->geslibSettings['geslib_folder_name'].'/';
        $this->histoFolderPath = (string) $this->mainFolderPath . 'HISTO/';
    }

	/**
	 * readFolder
	 * - Create a zip folder if missing
	 * - If no files present then finish and return false
	 * - Loop each files, if it´s a zip unzip and move the .zip file to a zip folder
	 *
	 * @return array|false
	 */
	public function readFolder(): array|false {
		$files = (array) glob($this->mainFolderPath . 'INTER*');
		$zipFolder = (string) $this->mainFolderPath . 'zip/';
		// create a zip folder if missing
		if ( !is_dir( $zipFolder ) ) mkdir( $zipFolder, 0755, true);
		if ( count($files) == 0 ) return false;
		$filenames = [];
		/** @var string $file */
		foreach( $files as $file) {
			if ( !is_file($file) ) continue;
			/**
			 * @var array $fileInfo
			 * $fileInfo is an array{ dirname:string, basename:string, extension:string, filename:string }
			 */
			$fileInfo = (array) pathinfo( $file );
			if ( isset( $fileInfo['extension'] )) {
				\Drupal::logger('geslib_files')->info('Descomprimiendo archivo ZIP; '.$fileInfo['basename']);
				$zip = new ZipArchive();
				if ( $zip->open( $file ) ) {
					// Extract the files to the mainFolderPath
					$zip->extractTo( $this->mainFolderPath );
					// Insert into geslib_log if not already
					$zip->close();
					$newLocation = (string) $zipFolder . $fileInfo['basename'];
					try {
						\Drupal::logger('geslib_files')->info($fileInfo['basename']. ' Guardada en  '.$zipFolder);
						//renames from geslib/INTER***.zip to geslib/zip/INTER***
						(bool) rename($file, $newLocation);
					} catch(\Exception $exception) {
						\Drupal::logger('geslib_files')->error("Error while copying the file to zip folder: ".$exception->getMessage());
					}
				}
			}
			// Check if the filename already exists in the database
			$filenames[] = $fileInfo['filename'];
			// Inserto into geslib_log with status logged.
			$this->insert2geslibLog($fileInfo['filename']);
		}
		return $filenames;
	}

	/**
	 * insert2geslibLog
	 * Inserts a file info into geslib_log for the first time status="logged"
	 *
	 * @param  string $filename
	 * @return void
	 */
	public function insert2geslibLog( string $filename ): void {
		$geslibApiDrupalLogManager = new GeslibApiDrupalLogManager;
		\Drupal::logger('geslib_log')->info('Checking for '.$filename.' status.');
		if ( !$geslibApiDrupalLogManager->isFilenameExists( basename( $filename ))) {
			\Drupal::logger('geslib_log')->info('Filename is not in the database.');
			$geslibApiDrupalLogManager->insertLogData( basename($filename ), 'logged', count( file( $this->mainFolderPath .$filename )));
		}
	}

	/**
	 * List files in the specified folder and retrieve their statuses.
	 *
	 * @return array|false The list of files with their statuses and modification times
	 */
	public function listFilesInFolder(): array|false {

    	$results = [];
		// Fetch filenames and statuses from the geslib_log table
    	$geslibApiDrupalManager = new GeslibApiDrupalManager;
		$geslibApiDrupalLogManager = new GeslibApiDrupalLogManager;
		$logFiles = (array) $geslibApiDrupalLogManager->fetchLoggedFilesFromDb();

		if ( !is_dir($this->mainFolderPath )) return false;
		if ( $dh = opendir( $this->mainFolderPath ) ) {
			while ( ( $currentFile = readdir( $dh ) )) {
				if (in_array($currentFile, ['.', '..'])) continue;

				// Check the presence of this file in the geslib_log database table
				$filenames = array_column(  $geslibApiDrupalLogManager->fetchLoggedFilesFromDb(), 'filename' );

				$results[] = [
					'filename' => $currentFile,
					'status' => in_array( $currentFile, $filenames ) ? 'logged' : 'queued',
					'fileTime' => filemtime($this->mainFolderPath . '/' . $currentFile) // Add the file time to each element
				];
			}
			closedir($dh);
		}

		// Sort the array by the fileTime key
		usort($results, function($a, $b) {
			return $a['fileTime'] <=> $b['fileTime']; // Sort in descending order
		});
		return $results;
	}


	/**
	 * countLines
	 * Count the number of lines in one file.
	 * - called from GeslibFilesController
	 *
	 * @param  string $filename
	 * @return int|false
	 */
	public function countLines( string $filename ): int|false {
		if( !file_exists( $filename ) ) return false;
		return (int) count( file( $filename ) );
	}

	/**
	 * countFilesInFolder
	 * Count the number of files in a folder
	 * Called from StoreProductsForm.php
	 *
	 * @return int
	 */
	public function countFilesInFolder(): int {
		return (int) count($this->listfilesInFolder());
	}

	/**
	 * countLinesWithGP4
	 * Used to show the "files" table in the admin interface.
	 * - called from GeslibFilesController
	 *
	 * @param  string $filename
	 * @param  string $type
	 * @return array|false
	 */
	public function countLinesWithGP4( string $filename, string $type='product'): array|false {
		// Check if the file exists
		$codes  = ['GP4', '1L','3'];
		if ( !file_exists( $filename ) ) {
			return false; // Return false if file not found
		}

		// Initialize the countsArray to 0
		$countsArray = [
			'total' => 0,
			'GP4A' => 0,
			'GP4M' => 0,
			'GP4B' => 0,
			'1LA' => 0,
			'1LM' => 0,
			'1LB' => 0,
			'3A' => 0,
			'3M' => 0,
			'3B' => 0,
		];

		$handle = fopen($filename, "r"); // Open the file for reading
		// Read line by line
		while (( $line = fgets( $handle )) ) {
			// Check if the line starts with "GP4"
			$lineArray = explode('|',$line);
			if (in_array($lineArray[0], $codes)) {
				$countsArray[ 'total' ]++; // Increment total GP4 lines count
				if ( count( $lineArray ) > 1 ) {
					if (in_array($lineArray[1], ['A', 'M', 'B'])) {
						// i.e.: $countArray['GP4A']
						$countsArray[$lineArray[0] . $lineArray[1]]++;
					}
				}
			}
		}
		fclose($handle); // Close the file handle
		return $countsArray; // Return the counts
	}
}