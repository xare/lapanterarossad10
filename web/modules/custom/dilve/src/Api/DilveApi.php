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

class DilveApi {

	private $url_host;
  	private $url_path;
  	private $url_user;
  	private $url_pass;
	private $config;

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
		var_dump($book);
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
  	private function get_file_url($filename, $isbn) {
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
	function create_cover($url, $filename, $mimetype = 'image/jpeg', $force = FALSE) {
		$httpClient = \Drupal::httpClient();
		$fileSystem = \Drupal::service('file_system');
		$messenger = \Drupal::messenger();
		try {
			$response = $httpClient->get($url);
			if( $response->getStatusCode() == 200 ) {
				$data = $response->getBody();
				$destination = 'public://cover_images/' . $filename;
				$uri = $fileSystem->saveData($data, $destination, FileSystemInterface::EXISTS_REPLACE);
				if ($uri) {
					$file = File::create([
					  'uri' => $uri,
					  'uid' => \Drupal::currentUser()->id(),
					  'filename' => $filename,
					]);
					$file->save();
					// Add file usage so the file won't be deleted on the next cron run.
					$file->setPermanent();
					$file->save();
					return $file;
				}
			} else {
				$messenger->addError('Failed to download image. ' . $response->getStatusCode . ' '. $response->getReasonPhrase());
            	return NULL;
			}
		} catch (RequestException $e) {
			\Drupal::logger('dilve')->error($e->getMessage());
		} catch (ConnectException $e) {
			\Drupal::logger('dilve')->error('ConnectException: ' . $e->getMessage());
			return NULL;
		} catch (RequestException $e) {
			\Drupal::logger('dilve')->error('RequestException: ' . $e->getMessage());
			return NULL;
		}
	}

  	function set_featured_image_for_product(File $file, $ean) {
		$query = \Drupal::entityQuery('commerce_product')
			->condition('field_ean.value', $ean)
			->accessCheck(FALSE);

		$product_ids = $query->execute();

		foreach ($product_ids as $product_id) {
			$product = \Drupal\commerce_product\Entity\Product::load($product_id);
			$product->set('field_portada', ['target_id' => $file->id()]);
			$product->save();
			\Drupal::service('file.usage')->add($file, 'dilve', 'node', $product_id);
		}
	}

}