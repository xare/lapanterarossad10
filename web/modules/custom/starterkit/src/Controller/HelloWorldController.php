<?php 

/**
 * @file 
 * Generates markup to be displayed. Functionality in this Controller is
 * wired to Drupal in mymodule.routing.yml
 */

 namespace Drupal\starterkit\Controller;

 use Drupal\Core\Controller\ControllerBase;

 class HelloWorldController extends ControllerBase {

    public function simpleContent() {
        return [
            '#type' => 'markup',
            '#markup' => t('Hello Drupal World.'), 
        ];
    }

    public function variableContent( $name_1, $name_2) {
        return [
            '#type' => 'markup',
            '#markup' => t('@name1 and @name2 say hello to you!', ['@name1' => $name_1, '@name2' => $name_2]), 
        ];
    }
 }