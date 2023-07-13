<?php 

namespace Drupal\geslib\Api;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\geslib\Api\GeslibApiDrupalManager;


class GeslibApiLog {

    private $drupal;
    private $logger_factory;

    public function __construct(LoggerChannelFactoryInterface $logger_factory){
        $this->logger_factory = $logger_factory;
        $this->drupal = new GeslibApiDrupalManager($logger_factory);
    }

    /* public function store2Log($filename){
        $this->db->insertLogData($filename);
    } */

    public function getQueuedFile(){
        return $this->drupal->getLogQueuedFile();
    }

}