<?php
/**
* @file
 * IntelPerson classes and plugin interface
 * 
 * @author Tom McCracken <tomm@getlevelten.com>
 */

class IntelVisitor extends Entity {
  
  protected $id;
  public $apiVisitor;
  public $apiPerson;
  public $apiLevel;
  public $identifiers = array();

  /**
   * Override constructor to set entity type.
   */
  public function __construct(array $values = array()) {
    parent::__construct($values, 'intel_visitor');

    /*
    if (empty($this->vtk) && !empty($this->vtkid)) {
      $this->vtk = $this->vtkid . $this->vtkc;
    }
    if (empty($this->vtkid) && !empty($this->vtk)) {
      $this->vtkid = substr($this->vtk, 0, 20);
      $this->vtkc = substr($this->vtk, 20);
    }
    */

    // Set to check if IAMP supports visitor data requests
    $this->apiLevel = intel_api_level();

    $apiClientProps = $this->getApiClientProps();
    
    intel_include_library_file('class.visitor.php');
    $this->apiVisitor = new \LevelTen\Intel\ApiVisitor($this->vtk, $apiClientProps);

    intel_include_library_file('class.person.php');
    $this->apiPerson = new \LevelTen\Intel\ApiPerson(array('email' => $this->email), $apiClientProps);
  }

  public function getApiClientProps() {
    return intel_get_ApiClientProps();
  }


  
  public function apiVisitorLoad($params = array()) {
    if ($this->apiLevel != 'pro') {
      return FALSE;
    }
    if (!isset($this->vtk)) {
      $this->apiVisitorLoad_error = new Exception(t('No vtk is set.'));
      return FALSE;
    }  
    if (!isset($this->apiVisitor)) {
      intel_include_library_file('class.visitor.php');
      $apiClientProps = $this->getApiClientProps();
      $this->apiVisitor = new \LevelTen\Intel\ApiVisitor($this->vtk, $apiClientProps);
    } 
    try {
      $this->apiVisitor->load($params);
      return TRUE;
    } 
    catch (Exception $e) {
      $this->apiVisitorLoadError = $e;
      //throw new Exception('Unable to load api visitor: ' . $e);
      return FALSE;
    }
  }
  
  public function apiPersonLoad($params = array()) {
    if ($this->apiLevel != 'pro') {
      return FALSE;
    }
    if (!isset($this->email)) {
      $this->apiPersonLoad_error = new Exception(Intel_Df::t('No email is set.'));
      return FALSE;
    }   
    if (!isset($this->apiPerson)) {
      intel_include_library_file('class.visitor.php');
      $apiClientProps = $this->getApiClientProps();
      $this->apiPerson = new \LevelTen\Intel\ApiPerson(array('email' => $this->email), $apiClientProps);
    }    
    $this->apiPerson->email = $this->email;
    try {
      $obj = $this->apiPerson->load($params);
    } 
    catch (Exception $e) {
      $this->apiPersonLoadError = $e;
      //throw new Exception('Unable to load api visitor: ' . $e);
    }
    return $obj;
  }
  
  public function merge() {
    $this->save();
  }
  
  public function label() {
    if ($this->name) {
      return $this->name;
    }
    else {
      $id = !empty($this->vtk) ? '(' . substr($this->vtk, 0, 10) . ')' : '';
      return 'anon ' . $id;
    }
  }

  public function identifier() {
    if (!empty($this->vid)) {
      return $this->vid;
    }
    elseif (!empty($this->vtkid)) {
      return $this->vtkid;
    }
    elseif (!empty($this->vtk)) {
      return $this->vtk;
    }
    return '';
  }

  public function uri() {
    return 'visitor/' . $this->identifier();
  }

  public function label_link($options = array()) {
    return l($this->label(), $this->uri(), $options);
  }
  
  public function getProperties() {
    $props = (object) get_object_vars($this);
    if (is_string($props->data)) {
      $props->data = unserialize($props->data);
    }
    if (is_string($props->ext_data)) {
      $props->ext_data = unserialize($props->ext_data);
    }
    return $props;
  }
  
  public function getApiVisitor() {
    return $this->apiVisitor->getVisitor();
  }
  
  public function getVar($scope, $namespace = '', $keys = '', $default = null) {
    $a = explode('_', $scope);
    if ($a[0] == 'api') {
      if ($this->apiLevel != 'pro') {
        return $default;
      }
      if ($a[1] == 'person') {
        return $this->apiPerson->getVar($a[2], $namespace, $keys, $default);
      }
      else {
        return $this->apiVisitor->getVar($a[1], $namespace, $keys, $default);
      }
    }
    if ($scope == 'ext') {
      $data = $this->ext_data;
    }
    else {
      $data = $this->data;
    }
    if (is_string($data)) {
      $data = unserialize($data);
    }
    if (empty($data[$namespace])) {
      return $default;
    }
    $data = $data[$namespace];
    intel_include_library_file("libs/class.intel_data.php");
    return \LevelTen\Intel\IntelData::getVar($data, $keys, $default);
  }
  
  public function updateData($namespace, $value, $keys = '') {
    $this->setVar('data', $namespace, $keys, $value);
    $this->data_updated = REQUEST_TIME;
  }
  
  public function updateExt($namespace, $value, $keys = '') {
    $this->setVar('ext', $namespace, $keys, $value);
    $this->ext_updated = REQUEST_TIME;
  }
  
  public function setVar($scope, $namespace, $keys, $value = null) {

    $a = explode('_', $scope);
    if ($a[0] == 'api') {
      if ($this->apiLevel != 'pro') {
        return FALSE;
      }
      if ($a[1] == 'person') {
        return $this->apiPerson->getVar($a[2], $namespace, $keys);
      }
      else {
        return $this->apiVisitor->getVar($a[1], $namespace, $keys);
      }
    }
    // check if three arg pattern
    $args = func_get_args();
    if (count($args) == 3) {
      $value = $keys;
      $keys = $namespace;
    }
    else {
      $keys = $namespace . (($keys) ? '.' . $keys : '');
    }
    if ($scope == 'ext') {
      $data = $this->ext_data;
    }
    else {
      $data = $this->data;
    }
    if (is_string($data)) {
      $data = unserialize($data);
    }
    intel_include_library_file("libs/class.intel_data.php");
    $data = \LevelTen\Intel\IntelData::setVar($data, $keys, $value);
    if ($scope == 'ext') {
      $this->ext_data = $data;
      $this->ext_updated = REQUEST_TIME;
    }
    else {
      $this->data = $data;
      $this->data_updated = REQUEST_TIME;
    }
    return TRUE;
  }
  
  public function getProp($prop_name) {
    if (strpos($prop_name, '.') === FALSE) {
      $prop_name = 'data.' . $prop_name;
    }
    $val = $this->getVar($prop_name);
    if (empty($val)) {
      $val = intel_get_visitor_property_construct($prop_name);
    }
    return $val;
  }
  
  public function setProp($prop_name, $values, $options) {
    $a = explode('.', $prop_name);
    if (count($a) == 2) {
      $scope = $a[0];
      $namespace = $a[1];
    }
    else {
      $scope = 'data';
      $namespace = $a[0];
    }
    $prop_info = intel_get_visitor_property_info("$scope.$namespace");
    
  
    $var = $this->getVar($scope, $namespace);
  
    foreach ($prop_info['variables'] AS $key => $default) {
      if (isset($values[$key])) {
        $var[$key] = $values[$key];
      }
    }
    if (!empty($options['source'])) {
      $var['_source'] = $options['source'];
    }
    $var['_updated'] = REQUEST_TIME;
 
    if (isset($prop_info['process callbacks'])) {
      $funcs = $prop_info['process callbacks'];
      if (!is_array($funcs)) { 
        $funcs = array($funcs);
      }
      foreach ($funcs AS $func) {
        $func($var, $prop_info, $this);
      }
    }
    
    // TODO prop history management
    /*
    $prop0 = $this->getProp($prop_name);
    
    $pkey = $prop_info['key'];
    
    if ($pkey) {
      // prop already set as primary
      if ($prop0[$pkey] == $prop[$pkey]) {
        return;
      }
    }
    */
    
    $this->setVar($scope, $namespace, '', $var);

    // process with special identifiers
    if ($prop_name == 'email') {
      if (empty($this->email)) {
        $this->setIdentifier('email', $values['email']);
        if (empty($this->contact_created)) {
          $this->setContactCreated(REQUEST_TIME);
        }
      }
    }
    elseif ($prop_name == 'phone') {
      if (empty($this->email)) {
        $this->setIdentifier('phone', $values['phone']);
        if (empty($this->contact_created)) {
          $this->setContactCreated(REQUEST_TIME);
        }
      }
    }
    elseif ($prop_name == 'name') {
      if (empty($this->name)) {
        $name = '';
        if (isset($values['full'])) {
          $name = $values['full'];
        }
        elseif (isset($values['first'])) {
          $name = $values['first'];
          if (isset($values['last'])) {
            $name .= ' ' . $values['last'];
          }
        }
        $this->setName($name);
      }
    }
  }
  /**
   * TODO manage aliases
   * @param $email
   */
  public function setEmail($email) {
    $this->email = $email;
  }
  
  public function setName($name) {
    $this->name = $name;
  }
  
  public function setUid($uid) {
    $this->uid = $uid;
  }
  
  public function setEid($eid) {
    $this->eid = $eid;
  }

  public function setVtk($vtk) {
    $this->vtk = $vtk;
  }

  public function setUserId($userId) {
    $this->userId = $userId;
  }
  
  public function setContactCreated($time = null) {
    $this->contact_created = isset($time) ? $time : REQUEST_TIME;
  }
  
  public function setLastActivity($time = null) {
    $this->last_activity = isset($time) ? $time : REQUEST_TIME;
  }
  
  public static function extractVtk() {
    intel_include_library_file('class.visitor.php');
    return \LevelTen\Intel\ApiVisitor::extractVtk();
  }

  public static function extractUserId() {
    intel_include_library_file('class.visitor.php');
    return \LevelTen\Intel\ApiVisitor::extractUserId();
  }

  public static function extractCid() {
    intel_include_library_file('class.visitor.php');
    return \LevelTen\Intel\ApiVisitor::extractCid();
  }

  /**
   * TODO build this to be more robust
   * @param $type
   * @param $value
   */
  public function filterIdentifier($type, $value) {
    if ($type == 'email') {
      return filter_var($value, FILTER_VALIDATE_EMAIL);
    }
    return $value;
  }
  
  public function setIdentifier($type, $value, $is_primary = TRUE, $mergeDuplicate = TRUE) {
    if (!$value = $this->filterIdentifier($type, $value)) {
      return;
    }

    if (!isset($this->identifiers[$type])) {
      $this->identifiers[$type] = array();
    }
    // check if visitor with same identifier exists.
    // if so, merge duplicate visitor into this.
    if ($mergeDuplicate) {
      $idents = array(
        $type => $value,
      );
      $dup = intel_visitor_load_by_identifiers($idents);
      if ($dup && ($dup->vid != $this->vid)) {
        $merge_dir = '';
        $this_vid = $this->vid;
        $dup_vid = $dup->vid;
        $this->mergeDupVisitor($dup, $merge_dir);
        // if dup has different vid, delete it and make sure merged data is saved
        if ($merge_dir) {
          if ($merge_dir == 'into_this') {
            intel_visitor_delete($dup_vid);
          }
          elseif ($merge_dir == 'into_dup') {
            intel_visitor_delete($this_vid);
          }
          $this->save();
        }
        if ($this->vid && !empty($this->is_new)) {
          unset($this->is_new);
        }
      }
    }
    
    // see if identifier already exists
    $existing_i = array_search($value, $this->identifiers[$type]);
    if ($is_primary && ($existing_i !== 0)) {
      if ($existing_i !== FALSE) {
        // remove existing element (it will be added at index 0)
        unset($this->identifiers[$type][$existing_i]);
        // reindex array
        $this->identifiers[$type] = array_values($this->identifiers[$type]);
      }
      array_unshift($this->identifiers[$type], $value);
      $this->$type = $value;
    }
    // if not primary and id does not already exists, add to end
    elseif ($existing_i === FALSE) {
      $this->identifiers[$type][] = $value;
    }
  }

  public function getIdentifiers($type) {
    return $this->identifiers[$type];
  }

  public function clearIdentifierType($type) {
    $this->identifiers[$type] = array();
  }

  public function mergeDupVisitor($dup, &$merge_dir = '') {
    // if this vid is not set, inherit the dup vid
    $to_vid = 0;
    $from_vid = 0;
    $merge_identifiers = array();
    if (empty($this->vid)) {
      $this->vid = $dup->vid;
    }
    // otherwise add dup vid for identifier processing
    else {
      // merge into first created vid
      if ($dup->vid < $this->vid) {
        $merge_dir = 'into_dup';
        $to_vid = $dup->vid;
        $from_vid = $this->vid;
        $merge_identifiers = $this->identifiers;
        $this->vid = $dup->vid;
        $this->name = $dup->name;
        $this->identifiers = $dup->identifiers;
        $this->data = drupal_array_merge_deep($dup->data, $this->data);
        $this->ext_data = drupal_array_merge_deep($dup->ext_data, $this->ext_data);
      }
      else {
        $merge_dir = 'into_this';
        $to_vid = $this->vid;
        $from_vid = $$dup->vid;
        $merge_identifiers = $dup->identifiers;
        $this->data = drupal_array_merge_deep($this->data, $dup->data);
        $this->ext_data = drupal_array_merge_deep($this->ext_data, $dup->ext_data);
      }
    }

    // add unique identifiers from dup
    foreach ($merge_identifiers AS $type => $values) {
      foreach ($values AS $i => $value) {
        $existing_i = FALSE;
        if (!isset($this->identifiers[$type])) {
          $this->identifiers[$type] = array();
        }
        else {
          $existing_i = array_search($value, $this->identifiers[$type]);
        }
        if ($existing_i === FALSE) {
          $this->identifiers[$type][] = $value;
        }
      }
    }
    
    if (!$this->created || ($dup->created < $this->created)) {
      $this->created = $dup->created;
    }
    if (!$this->contact_created || ($dup->contact_created < $this->contact_created)) {
      $this->contact_created = $dup->contact_created;
    }
    if ($dup->updated > $this->updated) {
      $this->updated = $dup->updated;
    }
    if ($dup->last_activity > $this->last_activity) {
      $this->last_activity = $dup->last_activity;
    }
    if ($dup->data_updated > $this->data_updated) {
      $this->data_updated = $dup->data_updated;
    }
    if ($dup->ext_updated  > $this->ext_updated ) {
      $this->ext_updated = $dup->ext_updated;
    }

    // update form submission vids
    if ($from_vid) {
      // update form submission vids
      $query = db_update('intel_submission')
        ->fields(array('vid' => $to_vid))
        ->condition('vid', $from_vid)
        ->execute();

      // update phone call vids
      $query = db_update('intel_phonecall')
        ->fields(array('vid' => $to_vid))
        ->condition('vid', $from_vid)
        ->execute();
    }
  }

  public function location($format = 'country') {
    $location = $this->getVar('data', 'location');
    $out = '';
    if ($format == 'city, state, country') {
      $out = !empty($location['city']) ? $location['city'] : t('(not set)') . ', ';
      $out .= ', ' . !empty($location['region']) ? $location['region'] : t('(not set)');
      $out .= ', ' . !empty($location['country']) ? $location['country'] : t('(not set)');
    }
    elseif ($format == 'map') {
      $out = !empty($location['city']) ? $location['city'] : '(not set)';
      $out .= ', ' . (!empty($location['region']) ? $location['region'] : t('(not set)'));
      if (isset($location['metro']) && ($location['metro'] != '(not set)')) {
        $out .= ' (' . $location['metro'] . ')';
      }
      $out .= "<br />\n" . (!empty($location['country']) ? $location['country'] : t('(not set)'));
    }
    else {
      $out = !empty($location['country']) ? $location['country'] : t('(not set)');
    }
    return $out;
  }
  
  public function __get($name) {
    // unserialize data if needed
    if (($name == 'data') && (is_string($this->data))) {
      $this->data = unserialize($this->data);
    }
    elseif (($name == 'ext_data') && (is_string($this->ext_data))) {
      $this->ext_data = unserialize($this->ext_data);
    }
    // return property if exists
    if (isset($this->$name)) {
      return $this->$name;
    }
    return null;
  }

  public function __isset($name) {
    $v = $this->__get($name);
    return isset($v);
  }
  
  public function __set($name, $value) {
    return $this->$name = $value;
  }
    
  public function __unset($name) {
    if (isset($this->$name)) {
      unset($this->$name);
    }
  }
  
  public function __call($method, $args) {
    if (method_exists($this->apiVisitor, $method)) {
      return $this->apiVisitor->$method($args);
    }
  }
  
  public function __toString() {
    return $this->identifier();
  }
}

/**
 * EntityExampleBasicControllerInterface definition.
 *
 * We create an interface here because anyone could come along and
 * use hook_entity_info_alter() to change our controller class.
 * We want to let them know what methods our class needs in order
 * to function with the rest of the module, so here's a handy list.
 *
 * @see hook_entity_info_alter()
 */
/*
interface IntelPersonControllerInterface
  extends DrupalEntityControllerInterface {
  public function create();
  public function save($entity);
  //public function load($ids = array(), $conditions = array());
  public function delete($entity);
}
*/

/**
 * EntityExampleBasicController extends DrupalDefaultEntityController.
 *
 * Our subclass of DrupalDefaultEntityController lets us add a few
 * important create, update, and delete methods.
 */
//class IntelPersonController
  //extends DrupalDefaultEntityController
  //implements IntelPersonControllerInterface {
class IntelVisitorController extends EntityAPIControllerExportable {
//class IntelPersonEntityAPIController extends DrupalDefaultEntityController {

  public $idType = 'vid';
  
  public function __construct($entityType = 'intel_visitor') {
    parent::__construct($entityType);
  }
  
  public function setIdType($idType) {
    if ($idType) {
      $this->idType = $idType;
    }    
  }
  
  public function getIdType() {
    return $this->idType;
    //return $this->idKey;
  }
    
  /**
   * Create and return a new intel_visitor entity.
   */
  public function create(array $values = array()) {
    global $user;

    $schema = drupal_get_schema('intel_visitor');
    foreach ($schema['fields'] AS $key => $field) {
      $values[$key] = isset($field['default']) ? $field['default'] : '';
    }
    
    $values['data'] = array();
    $values['ext_data'] = array();
    $values['identifiers'] = array();
    
    if (isset($values['id'])) {
      $vtk = '';
      if ($values['id'] == 'user') {
        intel_include_library_file('class.visitor.php');
        $vtk = \LevelTen\Intel\ApiVisitor::extractVtk();

        if ($user->uid) {
          $values['uid'] = $user->uid;
          $values['identifiers']['uid'] = array();
          $values['identifiers']['uid'][] = $user->uid;
        }
      }
      elseif (is_string($values['id']) && strlen($values['id']) >= 20) {
        $vtk = $values['id'];
      }
      if ($vtk) {
        $values['vtk'] = $vtk;
        $values['identifiers']['vtk'] = array();
        $values['identifiers']['vtk'][] = $vtk;
      }
    }


    $entity = parent::create($values);
    $entity->created = REQUEST_TIME;

    return $entity;
  }
  
  /**
   * Saves the custom fields using drupal_write_record()
   */
  public function save($entity) {
    if (!isset($entity->data)) {
      $entity->data_updated = 0;
      $entity->data = array();
    }
    if (!isset($entity->ext_data)) {
      $entity->ext_updated = 0;
      $entity->ext_data = array();
    }
    
    // If our entity has no eid, then we need to give it a
    // time of creation.
    if (empty($entity->vid)) {
      $entity->created = REQUEST_TIME;
    }
    
    if (empty($entity->contact_created) && !empty($entity->email)) {
      $entity->contact_created = REQUEST_TIME;
    }
    $return = parent::save($entity);

    $this->saveIdentifiers($entity);

    return $entity;
  }
  
  public function load($ids = array(), $conditions = array()) {
    $id = '';
    if (isset($ids[0])) {
      $id = $ids[0];
    }
    // if id = user, load the current user using the vtk cookie value
    if (!$id || ($id == 'user')) {
      intel_include_library_file('class.visitor.php');
      $vtk = \LevelTen\Intel\ApiVisitor::extractVtk();        
      $this->setIdType('vtk');
      //$id = substr($vtk, 0, 20);
      $ids = array($vtk);
    } 
    // check if id is vtk or vtkid
    elseif (is_string($id) && (strlen($id) >= 20)) {
      if (strlen($id) == 20) {
        $this->setIdType('vtkid');
      }
      else {
        $this->setIdType('vtk');
      }
    }

    $entities = parent::load($ids, $conditions);

    // re-index entities by idType if not vid
    if ($this->idType != 'vid') {
      $entities_i = array();
      foreach ($entities AS $vid => $entity) {
        $key = $entity->{$this->idType};
        if ($this->idType == 'vtkid') {
          $key = substr($entity->vtk, 0, 20);
        }
        else {
          $key = $entity->{$this->idType};
        }
        $entities_i[$key] = $entity;
      }
      return $entities_i;
    }
    return $entities;
  }
  
  protected function attachLoad(&$queried_entities, $revision_id = FALSE) {
    foreach ($queried_entities AS $i => $entity) {
      $queried_entities[$i]->identifiers = $this->loadIdentifiers($entity);
      foreach ($queried_entities[$i]->identifiers AS $type => $values) {
        $queried_entities[$i]->$type = $values[0];
      }
    }
    
    parent::attachLoad($queried_entities, $revision_id);
  }
  
  public function loadIdentifiers($entity, $type = '') {
    $identifiers = array();
    $query = db_select('intel_visitor_identifier', 'i')
      ->fields('i')
      ->condition('vid', $entity->vid);
    if ($type) {
      $query->condition('type', $type);
    }
    $query->orderBy('delta', 'ASC');
    $result = $query->execute();
    while ($row = $result->fetchObject()) {
      if (!isset($identifiers[$row->type])) {
        $identifiers[$row->type] = array();
      }
      $identifiers[$row->type][] = $row->value;
    }
    // add vid to identifiers if not already attached
    if (!isset($identifiers['vid']) || array_search($entity->vid, $identifiers['vid']) === FALSE) {
      if (!isset($identifiers['vid'])) {
        $identifiers['vid'] = array();
      }
      $identifiers['vid'][] = $entity->vid;
    }
    return $identifiers;
  }
  
  public function saveIdentifiers($entity) {
    $existing = $this->loadIdentifiers($entity);
    if ($entity->identifiers == $existing) {
      return FALSE;
    }
    $this->deleteIdentifiers($entity);
    foreach ($entity->identifiers AS $type => $values) {
      foreach ($values AS $delta => $value) {
        $fields = array(
          'vid' => $entity->vid,
          'type' => $type,
          'delta' => $delta,
          'value' => $value,
        );
        $query = db_insert('intel_visitor_identifier')
          ->fields($fields);
        $query->execute();
      }
    }
    return $entity->identifiers;
  }
  
  public function deleteIdentifiers($entity) {
    $query = db_delete('intel_visitor_identifier')
      ->condition('vid', $entity->vid);
    $query->execute();
  }
  
  /**
   * Delete a single entity.
   *
   * Really a convenience function for delete_multiple().
   */
  public function delete($vid) {
    // hack to solve issue which entity api calling delete with $vid as array
    $vids = is_array($vid) ? $vid : array($vid);
    $this->delete_multiple($vids);
  }

  /**
   * Delete one or more intel_visitor entities.
   *
   * Deletion is unfortunately not supported in the base
   * DrupalDefaultEntityController class.
   *
   * @param $pids
   *   An array of entity IDs or a single numeric ID.
   */
  public function delete_multiple($vids) {
    // delete intel_visitor records
    parent::delete($vids);

    // delete intel_visitor_identifier records
    db_delete('intel_visitor_identifier')
      ->condition('vid', $vids, 'IN')
      ->execute();
  }
}

/**
 * Builds a structured array representing the profile content.
 *
 * @param $account
 *   A user object.
 * @param $view_mode
 *   View mode, e.g. 'full'.
 * @param $langcode
 *   (optional) A language code to use for rendering. Defaults to the global
 *   content language of the current request.
 */
function intel_visitor_build_content(&$entity, $view_mode = 'full', $langcode = NULL) {
  if (!isset($langcode)) {
    $langcode = $GLOBALS['language_content']->language;
  }
  
  // Remove previously built content, if exists.
  $entity->content = array();

  intel_visitor_build_profile_content_elements($entity);

  // Allow modules to change the view mode.
  $context = array(
    'entity_type' => 'intel_visitor',
    'entity' => $entity,
    'langcode' => $langcode,
  );
  drupal_alter('entity_view_mode', $view_mode, $context);

  // Build fields content.
  field_attach_prepare_view('intel_visitor', array($entity->vid => $entity), $view_mode, $langcode);
  entity_prepare_view('intel_visitor', array($entity->vid => $entity), $langcode);
  $entity->content['fields'] = field_attach_view('intel_visitor', $entity, $view_mode, $langcode);
  //$entity->content += field_attach_view('intel_visitor', $entity, $view_mode, $langcode);

  // Populate $entity->content with a render() array.
  module_invoke_all('intel_visitor_view', $entity, $view_mode, $langcode);
  module_invoke_all('entity_view', $entity, 'intel_visitor', $view_mode, $langcode);

  // Make sure the current view mode is stored if no module has already
  // populated the related key.
  $entity->content += array('#view_mode' => $view_mode);
}

function intel_visitor_build_profile_content_elements($entity) {
  $weight = 0;
  $entity->content['picture'] = array(
    '#markup' => theme('intel_visitor_picture', array('entity' => $entity)),
    '#region' => 'header',
  );
  $entity->content['title'] = array(
    '#markup' => $entity->label(),
    '#region' => 'header',
  );  
  $entity->content['subtitle'] = array(
    '#markup' => $entity->email,
    '#region' => 'header',
  ); 
  $entity->content['header_content'] = array(
    '#region' => 'header',
  );
  $entity->content['header_content'][] = array(
    '#markup' => theme('intel_visitor_social_links', array('entity' => $entity)),
  );
  $entity->content['header_content'][] = array(
    '#markup' => theme('intel_visitor_bio', array('entity' => $entity)),
  );
  $entity->content['location'] = array(
    '#markup' => theme('intel_visitor_location', array('entity' => $entity)),
    '#region' => 'sidebar',
    '#weight' => $weight++,
  );
  $entity->content['browser_environment'] = array(
    '#markup' => theme('intel_visitor_browser_environment', array('entity' => $entity)),
    '#region' => 'sidebar',
    '#weight' => $weight++,
  );
  
  $entity->content['visit_table'] = array(
    '#markup' => theme('intel_visitor_visits_table', array('entity' => $entity)),
    '#weight' => $weight++,
  );
  

  
  // TODO: clean this up and put into themeing functions
  
  $vdata = $entity->data;

  $emailclicks = 0;
  if (variable_get('intel_track_emailclicks', INTEL_TRACK_EMAILCLICKS_DEFAULT)) {
    $filter = array(
      'conditions' => array(
        array('c.vid', $entity->identifiers['vid'], 'IN'),
      ),
    );
    $result = intel_load_filtered_emailclick_result($filter);
    $rows = array();
    $calls = array();
    $clicks = array();
    while ($row = $result->fetchObject()) {
      $clicks[$row->cid] = $row;
    }
    if (!empty($clicks)) {
      uasort($clicks, function ($a, $b) {
          return ($a->clicked < $b->clicked) ? 1 : -1;
        }
      );
    }

    // TODO: move mailchimp info processing to hook and process in mailchimp module

    $email_info = array();
    foreach ($clicks AS $row) {
      $emaildesc = $row->eid;
      $ops = array();
      $ops[] = l(t('meta'), 'emailclick/' . $row->cid);
      $title = 'NA';

      if ($row->type == 'mailchimp') {
        if (!isset($email_info['mailchimp'])) {
          $email_info['mailchimp'] = array();
        }
        if (!isset($email_info['mailchimp'][$row->eid])) {
          $campaigns = intel_mailchimp_api_campaigns_list_by_campaign_id($row->eid);
          if (isset($campaigns[$row->eid])) {
            $email_info['mailchimp'][$row->eid] = $campaigns[$row->eid];
          }
        }
        if (isset($email_info['mailchimp'][$row->eid]['archive_url_long'])) {
          $link_options = array(
            'attributes' => array(
              'target' => 'mailchimp',
            ),
          );
          $emaildesc = l( $campaigns[$row->eid]['title'], $campaigns[$row->eid]['archive_url_long'], $link_options);
        }
      }

      $row = array(
        format_date($row->clicked, 'medium'),
        $row->type,
        $emaildesc,
        implode(' ', $ops),
      );
      $rows[] = $row;
    }
    if (count($rows)) {
      $tvars = array();
      $tvars['rows'] = $rows;
      $emailclicks = count($rows);
      $tvars['header'] = array(
        t('Clicked'),
        t('Type'),
        t('Email'),
        t('Ops'),
      );
      $table = theme('table', $tvars);
      $entity->content['emailclicks_table'] = array(
        '#markup' => theme('intel_visitor_profile_block', array('title' => t('Email clicks'), 'markup' => $table)),
        '#weight' => $weight++,
      );
    }
  }

  $phonecalls = 0;
  if (variable_get('intel_track_phonecalls', INTEL_TRACK_PHONECALLS_DEFAULT)) {
    $filter = array(
      'conditions' => array(
        array('c.vid', $entity->identifiers['vid'], 'IN'),
      ),
    );
    $result = intel_load_filtered_phonecall_result($filter);
    $rows = array();
    $calls = array();
    while ($row = $result->fetchObject()) {
      $calls[$row->cid] = $row;
    }
    uasort($calls, function ($a, $b) {
        return ($a->initiated < $b->initiated) ? 1 : -1;
      }
    );
    foreach ($calls AS $row) {
      $ops = array();
      $ops[] = l(t('meta'), 'phonecall/' . $row->cid);
      $title = 'NA';
      $rows[] = array(
        format_date($row->initiated, 'medium'),
        $row->type,
        $row->to_num,
        $row->from_num,
        implode(' ', $ops),
      );
    }
    if (count($rows)) {
      $tvars = array();
      $tvars['rows'] = $rows;
      $phonecalls = count($rows);
      $tvars['header'] = array(
        t('Call date'),
        t('Type'),
        t('To'),
        t('From'),
        t('Ops'),
      );
      $table = theme('table', $tvars);
      $entity->content['phonecalls_table'] = array(
        '#markup' => theme('intel_visitor_profile_block', array('title' => t('Phone calls'), 'markup' => $table)),
        '#weight' => $weight++,
      );
    }
  }
  
  $form_submissions = 0;
  // generate form submission data
  if (module_exists('webform')) { 
    require_once drupal_get_path('module', 'webform') . "/includes/webform.submissions.inc";
  }
  $filter = array(
    'conditions' => array(
      array('s.vid', $entity->identifiers['vid'], 'IN'),
    ),
  );
  $ignore_field = array(
    'intel_submit_data' => 1
  );
  $result = intel_submission_load_filtered($filter);
  $rows = array();
  $wf_nodes = array();
  $items = array();
  $subs = array();
  while ($row = $result->fetchObject()) {
    $subs[$row->sid] = $row;
  }
  uasort($subs, function ($a, $b) {
      return ($a->submitted < $b->submitted) ? 1 : -1;
    }
  );  
  foreach ($subs AS $row) {
    $ops = array();
    $ops[] = l(t('meta'), 'submission/' . $row->sid);
    $title = 'NA';
    if ($row->type == 'webform') {
      if (!isset($wf_nodes[$row->fid])) {
        $wf_nodes[$row->fid] = node_load($row->fid);
      }
      $title = $wf_nodes[$row->fid]->title;
      $d = webform_get_submission($row->fid, $row->fsid);
      if (isset($d->data) && is_array($d->data)) {
        $comps = $wf_nodes[$row->fid]->webform['components'];
        foreach ($d->data AS $i => $v) {
          if (!empty($ignore_field[$comps[$i]['form_key']])) {
            continue;
          }
          $items['webform-field-' . $comps[$i]['form_key']] = array(
            '#title' => $comps[$i]['name'],
            '#markup' => $v['value'][0],
            '#theme' => 'intel_visitor_profile_item',
          );
        }
      }
      $ops[] = l(t('data'), 'node/' . $row->fid . '/submission/' . $row->fsid);
    }
    elseif ($row->type == 'disqus_comment') {
      $title = t('Comment');
      $a = explode('#', substr($row->details_url, 1));
      $options = array(
        'fragment' => isset($a[1]) ? $a[1] : '',
      );
      $ops[] = l(t('data'), $a[0], $options);
    }
    elseif ($row->type == 'hubspot') {
      $form_name = intel_hubspot_get_form_name($row->fid);
      if ($form_name) {
        $title = $form_name;
      }
    }
    $rows[] = array(
      format_date($row->submitted, 'medium'),
      $row->type,
      $title,
      implode(' ', $ops),
    );  
  }
  if (count($rows)) {
    $tvars = array();
    $tvars['rows'] = $rows;
    $form_submissions = count($rows);
    $tvars['header'] = array(
      t('Submission date'),
      t('Type'),
      t('Form'),
      t('Ops'),
    );
    $table = theme('table', $tvars);
    $entity->content['submissions_table'] = array(
      '#markup' => theme('intel_visitor_profile_block', array('title' => t('Form submissions'), 'markup' => $table)),
      '#weight' => $weight++,
    );
    if (count($items)) {
      $markup = '';
      foreach ($items AS $item) {
        $markup .= render($item);
      }
      $entity->content['submissions_fields_table'] = array(
        '#markup' => theme('intel_visitor_profile_block', array('title' => t('Submitted data fields'), 'markup' => $markup)),
        '#weight' => $weight++,
      );
    }    
  }

  $stats = array(); 
  if (!empty($vdata['analytics_visits'])) {
    $visits = $vdata['analytics_visits']['_totals'];
    if (!empty($visits['score'])) {
      $stats[] = array(
        'value' => number_format($visits['score'], 2),
        'title' => t('value score'),
        'class' => 'score',
      );
    }
    if (isset($visits['entrance']['entrances'])) {
      $stats[] = array(
        'value' => number_format($visits['entrance']['entrances']),
        'title' => t('visits'),
        'class' => 'visits',
      );
    }
    if (isset($visits['entrance']['pageviews'])) {
      $stats[] = array(
        'value' => number_format($visits['entrance']['pageviews']),
        'title' => t('page views'),
      );
    }
    if (isset($visits['entrance']['timeOnSite'])) {
      $value = ($visits['entrance']['timeOnSite'] > 3600) ? Date('G:m:s', $visits['entrance']['timeOnSite']) : Date('m:s', $visits['entrance']['timeOnSite']);
      $stats[] = array(
        'value' => $value,
        'title' => t('time on site'),
      );
    }
  }
  $stats[] = array(
    'value' => $form_submissions,
    'title' => t('form submissions'),
  );

  if (variable_get('intel_track_emailclicks', INTEL_TRACK_EMAILCLICKS_DEFAULT)) {
    $stats[] = array(
      'value' => $emailclicks,
      'title' => t('email clicks'),
    );
  }

  if (variable_get('intel_track_phonecalls', INTEL_TRACK_PHONECALLS_DEFAULT)) {
    $stats[] = array(
      'value' => $phonecalls,
      'title' => t('phone calls'),
    );
  }

  if (isset($vdata['klout']) && isset($vdata['klout']['score'])) {
    $stats[] = array(
      'value' => number_format($vdata['klout']['score']),
      'title' => t('Klout score'),
    );
  }
  if (isset($vdata['twitter']) && isset($vdata['twitter']['followers'])) {
    $stats[] = array(
      'value' => number_format($vdata['twitter']['followers']),
      'title' => t('Twitter followers'),
    );
  }
  $markup = '';
  foreach ($stats AS $stat) {
    $markup .= theme('intel_visitor_summary_item', $stat);
  }
  $entity->content['summary'] = array(
    '#markup' => $markup,
    '#region' => 'summary',
  ); 
}