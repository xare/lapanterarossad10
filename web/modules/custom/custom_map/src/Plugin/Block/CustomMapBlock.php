<?php

namespace Drupal\custom_map\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;

/**
 * Provides a 'Custom Map' Block.
 *
 * @Block(
 *   id = "custom_map_block",
 *   admin_label = @Translation("Custom Map Block"),
 *   category = @Translation("Custom"),
 * )
 */
class CustomMapBlock extends BlockBase implements BlockPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function build() {
        // Load the geocoder service.
        $geocoder = \Drupal::service('geocoder');
        
        // The address to be geocoded.
        $address = '1600 Amphitheatre Parkway, Mountain View, CA';

        // Geocode the address.
        $addressCollection = $geocoder->geocode($address, ['googlemaps']);
        
        // Check if geocoding was successful.
        if ($addressCollection === null || $addressCollection->isEmpty()) {
            return [
            '#markup' => 'Failed to geocode address.',
            ];
        }
        // Get the first address from the collection.
        $coordinates = $addressCollection->first()->getCoordinates();
        
        // Prepare the features.
        $features = [
            (object) [
            'type' => 'point',
            'latitude' => $coordinates->getLatitude(),
            'longitude' => $coordinates->getLongitude(),
            ],
        ];

        // Create a map using the Leaflet service.
        $leaflet_service = \Drupal::service('leaflet.service');
        $leaflet_map = leaflet_map_get_info('OSM.Mapnik'); // get the map definition
        $leaflet_map['settings'] = [
            'mapId' => 'leaflet-map',
            'zoom' => 10,
            'center' => [$coordinates->getLatitude(),$coordinates->getLongitude()],
        ];

        $render_array = $leaflet_service->leafletRenderMap($leaflet_map, $features, '480px');

        return $render_array;
    }
}
