<?php 
namespace Drupal\migrate_noticia_imagen\Plugin\migrate\source;

use Drupal\node\Plugin\migrate\source\d6\NodeComplete;

/**
 * Source plugin for retrieving data via SQL.
 *
 * @MigrateSource(
 *   id = "d6_noticias_complete_with_image",
 *   source_module = "node"
 * )
 */
class NoticiaCompleteWithImage extends NodeComplete {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Get the parent query.
    $query = parent::query();

    // Join with the content_field_act_imagen table.
    $query->leftJoin('content_field_noticia_imagen', 'cfni', 'n.vid = cfni.vid');

    // Select the field_act_imagen_fid field.
    $query->addField('cfni', 'field_noticia_imagen_fid');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = parent::fields();
    $fields['field_noticia_imagen_fid'] = $this->t('Image File ID');
    return $fields;
  }

}
