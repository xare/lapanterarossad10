<?php

namespace Drupal\geslib\Api;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\geslib\Api\GeslibApiDrupaLogManager;


/**
 * GeslibApiLog
 */
class GeslibApiLog {

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
    public function __construct(){

    }

    /**
     * getQueuedFile
     *
     * @return string
     */
    public function getQueuedFile(): string {
        $geslibApiDrupalLogManager = new GeslibApiDrupalLogManager;
        return $geslibApiDrupalLogManager->getLogLoggedFile();
    }

}