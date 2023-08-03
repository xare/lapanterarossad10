<?php 
namespace Drupal\migrate_act_image\Plugin\migrate\source;

use Drupal\node\Plugin\migrate\source\d6\NodeComplete;

/**
 * Source plugin for retrieving data via SQL.
 *
 * @MigrateSource(
 *   id = "d6_node_complete_with_image",
 *   source_module = "node"
 * )
 */
class NodeCompleteWithImage extends NodeComplete {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Get the parent query.
    $query = parent::query();

    // Join with the content_field_act_imagen table.
    $query->leftJoin('content_field_act_imagen', 'cfai', 'n.vid = cfai.vid');

    // Select the field_act_imagen_fid field.
    $query->addField('cfai', 'field_act_imagen_fid');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = parent::fields();
    $fields['field_act_imagen_fid'] = $this->t('Image File ID');
    return $fields;
  }

}
