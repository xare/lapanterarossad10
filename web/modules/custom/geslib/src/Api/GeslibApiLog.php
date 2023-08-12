<?php 

namespace Drupal\geslib\Api;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\geslib\Api\GeslibApiDrupalManager;


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
    public function __construct(LoggerChannelFactoryInterface $logger_factory){
        $this->logger_factory = $logger_factory;
        $this->drupal = new GeslibApiDrupalManager($logger_factory);
    }

    /* public function store2Log($filename){
        $this->db->insertLogData($filename);
    } */
    
    /**
     * getQueuedFile
     *
     * @return void
     */
    public function getQueuedFile(){
        return $this->drupal->getLogQueuedFile();
    }

}