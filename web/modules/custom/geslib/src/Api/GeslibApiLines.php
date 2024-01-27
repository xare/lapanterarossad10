<?php

namespace Drupal\geslib\Api;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\geslib\Api\GeslibApiDrupalManager;

/**
 * GeslibApiLines
 */
class GeslibApiLines {
	static $productDeleteKeys = [
			"type",
			"action",
			"geslib_id"
	];
	static $authorDeleteKeys = [
			"type",
			"action",
			"geslib_id"
	];
	static $editorialDeleteKeys = [
			"type",
			"action",
			"geslib_id"
	];
	static $categoriaDeleteKeys = [
			"type",
			"action",
			"geslib_id"
	];
	static $productKeys = [
			"type",
			"action",
			"geslib_id",
			"description",
			"author",
			"pvp_ptas",
			"isbn",
			"ean",
			"num_paginas",
			"num_edicion",
			"origen_edicion",
			"fecha_edicion",
			"fecha_reedicion",
			"año_primera_edicion",
			"año_ultima_edicion",
			"ubicacion",
			"stock",
			"materia",
			"fecha_alta",
			"fecha_novedad",
			"Idioma",
			"formato_encuadernacion",
			"traductor",
			"ilustrador",
			"colección",
			"numero_coleccion",
			"subtitulo",
			"estado",
			"tmr",
			"pvp",
			"tipo_de_articulo",
			"clasificacion",
			"editorial",
			"pvp_sin_iva",
			"num_ilustraciones",
			"peso",
			"ancho",
			"alto",
			"fecha_aparicion",
			"descripcion_externa",
			"palabras_asociadas",
			"ubicacion_alternativa",
			"valor_iva",
			"valoracion",
			"calidad_literaria",
			"precio_referencia",
			"cdu",
			"en_blanco",
			"libre_1",
			"libre_2",
			"premiado",
			"pod",
			"distribuidor_pod",
			"codigo_old",
			"talla",
			"color",
			"idioma_original",
			"titulo_original",
			"pack",
			"importe_canon",
			"unidades_compra",
			"descuento_maximo"
	];
	static $categoriaKeys = [
		"type",
		"action",
		"geslib_id",
		"name",
		"",
		""
	];
	static $authorKeys = [
		"type",
		"action",
		"geslib_id",
		"name"
	];

	static $editorialKeys = [
		'type',
		'action',
		'geslib_id',
		'name',
		'name',
		'country_code'
	];
	static $lineTypes = [
		'1L', // Editoriales
		'1A', // Compañías discográficas
		//"1P", // Familias de papelería
		//"1R", // Publicaciones de prensa
		//"2", // Colecciones editoriales
		"3", // Materias
		"GP4", // Artículos
		//"EB", // eBooks (igual que los libros)
		//"IEB", // Información propia del eBook
		"5", // Materias asociadas a los artículos
		//"BIC", // Materias IBIC asociadas a los artículos
		"6", // Referencias de la librería
		"6E", // Referencias del editor
		//"6I", // Índice del libro
		//"6T", // Referencias de la librería (traducidas)
		//"6TE", // Referencias del editor (traducidas)
		//"6IT", // Índice del libro (traducido)
		//"LA", // Autores normalizados asociados a un artículo
		//"7", // Formatos de encuadernación
		//"8", // Idiomas
		//"9", // Palabras vacías
		"B", // Stock
		//"B2", // Stock por centros
		//"E", // Estados de artículos
		//"CLI", // Clientes
		"AUT", // Autores
		"AUTBIO", // Biografías de los autores
		//"I", // Indicador de carga inicial. Cuando este carácter aparece en la primera línea, indica que se están enviando todos los datos y de todas las entidades
		//"IPC", // Incidencias en pedidos de clientes
		//"P", // Promociones de artículos (globales a todos los centros)
		//"PROCEN", // Promociones de artículos por centros
		//"PC", // Pedidos de clientes
		//"VTA", // Ventas
		//"PAIS", // Países
		//"CLOTE", // Lotes de artículos: Cabecera
		//"LLOTE", // Lotes de artículos: Líneas
		//"TIPART", // Tipos de artículos
		//"CLASIF", // Clasificaciones de artículos
		//"ATRA", // Traducciones asociadas a los artículos
		//"ARTATR",
		//"CA", // Claves alternativas asociadas a los artículos
		//"CLOTCLI", // Lotes de clientes: Cabecera
		//"LLOTCLI", // Lotes de clientes: Líneas
		//"PROFES", // Profesiones
		//"PROVIN", // Provincias
		//"CAGRDTV", // Agrupaciones de descuentos de ventas: Cabecera
		//"LAGRDTV" // Agrupaciones de descuentos de ventas: Líneas
	];
	/**
	 * drupal
	 *
	 * @var mixed
	 */
	private $drupal;
	/**
	 * mainFolderPath
	 *
	 * @var mixed
	 */
	private $mainFolderPath;
	/**
	 * geslibSettings
	 *
	 * @var mixed
	 */
	private $geslibSettings;
	/**
	 * geslibApiSanitize
	 *
	 * @var mixed
	 */
	private $geslibApiSanitize;

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct( ) {
		$this->geslibSettings = \Drupal::config('geslib.settings')->get('geslib_settings');
        $public_files_path = \Drupal::service('file_system')->realpath("public://");
        $this->mainFolderPath = $public_files_path . '/' . $this->geslibSettings['geslib_folder_name'].'/';
		$this->drupal = new GeslibApiDrupalManager();
		$this->geslibApiSanitize = new GeslibApiSanitize();
	}

	/**
	 * storeToLines
	 * This function takes the log table and stores it in the queues table (type=store_lines)
	 *
	 *
	 * @param int $log_id
	 * @return int
	 */
	public function storeToLines( int $log_id = 0 ): int {
		$geslibApiDrupalLogManager = new GeslibApiDrupalLogManager;
		$geslibApiDrupalQueueManager = new GeslibApiDrupalQueueManager;
		// 1. Read the log table
		if ( $log_id == 0 ) {
			$filename = $geslibApiDrupalLogManager->getLogQueuedFilename();
			$log_id = $geslibApiDrupalLogManager->getLogId( $filename );
		} else {
			$filename = $geslibApiDrupalLogManager->getFilename( $log_id );
		}
		// 2. Read the file and store in lines table
		$lines = (array) file($this->mainFolderPath.$filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		$batch_size = 100; // Choose a reasonable batch size
		$batch = [];
		$i = 0;
		\Drupal::logger('geslib_storeTolines')->notice('Log_id '.$log_id.' .Function storeToLines. lines: ' . count( $lines ).' for log id:' .$log_id );
		foreach ( $lines as $line ) {
			\Drupal::logger( 'geslib_storeTolines' )->notice( 'Log_id '.$log_id.' Before converting Line to array at loop: '. $i . ' - Line: '.$this->sanitizeLine($line) );
			$line_array = explode('|', $line);
			$line = $this->sanitizeLine( $line );
			if( $this->isUnnecessaryLine( $line ) ){
				\Drupal::logger('geslib_storeToLines')->notice( 'Log_id '.$log_id.' Line escaped. Generic category. '.$line);
				$i++;
				continue;
			}
			if( !$this->isInProductKey( $line ) ){
				\Drupal::logger('geslib_storeToLines')->notice( 'Log_id '.$log_id.' Line escaped. Not in product key. '.$line);
				$i++;
				continue;
			}
			if( $this->isInEditorials( $line )){
				\Drupal::logger('geslib_storeToLines')->notice( 'Log_id '.$log_id.' Line escaped. Already in editorials. '.$line);
				$i++;
				continue;
			}
			if( $this->isInAuthors( $line )){
				\Drupal::logger('geslib_storeToLines')->notice( 'Log_id '.$log_id.'Line escaped. Already in authors. '.$line);
				$i++;
				continue;
			}
			if( $this->isInProducts( $line )){
				\Drupal::logger('geslib_storeToLines')->notice( 'Log_id '.$log_id.'Line escaped. Already in products. '.$line);
				$i++;
				continue;
			}

			$index = ( in_array( $line_array[0],['6E', '6TE', 'AUTBIO', 'BIC', 'B'] ) ) ? 1 : 2;
			$action = null;
			if( in_array( $line_array[0], ['GP4', 'AUT', '5','1L'] ) ) $action = $line_array[1];
			if( $line_array[0] == 'B' ) $action = 'stock';
			$item = [
				'data' => $line,
				'log_id' => $log_id,
				'geslib_id' => $line_array[$index],
				'action' => $action,
				'type' => 'store_lines'  // type to identify the task in processQueue
			];
			\Drupal::logger( 'geslib_storeTolines' )->notice(  'Log_id '.$log_id.' - Item: ' .implode( " - ", $item ));
			$batch[] = $item;
			\Drupal::logger( 'geslib_storeTolines' )->notice(  'Log_id '.$log_id.' Count Batch: ' .count( $batch ) );
			if ( count($batch) >= $batch_size ) {
				\Drupal::logger( 'geslib_storeTolines' )->notice( 'Log_id '.$log_id.' End of batch - LOOP: '. $i.'. We reached the number: '.count($batch). ' we store the data in insertLinesIntoQueue');
				$geslibApiDrupalQueueManager->insertLinesIntoQueue($batch);
				$batch = [];
			}
			\Drupal::logger( 'geslib_storeTolines' )->notice( 'Log_id '.$log_id.' Geslib Lines - LOOP: '. $i );
			$i++;
		}
		// Don't forget the last batch
		if ( !empty( $batch ) ) {
			\Drupal::logger( 'geslib_storeTolines' )->notice( 'Log_id '.$log_id.' Out of batch: '.count($batch).' left to be processed');
			$geslibApiDrupalQueueManager->insertLinesIntoQueue($batch);
		}
		return $i;
	}

	/**
	 * sanitizeLine
	 *
	 * @param  mixed $line
	 * @return string
	 */
	public function sanitizeLine($line): string {
		// Split the line into its components
		$line_items = explode('|', $line);

		// Sanitize each component
		$sanitized_items = array_map(function($line_item) {
			if(is_string($line_item))
				return $this->geslibApiSanitize->utf8_encode($line_item);
			return $line_item;
		}, $line_items);

		// Join the components back together
		$sanitized_line = implode('|', $sanitized_items);

		return $sanitized_line;
	}


	/**
	 * readLine
	 * Reads a line from the file and processes it.
	 * Called from:
	 * - StoreToGeslibLines
	 * - GeslibApiDrupalQueueManager::processBatchStoreLines
	 *
	 * @param  string $line
	 * @param  int $log_id
	 * @return void
	 */
	public function readLine( string $line, int $log_id , int $queue_id): void {
		$geslibApiDrupalQueueManager = new GeslibApiDrupalQueueManager;
		\Drupal::logger( 'geslib_lines' )->info( "Log ID: ". $log_id. "Queue ID: ". $queue_id. " Inside readLine: ". $line );
		$data = explode( '|', $line ) ;
		array_pop( $data );

		if( in_array( $data[0], self::$lineTypes ) ) {
			$function_name = 'process' . $data[0];
			\Drupal::logger( 'geslib_lines' )->info( "Log ID: ". $log_id. " Inside readLine - PROCESS ". $function_name );
			if ( method_exists( $this, $function_name ) ) {
				$this->{$function_name}( $data, $log_id );
				$geslibApiDrupalQueueManager->deleteItemFromQueue($queue_id);
			}
		}
	}

	/**
	 * processGP4
	 * "type" | "action" | "geslib_id" |	"description" |	"author" | "pvp_ptas" |	"isbn" | "ean" |"num_paginas" |	"num_edicion" |	"origen_edicion" |"fecha_edicion" |	"fecha_reedicion" |	"año_primera_edicion" |"año_ultima_edicion" |"ubicacion" |"stock" |	"materia" |	"fecha_alta" |	"fecha_novedad" |"Idioma" |	"formato_encuadernacion" |"traductor" |"ilustrador" |"colección" |"numero_coleccion" |"subtitulo" |	"estado" |	"tmr" |	"pvp" |	"tipo_de_articulo" |"clasificacion" |"editorial" |	"pvp_sin_iva" |	"num_ilustraciones" |"peso" |"ancho" |"alto" |		"fecha_aparicion" |	"descripcion_externa" |	"palabras_asociadas" |			"ubicacion_alternativa" |"valor_iva" |"valoracion" |"calidad_literaria" |	"precio_referencia" | "cdu" |"en_blanco" |"libre_1" |"libre_2" | 			"premiado" |"pod" | "distribuidor_pod" | "codigo_old" | "talla" |			"color" |"idioma_original" |"titulo_original" |	"pack" |"importe_canon" |	"unidades_compra" |"descuento_maximo"
	 * GP4|A|17|BODAS DE SANGRE|GARRIGA MART�NEZ, JOAN|3660|978-84-946952-8-5|9788494695285|56|01||20180101||    |    ||1|06|20230214||003|02|BROGGI RULL, ORIOL||1||APUNTS I CAN�ONS DE JOAN GARRIGA SOBRE TEXTOS DE FEDERICO GARC�A LORCA (A PARTIR|0|0,00|22,00|L0|1|15|21,15|||210|148|||||4,00|||0,00|||||N|N||12530|||001||N||1|100,00|
	 *
	 * Called dynamically from readLine
	 *
	 * @param  array $data
	 * @param  int $log_id
	 * @return bool
	 */
	private function processGP4( array $data, int $log_id ) :bool {
		$geslibApiDrupalManager = new GeslibApiDrupalManager;
		if ($data[1] === 'B') {
			$keys = self::$productDeleteKeys;
		} elseif (in_array($data[1], ['A', 'M'])) {
			$keys = self::$productKeys;
		}
		if (! isset($keys)) return false;

		$content_array = array_combine($keys, $data);
		$content_array = $this->geslibApiSanitize->sanitize_content_array($content_array);
		\Drupal::logger('geslib_processGP4')->notice('Before inserting data '.implode(' ', $content_array));
		$geslibApiDrupalManager->insertData($content_array, $data[1], $log_id, 'product');
		return true;
	}

	/**
	 * process6E
	 *
	 * Called dynamically from readLine
	 *
	 * @param  array $data
	 * @param  int $log_id
	 * @return void
	 */
	private function process6E( array $data, int $log_id ) {

		// Procesa las líneas 6E aquí
		// 6E|geslib_id|Contador|Texto|
		// 6E|1|1|Els grans mitjans ens han repetit fins a l'infinit escenes de mort i destrucci� a Gaza, per� ens han amagat la quotidianitat m�s extraordin�ria. Viure morir i n�ixer a Gaza recull un centenar de fotografies que ens mostren les meravelles que David Segarra es va trobar enmig de la trag�dia: la capacitat de viure, d'estimar, de resistir i de sobreviure malgrat l'horror.\n\nAcompanyant les imatges, les paraules antigues de la Mediterr�nia. Ausi�s March, Estell�s, al-Russaf�, Llach, Espriu, Aub, Ibn Arab�, Lorca, Darwix o Kavafis. Veus de les tradicions que ens han forjat com a civilitzacions. Per� tamb� peda�os de relats i hist�ries poc conegudes que l'autor va descobrir durant tres mesos de conviv�ncia en aquest tros de Palestina. Hist�ries de saviesa i dolor. Hist�ries de paci�ncia i perseveran�a. Hist�ries de p�rdua i renaixen�a. Hist�ries de la bellesa oculta de Gaza.|
		$content_array['sinopsis'] = $data[3];
		$content_array = $this->geslibApiSanitize->sanitize_content_array($content_array);

		return $this->mergeContent($data[1], $content_array, 'product');
	}

	/**
	 * process1L
	 * Called dynamically from readLine
	 * - 1L|B|codigo_editorial
	 * - 1L|Tipo movimiento|Codigo_editorial|Nombre|nombre_externo|País|
	 * - 1L|A|1|VARIAS|VARIAS|ES|
	 *
	 * @param  array $data
	 * @param  int $log_id
	 * @return mixed
	 */
	private function process1L( array $data, int $log_id ): bool {
		$geslibApiDrupalLinesManager = new GeslibApiDrupalLinesManager;
		if ($data[1] === 'B') {
			$keys = self::$editorialDeleteKeys;
		} elseif (in_array($data[1], ['A', 'M'])) {
			$keys = self::$editorialKeys;
		}
		if (! isset($keys)) return false;
		$content_array = array_combine($keys, $data);
		$content_array = $this->geslibApiSanitize->sanitize_content_array($content_array);
		$geslibApiDrupalLinesManager->insertData( $content_array, $data[1], $log_id , 'editorial');
		return true;
	}

	/**
	 * process3
	 * Called dynamically from readLine
	 * Add categories
	 * 3|A|01|Cartes|||
	 *
	 * @param  array $data
	 * @param  int $log_id
	 * @return mixed
	 */
	private function process3( array $data, int $log_id ): bool {
		$geslibApiDrupalManager = new GeslibApiDrupalManager;
		if ($data[1] === 'B')
			$keys = self::$categoriaDeleteKeys;
		elseif (in_array($data[1], ['A', 'M']))
			$keys = self::$categoriaKeys;

		if ( !isset($keys)) return false;
		$content_array = array_combine( $keys, $data );
		$content_array = $this->geslibApiSanitize->sanitize_content_array( $content_array );
		$geslibApiDrupalManager->insertData( $content_array, $data[1], $log_id , 'product_cat');
		return true;
	}

	/**
	 * process5
	 * Called dynamically from readLine
	 *
	 * Add a category to to a
	 * 5|Código de materia (varchar(12))|Código de articulo + SEPARADOR
	 * 5|17|1|
	 * @param  array $data
	 * @param  int $log_id
	 * @return bool
	 */
	private function process5( array $data, int $log_id ): bool {
		$content_array = [];
		if($data[1] == 0 || $data[1] == "0") return false;
			/* if( isset( $content_array['categories'] ) )
				array_push( $content_array['categories'], [ $data[1] => $data[2] ] );
			else */
		$content_array['categories'][$data[1]] = $data[2];

		$this->mergeContent($data[2], $content_array, 'product');
		return true;
	}
	/**
	 * processAUT
	 * Procesa las líneas AUT
	 * AUT|Acción|GeslibID|Nombre del autor
	 * AUT|A|2806|HILAL, JAMIL|
	 * AUT|B|GeslibId
	 *
	 * @param  array $data
	 * @param  int $log_id
	 * @return void
	 */
	private function processAUT( array $data, int $log_id ): void {
		if ( in_array( $data[1], ['A','M'] ) ){
			// Insert or Update
			$content_array = array_combine( self::$authorKeys, $data );
			$content_array = $this->geslibApiSanitize->sanitize_content_array( $content_array );
		} elseif ( $data[1] == 'B' ){
			// Delete
			$content_array = array_combine( self::$authorDeleteKeys, $data );
		}
		$geslibApiDrupalManager = new GeslibApiDrupalManager;
		$geslibApiDrupalManager->insertData( $content_array, $data[1], $log_id , 'autor');
	}

	/**
	 * processAUTBIO
	 * //AUTBIO|3|Realiz� estudios de econom�a, ciencias pol�ticas y sociolog�a. Doctor en Ciencias Pol�ticas y profesor titular en la Facultad de Ciencias Pol�ticas y Sociolog�a de la Universidad Complutense de Madrid, hizo sus estudios de posgrado en la Universidad de Heidelberg (Alemania). En septiembre de 2010 fue ponente central en la conmemoraci�n del D�a Internacional de la Democracia en la Asamblea General de las Naciones Unidas en Nueva York. Dirige el Departamento de Gobierno, Pol�ticas P�blicas y Ciudadan�a Global del Instituto Complutense de Estudios Internacionales y pertenece al consejo cient�fico de ATTAC.|
	 *
	 * @param  array $data
	 * @param  int $log_id
	 * @return void
	 */
	private function processAUTBIO( array $data, int $log_id ): void {
		$content_array['biografia'] = $data[2];
		$content_array = $this->geslibApiSanitize->sanitize_content_array($content_array);
		$this->mergeContent( $data[1], $content_array, 'autor');
	}

	/**
	 * processB
	 * //B|21544|1
	 * Añade datos de stock
	 *
	 * @param  mixed $data
	 * @param  mixed $log_id
	 * @return void
	 */
	private function processB( $data, int $log_id ): void {
		$geslibApiDrupalManager = new GeslibApiDrupalManager;
		$content_array['stock'] = $data[2];
		$content_array['geslib_id'] = $data[1];
		$geslibApiDrupalManager->insertData($content_array, 'stock', $log_id, 'product');
	}

	/**
	 * mergeContent
	 * this function is called when the product has been created but we need to add more data to its content json string
	 * Called by
	 * - GeslibApiLines::process5
	 * - GeslibApiLines::processAUTBIO
	 * - GeslibApiLines::process6E
	 *
	 * @param  int $geslib_id
	 * @param  array $new_content_array
	 * @param  string $type
	 * @return bool
	 */
	private function mergeContent( int $geslib_id, array $new_content_array, string $type ): bool {
		$geslibApiDrupalLinesManager = new GeslibApiDrupalLinesManager;
		//1. Get the content given the $geslib_id
		$original_content = $geslibApiDrupalLinesManager->getLinesContent( $geslib_id, $type );
		if ( !$original_content ) return false;
		$original_content_array = json_decode( $original_content, true);
		// Merge 'stock' if set
		if (
			isset($original_content_array['stock'])
			&& isset($new_content_array['stock'])
			) {
				$original_content_array['stock'] = $new_content_array['stock'];
		} elseif (isset($new_content_array['stock'])) {
			$original_content_array['stock'] = $new_content_array['stock'];
		}
		$original_content_array = json_decode( $original_content, true);
		// Merge 'categories' if set
		if (
			isset($original_content_array['categories'])
			&& isset($new_content_array['categories'])
			) {
				$original_content_array['categories'] = array_merge( $original_content_array['categories'], $new_content_array['categories'] );
		} elseif (isset($new_content_array['categories'])) {
			$original_content_array['categories'] = $new_content_array['categories'];
		}

		$fields = ['sinopsis','biografia'];
		foreach( $fields as $field ) {
			if ( !isset($original_content_array[$field])
			&& isset($new_content_array[$field])) {
				$original_content_array[$field] = $new_content_array[$field];
			}
		}

		$content = json_encode($original_content_array);
		// update
		$geslibApiDrupalLinesManager = new GeslibApiDrupalLinesManager;
		$geslibApiDrupalLinesManager->updateGeslibLines($geslib_id, $type, $content);
		return true;
	}
	/**
	 * unnecessaryLine
	 *
	 * @param  string $line
	 * @return bool
	 */
	public function isUnnecessaryLine( string $line ): bool {
		return (bool) strpos($line, '< Genérica >');
	}

	/**
	 * isInEditorials
	 * Checks if the line reffers to an already stored editorial in taxonomy
	 * 1L|A|1|MELUSINA|MELUSINA|ES|
	 *
	 * @param  mixed $line
	 * @return bool
	 */
	public function isInEditorials( string $line ): bool {

		[$type, $action, $geslib_id] = explode('|', $line) + [null, null, null];
		return $type === '1L' && $action === 'A' &&
			!empty(\Drupal::entityTypeManager()
					->getStorage('taxonomy_term')
					->loadByProperties([
						'vid' => 'editorials', // Replace with your vocabulary machine name.
						'geslib_id' => $geslib_id, // Replace with your actual field name.
					]));
	}

	/**
	 * isInAuthors
	 *
	 * @param  string $line
	 * @return bool
	 */
	public function isInAuthors( string $line ): bool {
		//AUT|A|9|RILEY, ANDY|
		[$type, $action, $geslib_id] = explode('|', $line) + [null];
		return $type === 'AUT' && $action === 'A' &&
			!empty(\Drupal::entityTypeManager()
					->getStorage('taxonomy_term')
					->loadByProperties([
						'vid' => 'autores', // Replace with your vocabulary machine name.
						'geslib_id' => $geslib_id, // Replace with your actual field name.
					]));
	}

	/**
	 * Check if a given line is in the products list.
	 * "type" | "action" | "geslib_id" |	"description" |	"author" | "pvp_ptas" |	"isbn" | "ean" |"num_paginas" |	"num_edicion" |	"origen_edicion" |"fecha_edicion" |	"fecha_reedicion" |	"año_primera_edicion" |"año_ultima_edicion" |"ubicacion" |"stock" |	"materia" |	"fecha_alta" |	"fecha_novedad" |"Idioma" |	"formato_encuadernacion" |"traductor" |"ilustrador" |"colección" |"numero_coleccion" |"subtitulo" |	"estado" |	"tmr" |	"pvp" |	"tipo_de_articulo" |"clasificacion" |"editorial" |	"pvp_sin_iva" |	"num_ilustraciones" |"peso" |"ancho" |"alto" |		"fecha_aparicion" |	"descripcion_externa" |	"palabras_asociadas" |			"ubicacion_alternativa" |"valor_iva" |"valoracion" |"calidad_literaria" |	"precio_referencia" | "cdu" |"en_blanco" |"libre_1" |"libre_2" | 			"premiado" |"pod" | "distribuidor_pod" | "codigo_old" | "talla" |			"color" |"idioma_original" |"titulo_original" |	"pack" |"importe_canon" |	"unidades_compra" |"descuento_maximo"
	 * GP4|A|17|BODAS DE SANGRE|GARRIGA MART�NEZ, JOAN|3660|978-84-946952-8-5|9788494695285|56|01||20180101||    |    ||1|06|20230214||003|02|BROGGI RULL, ORIOL||1||APUNTS I CAN�ONS DE JOAN GARRIGA SOBRE TEXTOS DE FEDERICO GARC�A LORCA (A PARTIR|0|0,00|22,00|L0|1|15|21,15|||210|148|||||4,00|||0,00|||||N|N||12530|||001||N||1|100,00|
	 *
	 * @param string $line The input line to check
	 * @return bool
	 */
	public function isInProducts( string $line ): bool {
		$lineArray = explode('|', $line);
		$product_storage = \Drupal::entityTypeManager()
                            ->getStorage('commerce_product');
		return (bool) $lineArray[0] === 'GP4' &&
				$lineArray[1]	=== 'A' &&
				!empty( $product_storage
				->loadByProperties(['field_geslib_id_producto' => $lineArray[2]]));
	}

	/**
	 * Check if the line type is in product key and return the line if true.
	 *
	 * @param string $line
	 *   The input line, e.g., 'Type|Other|Data'.
	 *
	 * @return string|bool
	 *   The input line if the type is in product key, FALSE otherwise.
	 */
	public function isInProductKey( $line ) {
		return in_array( explode( '|', $line )[0], self::$lineTypes) ? $line : false;
	}



}