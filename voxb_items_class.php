<?php
/** Helper class for fetchDataRequest. Might come in handy
 * on further refactoring
 *
 * Here is a sample of a voxb_item from database
    [USERID] => 123782
    [OBJECTID] => 11693
    [RATING] => 80
    [CREATION_DATE] => 11-12-11
    [MODIFICATION_DATE] => 11-12-11
    [DISABLED] => 1
    [ITEMIDENTIFIERVALUE] => 6099647

 * Notice: ITEMIDENTIFIERVALUE is ITEMID in other tables voxb_objects, voxb_tag, summary, reviews ...
 */


/*
 * TODO
 * log 
 */

class voxb_items{
  private $oci;
  private $params;
  private static $items;
  private $error;

  public function __construct($oci, $params){   
    $this->oci = $oci;
    $this->params = $params;     
  
    $this->setItems();
  }
  
  public function setItems(){   
    $type = $this->getItemType($this->params);
    switch($type){
    case 'latestReviews':
      self::$items = $this->latestReviews();
      break;
    case 'findObjects':
      self::$items = $this->getObjects();
      break;
    case 'voxbItems':
      self::$items = $this->getVoxbItems();
      break;
    default:
      // @TODO set an appropiate message
      $this->error = ERROR_FETCHING_ITEM_FROM_DATABASE;
      break;
    }

  }

  private function getInstitutionId(){
    $institutionId = isset($this->params->institutionId) ? $this->params->institutionId : NULL;
    $ids = NULL;
    if(isset($institutionId)){
      if(!is_array($institutionId)){
	$institutionId = array($institutionId);
      }
      foreach($institutionId as $institution){
	$ids[] = $institution->_value;
      }
    }
    return $ids;
  }
 
  public function getError(){
    return $this->error;
  }  
 
  /** Get data for items. For now get all the data and filter later
   *
   * @TODO only get the data needed
   */
  public function data(){   
    $data = self::$items;
    // NOTICE item array passed as reference
    if(!empty($data)){
      foreach($data as $identifier=>&$item){
	$this->setUserData($item['ITEMDATA']);
	$this->setReviews($item['ITEMDATA']);
	$this->setLocals($item['ITEMDATA']);
	$this->setTags($item['ITEMDATA']);     
      }
      return $data;
    } 
    else{
      $this->error = COULD_NOT_FIND_ITEM;
    }   
  } 

  /**
   *
   */
  private function getVoxbItems(){
    $fetchData = $this->params->fetchData;
    if(!is_array($fetchData)){
      $fetchData = array($fetchData);
    }
    foreach($fetchData as $fetch){
      $itemIds[] = $fetch->_value->voxbIdentifier->_value;
    }

    return $this->getItemsFromIds($itemIds);    
  }

  /** Get the columns to select. 
   *
   */
  private function itemColumns(){
    $columns = 
      array('u.USERID','i.OBJECTID', 'i.RATING', 'i.CREATION_DATE', 'i.MODIFICATION_DATE', 'i.DISABLED' ,'i.ITEMIDENTIFIERVALUE');
    return $columns;
  }

  /** Get the tables to select from. We need voxb_items and voxb_users tables
   * Do a LEFT join, since we don't need the columns from voxb_users at this time
   */
  private function itemTables(){
    $tables = 'voxb_items i LEFT JOIN voxb_users u ON i.userid = u.userid';
    return $tables;
  }

  /** Filter clause for sql. 
   * Do not return items where either item or user has been disabled   
   * Check if institutionid was given. If so filter on that
   */
  private function filterClause(){
    $clause = 'i.disabled IS NULL AND u.disabled IS NULL';
    $institutionId = $this->getInstitutionId();
   
    if(isset($institutionId)){
      $clause.=' AND u.institutionId IN ('.implode(',',$institutionId).')';
    }

    return $clause;
  }

  private function getItemsFromIds($itemIds){
    $columns = implode(',',$this->itemColumns());    
    $tables = $this->itemTables();
    $filter = $this->filterClause();
    try {
      $this->oci->set_query('SELECT '.$columns.' from '.$tables.' WHERE i.ITEMIDENTIFIERVALUE in ('.implode(',',$itemIds).') AND '.$filter);
      while ($row = $this->oci->fetch_into_assoc()) {
	$item_id = $row['ITEMIDENTIFIERVALUE'];
	$data[$item_id]['ITEMIDENTIFIERVALUE'] = $itemid;
	$data[$item_id]['ITEMDATA'][$item_id] = $row;
      }
    }
    catch (ociException $e) {
      $this->error = ERROR_FETCHING_ITEM_FROM_DATABASE;
      verbose::log(FATAL, "fetchData(".__LINE__."):: OCI select error: " . $this->oci->get_error_string());
    } 
    return $data;    
  }

  private function getObjects(){  
    $fetchData = $this->params->fetchData;
    if(!is_array($fetchData)){
      $fetchData = array($fetchData);
    }

    foreach($fetchData as $object){
      $identifier['idType'] = $object->_value->objectIdentifierType->_value;
      $identifier['idValue'] = $object->_value->objectIdentifierValue->_value;
      $this->normalizeIdentifier($identifier);

      $objectIdentifiers[] = $identifier;
    }

    $objects = new voxb_objects($this->oci);
    $ids =  $objects->getObjectIdsFromIdentifiers($objectIdentifiers);

    return $this->getItemsFromObjectIds($ids);   
  }

 /** Normalize identifer value
   *
   * @Param array identifier [idValue,idType]
   */
  protected function normalizeIdentifier(&$identifier){
    switch($identifier['idType']) {
    case "ISBN":
      $identifier['idValue']=materialId::normalizeISBN($identifier['idValue']);
      //$identifier['idValue']=materialId::convertISBNToEAN($identifier['idValue']);
      break;
    case "ISSN":
      $identifier['idValue']=materialId::normalizeISSN($identifier['idValue']);
      break;
    case "EAN":
      $identifier['idValue']=materialId::normalizeEAN($identifier['idValue']);
      break;
    case "FAUST":
      $identifier['idValue']=materialId::normalizeFAUST($identifier['idValue']);
      break;
    }
  }


  /** Get voxb_items from given objectIds
   *
   * @param array $objectIds [OBJECTID => [OBJECTIDENTIFIERTYPE,OBJECTIDENTIFIERVALUE]]
   * @return array[identifier=>[itemid=>[itemvalue]]];
   */
  private function getItemsFromObjectIds($objectIds){ 
    $ids = array_keys($objectIds);
    if(empty($ids)){
      $this->error = COULD_NOT_FIND_ITEM;
      return;
    }
    $columns = implode(',',$this->itemColumns());
    $tables = $this->itemTables();
    $filter = $this->filterClause();  
    try {
      $this->oci->set_query('SELECT '.$columns.' from '.$tables.' where i.objectid in ('.implode(',',$ids).') AND '.$filter);
      while ($row = $this->oci->fetch_into_assoc()) {
	$item_id = $row['ITEMIDENTIFIERVALUE'];
	// enrich with OBJECTIDENTIFIERVALUE and OBJECTIDENTIFIERTYPE
	$object_id = $row['OBJECTID'];
	$data[$object_id]['OBJECTIDENTIFIERTYPE'] = $objectIds[$object_id]['OBJECTIDENTIFIERTYPE'];
	$data[$object_id]['OBJECTIDENTIFIERVALUE'] = $objectIds[$object_id]['OBJECTIDENTIFIERVALUE'];
	$data[$object_id]['ITEMDATA'][$item_id] = $row;
      }
    }
    catch (ociException $e) {
      echo $e->getMessage();
      $this->error = ERROR_FETCHING_ITEM_FROM_DATABASE;
      verbose::log(FATAL, "fetchData(".__LINE__."):: OCI select error: " . $this->oci->get_error_string());
    } 

    return $data;
  }
 
  /** Enrich data array with tags
   * @TODO .. does this method belong here ..?? 
   */
  private function setTags(&$data){
     $item_ids = array_keys($data);
     try {
       $this->oci->set_query('select * from voxb_tags where ITEMID in ('.implode(',',$item_ids).')');
      while ($row = $this->oci->fetch_into_assoc()) {
	$item_id = $row['ITEMID'];
	$data[$item_id]['TAGS'][] = $row;
      }
    } catch (ociException $e) {
       $this->error = ERROR_FETCHING_ITEM_FROM_DATABASE;
       verbose::log(FATAL, "fetchData(".__LINE__."):: OCI select error: " . $this->oci->get_error_string());
    } 
  }

  /** Enrich data array with local
   * @TODO .. does this method belong here ..?? 
   */
  private function setLocals(&$data){
    $item_ids = array_keys($data);
    try {
      $this->oci->set_query('SELECT * from voxb_locals where itemid in ('.implode(',',$item_ids).')');
      while ($row = $this->oci->fetch_into_assoc()) {
	$item_id = $row['ITEMID'];
	$data[$item_id]['LOCALS'][] = $row;	
      }
    }
    catch (ociException $e) {
      $this->error = ERROR_FETCHING_ITEM_FROM_DATABASE;
      verbose::log(FATAL, "fetchData(".__LINE__."):: OCI select error: " . $this->oci->get_error_string());
    } 
  }

  /** Enrich data array with user
   * @TODO .. does this method belong here ..?? 
   */
  private function setUserData(&$data){
    foreach($data as $item){
      $userids[] = $item['USERID'];
    }
    try {
      $this->oci->set_query('SELECT * from voxb_users where userid in ('.implode(',',$userids).')');
      while ($row = $this->oci->fetch_into_assoc()) {
	foreach( $data as $item_id => $item ){
	  if (in_array($row['USERID'], $item)){	   
	    $data[$item_id]['USER'] = $row;	    
	  }
	}
      }
    }
    catch (ociException $e) {
      $this->error = ERROR_FETCHING_ITEM_FROM_DATABASE;
      verbose::log(FATAL, "fetchData(".__LINE__."):: OCI select error: " . $this->oci->get_error_string());
    } 
  }

  /** Enrich data array with reviews
   *
   */
  // @TODO .. does this method belong here ..?? 
  private function setReviews(&$data){
    $item_ids = array_keys($data);
     try {
       $this->oci->set_query('SELECT * from voxb_reviews where itemid in ('.implode(',',$item_ids).')');
      while ($row = $this->oci->fetch_into_assoc()) {
	$item_id = $row['ITEMID'];
	$data[$item_id]['REVIEWS'][] = $row;
      }
    }
    catch (ociException $e) {
      $this->error = ERROR_FETCHING_ITEM_FROM_DATABASE;
      verbose::log(FATAL, "fetchData(".__LINE__."):: OCI select error: " . $this->oci->get_error_string());
    } 
  }    

  /** Set items for latest reviews
   *
   */
  private function latestReviews(){
    $columns = implode(',',$this->itemColumns());
    $tables = $this->itemTables();
    $filter = $this->filterClause();
    $limit = $this->getLatestReviewsLimit($this->params);
    $items = array();
    $this->oci->set_query('select * from('.
			  'select '.$columns.' from '.$tables.' where i.itemidentifiervalue in (select itemid from voxb_reviews) and '.$filter.' order by modification_date desc)'.
			  'where rownum<='.$limit);   
    while($row = $this->oci->fetch_into_assoc()) {
      	$item_id = $row['ITEMIDENTIFIERVALUE'];
	$data[$item_id]['ITEMIDENTIFIERVALUE'] = $item_id;
	$data[$item_id]['ITEMDATA'][$item_id] = $row;
    }   
    return $data;
  }

  /** Get the type of items requested from given params
   *
   */
  private function getItemType($params){
    // latest reviews
    if( isset($params->fetchData->_value->latestReviews)){
      return 'latestReviews';
    }
    // some object
    elseif($this->checkFetchDataElement($params->fetchData,'objectIdentifierType')){
      return 'findObjects';
    }
    // voxbIdentifier
    elseif( $this->checkFetchDataElement($params->fetchData,'voxbIdentifier') ){
      return 'voxbItems';
    }
    
    return 'latestReviews';
  }

  /** Check if given element is set.
   *
   * @param OLS object $fetchData to check in;
   * @param string $elementToCheck
   */
  private function checkFetchDataElement($fetchData, $elementToCheck){
    if( !is_array($fetchData) ){
      $fetchData = array($fetchData);
    }
       
    if(isset($fetchData[0]->_value->$elementToCheck) ){
      return TRUE;
    }
    
    return FALSE;
  }

  private function getLatestReviewsLimit($params){
    $limit = isset( $params->fetchData->_value->latestReviews->_value ) ? $params->fetchData->_value->latestReviews->_value : 10;
    if($limit > 100){
      $limit = 100;
    }
    return $limit;
  }
  
}
?>