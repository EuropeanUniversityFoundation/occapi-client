<?php

namespace Drupal\occapi_client;

/**
 * Service for managing OCCAPI fields.
 */
class OccapiFieldManager {

  /**
   * An array of Institution resource fields and types.
   */
  protected $heiFields;

  /**
   * An array of Organizational Unit resource fields and types.
   */
  protected $ounitFields;

  /**
   * An array of Programme resource fields and types.
   */
  protected $programmeFields;

  /**
   * An array of Course resource collection fields and types.
   */
  protected $courseFields;

  /**
   * An array of Course resource additional fields and types.
   */
  protected $courseExtraFields;

  /**
   * Curated list of Institution fields and types.
   *
   * @return array
   *   An array of fields (as keys) and types (as values).
   */
  public static function getHeiFields() {
    $fields = [
      'title' => [
        'string'      => 'string',
        'lang'        => 'string'
      ],
      'abbreviation'  => 'string',
      'heiId'         => 'string',
      'url' => [
        'uri'         => 'string',
        'lang'        => 'string'
      ]
    ];

    return $fields;
  }

  /**
   * Curated list of Organizational Unit fields and types.
   *
   * @return array
   *   An array of fields (as keys) and types (as values).
   */
  public static function getOunitFields() {
    $fields = [
      'title' => [
        'string'      => 'string',
        'lang'        => 'string'
      ],
      'abbreviation'  => 'string',
      'ounitId'       => 'string',
      'ounitCode'     => 'string',
      'url' => [
        'uri'         => 'string',
        'lang'        => 'string'
      ]
    ];

    return $fields;
  }

  /**
   * Curated list of Programme fields and types.
   *
   * @return array
   *   An array of fields (as keys) and types (as values).
   */
  public static function getProgrammeFields() {
    $fields = [
      'title' => [
        'string'              => 'string',
        'lang'                => 'string'
      ],
      'code'                  => 'string',
      'description' => [
        'multiline'           => 'string',
        'lang'                => 'string'
      ],
      'ects'                  => 'integer',
      'eqfLevelProvided'      => 'integer',
      'iscedCode'             => 'string',
      'length'                => 'integer',
      'languageOfInstruction' => 'string',
      'url' => [
        'uri'                 => 'string',
        'lang'                => 'string'
      ]
    ];

    return $fields;
  }

  /**
   * Curated list of Course fields and types.
   *
   * @return array
   *   An array of fields (as keys) and types (as values).
   */
  public static function getCourseFields() {
    $fields = [
      'title' => [
        'string'              => 'string',
        'lang'                => 'string'
      ],
      'code'                  => 'string',
      'description' => [
        'multiline'           => 'string',
        'lang'                => 'string'
      ],
      'learningOutcomes' => [
        'multiline'           => 'string',
        'lang'                => 'string'
      ],
      'academicTerm'          => 'string',
      'ects'                  => 'float',
      'languageOfInstruction' => 'string',
      'iscedCode'             => 'string',
      'subjectArea'           => 'string',
      'otherCategorization'   => 'string',
      'url' => [
        'uri'                 => 'string',
        'lang'                => 'string'
      ]
    ];

    return $fields;
  }

  /**
   * Curated list of Course additional fields and types.
   *
   * @return array
   *   An array of fields (as keys) and types (as values).
   */
  public static function getCourseExtraFields() {
    $fields = [
      'bibliography' => [
        'multiline'           => 'string',
        'lang'                => 'string'
      ],
      'courseContent' => [
        'multiline'           => 'string',
        'lang'                => 'string'
      ],
      'prerequisites' => [
        'multiline'           => 'string',
        'lang'                => 'string'
      ],
      'courseAvailability' => [
        'multiline'           => 'string',
        'lang'                => 'string'
      ],
      'teachingMethod' => [
        'multiline'           => 'string',
        'lang'                => 'string'
      ],
      'assessmentMethod' => [
        'multiline'           => 'string',
        'lang'                => 'string'
      ],
    ];

    return $fields;
  }

}
