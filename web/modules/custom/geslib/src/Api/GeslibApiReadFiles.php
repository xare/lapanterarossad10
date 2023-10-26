<?php

namespace Drupal\geslib\Api;

use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\geslib\Api\GeslibApiDrupalManager;
use ZipArchive;

// This file contains all the functions necessary to read the contents in the geslib folder and store them to the logs table

/**
 * GeslibApiReadFiles
 */
class GeslibApiReadFiles {
	/**
	 * mainFolderPath
	 *
	 * @var mixed
	 */
	private $mainFolderPath;
    /**
     * histoFolderPath
     *
     * @var mixed
     */
    private $histoFolderPath;
	/**
	 * geslibSettings
	 *
	 * @var mixed
	 */
	private $geslibSettings;
    /**
     * drupal
     *
     * @var mixed
     */
    private $drupal;
	/**
	 * logger_factory
	 *
	 * @var mixed
	 */
	private $logger_factory;
    /**
     * __construct
     *
     * @param  mixed $logger_factory
     * @return void
     */
    public function __construct( ) {
		$this->geslibSettings = \Drupal::config('geslib.settings')->get('geslib_settings');
		$public_files_path = \Drupal::service('file_system')->realpath("public://");
        $this->mainFolderPath = $public_files_path . '/' . $this->geslibSettings['geslib_folder_name'].'/';
        $this->histoFolderPath = $this->mainFolderPath . 'HISTO/';
    }

	/**
	 * readFolder
	 *
	 * @return mixed
	 */
	public function readFolder():mixed {
		//echo $this->mainFolderPath;
		$files = glob($this->mainFolderPath . 'INTER*');

		$geslibApiDbManager = new GeslibApiDrupalManager();
		if ( count($files) == 0 ) {
			return false;
		} else {
			foreach($files as $file) {
				if (is_file($file)) {
					$filename = basename($file);
					$linesCount = count(file($file));
					// Check if the filename already exists in the database
					if ( !$geslibApiDbManager->isFilenameExists( $filename ) ) {
						$item = [
							'filename' => $filename,
							'status' => 'logged',
							'linesCount' => $linesCount,
						];
						\Drupal::queue('geslib_store_geslib_log')->createItem($item);
					}
				}
			}
			return true;
		}
		$this->processZipFiles();
	}


	public function listFilesInFolder() {
		$dir = $this->mainFolderPath; // Replace with the actual path to the geslib folder

    	$result = [];
		// Fetch filenames and statuses from the geslib_log table
    	$geslibApiDrupalManager = new GeslibApiDrupalManager;
		$logFiles = $geslibApiDrupalManager->fetchLoggedFilesFromDb();
    	$logFileNames = array_column((array) $logFiles, 'filename');

		if (is_dir($dir)) {
			if ( $dh = opendir( $dir ) ) {
				while ( ( $file = readdir( $dh ) ) !== false) {
					// Skip '.' and '..'
					if ( $file === "." || $file === ".." ) {
						continue;
					}

					// Here, you should check the presence of this file in the geslib_log database table
					// For demonstration, I'm using a mock database array $geslib_log
					$geslibApiDbManager = new GeslibApiDrupalManager();
					$geslib_log = $geslibApiDbManager->fetchLoggedFilesFromDb();
					$filenames = array_column($geslib_log, 'filename');
					$status = in_array( $file, $filenames ) ? 'logged' : 'waiting';

					$filePath = $dir . '/' . $file; // Construct the full file path
					$fileTime = filemtime($filePath); // Get the file modification time

					$result[] = [
						'filename' => $file,
						'status' => $status,
						'fileTime' => $fileTime // Add the file time to each element
					];
				}
				closedir($dh);
			}
		}
		// Sort the array by the fileTime key
		usort($result, function($a, $b) {
			return $a['fileTime'] <=> $b['fileTime']; // Sort in descending order
		});
		return $result;
	}

	/**
     * processZipFiles
     * Process ZIP files in the HISTO folder: uncompress, read, compress, and insert data into the database.
     * @return void
     */
    public function processZipFiles():bool {
        // Check if the "HISTO" folder exists
        if (is_dir($this->histoFolderPath)) {
            // Get all ZIP files in the "HISTO" folder
            $zipFiles = glob($this->histoFolderPath . 'INTER*.zip');
            // Iterate through each ZIP file
            foreach ($zipFiles as $zipFile) {
                $this->processZipFile($zipFile);
            }
			return true;
        } else {
			return false;
		}
    }

	/**
	 * processZipFile
     * Process a ZIP file: uncompress, read its contents, compress again, and insert data into the database.
     *
     * @param string $zipFilePath Path to the ZIP file.
     * @return void
     */
    private function processZipFile( string $zipFilePath ) {
        // Uncompress the ZIP file to a temporary directory
        $tempDir = tempnam(sys_get_temp_dir(), '');
        $zip = new ZipArchive();
        if ($zip->open($zipFilePath) === true) {
            $zip->extractTo($tempDir);
            $zip->close();
        }

        // Get the name of the uncompressed file
        $uncompressedFileName = basename($zipFilePath, '.zip');

        // Read the contents of the uncompressed file
        $uncompressedFilePath = $tempDir . '/' . $uncompressedFileName;
        $lines = file($uncompressedFilePath);
        $linesCount = count($lines);

        // Compress the file again and overwrite the original ZIP file
        $newZipPath = $this->histoFolderPath . $uncompressedFileName . '.zip';
        $newZip = new ZipArchive();
        if ($newZip->open($newZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            foreach ($lines as $line) {
                $newZip->addFromString($uncompressedFileName, $line);
            }
            $newZip->close();
        }
		// Remove the temporary directory
		if (is_dir($tempDir)) {
			$filesToRemove = glob($tempDir . '/*');
			foreach ($filesToRemove as $fileToRemove) {
				if (is_file($fileToRemove)) {
					unlink($fileToRemove);
				}
			}
			rmdir($tempDir);

		}

		// Insert data into the database table for the compressed file
		$startDate = date('Y-m-d H:i:s');

		// Check if the filename already exists in the database
		if (!$this->drupal->isFilenameExists($uncompressedFileName)) {
			// Insert data into the database table
			$this->drupal->insertLogData($uncompressedFileName, 'logged', $linesCount);
		}
		return true;
	}


	/* private function insertLogData($filename, $line_count) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'geslib_log';

		$result = $wpdb->insert(
			$table_name,
			array(
				'start_date' => current_time('mysql', 1),
				'imported_file' => $filename,
				'processed_lines' => $this->countLines($filename),
				'status' => 'logged'
			),
			array(
				'%s', // for start_date (formatted as a string)
				'%s', // for imported_file
				'%d', // for processed_lines
			)
		);

		if (false === $result) {
			error_log('Insert failed: ' . $wpdb->last_error);
		}
	} */

	/**
	 * countLines
	 *
	 * @param  mixed $filename
	 * @return int|false
	 */
	public function countLines( string $filename ):mixed{
		// Check if the file exists
		if( file_exists( $filename ) )
			return count( file( $filename ) );
		else
			return false; // Return false if file not found
	}
}