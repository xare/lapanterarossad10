<?php

namespace Drupal\remove_empty_files\Command;

use Drush\Commands\DrushCommands;
use Drupal\Core\Database\Database;
use Symfony\Component\Filesystem\Filesystem;

/**
 * A Drush commandfile.
 */
class RemoveEmptyFilesCommands extends DrushCommands {

  /**
   * Checks for missing files in the legacy database.
   *
   * @command remove_empty_files:check-files
   * @aliases refcf
   */
  public function checkFiles() {
    $legacyDatabase = Database::getConnection('default', 'legacy'); // Adjust to your legacy database key.
    $query = $legacyDatabase->select('files', 'f');
    $query->fields('f', ['filepath']);
    $results = $query->execute()->fetchAll();

    $filesystem = new Filesystem();
    $basePath = '/var/www/html/lapanterarossad6'; // Adjust to your legacy files path.

    foreach ($results as $file) {
      $fullPath = $basePath . '/' . $file->filepath;
      if (!$filesystem->exists($fullPath)) {
        // If file doesn't exist, delete the record.
        $legacyDatabase->delete('files')
          ->condition('filepath', $file->filepath)
          ->execute();
        $this->logger()->notice('Deleted missing file reference: ' . $fullPath);
        $this->output()->writeln('Deleted missing file reference: ' . $fullPath);
      }
    }
  }

}
