<?php

/**
 * @file
 * Contains \Drupal\typed_entity\TypedEntity\TypedEntity.
 */

namespace Drupal\typed_entity\TypedEntity;

use Drupal\typed_entity\Exception\TypedEntityException;
use Drupal\xautoload\DIC\ServiceContainer;

class TypedEntity implements TypedEntityInterface {

  /**
   * The entity ID.
   *
   * @var int
   */
  protected $entityId;

  /**
   * The fully loaded entity.
   *
   * @var object
   */
  protected $entity;

  /**
   * The entity type.
   *
   * @var string
   */
  protected $entityType;

  /**
   * The entity bundle.
   *
   * @var string
   */
  protected $bundle;

  /**
   * The EMW
   *
   * @var \EntityDrupalWrapper
   */
  protected $wrapper;

  /**
   * The EMW
   *
   * @var ServiceContainer
   */
  protected $dic;

  /**
   * Constructs a TypedEntity object.
   *
   * @param ServiceContainer $dic
   *   The dependency injection container from xautoload.
   * @param string $entity_type
   *   The type of the entity.
   * @param int $entity_id
   *   The entity ID.
   * @param object $entity
   *   The fully loaded entity.
   * @param string $bundle
   *   (Optional). Allow forcing a bundle. This is mainly for unit testing.
   *
   * @throws \Drupal\typed_entity\Exception\TypedEntityException
   * @throws \EntityMalformedException
   */
  public function __construct(ServiceContainer $dic, $entity_type, $entity_id = NULL, $entity = NULL, $bundle = NULL) {
    if (empty($entity_type)) {
      throw new TypedEntityException('You need to provide the entity type for the TypedEntity.');
    }
    if (empty($entity_id) && empty($entity)) {
      throw new TypedEntityException('You need to provide the fully loaded entity or the entity ID.');
    }
    $this->dic = $dic;
    $this->entityType = $entity_type;
    $this->entityId = $entity_id;
    $this->entity = $entity;
    $this->bundle = $bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityId() {
    if (isset($this->entityId)) {
      return $this->entityId;
    }
    if (empty($this->entity)) {
      // This means that somehow we do not have neither entity nor entity ID.
      throw new TypedEntityException('You need to provide the fully loaded entity or the entity ID.');
    }
    $this->entityId = $this
      ->getWrapper()
      ->getIdentifier();

    return $this->entityId;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity() {
    if (isset($this->entity)) {
      return $this->entity;
    }
    $entity_id = $this->getEntityId();
    if (empty($entity_id)) {
      // We do not have neither entity nor ID. We cannot load.
      return NULL;
    }

    $this->entity = $this
      ->dic
      ->get('entity_wrapper')
      ->wrap($this->getEntityType(), $entity_id)
      ->value();
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType() {
    return $this->entityType;
  }

  /**
   * {@inheritdoc}
   */
  public function getBundle() {
    if (isset($this->bundle)) {
      return $this->bundle;
    }

    $this->bundle = $this
      ->getWrapper()
      ->getBundle();
    return $this->bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function getWrapper() {
    if (isset($this->wrapper)) {
      return $this->wrapper;
    }
    $this->wrapper = $this
      ->dic
      ->get('entity_wrapper')
      ->wrap($this->getEntityType(), $this->getEntity());
    return $this->wrapper;
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    return $this->getWrapper()->save();
  }

  /**
   * {@inheritdoc}
   */
  public function access($op, $account = NULL) {
    return $this->getWrapper()->access($op, $account);
  }

  /**
   * Implements the magic methods to proxy property access to the entity.
   *
   * @param string $property_name
   *   The name of the property being accessed.
   *
   * @return mixed
   *   The property value.
   */
  public function __get($property_name) {
    return $this->getEntity()->{$property_name};
  }

  /**
   * Implements the magic methods to proxy property access to the entity.
   *
   * @param string $property_name
   *   The name of the property being accessed.
   * @param mixed $value
   *   The property value.
   */
  public function __set($property_name, $value) {
    $this->getEntity()->{$property_name} = $value;
  }

  /**
   * Implements the magic methods to proxy methods to the entity.
   *
   * @param string $name
   *   The method name.
   * @param array $arguments
   *   The arguments.
   *
   * @return mixed
   *   The value returned by the entity method.
   */
  public function __call($name, array $arguments) {
    $callable = array($this->getEntity(), $name);
    if (is_callable($callable)) {
      return call_user_func_array($callable, $arguments);
    }
    return NULL;
  }

}
