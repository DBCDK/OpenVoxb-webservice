<?php
class voxb_objects{
  private $oci;
  private $error;

  public function __construct($oci){
    $this->oci = $oci;
  }

  public function getError(){
    return $this->error;
  }

 
  /** Get objectIds from given identifiers 
   *
   * @param array $objectidentifiers [idValue,idType]
   * @param bool $useXID; whether to lookup additional identifiers on OpenXID
   */
  public function getObjectIdsFromIdentifiers($objectIdentifiers,$useXID = TRUE){
    if( $useXID ){
      $XIDS = $this->getFromOpenXID($objectIdentifiers);
      $objectIdentifiers = $this->arrayMergeValues($XIDS, $objectIdentifiers);     
    } 

    $whereClause = $this->objectSqlWhereClause($objectIdentifiers);   
    $ret = $this->getObjectIdsAndIdentifiers($whereClause);
    
    return $ret;
  }

  /** Merge ids array in XID array if it is not already there.
   *
   * NOTE this method emerged from the fact that OpenXid returns an error
   * on some ids and thus we need to preserve the original object identifiers
   */
  private function arrayMergeValues($XIDs, $ids) {
    foreach($ids as $key=> $id){
      if( !($this->in_multiarray($id['idValue'], $XIDs))  ){
	$XIDs[] = $id;
      }
    }
    return $XIDs;
  }

  /** Check if needle is in multidimensional array
   *
   */
  private function in_multiarray($needle, $array) {
    foreach ($array as $key => $value) {
      if ($value==$needle){
	return true;
      }
      elseif(is_array($value)){
	if($this->in_multiarray($needle, $value)){
	  return true;
	}
      }
    }       
    return false;
  }

  /** Get object ids and identifiers 
   * @param string $whereClause; the sql filter to apply
   * @return array [objectid=>[OBJECTIDENTIFIERVALUE,OBJECTIDENTIFIERTYPE]]
   */
  private function getObjectIdsAndIdentifiers($whereClause){
    $ids = array();
    try {
       $this->oci->set_query('SELECT objectid, OBJECTIDENTIFIERVALUE, OBJECTIDENTIFIERTYPE FROM voxb_objects where '.$whereClause );       
      while ($data = $this->oci->fetch_into_assoc()) {
	$id = $data['OBJECTID'];
	$ids[$id] = $data;
      }
    }
    catch (ociException $e) {
      verbose::log(FATAL, "voxb_objects(".__LINE__."):: OCI select error: " . $this->oci->get_error_string());
      $this->error = ERROR_FETCHING_OBJECT_FROM_DATABASE;
      return array();
    }
    return $ids;
  }

 
  /** Set the where clause for retrieving object data - that is which objects to select
   * 
   * @param array $objectIdentifiers; [idType,idValue]
   * @return string; sql WHERE clause 
   */
  private function objectSqlWhereClause($objectIdentifiers){
    $where = '';
    foreach($objectIdentifiers as $id){
      if( strlen($where) > 1 ){
	$where.=' OR ';
      }
      $where.= '(OBJECTIDENTIFIERTYPE = \''.strtoupper($id["idType"]).'\' AND OBJECTIDENTIFIERVALUE = \''.$id["idValue"].'\')';
    }
     return $where;
  } 

  /** Get additional object identifiers via OpenXID
   * 
   * @param array $objectIdentifiers [idType, idValue]
   * return array [idType, idValue]
   *
   * @TODO error handling
   */
  private function getFromOpenXID($objectIdentifiers){
    $url = voxb::$OpenXidUrl.'/';
    $identifiers = openXidWrapper::sendGetIdsRequest($url, $objectIdentifiers);   
    return $this->parseOpenXID($identifiers);    
  }

  private function parseOpenXID($identifiers){
    $ret = array();
    foreach($identifiers as $id){
      if(!isset($id['error'])){
	$ret = array_merge($ret,$id['ids']['id']);
      }        
    }     

    return $ret;  
  }
}
?>