<?php

namespace Drupal\geslib\Api;

/**
 * GeslibApiDrupalLinesManager
 */
class GeslibApiDrupalLinesManager extends GeslibApiDrupalManager
{

    /**
     * getEditorialsFromGeslibLines
     * Get Editorials from Geslib Lines.
     * Called by geslibApiStoreData
     *
     * @return array|false
     */
    public function getEditorialsFromGeslibLines(): array|false {
        try {
            return \Drupal::database()
                    ->select(self::GESLIB_LINES_TABLE, 't')
                    ->fields('t')
                    ->condition('entity', 'editorial')
                    ->execute()
                    ->fetchAllAssoc('entity',\PDO::FETCH_BOTH);
        } catch (\Exception $exception){
            \Drupal::logger('geslib')->error('Function getEditorialsFromGeslibLines ' . $exception->getMessage());
            return FALSE;
        }
    }
    /**
     * getAuthorsFromGeslibLines
     * Get Authors from Geslib Lines
     * Called by geslibApiStoreData
     *
     * @return array|false
     */
    public function getAuthorsFromGeslibLines(): array|false {
        try {
            \Drupal::logger('geslib_lines')->info('Function getAuthorsFromGeslibLines ');

            return \Drupal::database()
                    ->select(self::GESLIB_LINES_TABLE, 'gl')
                    ->fields('gl')
                    ->condition('entity', 'autor')
                    ->execute()
                    ->fetchAllAssoc('entity',\PDO::FETCH_BOTH);
        } catch (\Exception $exception){
            \Drupal::logger('geslib_lines')->error('Function getAuthorsFromGeslibLines ' . $exception->getMessage());
            return FALSE;
        }
    }
    /**
     * getLinesContent
     * Gets the value from the content column from the geslib_lines depending on a geslib_id value.
     * Called from geslibApiLines::mergeContent
     *
     * @param  int $geslib_id
     * @param  string $type
     * @return string
     */
    public function getLinesContent( int $geslib_id, string $type ): string {
		return \Drupal::database()->select(self::GESLIB_LINES_TABLE,'gl')
                    ->fields('gl',['content'])
                    ->condition('geslib_id', $geslib_id, '=')
                    ->condition('entity', $type, '=')
                    ->range( 0, 1 )
                    ->execute()
                    ->fetchField();
	}

    /**
     * getProductCategoriesFromGeslibLines
     * Called by GeslibApiStoreData::storeProductCategories()
     *
     * @return array|false
     */
    public function getProductCategoriesFromGeslibLines(): array|false {
        try {
            return \Drupal::database()
                ->select(self::GESLIB_LINES_TABLE, 't')
                ->fields('t')
                ->condition('entity', 'product_cat')
                ->execute()
                ->fetchAll();
        } catch( \Exception $exception ) {
            \Drupal::logger('geslib')->error( 'Function getProductCategoriesFromGeslibLines' . $exception->getMessage());
            return false;
        }
    }
    /**
     * truncateGeslibLines
     * Will empty the geslib_lines table.
     * Called by:
     * - GeslibProcessAllCommand
     * - GeslibProcessFileCommand
     * - StoreProductsForm
     *
     * @return void
     */
    public function truncateGeslibLines(): void {
        try {
            \Drupal::database()->truncate( self::GESLIB_LINES_TABLE )->execute();
            \Drupal::logger('geslib')->info('@table has been emptied.:'. self::GESLIB_LINES_TABLE );
        } catch(\Exception $e) {
            \Drupal::logger('geslib')->error('Could not truncate table @table. Make sure the table name is correct:'. self::GESLIB_LINES_TABLE  );
        }
    }

    /**
     * countGeslibLines
     * Called by:
     * - StoreProductsForm
     * - GeslibAjaxStatistics
     *
     * @return int
     */
    public function countGeslibLines(): int {
        $query = \Drupal::database()
        ->select(self::GESLIB_LINES_TABLE, 't');
        $query->fields('t',['type']);
        $query->addExpression('COUNT(*)');
        return (int) $query->countQuery()->execute()->fetchfield();
    }

    /**
     * updateGeslibLines
     * Called by GeslibApiLines
     *
     * @param  int $geslib_id
     * @param  string $type
     * @param  mixed $content
     * @return string
     */
    public function updateGeslibLines( int $geslib_id, string $type, mixed $content ): string {
		try {
			\Drupal::database()
                ->update(self::GESLIB_LINES_TABLE)
                ->fields(['content' => $content])
                ->condition('geslib_id', $geslib_id, '=')
                ->condition('entity', $type,'=')
                ->execute();
                return "The " . $type . " item with geslib_id " . $geslib_id ." has been updated";
		} catch( \Exception $e ) {
			return "Un error ha ocurrido al intentar actualizar la tabla". self::GESLIB_LINES_TABLE. " :  ".$e->getMessage() ;
		}
	}
}