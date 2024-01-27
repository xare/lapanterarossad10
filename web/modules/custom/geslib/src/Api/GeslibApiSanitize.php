<?php

namespace Drupal\geslib\Api;

use Drupal\geslib\Api\Encoding;

/**
 * GeslibApiSanitize
 */
class GeslibApiSanitize {
    /**
     * sanitize_content_array
     *
     * @param  mixed $content_array
     * @return mixed $content_array
     */
    public function sanitize_content_array( $content_array ){
        // Regular expression for URL
        $urlRegex = '/\b(?:https?|ftp):\/\/[a-zA-Z0-9-\.]+\.[a-zA-Z]{2,}(?:\/\S*)?\b/';
        // Sanitize the array values
        $content_array = array_map( function( $value ) use($urlRegex) {
            // Find and replace URLs with placeholders
            $placeholders = [];
            $counter = 0;
            $value = preg_replace_callback($urlRegex, function($matches) use (&$placeholders, &$counter) {
                $placeholder = "__URL{$counter}__";
                $placeholders[$placeholder] = $matches[0];
                $counter++;
                return $placeholder;
            }, $value);
            // Convert numeric strings to numbers
            // Convert string values to compatible encoding
            if ( is_string( $value ) ) {
                $value = $this->utf8_encode($value);
            }
            if ( is_numeric( $value ) && !is_int( $value ) ) {
                $value = floatval( str_replace( ',', '.', $value ) );
            }

            // Remove empty strings
            if ( $value === '' ) {
                $value = null;
            }

            // Restore URLs from placeholders
            foreach ($placeholders as $placeholder => $url) {
                $value = str_replace($placeholder, $url, $value);
            }
            return $value;
        }, $content_array );

        return $content_array;
    }

    /**
    * Convert and Fix UTF8 strings
    *
    * @param string $string
    *     String to be fixed
    *
    * @return mixed $string | NULL
    */
    public function utf8_encode( $string ) {
        if ( $string ) {
           return Encoding::fixUTF8( $string );
        } else {
            return NULL;
        }
    }

}