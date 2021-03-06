<?php

/**
 * @file
 * Contains \Drupal\typed_entity\TypedEntity\TypedEntityManager.
 */

namespace Drupal\typed_entity\TypedEntity;

use Drupal\xautoload\DIC\ServiceContainer;
use Drupal\xautoload\DrupalSystem\DrupalSystemInterface;

class TypedEntityManager implements TypedEntityManagerInterface {

  /**
   * Drupal system wrapper.
   *
   * @var DrupalSystemInterface
   */
  protected static $system;

  /**
   * Drupal system wrapper.
   *
   * @var ServiceContainer
   */
  protected static $dic;

  /**
   * Constructor.
   *
   * Declare a private constructor to make sure this class is never
   * instantiated. All method and properties here must be static.
   */
  private function __construct() {}

  /**
   * {@inheritdoc}
   */
  public static function create($entity_type, $entity, ServiceContainer $dic = NULL) {
    if (!isset($dic)) {
      $dic = xautoload()->getServiceContainer();
    }
    static::$dic = $dic;
    static::$system = $dic->get('system');
    $class_name = static::getClass($entity_type, $entity);
    return new $class_name($dic, $entity_type, NULL, $entity);
  }

  /**
   * Helper function to get a class name given an entity type and an entity.
   *
   * @param string $entity_type
   *   The entity type
   * @param object $entity
   *   The entity object.
   *
   * @return string
   *   A valid class name if one exists. It defaults to TypedEntity.
   */
  public static function getClass($entity_type, $entity) {
    $classes = &drupal_static(__METHOD__);
    /** @var \EntityDrupalWrapperInterface $wrapper */
    $wrapper = static::$dic
      ->get('entity_wrapper')
      ->wrap($entity_type, $entity);
    $bundle = $wrapper->getBundle();
    $cid = $entity_type . ':' . $bundle;

    if (isset($classes[$cid])) {
      return $classes[$cid];
    }

    $cached_classes = array();
    if ($cache = static::$system->cacheGet('typed_entity_classes', 'cache_bootstrap')) {
      $cached_classes = $cache->data;
    }

    $classes = array_merge($cached_classes, isset($classes) ? $classes : array());
    if (isset($classes[$cid])) {
      return $classes[$cid];
    }

    // The default class should always be TypedEntity. Assume that TypedEntity
    // is under the same namespace as TypedEntityManager.
    $classes[$cid] = '\\' . __NAMESPACE__ . '\\TypedEntity';
    $candidates = static::getClassNameCandidates($entity_type, $bundle);
    foreach ($candidates as $candidate) {
      if (class_exists($candidate)) {
        $classes[$cid] = $candidate;
        break;
      }
    }
    static::$system->cacheSet('typed_entity_classes', $classes, 'cache_bootstrap');

    return $classes[$cid];
  }

  /**
   * Helper function to get possible class names given entity type & bundle.
   *
   * If you want your entity types to be auto loaded then you need to place your
   * class in your custom module, under src/TypedEntity. The class needs to be
   * named Typed<EntityTypeCamelCase> or
   * Typed<EntityTypeCamelCase><BundleCamelCase>. For instance TypedNodeArticle.
   *
   * @param string $entity_type
   *   The type of the entity.
   * @param string $bundle
   *   The bundle of the entity.
   *
   * @return array
   *   An array of class name candidates.
   */
  protected static function getClassNameCandidates($entity_type, $bundle = NULL) {
    $candidates = static::$system->moduleInvokeAll('typed_entity_registry_info');
    $candidate_entity_type = $candidate_bundle = '';
    foreach ($candidates as $candidate) {
      if ($candidate['entity_type'] != $entity_type) {
        continue;
      }
      if (empty($candidate['bundle'])) {
        $candidate_entity_type = $candidate['class'];
      }
      if (!empty($candidate['bundle']) && $candidate['bundle'] == $bundle) {
        $candidate_bundle = $candidate['class'];
      }
    }
    $names = array();

    // First add the specific suggestions for bundles. It is important to add
    // the most specific first.
    if (!empty($candidate_bundle)) {
      $names[] = $candidate_bundle;
    }
    $names = array_merge($names, static::getClassNameCandidatesBundle($entity_type, $bundle));

    // Then add the generic ones for entity types.
    if (!empty($candidate_entity_type)) {
      $names[] = $candidate_entity_type;
    }
    $names = array_merge($names, static::getClassNameCandidatesEntity($entity_type));

    return $names;
  }

  /**
   * Helper function to get possible class names given entity type & bundle.
   *
   * @param string $entity_type
   *   The type of the entity.
   *
   * @return array
   *   An array of class name candidates.
   */
  protected static function getClassNameCandidatesEntity($entity_type) {
    $names = array();
    $class_name_entity_type = 'Typed' . static::camelize($entity_type);
    foreach (static::$system->moduleList() as $module_name) {
      $names[] = '\\Drupal\\' . $module_name . '\\TypedEntity\\' . $class_name_entity_type;
    }

    return $names;
  }

  /**
   * Helper function to get possible class names given entity type & bundle.
   *
   * @param string $entity_type
   *   The type of the entity.
   * @param string $bundle
   *   The bundle of the entity.
   *
   * @return array
   *   An array of class name candidates.
   */
  protected static function getClassNameCandidatesBundle($entity_type, $bundle) {
    $names = array();

    if (empty($bundle)) {
      return $names;
    }
    $class_name_bundle = 'Typed' . static::camelize($entity_type) . static::camelize($bundle);
    foreach (static::$system->moduleList() as $module_name) {
      $names[] = '\\Drupal\\' . $module_name . '\\TypedEntity\\' . $class_name_bundle;
    }

    return $names;
  }

  /**
   * {@inheritdoc}
   */
  public static function camelize($input) {
    $input = preg_replace('/[-_]/', ' ', $input);
    $input = ucwords($input);
    $parts = explode(' ', $input);
    return implode('', $parts);
  }

}
