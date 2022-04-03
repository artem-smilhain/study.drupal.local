<?php

namespace Drupal\activit\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Tags;
use Drupal\Component\Utility\Unicode;

/**
 * Defines a route controller for entity autocomplete form elements.
 */
class AutocompleteController extends ControllerBase {
  //autocomplete for cities
  public function handleAutocomplete(Request $request, $field_name, $count) {
    $results = [];
    $input = $request->query->get('q');
    $input = Tags::explode($input);
    $input = strtolower(array_pop($input));
    if (!$input) { //если пустое //if empty
      return new JsonResponse($results);
    }
    //берем данные из БД //get data from DB
    $query = \Drupal::database();
    $records = $query->select('cities', 'e')
      ->fields('e', ['city_name'])
      ->execute();
    //
    $i = 1;
    foreach ($records as $record){
      if (str_contains(strtolower($record->city_name), $input)) {
        $results[] = [
          'value' => $record->city_name,
          'label' => $i.' - '.$record->city_name,
        ];
      }
      $i++;
    }
    //
    return new JsonResponse($results);
  }

}
