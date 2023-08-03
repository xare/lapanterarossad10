<?php

namespace Drupal\path_replacer\Command;

use Drush\Commands\DrushCommands;
use Drupal\Core\Database\Connection;

/**
 * Class CustomCommands.
 *
 * @package Drupal\path_replacer\Commands
 */
class pathReplacerCommand extends DrushCommands {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new CustomCommands object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    parent::__construct();
    $this->database = $database;
  }

  /**
   * Replace old URLs in node body text.
   *
   * @command path_replacer:replace-urls
   * @aliases replace-urls
   */
  public function replaceUrls() {
    $old_path = 'https://lapanterarossa.net/sites/default/files';
    $new_path = '/sites/default/files/files_legacy';

    $query = $this->database->update('node__body')
      ->expression('body_value', "REPLACE(body_value, :old_path, :new_path)", [
        ':old_path' => $old_path,
        ':new_path' => $new_path,
      ])
      ->condition('body_format', 'full_html', '=');

    $query->execute();

    $this->logger()->success(dt('Replaced old URLs in node body text.'));
  }

}
