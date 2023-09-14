<?php

namespace Drupal\book_listing\Service;

use Drupal\Core\Url;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\Core\Link;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Provides functionality to fetch related books.
 */
class BookService {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;
  /**
     * The pager manager service.
     *
     * @var \Drupal\Core\Pager\PagerManagerInterface
     */
    protected $pagerManager;

    /**
     * The pager manager service.
     *
     * @var \Drupal\Core\Entity\EntityTypeManagerInterface
     */
    protected $entityTypeManager;
    
    /**
     * Constructs a BookService object.
     *
     * @param \Drupal\Core\Database\Connection $database
     *   The database connection.
     */
    public function __construct(
            Connection $database, 
            EntityTypeManagerInterface $entityTypeManager, 
            PagerManagerInterface $pagerManager) {
        $this->database = $database;
        $this->entityTypeManager = $entityTypeManager;
        $this->pagerManager = $pagerManager;
    }

  /**
   * Gets related books by term ID.
   *
   * @param int $tid
   *   The taxonomy term ID.
   *
   * @return array
   *   An array of related books with URL and title.
   */
    public function getRelatedBooks($termId, $limit = 12) {
        $related_books = [];
        /* var_dump($termId);
        die; */
        // Determine the vocabulary of the term.
        $term = Term::load($termId);
        if (!$term) {
            return [];
        }
        $vocabulary = $term->bundle();
        
        // Start by retrieving the product IDs related to the given taxonomy term.
        $product_query = $this->database->select('commerce_product_field_data', 'p');
        $product_query->fields('p', ['product_id']);
        if ($vocabulary == 'editorials') {
            $product_query->join('commerce_product__field_editorial', 'f', 'p.product_id = f.entity_id');
            $product_query->condition('f.field_editorial_target_id', $termId);
        } else if ($vocabulary == 'autores') {
            $product_query->join('commerce_product__field_autor', 'author', 'p.product_id = author.entity_id');
            $product_query->condition('author.field_autor_target_id', $termId);
        } else if ($vocabulary == 'product_categories') {
            $product_query->join('commerce_product__field_categoria', 'categoria', 'p.product_id = categoria.entity_id');
            $product_query->condition('categoria.field_categoria_target_id', $termId);
        }
        $product_ids = $product_query->execute()->fetchCol();

        if (!empty($product_ids)) {
            // Now, retrieve the product details along with their default variation's price.
            // Query the database to get the related node;s/books.
            $base_query = $this->database->select('commerce_product_field_data', 'p');
            $base_query->fields('p', ['product_id', 'title']);
            $base_query->join('commerce_product__field_portada', 'portada', 'p.product_id = portada.entity_id');
            $base_query->addField('portada', 'field_portada_target_id', 'file_id');
            $base_query->leftJoin('commerce_product_variation', 'v', 'p.product_id = v.variation_id AND v.type = :defaultType', [':defaultType' => 'default']);
            $base_query->join('commerce_product_variation_field_data', 'vd', 'v.variation_id = vd.variation_id');
            $base_query->addField('vd', 'price__number', 'price');
            $base_query->condition('p.product_id', $product_ids, 'IN');
            // Clone the base query for the pager count.
            $count_query = clone $base_query;
            $total = $count_query->countQuery()->execute()->fetchField();

            // Now apply the range to get the current page results.
            $current_page = \Drupal::service('pager.manager')->createPager($total, $limit)->getCurrentPage();
            $offset = $current_page * $limit;
            $base_query->range($offset, $limit);

            // Adjust the query for the current page
            $results = $base_query->execute()->fetchAll();

            // Initialize the pager.
            $pager = \Drupal::service('pager.manager')->createPager($total, $limit);
            foreach ($results as $result) {
                $image_uri = NULL;
                $product_ids[] = $result->product_id;
                if (!empty($result->file_id)) {
                    $file = File::load($result->file_id);
                    if ($file) {
                        $image_uri = $file->getFileUri();
                        $style = ImageStyle::load('portada_en_tienda_online_grid');  // replace 'your_image_style_machine_name' with the machine name of your chosen/created image style
                        if ($style) {
                            $image_url = $style->buildUrl($image_uri);
                            $base_url = \Drupal::request()->getSchemeAndHttpHost();
                            $relative_url = str_replace($base_url, '', $image_url);
                        }
                    }
                }
                $related_books[] = [
                    'url' => Url::fromRoute('entity.commerce_product.canonical', ['commerce_product' => $result->product_id])->toString(),
                    'title' => $result->title,
                    'image_uri' => $image_uri ? $relative_url : NULL,
                    'price' => $result->price, // Add the price to your results array
                ];
            }
            return [
                'books' => $related_books,
                'pager' => $pager, // Get the pager object for the results.
            ];
    }
    return [
        'books' => $related_books,
        'pager' => '', // Get the pager object for the results.
    ];
  }

}
