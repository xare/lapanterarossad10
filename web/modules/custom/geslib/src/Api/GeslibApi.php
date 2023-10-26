<?php

namespace Drupal\geslib\Api;

class GeslibApi {
    public function messageThis(
        string $message,
        string $type = "status",
        array $placeholders = []) {
		$type = ($type == "notice") ?: "status";
		$allowed_types = ['status', 'warning', 'error'];
		if( !in_array( $type, $allowed_types ) ) {
			\Drupal::messenger()->addMessage( 'Invalid message type provided: ' . $type, 'error' );
			return;
		}
		if (!empty($placeholders)) {
			$message = strtr($message, $placeholders);
		  }
        \Drupal::messenger()->addMessage( $message, $type );
    }

    public function fileThis(
        string $message,
        string $type = "Notice",
        array $placeholders = [] ){
		$logPath = \Drupal::root() . '/logs/geslibLogs.log';
		if ( !empty($placeholders) ) {
			$message = strtr($message, $placeholders);
		}
		file_put_contents( $logPath, '['.$type .'] '. $message. PHP_EOL, FILE_APPEND );
    }

	public function logThis(
		string $message,
		string $type = "info",
		array $placeholders = [] ){

		$allowed_types = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

		if ( !in_array( $type, $allowed_types ) ) {
			\Drupal::logger('dilve')->error('Invalid log type provided: ' . $type);
			return;
		}
		if (!empty($placeholders)) {
			$message = strtr($message, $placeholders);
		}
		\Drupal::logger($this->_getModuleName())->{$type}($message);
	}

	private function _getModuleName () {
		$reflection = new \ReflectionClass($this);
    	$namespace = $reflection->getNamespaceName();
    	return $moduleName = explode('\\', $namespace)[1];
		// Assumes the module name is the second part of the namespace
	}

	public function reportThis ( string $message, string $type = "notice", array $placeholders =[] ) {
		$this->messageThis($message, $type, $placeholders);
		$this->logThis($message, $type, $placeholders);
		$this->fileThis($message, $type, $placeholders);

	}

	public function checkDimensions($data) {
		$dimensions = getimagesizefromstring($data);
		if ($dimensions[0] === 1 ) {
			// Log or handle the 1x1 image case
			$this->reportThis( 'Image dimensions are 1x1, skipping download.', 'warning');
		}
	}
}