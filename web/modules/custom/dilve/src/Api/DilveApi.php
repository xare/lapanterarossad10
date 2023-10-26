<?php

namespace Drupal\dilve\Api;

use Drupal\Component\Serialization\Json;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;

/**
 * DilveApi
 */
class DilveApi {

	/**
	 * url_host
	 *
	 * @var mixed
	 */
	private $url_host;
  	/**
  	 * url_path
  	 *
  	 * @var mixed
  	 */
  	private $url_path;
  	/**
  	 * url_user
  	 *
  	 * @var mixed
  	 */
  	private $url_user;
  	/**
  	 * url_pass
  	 *
  	 * @var mixed
  	 */
  	private $url_pass;
	/**
	 * config
	 *
	 * @var mixed
	 */
	private $config;

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct(){
		$this->config = \Drupal::config('dilve.settings');

		$this->url_host = "www.dilve.es";
		$this->url_path = "/dilve/dilve";
		$this->url_user = $this->config->get('dilve_user');
    	$this->url_pass = (NULL !== $this->config->get('dilve_pass')) ? $this->config->get('dilve_pass') : '';

	}

	/**
	* Function DilveApi::search
	*
	* @param string $isbn
	*   ISBN code to search
	* @return hash
	*   hash data of book
	*/
	public function search($isbn) {
		$url = "http://{$this->url_host}{$this->url_path}/getRecordsX.do";
		$query = [
			'user' => $this->url_user,
			'password' => $this->url_pass,
			'identifier' => $isbn,
			'metadataformat' => 'ONIX',
			'version' => '2.1',
			'encoding' => 'UTF-8',
		];
		try {
			$http_client = \Drupal::httpClient();
			$response = $http_client->get($url, ['query' => $query]);
			if ($response->getStatusCode() == 200) {
				$body = (string) $response->getBody();
				$xml = new SimpleXMLElement($body);
				// Your code here to handle the $xml object
			}
		} catch (RequestException $e) {
			\Drupal::logger('dilve')->error($e);
		}


		if($xml->ONIXMessage->Product != NULL ) {
			$xml_book = $xml->ONIXMessage->Product[0];
			$book = [];
			if ($xml_book) {

				//drupal_set_message(dprint_r($xml_book, 1));
				$book['isbn'] = $isbn;//(string)$xml_book->RecordReference;
				$book['ean'] = (string)$xml_book->RecordReference;
				$book['date'] = (int)$xml_book->PublicationDate;
				$book['year'] = substr($book['date'],0, 4);

				#Get Price
				foreach($xml_book->SupplyDetail->Price as $price) {
					$book['price'] = (float)$price->PriceAmount;
					$book['price'] = str_replace('.', '', number_format($book['price'], 2));
				}
				# Get title
				foreach($xml_book->Title as $title) {
					if ($title->TitleType == "01") {
						$book["title"] = (string)$title->TitleText;
						if ($title->Subtitle) {
							$book["subtitle"] = (string)$title->Subtitle;
						}
					}
				}

				//Get Publisher
				foreach ($xml_book->Publisher as $publisher) {
					if ($publisher->NameCodeType == 02) {
					$book['publisher'] = (string)$xml_book->Publisher->PublisherName;
					}
				}

				# Get author
				foreach($xml_book->Contributor as $contributor) {
					if ($contributor->ContributorRole == "A01") {
						$author_name = (string) $contributor->PersonNameInverted;
						$author_description = (string) $contributor->BiographicalNote;
						if ($author_description) {
							$book["author"][] = array('name' => $author_name, 'description' => $author_description);
						} else {
							$book["author"][] = array('name' => $author_name);
						}
					}
				}
				# Get measurements
				foreach($xml_book->Measure as $measure) {
					switch ($measure->MeasureTypeCode) {
					case "01":
						$book["length"] = array('unit' => (string)$measure->MeasureUnitCode, 'value' => (string)$measure->Measurement);
						break;
					case "02":
						$book["width"] = array('unit' => (string)$measure->MeasureUnitCode, 'value' => (string)$measure->Measurement);
						break;
					case "08":
						$book["weight"] = array('unit' => (string)$measure->MeasureUnitCode, 'value' => (string)$measure->Measurement);
						break;
					}
				}
				# Get number of pages
				if($xml_book->NumberOfPages) {
					$book["pages"] = (string)$xml_book->NumberOfPages;
				}
				# Get descriptions
				foreach($xml_book->OtherText as $description) {
					switch ($description->TextTypeCode) {
					case "01":
					case "03":
					case "05":
					case "07":
					case "31":
						//Descripción general
						$book["description"] = nl2br( (string) $description->Text );
						break;
					case "09":
						$book["promoting_description"] = nl2br( (string) $description->Text );
						break;
					case "12":
						$book["short_description"] = nl2br( (string) $description->Text );
						break;
					case "13":
						if ( isset($book['author']) && count($book['author']) == 1 ) {
						$book["author"][0]["description"] = nl2br( (string) $description->Text );
						}
						break;
					case "23":
						$book["preview_url"] = $this->get_file_url((string) $description->TextLink, $isbn);
						#print "\n---> Recogido fichero de preview: " . $book["*preview_url"] ." --- ";
						#print_r($description);
						break;
					default:
						#print "\n-----------------------> Tipo de texto no definido (".$description->TextTypeCode.") para el libro con ISBN ".$isbn."\n\n";
					}
				}
				# Get cover URL
				foreach ($xml_book->MediaFile as $media) {
					switch ($media->MediaFileTypeCode) {
					# Covers
					case "03":
					case "04":
					case "05":
					case "06":
						# Its better dilve uris
						if (!isset($book["cover_url"]) || $media->MediaFileLinkTypeCode == "06") {
						$book["cover_url"] = $this->get_file_url((string) $media->MediaFileLink, $isbn);
						}
					break;
					# Cover miniature
					case "07":
						break;
					# Author image
					case "08":
						$book["image_author_url"] = $this->get_file_url((string) $media->MediaFileLink, $isbn);
						#print "\n---> Recogido imagen del autor: " . $book["*image_author_url"];
						#print "\n---> Formato: " . $media->MediaFileFormatCode;
						#print "\n---> Tipo de Enlace: " . $media->MediaFileLinkTypeCode;
						break;
					# Publisher logo
					case "17":
						$book["image_publisher_url"] = $this->get_file_url((string) $media->MediaFileLink, $isbn);
						#print "\n---> Recogido logo de editorial: " . $book["*image_publisher_url"];
						#print "\n---> Formato: " . $media->MediaFileFormatCode;
						#print "\n---> Tipo de Enlace: " . $media->MediaFileLinkTypeCode;
						break;
					# Preview book
					case "51";
						#$book["*preview_media_url"] = $this->::get_file_url((string) $media->MediaFileLink, $isbn);
						#print "\n---> Recogido fichero de preview: " . $book["*preview_media_url"];
						#print "\n---> Formato: " . $media->MediaFileFormatCode;
						#print "\n---> Tipo de Enlace: " . $media->MediaFileLinkTypeCode;
						#break;e
					default:
						#print_r ($media);
						#print "\n-----------------------> Tipo de medio no definido (".$media->MediaFileTypeCode.") para el libro con ISBN ".$isbn."\n\n";
					}
				}
			}
		} else {
			$book = (string)$xml->error->text;
		}
		return $book;
  	}

	/**
	 * Function DilveSearch::get_file_url
	*
	* @param string $filename
	*   local or remote filename
	* @param string $isbn
	*   ISBN code to search
	* @return string
	*   Full URL of requested resource
	*/
  	private function get_file_url(string $filename, string $isbn) {
    	# If URL is a DILVE reference, complete full request
    	if (strpos($filename, 'http://') === 0 || strpos($filename, 'https://') === 0) {
      		$url = $filename;
    	} else {
      		$url  = 'http://'.$this->url_host.$this->url_path.
					'/getResourceX.do?user='.$this->url_user.
					'&password='.$this->url_pass;
      		$url .= '&identifier='.$isbn.
					'&resource='.urlencode($filename);
    	}
    	return $url;
  	}

	/**
	 * Checks if the cover exists and if it does returns the file object.
	 * It it doesn't exists downloads it and creates the object
	 *
	 * @param type $url
	 * @param type $isbn
	 * @return type
	 */
	function create_cover(string $url, string $filename, string $mimetype = 'image/jpeg', bool $force = FALSE) :mixed {
		$drupalApiManager = new DilveApiDrupalManager();
		$httpClient = \Drupal::httpClient();
		$fileSystem = \Drupal::service('file_system');

		try {
			$response = $httpClient->get($url);
			if( $response->getStatusCode() == 200 ) {
				$data = $response->getBody();
				$destination = 'public://cover_images/' . $filename;
				// Check if a file with the destination path already exists.
				if ( file_exists($destination) ) {
					// File already exists, load the existing file and return it.
					$existing_files = $drupalApiManager->getExistingFiles($destination);
					$file = reset($existing_files);
					$this->reportThis('The file '.$filename . ' already exists.','error');
					return $file;
				}
				if ( !$this->checkDimensions( $data ) ) {
					$uri = $fileSystem->saveData($data, $destination, FileSystemInterface::EXISTS_REPLACE);
					if ($uri) {
						return $drupalApiManager->createFile($uri,$filename);
					}
				}
			} else {
				$message = "File for filename: "
							. $filename. " with location at url: "
							. $url." FAILED. Status Code: "
							. $response->getStatusCode ." "
							. $response->getReasonPhrase();

				$this->reportThis( $message, 'error');
				return NULL;
			}
		} catch (RequestException $e) {
			$this->reportThis($e->getMessage(), 'error');
			return NULL;
		} catch (ConnectException $e) {
			$this->reportThis('ConnectException: ' .$e->getMessage(), 'error' );
			return NULL;
		} catch (RequestException $e) {
			$this->reportThis('RequestException: ' . $e->getMessage(), 'error' );
			return NULL;
		}
	}

  	function set_featured_image_for_product(File $file, string $ean) {
		$drupalApiManager = new DilveApiDrupalManager();
		$product_ids = $drupalApiManager->getProductIds($ean);

		foreach ($product_ids as $product_id) {
			$product = \Drupal\commerce_product\Entity\Product::load($product_id);
			try {
				$product->set('field_portada', ['target_id' => $file->id()]);
				$product->save();
				$this->reportThis('The product with ID @productId, EAN @ean and title @productTitle was correctly saved.','info',
					[
						'@productId' => $product->id,
				  		'@productTitle' => $product->title->value,
				  		'@ean' => $ean
					]
				);
				//In summary, this line of code is registering the usage of a file by a specific entity (in this case, a node of type 'dilve') within the Drupal system. This information can be useful, for example, to track which nodes are using a particular file or to perform cleanup operations when a file is no longer in use.

				\Drupal::service('file.usage')->add($file, 'dilve', 'node', $product_id);
			} catch(\Exception $exception){
				$this->reportThis('The product was not correctly saved: @exception','error', [ '@exception' => $exception->getMessage() ]);
			}
		}
	}

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
		$logPath = \Drupal::root() . '/logs/' . $this->_getModuleName()
		. 'PortadasErrorLogs.log';
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
		if ( !empty( $placeholders )) {
			$message = strtr( $message, $placeholders );
		}
		\Drupal::logger($this->_getModuleName())->{$type}($message);
	}

	public function reportThis (
		string $message,
		string $type = "notice",
		array $placeholders = [] ) {
		$this->messageThis($message, $type, $placeholders);
		$this->logThis($message, $type, $placeholders);
		$this->fileThis($message, $type, $placeholders);
	}

	private function _getModuleName () {
		$reflection = new \ReflectionClass($this);
    	$namespace = $reflection->getNamespaceName();
    	return $moduleName = explode('\\', $namespace)[1];
		// Assumes the module name is the second part of the namespace
	}

	public function checkDimensions($data) {
		$dimensions = getimagesizefromstring($data);
		if ($dimensions[0] === 1 ) {
			// Log or handle the 1x1 image case
			$this->reportThis( 'Image dimensions are 1x1, skipping download.', 'warning');
		}
	}
}