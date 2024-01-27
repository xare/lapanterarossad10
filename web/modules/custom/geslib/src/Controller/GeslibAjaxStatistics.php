<?php
namespace Drupal\geslib\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\geslib\Api\GeslibApi;
use Drupal\geslib\Api\GeslibApiDrupalLinesManager;
use Drupal\geslib\Api\GeslibApiDrupalLogManager;
use Drupal\geslib\Api\GeslibApiDrupalManager;
use Drupal\geslib\Api\GeslibApiDrupalProductsManager;
use Drupal\geslib\Api\GeslibApiDrupalQueueManager;
use Drupal\geslib\Api\GeslibApiReadFiles;
use Symfony\Component\HttpFoundation\JsonResponse;

class GeslibAjaxStatistics extends ControllerBase {

  public function ajaxStatistics() {
    // Get your data
    $geslibApiDbManager = new GeslibApiDrupalManager;
    $geslibApiDrupalLogManager = new GeslibApiDrupalLogManager;
    $geslibApiDrupalLinesManager = new GeslibApiDrupalLinesManager;
    $geslibApiDrupalQueueManager = new GeslibApiDrupalQueueManager;
    $geslibApiDrupalProductsManager = new GeslibApiDrupalProductsManager;
    $geslibReadFiles = new GeslibApiReadFiles;
    $geslibApi = new GeslibApi;

    $data = [
      'total-products' => $geslibApiDrupalProductsManager->getTotalNumberOfProducts(),
      'file-count' => $geslibReadFiles->countFilesInFolder(),
      'total-logs'=>$geslibApiDrupalLogManager->countGeslibLog(),
      'total-lines' => $geslibApiDrupalLinesManager->countGeslibLines(),
      'total-queue-lines' => $geslibApiDrupalQueueManager->getQueueCount('store_lines'),
      'total-queue-lines-products' => $geslibApiDrupalQueueManager->countProductsInGeslibLinesQueue(),
      'total-queue-lines-authors' => $geslibApiDrupalQueueManager->countAuthorsInGeslibLinesQueue(),
      'total-queue-lines-editorials' => $geslibApiDrupalQueueManager->countEditorialsInGeslibLinesQueue(),
      'latest-queue-lines-gp4a'=> $geslibApiDrupalQueueManager->getLastProductInGeslibLinesQueue(),
      'latest-queue-lines-auta'=> $geslibApiDrupalQueueManager->getLastAuthorInGeslibLinesQueue(),
      'latest-queue-lines-1la'=> $geslibApiDrupalQueueManager->getLastEditorialInGeslibLinesQueue(),
      'total-queue-products' => $geslibApiDrupalQueueManager->getQueueCount('store_products'),
      'queued-filename' => $geslibApiDrupalLogManager->getLogQueuedFilename(),
      'geslib-log-logged' => $geslibApiDrupalLogManager->countGeslibLogStatus('logged'),
      'geslib-log-queued' => $geslibApiDrupalLogManager->countGeslibLogStatus('queued'),
      'geslib-log-dilve' => $geslibApiDrupalLogManager->countGeslibLogStatus('dilve'),
      'geslib-log-processed' => $geslibApiDrupalLogManager->countGeslibLogStatus('processed'),
      'geslib-latest-logs' => $geslibApi->getLatestLogs()
    ];

    return new JsonResponse($data);
  }

  // Define your methods to get data, like getTotalNumberOfProducts(), etc.
}
