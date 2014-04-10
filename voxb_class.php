<?php

require_once("./OLS_class_lib/webServiceServer_class.php");
require_once("./OLS_class_lib/oci_class.php");
require_once("./OLS_class_lib/material_id_class.php");

require_once('openXidWrapper_class.php');
require_once('voxb_logger_class.php');
require_once("voxb_constants.php");

require_once("voxb_items_class.php");
require_once('voxb_objects_class.php');

$voxb_error = array(
  CONTENTTYPES_MISSING => "contentTypes missing",
  COULD_NOT_CREATE_DATA_USERID_DID_NOT_EXIST => "Could not create data, userID did not exist",
  COULD_NOT_CREATE_USER => "Could not create user",
  COULD_NOT_FIND_ITEM => "Could not find item",
  COULD_NOT_FIND_ITEM_FOR_USER => "Could not find item for user",
  COULD_NOT_FIND_ITEMS => "Could not find items",
  COULD_NOT_FIND_OBJECT => "Could not find object",
  COULD_NOT_FIND_OBJECT_ORPHANED_ITEMS_SHOULD_BE_DELETED => "Could not find object. Orphaned items should be deleted",
  COULD_NOT_FIND_USER => "Could not find user",
  COULD_NOT_LOCATE_USER_WITH_GIVEN_FINGERPRINT => "Could not locate user with given fingerprint",
  EMPTY_QUALIFIER => "Empty qualifier",
  EMPTY_SEARCHSTRING => "Empty searchstring",
  ERROR_DELETING_ITEM_FROM_DATABASE => "Error deleting item from database",
  ERROR_DELETING_USER_FROM_DATABASE => "Error deleting user from database",
  ERROR_FETCHING_ITEM_FROM_DATABASE => "Error fetching item from database",
  ERROR_FETCHING_ITEMS_FROM_DATABASE => "Error fetching items from database",
  ERROR_FETCHING_LOCALS_DATA_FROM_DATABASE => "Error fetching locals data from database",
  ERROR_FETCHING_OBJECT_FROM_DATABASE => "Error fetching object from database",
  ERROR_FETCHING_REVIEW_DATA_FROM_DATABASE => "Error fetching review data from database",
  ERROR_FETCHING_USER_FROM_DATABASE => "Error fetching user from database",
  ERROR_INSERTING_COMPLAINT_INTO_DATABASE => "Error inserting complaint into database",
  ERROR_INSERTING_ITEM_IN_DATABASE => "Error inserting item in database",
  ERROR_INSERTING_LOCALS_IN_DATABASE => "Error inserting locals in database",
  ERROR_INSERTING_OBJECT_IN_DATABASE => "Error inserting object in database",
  ERROR_INSERTING_REVIEW_IN_DATABASE => "Error inserting review in database",
  ERROR_INSERTING_TAGS_IN_DATABASE => "Error inserting tags in database",
  ERROR_UNDELETING_ITEM_FROM_DATABASE => "Error undeleting item from database",
  ERROR_UNDELETING_USER_FROM_DATABASE => "Error undeleting user from database",
  ERROR_UPDATING_ITEMS_IN_DATABASE => "Error updating items in database",
  ERROR_UPDATING_LOCALS_IN_DATABASE => "Error updating locals in database",
  ERROR_UPDATING_OBJECTS_IN_DATABASE => "Error updating objects in database",
  ERROR_UPDATING_REVIEW_IN_DATABASE => "Error updating review in database",
  ERROR_UPDATING_TAGS_IN_DATABASE => "Error updating tags in database",
  FINGERPRINT_NOT_VALID => "Fingerprint not valid",
  FOUND_NO_ITEM_FROM_GIVEN_VOXBIDENTIFIER => "Found no item from given voxbIdentifier",
  FOUND_NO_INSTITUTIONID_FROM_GIVEN_ITEMIDENTIFIERVALUE => "Found no institutionId from given ItemiIdentifierValue",
  NO_FIELDS_TO_UPDATE => "No fields to update",
  NO_ITEMS_OR_OBJECTS_TO_FETCH => "No items or objects to fetch",
  NO_USER_FOUND_WITH_GIVEN_ID => "No user found with given id",
  QUALIFIER_NOT_YET_IMPLEMENTED => "Qualifier not yet implemented",
  UNKNOWN_QUALIFIER => "Unknown qualifier",
  USERID_INVALID_MUST_BE_AN_INTEGER => "userID invalid. Must be an integer",
  VOXBIDENTIFIER_INVALID_MUST_BE_AN_INTEGER => "voxbIdentifier invalid. Must be an integer",
  USER_ALREADY_EXISTS => "User already exists",
  AUTHENTICATION_ERROR => "Authentication error",
  COULD_NOT_REACH_DATABASE => "Could not reach database",
  NO_USER_FOUND_WITH_GIVEN_FINGERPRINT => "No user found with given fingerprint",
);

$voxb_qualifiers = array(
  'VOXB' => QUALIFIER_VOXB,
  'LOCAL' => QUALIFIER_LOCAL,
  'EAN' => QUALIFIER_EAN,
  'ISBN' => QUALIFIER_ISBN,
  'ISSN' => QUALIFIER_ISSN,
  'TAG' => QUALIFIER_TAG,
  'TITLE' => QUALIFIER_TITLE,
  'CONTRIBUTOR' => QUALIFIER_CONTRIBUTOR,
  'TIMESTAMPMIN' => QUALIFIER_TIMESTAMPMIN,
  'TIMESTAMPMAX' => QUALIFIER_TIMESTAMPMAX,
  'USERID' => QUALIFIER_USERID,
);







class voxb extends webServiceServer {
  var $content;
  var $response;
  var $log;

  public static $OpenXidUrl;



  /** createMyData
   *
   *
   * Statistics (logging)
   * - userId
   * - p1 = antal ratings (0 eller 1)
   * - p2 = antal tags
   * - p3 = antal reviews (0 eller 1)
   * - p4 = antal locals
   *
   */
  function createMyData($params) {
    if ($error = $this->_prolog(__FUNCTION__) ) {
      return self::_error($error);
    }

    $userId = $params->userId->_value;
    $this->log->set_userId($userId);
    $rating = min($params->item->_value->rating->_value, 100);
    $this->log->add_p1($rating ? 1 : 0);

    if (isset($params->item->_value->tags->_value->tag)) {
      if (is_array($params->item->_value->tags->_value->tag))  {
         foreach($params->item->_value->tags->_value->tag as $k=>$v) {
            $tags[] = str_replace("'", "''", strtolower(trim(strip_tags($v->_value))));
        }
      } else {
        $tags[] = str_replace("'", "''", strtolower(trim(strip_tags($params->item->_value->tags->_value->tag->_value))));
      }
    }
    $this->log->add_p2(count($tags));

    if (!empty($params->item->_value->review)) {
      $review['Title'] = str_replace("'", "''", substr(strip_tags($params->item->_value->review->_value->reviewTitle->_value), 0, 256));
      $review['Data'] = str_replace("'", "''", strip_tags($params->item->_value->review->_value->reviewData->_value));
      $review['Type'] = str_replace("'", "''", strip_tags($params->item->_value->review->_value->reviewType->_value));
      $this->log->add_p3(1);
    } else {
      $this->log->add_p3(0);
    }

    if ( !empty($params->item->_value->local)) {
      if ( is_array($params->item->_value->local)) {
        foreach ($params->item->_value->local as $i=>$loc) {
          $local[$i]['Data'] = str_replace("'", "''", strip_tags($loc->_value->localData->_value));
          $local[$i]['Type'] = str_replace("'", "''", strip_tags($loc->_value->localType->_value));
          $local[$i]['ItemType'] = str_replace("'", "''", strip_tags($loc->_value->localItemType->_value));
        }
      } else {
        $local[0]['Data'] = str_replace("'", "''", strip_tags($params->item->_value->local->_value->localData->_value));
        $local[0]['Type'] = str_replace("'", "''", strip_tags($params->item->_value->local->_value->localType->_value));
        $local[0]['ItemType'] = str_replace("'", "''", strip_tags($params->item->_value->local->_value->localItemType->_value));
      }
    }
    $this->log->add_p4(count($local));

    if (!empty($params->object)) {
      $object['IdentifierValue'] = str_replace("'", "''", strip_tags($params->object->_value->objectIdentifierValue->_value));
      $object['IdentifierType'] = str_replace("'", "''", strip_tags($params->object->_value->objectIdentifierType->_value));
      $object['Title'] = str_replace("'", "''", strip_tags($params->object->_value->objectTitle->_value));
      $object['Contributors'] = str_replace("'", "''", strip_tags($params->object->_value->objectContributors->_value));
      $object['MaterialType'] = str_replace("'", "''", strip_tags($params->object->_value->objectMaterialType->_value));
      $object['PublicationYear'] = str_replace("'", "''", strip_tags($params->object->_value->objectPublicationYear->_value));
    }

    switch($object['IdentifierType']) {
    			case "ISBN":
            $object['IdentifierValue']=materialId::normalizeISBN($object['IdentifierValue']);
            //$object['IdentifierValue']=materialId::convertISBNToEAN($object['IdentifierValue']);
          break;
          case "ISSN":
            $object['IdentifierValue']=materialId::normalizeISSN($object['IdentifierValue']);
          break;
          case "EAN":
            $object['IdentifierValue']=materialId::normalizeEAN($object['IdentifierValue']);
          break;
          case "FAUST":
            $object['IdentifierValue']=materialId::normalizeFAUST($object['IdentifierValue']);
          break;
        }

    // check userId exists
    try {
      $this->oci->bind("userId", $userId);
      $this->oci->set_query("SELECT * FROM voxb_users WHERE userId=:userId AND disabled IS NULL");
      $data = $this->oci->fetch_into_assoc();
    } catch (ociException $e) {
      verbose::log(FATAL, "createMyData(".__LINE__."):: OCI select voxb_users error: " . $this->oci->get_error_string());
      return self::_error(ERROR_FETCHING_USER_FROM_DATABASE);
    }

    // Kunne en bruger findes?
    if (empty($data)) {
      return self::_error(COULD_NOT_CREATE_DATA_USERID_DID_NOT_EXIST);
    }

    // look for existing object
    try {
      $this->oci->bind("objectIdentifierValue", $object['IdentifierValue']);
      $this->oci->bind("objectIdentifierType", $object['IdentifierType']);
      $this->oci->set_query("SELECT objectId as ID FROM voxb_objects WHERE objectIdentifierValue=:objectIdentifierValue AND objectIdentifierType=:objectIdentifierType");
      $data = $this->oci->fetch_into_assoc();
    } catch (ociException $e) {
      verbose::log(FATAL, "createMyData(".__LINE__."):: OCI select voxb_object error: " . $this->oci->get_error_string());
      return self::_error(ERROR_FETCHING_OBJECT_FROM_DATABASE);
    }

    // if not exists, create object
    if (empty($data)) {
      try {
        $this->oci->set_query("INSERT INTO voxb_objects (objectIdentifierValue, objectIdentifierType, objectTitle, objectContributors, objectMaterialType, objectPublicationYear) VALUES ('" .
                        $object['IdentifierValue'] . "', '" .
                        $object['IdentifierType'] . "', '" .
                        $object['Title'] . "', '" .
                        $object['Contributors'] . "', '" .
                        $object['MaterialType'] . "', '" .
                        $object['PublicationYear'] . "')");
        $this->oci->set_query("SELECT voxb_objects_seq.currval as ID FROM dual");
        $data = $this->oci->fetch_into_assoc();
      } catch (ociException $e) {
        verbose::log(FATAL, "createMyData(".__LINE__."):: OCI insert voxb_object error: " . $this->oci->get_error_string());
        return self::_error(ERROR_INSERTING_OBJECT_IN_DATABASE);
      }
    }
    $objectId = $data['ID'];

    // create item
    try {
      $this->oci->set_query("INSERT INTO voxb_items (userId, objectId, rating) VALUES ('$userId', '$objectId', '$rating')");
      $this->oci->commit();
      $this->oci->set_query("SELECT voxb_items_seq.currval as ID FROM dual");
      $data = $this->oci->fetch_into_assoc();
      $itemId = $data['ID'];
    } catch (ociException $e) {
      return self::_error(ERROR_INSERTING_ITEM_IN_DATABASE);
    }

    if (!empty($review)) {
      try {
        $this->oci->set_query("INSERT INTO voxb_reviews (itemId, title, type, data) VALUES ('" .
                        $itemId . "', '" .
                        $review['Title'] . "', '" .
                        $review['Type'] . "', '" .
                        $review['Data'] . "')" );
        $this->oci->commit();
      } catch (ociException $e) {
        return self::_error(ERROR_INSERTING_REVIEW_IN_DATABASE);
      }
    }

    if (is_array($local)) {
      foreach ($local as $loc) {
        try {
          $this->oci->set_query("INSERT INTO voxb_locals (itemId, data, type, itemType) VALUES ('" .
                          $itemId . "', '" .
                          $loc['Data'] . "', '" .
                          $loc['Type'] . "', '" .
                          $loc['ItemType'] . "')" );
          $this->oci->commit();
        } catch (ociException $e) {
          return self::_error(ERROR_INSERTING_LOCALS_IN_DATABASE);
        }
      }
    }

    if (is_array($tags)) {
      foreach ($tags as $tag) {
        try {
          $this->oci->set_query("INSERT INTO voxb_tags (itemId, tag) VALUES ('" . $itemId . "', '" . $tag . "')" );
          $this->oci->commit();
        } catch (ociException $e) {
          return self::_error(ERROR_INSERTING_TAGS_IN_DATABASE);
        }
      }
    }

    $this->_end_node($this->content, "voxbIdentifier", $itemId);
    return $this->_epilog($this->response);
  }



  /** createUser
   *
   *
   * Statistics (logging)
   * - userId
   * - p1 = 1: bruger oprettet
   * - p1 = 2: userAliasSuggestion foreslået
   * 
   */
  function createUser($params) {
    if ($error = $this->_prolog(__FUNCTION__) ) {
      return self::_error($error);
    }

    $aliasName = str_replace("'", "''", strip_tags($params->userAlias->_value->aliasName->_value));
    $profileurl = str_replace("'", "''", strip_tags($params->userAlias->_value->profileLink->_value));
    $userIdentifierValue = str_replace("'", "''", strip_tags($params->authenticationFingerprint->_value->userIdentifierValue->_value));
    $userIdentifierType = str_replace("'", "''", strip_tags($params->authenticationFingerprint->_value->userIdentifierType->_value));
    $identityProvider = str_replace("'", "''", strip_tags($params->authenticationFingerprint->_value->identityProvider->_value));
    $institutionId = str_replace("'", "''", strip_tags($params->authenticationFingerprint->_value->institutionId->_value));

    if ($userIdentifierType=="CPR") {
      $userIdentifierValue = md5($this->_normalize_cpr($userIdentifierValue) . $this->config->get_value("salt", "setup"));
    }

    try {
      $sql = "SELECT alias_name, profileurl FROM voxb_users WHERE (alias_name='$aliasName' AND profileurl='$profileurl')";
      $this->oci->set_query($sql);
      $data = $this->oci->fetch_into_assoc();
    } catch (ociException $e) {
      return self::_error(COULD_NOT_CREATE_USER);
    }

    if (!empty($data)) {
      // alias name already used - find another alias name
      for ($i=1; $i<100; $i++) {
        $suggested_alias = $aliasName . " " . $i;
        try {
          $sql = "SELECT alias_name, profileurl FROM voxb_users WHERE (alias_name='$suggested_alias' AND profileurl='$profileurl')";
          $this->oci->set_query($sql);
          $data = $this->oci->fetch_into_assoc();
        } catch (ociException $e) {
          return self::_error(COULD_NOT_CREATE_USER);
        }
        if (empty($data)) {  // Gotcha - here is a name, not in use
          $this->log->add_p1(2);  // 1 meaning user created - 2 meaning userAliasSuggestion used
          $this->_end_node($this->content, "userAliasSuggestion", $suggested_alias);
          return $this->_epilog($this->response);
        }
      }
      // At this stage, no usable userAliasSuggestion found - giving up
      return self::_error(USER_ALREADY_EXISTS);  // Searched for 100 alias names - no more attempts will be made now
    }

    try {
      $sql = "INSERT
              INTO voxb_users (alias_name, profileurl, userIdentifierValue, userIdentifierType, identityProvider, institutionId) 
              VALUES ('$aliasName', '$profileurl', '$userIdentifierValue', '$userIdentifierType', '$identityProvider', '$institutionId')";
      $this->oci->set_query($sql);
      $this->oci->commit();
      $this->oci->set_query("SELECT voxb_users_seq.currval AS ID FROM dual");
      $data = $this->oci->fetch_into_assoc();
      $userId = $data['ID'];
      $this->log->set_userId($userId);
    } catch (ociException $e) {
      return self::_error(COULD_NOT_CREATE_USER);
    }

    $this->log->add_p1(1);  // 1 meaning user created - 2 meaning userAliasSuggestion used
    $this->_end_node($this->content, "userId", $userId);
    return $this->_epilog($this->response);
  }



  /** deleteMyData
   *
   *
   * Statistics (logging)
   * - userId
   *
   */
  function deleteMyData($params) {
    if ($error = $this->_prolog(__FUNCTION__) ) {
      return self::_error($error);
    }

    if (!is_numeric($params->voxbIdentifier->_value)) {
      return self::_error(VOXBIDENTIFIER_INVALID_MUST_BE_AN_INTEGER);
    }

    /* fetch by voxbIdentifier */
    $voxbIdentifier = $params->voxbIdentifier->_value;
    $institutionId = str_replace("'", "''", strip_tags($params->institutionId->_value));
    $this->oci->bind('voxbIdentifier', $voxbIdentifier);
    $this->oci->bind('institutionId', $institutionId);

    try {

      $this->oci->set_query("SELECT itemidentifiervalue, objectid, userid FROM voxb_items WHERE itemidentifiervalue=:voxbIdentifier AND disabled IS NULL AND userid IN (select userid from voxb_users where institutionId=:institutionId)");
      $data = $this->oci->fetch_into_assoc();
    } catch (ociException $e) {
      return self::_error(ERROR_FETCHING_ITEM_FROM_DATABASE);
    }

    if (empty($data)) {
      return self::_error(COULD_NOT_FIND_ITEM);
    }
    $this->log->set_userId($data['USERID']);

    try {
      $this->oci->set_query("UPDATE voxb_items SET disabled=1 WHERE itemidentifiervalue=$voxbIdentifier");
      $this->oci->commit();
    } catch (ociException $e) {
      verbose::log(FATAL, "deleteMyData(".__LINE__."):: OCI update error: " . $this->oci->get_error_string());
      return self::_error(ERROR_DELETING_ITEM_FROM_DATABASE);
    }

    /* disabled... remeber to garbage collect!    Skal objekter osse disables når et item er disablet?
    try {
      $objectId = $data['OBJECTID'];
      $this->oci->bind('objectId', $objectId);
      $this->oci->set_query("SELECT itemidentifiervalue FROM voxb_items WHERE objectId=:objectId AND disabled IS NULL");
      $data2 = $this->oci->fetch_into_assoc();
      if (empty($data2)) {
        $this->oci->set_query("UPDATE voxb_objects SET disabled=1 WHERE objectId=$objectId");
      }
    } catch (ociException $e) {
    }
    */

    $this->_end_node($this->content, "voxbIdentifier", $voxbIdentifier);
    return $this->_epilog($this->response);
  }

#

  /** undeleteMyData
   *
   *
   * Statistics (logging)
   * - userId
   *
   */
  function undeleteMyData($params) {
    if ($error = $this->_prolog(__FUNCTION__) ) {
      return self::_error($error);
    }

    if (!is_numeric($params->voxbIdentifier->_value)) {
      return self::_error(VOXBIDENTIFIER_INVALID_MUST_BE_AN_INTEGER);
    }

    $voxbIdentifier = $params->voxbIdentifier->_value;
    $institutionId = str_replace("'", "''", strip_tags($params->institutionId->_value));

    $this->oci->bind('voxbIdentifier', $voxbIdentifier);
    $this->oci->bind('institutionId', $institutionId);

    try {
      $this->oci->set_query("SELECT itemidentifiervalue, userid FROM voxb_items WHERE itemidentifiervalue=:voxbIdentifier AND disabled=1 AND userid IN (select userid from voxb_users where institutionId=:institutionId)");
      $data = $this->oci->fetch_into_assoc();
    } catch (ociException $e) {
      return self::_error(ERROR_FETCHING_ITEM_FROM_DATABASE);
    }

    if (empty($data)) {
      return self::_error(COULD_NOT_FIND_ITEM);
    }
    $this->log->set_userId($data['USERID']);

    try {
      $this->oci->set_query("UPDATE voxb_items SET disabled=NULL WHERE itemidentifiervalue=$voxbIdentifier");
      $this->oci->commit();
    } catch (ociException $e) {
      verbose::log(FATAL, "undeleteMyData(".__LINE__."):: OCI update error: " . $this->oci->get_error_string());
      return self::_error(ERROR_UNDELETING_ITEM_FROM_DATABASE);
    }

    $this->_end_node($this->content, "voxbIdentifier", $voxbIdentifier);
    return $this->_epilog($this->response);
  }



  /**  deleteUser
   *
   *
   * Statistics (logging)
   * - userId
   *
   */
  function deleteUser($params) {
    if ($error = $this->_prolog(__FUNCTION__) ) {
      return self::_error($error);
    }

    if (!is_numeric($params->userId->_value)) {
      return self::_error(USERID_INVALID_MUST_BE_AN_INTEGER);
    }

    $institutionId = str_replace("'", "''", strip_tags($params->institutionId->_value));

    /* fetch by userid */
    $userId = $params->userId->_value;
    $this->log->set_userId($userId);
    $this->oci->bind('userId', $userId);
    $this->oci->bind('institutionId', $institutionId);
    try {
      $this->oci->set_query("SELECT userId FROM voxb_users WHERE userId=:userId AND institutionId=:institutionId AND disabled IS NULL");
      $data = $this->oci->fetch_into_assoc();
    } catch (ociException $e) {
      return self::_error(ERROR_FETCHING_USER_FROM_DATABASE);
    }

    if (empty($data)) {
      return self::_error(COULD_NOT_FIND_USER);
    }

    try { $this->oci->set_query("UPDATE voxb_users SET disabled=1 WHERE userId=$userId AND institutionId=$institutionId"); }
    catch (ociException $e) {
      verbose::log(FATAL, "deleteUser(".__LINE__."):: OCI update error: " . $this->oci->get_error_string());
      return self::_error(ERROR_DELETING_USER_FROM_DATABASE);
    }

    $this->oci->set_query("UPDATE voxb_items SET disabled=1 WHERE userId=$userId");

    try { $this->oci->commit(); }
    catch (ociException $e) { 
      verbose::log(FATAL, "deleteUser(".__LINE__."):: OCI commit error: " . $this->oci->get_error_string());
    }

    $this->_end_node($this->content, "userId", $userId);
    return $this->_epilog($this->response);
  }



  /**  undeleteUser
   *
   *
   * Statistics (logging)
   * - userId
   *
   */
  function undeleteUser($params) {
    if ($error = $this->_prolog(__FUNCTION__) ) {
      return self::_error($error);
    }

    if (!is_numeric($params->userId->_value)) {
      return self::_error(USERID_INVALID_MUST_BE_AN_INTEGER);
    }
		$institutionId = str_replace("'", "''", strip_tags($params->institutionId->_value));
    

    $userId = $params->userId->_value;
    $this->log->set_userId($userId);
    $this->oci->bind('userId', $userId);
    $this->oci->bind('institutionId', $institutionId);
    try {
      $this->oci->set_query("SELECT userId FROM voxb_users WHERE userId=:userId AND institutionId=:institutionId AND disabled=1");
      $data = $this->oci->fetch_into_assoc();
    } catch (ociException $e) {
      return self::_error(ERROR_FETCHING_USER_FROM_DATABASE);
    }

    if (empty($data)) {
      return self::_error(COULD_NOT_FIND_USER);
    }

    try {
      $this->oci->set_query("UPDATE voxb_users SET disabled=NULL WHERE userId=$userId AND institutionId=$institutionId");
    } catch (ociException $e) {
      verbose::log(FATAL, "undeleteUser(".__LINE__."):: OCI update error: " . $this->oci->get_error_string());
      return self::_error(ERROR_UNDELETING_USER_FROM_DATABASE);
    }

    $this->oci->set_query("UPDATE voxb_items SET disabled=NULL WHERE userId=$userId");

    try { $this->oci->commit(); }
    catch (ociException $e) { 
      verbose::log(FATAL, "undeleteUser(".__LINE__."):: OCI commit error: " . $this->oci->get_error_string());
    }

    $this->_end_node($this->content, "userId", $userId);
    return $this->_epilog($this->response);
  }



  /*****************' PJO REFACTORING FETCHDATA METHOD  *******************/

 

  /** fetchData
   *
   *
   * Statistics (logging)
   * - p1 = antal ratings
   * - p2 = antal tags
   * - p3 = antal reviews
   * - p4 = antal locals
   * - p5 = antal requested items
   * - p6 = antal requested objects
   * - p7 = antal objects med aktive items
   *
   */

  function fetchData($params) {
    if ($error = $this->_prolog(__FUNCTION__) ) {
      return self::_error($error);
    }

    $contentType = $this->setContentType($params);    
    if (empty($contentType)) {
      return self::_error(CONTENTTYPES_MISSING);
    } 

    // get the data to output (@see voxb_items_class.php)
    $voxb_items = new voxb_items($this->oci, $params);
    $data =  $voxb_items->data();  

    $error = $voxb_items->getError();
    if (!empty($error) ) {
      return self::_error($error);
    }

    $response = $this->outputData($data, $contentType, 'fetchDataResponse');
    return $this->_epilog($response);    
  }


  /* *
   *  Set contentType according to parameter in request 
   */
  function setContentType($params){
    // check for contentType
    if (isset($params->output->_value->contentType)) {
      // if it's an array
      if (is_array($params->output->_value->contentType))
        $cT = &$params->output->_value->contentType;
      else
        $cT[] = &$params->output->_value->contentType;
 
      if (is_array($cT)) {
        foreach ($cT as $v) {
          $cType = strtolower($v->_value);
          switch($cType) {
	  case 'review':
	  case 'tags':
	  case 'summarytags':
	  case 'rating':
	  case 'totalratings':
	  case 'local':
	    $contentType[$cType] = true;
	    break;
	  case 'all':
	    $contentType = array('review'=>true, 'tags'=>true, 'summarytags'=>true, 'rating'=>true, 'totalratings'=>true, 'local'=>true);
	    break;
	    // return empty array as default.
	  default :
	    $contentType = array();
          }
        }
      }
    }
    return $contentType;  
  }  


  /** Output data in OLS object.
   *
   * @param array $data; [identifier=>[itemid,itemdata]]
   * @param array $contentType; one or all off: [review, tags, summary, rating, totalRatings, local, object]
   */
  function outputData($data, $contentType, $responseName='fetchDataResponse'){ 
    $response->$responseName->_namespace = $this->xmlns['voxb'];
    foreach($data as $identifier => $value){
      $response->$responseName->_value->totalItemData[] = $this->setFetchDataTotalItemData($identifier,$value, $contentType);;
    }
    return $response;
  }  

  /** Output totalItemData element
   *
   */
  function setFetchDataTotalItemData($identifier, $value, $contentType){
     $element->_namespace = $this->xmlns['voxb'];

     if(isset($value['OBJECTIDENTIFIERTYPE']) && isset($value['OBJECTIDENTIFIERVALUE'])){
       // this is an object request
       $itemid = array($value['OBJECTIDENTIFIERTYPE'],$value['OBJECTIDENTIFIERVALUE']);
     }
     else{
       $itemid = $identifier;
     }
     $element->_value->fetchData = $this->setFetchDataIdentifier($itemid);

     // set summaryTags   
     $summary = $this->getSummaryTags($value['ITEMDATA'], $contentType);
     if(!empty($summary['summaryTags']) ){
       foreach( $summary['summaryTags'] as $tag=>$count ){
	 $element->_value->summaryTags[] = $this->outputSummaryTag($tag, $count);
       }
     }
     // set totalRating
     if(!empty($summary['ratings'])){
       $element->_value->totalRatings->_namespace = $this->xmlns['voxb'];
       $element->_value->totalRatings->_value = $this->setTotalRatings($summary['ratings']);
     }
     // set userItems
     foreach($value['ITEMDATA'] as $itemid=>$itemdata){
       $element->_value->userItems[] =  $this->setFetchDataUserItems($itemdata, $contentType);
     }     
    
     return $element;
  } 

  /** Output totalRatings element
   *
   */
  function setTotalRatings($ratings){
    $average = $this->calculateAverageRating($ratings);  
  
    $element->averageRating->_namespace =  $this->xmlns['voxb'];
    $element->averageRating->_value = $average['averageRating'];
    $element->totalNumberOfRaters->_namespace =  $this->xmlns['voxb'];
    $element->totalNumberOfRaters->_value = $average['totalNumberOfRaters'];
    foreach($ratings as $rating => $count){
      $element->ratingSummary[] =$this->outputRatingSummary($rating, $count);
    }
    return $element;
  }

  /** Output ratingSummary element
   *
   */
  function outputRatingSummary($rating, $count){
    $element->_namespace = $this->xmlns['voxb'];
    $this->_end_node($element,'rating',$rating);
    $this->_end_node($element,'numberOfRaters',$count);

    return $element;
  }

  /** Calculate Average rating
   *
   * @param array $ratings; [rating=>count]
   */
  function calculateAverageRating($ratings){
    $totalRating = 0;
    $totalCount = 0;
    foreach($ratings as $rating=>$count){
      $totalRating = $totalRating + ($count*$rating);
      $totalCount = $totalCount + $count;      
    }
    $value = $totalRating/$totalCount;
    return array( 
       'averageRating' => round( (float)$value,2 ),
       'totalNumberOfRaters' => $totalCount,);
  }

  /** Get an OLS object for output of summaryTags
   *
   * @param array $summary; [tag=>count]
   */
  function outputSummaryTag($tag ,$count){
    $element->_namespace = $this->xmlns['voxb'];
    $this->_end_node($element, 'tag', $tag);
    $this->_end_node($element, 'tagCount', $count);

    return $element;
  }

  /** Set summaryTags and ratings
   * @param array $userItems [itemID => userItems]
   * @return array [tag=>count]
   */
  function getSummaryTags($userItems, $contentType){
    $summary = array();
    foreach($userItems as $itemId => $userItem){
      if( isset($userItem['TAGS']) && isset($contentType['summarytags'])){
	foreach($userItem['TAGS'] as $tag){	 
	  if(isset($summary['summaryTags'][$tag['TAG']])){
	    $summary['summaryTags'][$tag['TAG']]++;
	  }
	  else{
	    $summary['summaryTags'][$tag['TAG']] = 1;
	  }	  
	}
      }
      if( isset($userItem['RATING']) && isset($contentType['totalratings'])){
	if( isset($summary['ratings'][$userItem['RATING']] ) ){
	  $summary['ratings'][$userItem['RATING']]++;
	}
	else{
	  $summary['ratings'][$userItem['RATING']] = 1;
	}
      }      
    }
    return $summary;
  }

  

  /** Output fetchData identifier element
   *
   */
  function setFetchDataIdentifier($identifier){
    $element->_namespace = $this->xmlns['voxb'];
    if( is_array($identifier) ){
      $this->_end_node($element, 'objectIdentifierValue',  $identifier[1]);
      $this->_end_node($element, 'objectIdentifierType',  $identifier[0]);
    }
    else{
      $this->_end_node($element, 'voxbIdentifier',  $identifier);
    }
    return $element;
  }   

  /** Output userItems element
   *
   */
  function setFetchDataUserItems(array $value, $contentType){
    $element->_namespace = $this->xmlns['voxb'];

    // userAlias
    $element->_value->userAlias->_namespace = $this->xmlns['voxb'];
    $alias = &$element->_value->userAlias;
    $this->_end_node($alias, 'aliasName', $value['USER']['ALIAS_NAME']);
    $this->_end_node($alias, 'profileLink', $value['USER']['PROFILEURL']);

    // userId
    $this->_end_node($element, 'userId', $value['USER']['USERID']);

    // identifier
    $this->_end_node($element, 'voxbIdentifier', $value['ITEMIDENTIFIERVALUE']);

    //ratings
    if(isset($value['RATING']) && isset($contentType['rating']) ){  
      $this->_end_node($element, 'rating', $value['RATING']);
    }

     // tags
     if(is_array($value['TAGS']) && isset($contentType['tags']) ){
      foreach($value['TAGS'] as $tag){
	$element->_value->tags[] = $this->setFetchDataTag($tag);
      }
    }

    // reviews
    if(is_array($value['REVIEWS']) && isset($contentType['review']) ){
      foreach($value['REVIEWS'] as $review){
	$element->_value->review[] = $this->setFetchDataReview($review);
      }
    }

    // locals
    if(is_array($value['LOCALS']) && isset($contentType['local']) ){
      foreach($value['LOCALS'] as $local){
	$element->_value->local[] = $this->setFetchDataLocal($local);
      }
    }   
  
    // timestamp
    $this->_end_node($element, 'timestamp', $value['CREATION_DATE']);
    $this->_end_node($element, 'timestampModified', $value['MODIFICATION_DATE']);
    
    return $element;
  }
  
  /** Output Tag element
   *
   */
  function setFetchDataTag($tag){
    $element->_namespace = $this->xmlns['voxb'];
    $this->_end_node($element,'tag',$tag['TAG']);    
    return $element;
  }

  /** Output local element
   *
   */
  function setFetchDataLocal($local){
    $element->_namespace = $this->xmlns['voxb'];
    $this->_end_node($element,'localData',$local['DATA']);
    $this->_end_node($element,'localType',$local['TYPE']);
    $this->_end_node($element,'localItemType',$local['ITEMTYPE']);
    
    return $element;
  }

  /** Output review element
   *
   */
  function setFetchDataReview(array $review){
    $element->_namespace = $this->xmlns['voxb'];
    // reviewTitle
    $this->_end_node($element,'reviewTitle',$review['TITLE']);
    // reviewData
    $this->_end_node($element,'reviewData',$review['DATA']);
    // reviewType
    $this->_end_node($element,'reviewType',$review['TYPE']);
    
    return $element;
  } 
 
  /************ PJO end refactoring ******************/

  


  /** fetchMyData
   *
   *
   * Statistics (logging)
   * - p1 = antal ratings
   * - p2 = antal tags
   * - p3 = antal reviews
   * - p4 = antal locals
   * - p5 = antal items
   * - p6 = antal objects
   *
   */
  function fetchMyData($params) {
    if ($error = $this->_prolog(__FUNCTION__) ) {
      return self::_error($error);
    }

    $userId = $params->userId->_value;
    $this->log->set_userId($userId);
    $this->_end_node($this->content, "userId", $userId);

    if (!is_numeric($userId)) {
      return self::_error(USERID_INVALID_MUST_BE_AN_INTEGER);
    }
 
    // Fetch object data
    try {
      $this->oci->bind('userId', $userId);
      $this->oci->set_query("SELECT distinct * FROM voxb_objects WHERE objectid in (SELECT distinct objectid FROM voxb_items where userid=:userId and disabled IS NULL)");
      while ($data = $this->oci->fetch_into_assoc()) {
        $object_data[$data['OBJECTID']] = $data;
      }
    }
    catch (ociException $e) {
      verbose::log(FATAL, "fetchData(".__LINE__."):: OCI select error: " . $this->oci->get_error_string());
      return self::_error(ERROR_FETCHING_OBJECT_FROM_DATABASE);
    }
    if (empty($object_data)) {
      return self::_error(COULD_NOT_FIND_OBJECT);
    }

    // Fetch items
    try {
      $this->oci->bind('userId', $userId);
      $this->oci->set_query("SELECT ITEMIDENTIFIERVALUE, USERID, OBJECTID, RATING, replace(to_char(creation_date, 'YYYY-MM-DD=HH24:MI:SS'), '=', 'T') || '+01:00' as CREATION_DATE, replace(to_char(modification_date, 'YYYY-MM-DD=HH24:MI:SS'), '=', 'T') || '+01:00' as MODIFICATION_DATE from voxb_items where userId=:userId and disabled IS NULL");
      while ($data = $this->oci->fetch_into_assoc()) {
        $item_data[$data['ITEMIDENTIFIERVALUE']] = $data;
      }
    } catch (ociException $e) {
      verbose::log(FATAL, "fetchMyData(".__LINE__."):: OCI select error: " . $this->oci->get_error_string());
      return self::_error(ERROR_FETCHING_ITEM_FROM_DATABASE);
    }

    if (empty($item_data)) {
      return self::_error(COULD_NOT_FIND_ITEM_FOR_USER);
    }

    // Fetch locals data
    try {
      $this->oci->bind('userId', $userId);
			$item_data_subselect="SELECT ITEMIDENTIFIERVALUE from voxb_items where userId=:userId and disabled IS NULL";
      $this->oci->set_query("select LOCALID, ITEMID, DATA, TYPE, ITEMTYPE from voxb_locals where ITEMID in ($item_data_subselect)");
      while ($data = $this->oci->fetch_into_assoc()) {
        $item_data[$data['ITEMID']]['LOCALS'][$data['LOCALID']] = $data;
      }
    } catch (ociException $e) {
      verbose::log(FATAL, "fetchData(".__LINE__."):: OCI select error: " . $this->oci->get_error_string());
      return self::_error(ERROR_FETCHING_LOCALS_DATA_FROM_DATABASE);
    }

    // Fetch review data
    try {
      $this->oci->bind('userId', $userId);
			$item_data_subselect="SELECT ITEMIDENTIFIERVALUE from voxb_items where userId=:userId and disabled IS NULL";
      $this->oci->set_query("select REVIEWID, ITEMID, TITLE, TYPE, DATA from voxb_reviews where ITEMID in ($item_data_subselect)");
      while ($data = $this->oci->fetch_into_assoc()) {
        $item_data[$data['ITEMID']]['REVIEWS'][$data['REVIEWID']] = $data;
      }
    } catch (ociException $e) {
      verbose::log(FATAL, "fetchData(".__LINE__."):: OCI select error: " . $this->oci->get_error_string());
      return self::_error(ERROR_FETCHING_REVIEW_DATA_FROM_DATABASE);
    }

    // Fetch tags data
    try {
      $this->oci->bind('userId', $userId);
			$item_data_subselect="SELECT ITEMIDENTIFIERVALUE from voxb_items where userId=:userId and disabled IS NULL";
      $this->oci->set_query("select TAG, ITEMID from voxb_tags where ITEMID in ($item_data_subselect)");

      while ($data = $this->oci->fetch_into_assoc()) {
        $item_data[$data['ITEMID']]['TAGS'][] = $data['TAG'];
      }
    } catch (ociException $e) {
      verbose::log(FATAL, "fetchData(".__LINE__."):: OCI select error: " . $this->oci->get_error_string());
      return self::_error(ERROR_FETCHING_REVIEW_DATA_FROM_DATABASE);
    }

    // Put items elements in the $object_data array
    if (is_array($item_data)) {
      foreach ($item_data as $k=>$item) {
        if (is_array($object_data[$item['OBJECTID']])) {
          $object_data[$item['OBJECTID']]['ITEMS'][$k] = &$item_data[$k];
        }
      }
    }

    // Prepare output xml
    if (is_array($object_data)) {
      foreach ($object_data as $object) {
        if (is_array($object['ITEMS'])) {
          foreach ($object['ITEMS'] as $item) {
            $rResult = &$this->content->_value->result[];
            $rResult->_namespace = $this->xmlns['voxb'];
            $this->_end_node($rResult, "voxbIdentifier", $item['ITEMIDENTIFIERVALUE']);
            $this->_end_node($rResult, "timestamp", $item['CREATION_DATE']);
            $this->_end_node($rResult, "timestampModified", $item['MODIFICATION_DATE']);
            $rItem = &$rResult->_value->item;
            $rItem->_namespace = $this->xmlns['voxb'];
            // Ratings
            if (!empty($item['RATING'])) {
              $this->_end_node($rItem, "rating", $item['RATING']);
              $this->log->add_p1(1);
            }
            // Tags
            if (!empty($item['TAGS'])) {
              $rTags = &$rItem->_value->tags;
              $rTags->_namespace = $this->xmlns['voxb'];
              if (is_array($item['TAGS'])) {
                foreach ($item['TAGS'] as $tag) {
                  $rTag = &$rTags->_value->tag[];
                  $rTag->_namespace = $this->xmlns['voxb'];
                  $rTag->_value = $tag;
                  $this->log->add_p2(1);
                }
              }
            }
            // Review(s) (though only one review is allowed according to current xsd, this code handles multiple - but only can be entered)
            if (is_array($item['REVIEWS'])) {
              foreach ($item['REVIEWS'] as $review) {
                $rReview = &$rItem->_value->review[];
                $rReview->_namespace = $this->xmlns['voxb'];
                if (!empty($review['TITLE'])) {
                  $this->_end_node($rReview, "reviewTitle", $review['TITLE']);
                }
                $this->_end_node($rReview, "reviewData", $review['DATA']);
                $this->_end_node($rReview, "reviewType", $review['TYPE']);
                $this->log->add_p3(1);
              }
            }
            // Locals
            if (is_array($item['LOCALS'])) {
              foreach ($item['LOCALS'] as $local) {
                $rLocal = &$rItem->_value->local[];
                $rLocal->_namespace = $this->xmlns['voxb'];
                $this->_end_node($rLocal, "localData", $local['DATA']);
                $this->_end_node($rLocal, "localType", $local['TYPE']);
                $this->_end_node($rLocal, "localItemType", $local['ITEMTYPE']);
                $this->log->add_p4(1);
              }
            }
            if (empty($item['OBJECTID'])) {
              return self::_error(COULD_NOT_FIND_OBJECT_ORPHANED_ITEMS_SHOULD_BE_DELETED);
            }
            // Object
            $rObject = &$rResult->_value->object;
            $rObject->_namespace = $this->xmlns['voxb'];
            if (!empty($object['OBJECTCONTRIBUTORS'])) {
              $this->_end_node($rObject, "objectContributors", $object['OBJECTCONTRIBUTORS']);
            }
            $this->_end_node($rObject, "objectIdentifierValue", $object['OBJECTIDENTIFIERVALUE']);
            $this->_end_node($rObject, "objectIdentifierType", $object['OBJECTIDENTIFIERTYPE']);
            if (!empty($object['OBJECTMATERIALTYPE'])) {
              $this->_end_node($rObject, "objectMaterialType", $object['OBJECTMATERIALTYPE']);
            }
            if (!empty($object['OBJECTPUBLICATIONYEAR'])) {
              $this->_end_node($rObject, "objectPublicationYear", $object['OBJECTPUBLICATIONYEAR']);
            }
            if (!empty($object['OBJECTTITLE'])) {
              $this->_end_node($rObject, "objectTitle", $object['OBJECTTITLE']);
            }
            $this->log->add_p5(1);
          }
        }
        $this->log->add_p6(1);
      }
    }
    return $this->_epilog($this->response);
  }



  /** fetchUser
   *
   *
   * Statistics (logging)
   * - userId
   * - p1 = 1: userId brugt
   * - p1 = 2: authenticationFingerPrint brugt
   * - p2 = antal brugere fundet
   *
   */
  function fetchUser($params) {
    if ($error = $this->_prolog(__FUNCTION__) ) {
      return self::_error($error);
    }

    /* fetch by userid */
    if (!empty($params->userId->_value)) {  // userId given in input
      $this->log->set_userId($params->userId->_value);
      $this->log->add_p1(1);  // 1 means userId used
      try {
        $this->oci->bind("userId", $params->userId->_value);
        $this->oci->set_query("SELECT * FROM voxb_users WHERE userId=:userId AND disabled IS NULL");
        $data = $this->oci->fetch_all_into_assoc();
      } catch (ociException $e) {
        verbose::log(FATAL, "fetchUser(".__LINE__."):: OCI select error: " . $this->oci->get_error_string());
        return self::_error(ERROR_FETCHING_USER_FROM_DATABASE);
      }
      if (empty($data)) {
        return self::_error(NO_USER_FOUND_WITH_GIVEN_ID);
      }
    } else {  // No userId given in input => find user by fingerprint
      $aFv = &$params->authenticationFingerprint->_value;
      if (empty($aFv->userIdentifierValue->_value)
        || empty($aFv->userIdentifierType->_value)
        || empty($aFv->identityProvider->_value)
        || empty($aFv->institutionId->_value)) {
        return self::_error(FINGERPRINT_NOT_VALID);
      }
      /* fetch by fingerprint */
      $this->log->add_p1(2);  // 2 means authenticationFingerPrint used
      if ($aFv->userIdentifierType->_value=="CPR") {
        
        $userIdentifierValue = md5($this->_normalize_cpr($aFv->userIdentifierValue->_value) . $this->config->get_value("salt", "setup"));
        try {
          $this->oci->bind("userIdentifierValue", $userIdentifierValue);
          $this->oci->set_query("SELECT * FROM voxb_users WHERE userIdentifierValue=:userIdentifierValue AND disabled IS NULL");
          $data = $this->oci->fetch_all_into_assoc();
        } catch (ociException $e) {
          verbose::log(FATAL, "fetchUser(".__LINE__."):: OCI select error: " . $this->oci->get_error_string());
          return self::_error(ERROR_FETCHING_USER_FROM_DATABASE);
        }
      } else {  // userIdentifierType == "local"
        try {
          $userIdentifierValue = $aFv->userIdentifierValue->_value;
          $this->oci->bind("userIdentifierValue", $userIdentifierValue);
          $this->oci->bind("identityProvider", $aFv->identityProvider->_value);
          $this->oci->bind("institutionId", $aFv->institutionId->_value);
          $this->oci->set_query("SELECT * FROM voxb_users WHERE identityProvider=:identityProvider AND userIdentifierValue=:userIdentifierValue AND institutionId=:institutionId AND disabled IS NULL");
          $data = $this->oci->fetch_all_into_assoc();
        } catch (ociException $e) {
          verbose::log(FATAL, "fetchUser(".__LINE__."):: OCI select error: " . $this->oci->get_error_string());
          return self::_error(ERROR_FETCHING_USER_FROM_DATABASE);
        }
      }
      if (empty($data)) {
        return self::_error(NO_USER_FOUND_WITH_GIVEN_FINGERPRINT);
      }
      $found = false;
      foreach ($data as $uData) {
        if (($uData['USERIDENTIFIERVALUE'] == $userIdentifierValue) and
            ($uData['USERIDENTIFIERTYPE'] == $aFv->userIdentifierType->_value) and
            ($uData['IDENTITYPROVIDER'] == $aFv->identityProvider->_value) and
            ($uData['INSTITUTIONID'] == $aFv->institutionId->_value) ) {
          $found = true;
          break;
        }
      }
      if (!$found) {
        return self::_error(NO_USER_FOUND_WITH_GIVEN_FINGERPRINT);
      }
    }

    if (is_array($data)) {
      $this->log->add_p2(count($data));  // number of users found
      foreach ($data as $k=>$v) {
        $u = &$this->content->_value->users[$k];
        $u->_namespace = $this->xmlns['voxb'];
        $this->_end_node($u, "userId", $data[$k]["USERID"]);
  
        $uA = &$u->_value->userAlias;
        $uA->_namespace = $this->xmlns['voxb'];
        $this->_end_node($uA, "aliasName", $data[$k]["ALIAS_NAME"]);
        $this->_end_node($uA, "profileLink", $data[$k]["PROFILEURL"]);
  
        $aF = &$u->_value->authenticationFingerprint;
        $aF->_namespace = $this->xmlns['voxb'];
        $this->_end_node($aF, "userIdentifierValue", $data[$k]["USERIDENTIFIERVALUE"]);
        $this->_end_node($aF, "userIdentifierType", $data[$k]["USERIDENTIFIERTYPE"]);
        $this->_end_node($aF, "identityProvider", $data[$k]["IDENTITYPROVIDER"]);
        $this->_end_node($aF, "institutionId", $data[$k]["INSTITUTIONID"]);
      }
    }
    return $this->_epilog($this->response);
  }



  /** reportOffensiveContent
   *
   *
   * Statistics (logging)
   * - userId
   * - p1 = item no.
   *
   */
  function reportOffensiveContent($params) {
    if ($error = $this->_prolog(__FUNCTION__) ) {
      return self::_error($error);
    }

    $offending_itemId = $params->voxbIdentifier->_value;
    $complainant_userId = $params->userId->_value;

    try {
      $this->oci->bind("offending_itemId", $offending_itemId);
      $this->oci->set_query("SELECT USERID from voxb_items where ITEMIDENTIFIERVALUE=:offending_itemId");
      $data = $this->oci->fetch_into_assoc();
    } catch (ociException $e) {
      verbose::log(FATAL, "reportOffensiveContent(".__LINE__."):: OCI select error: " . $this->oci->get_error_string());
      return self::_error(FOUND_NO_ITEM_FROM_GIVEN_VOXBIDENTIFIER);
    }

    if (!is_numeric($data['USERID'])) {
      return self::_error(FOUND_NO_ITEM_FROM_GIVEN_VOXBIDENTIFIER);
    }

    $offending_userId = $data['USERID'];

    try {
      $this->oci->bind("itemIdentifierValue", $offending_itemId);
      $this->oci->set_query("select institutionid from voxb_users where userid=(select userid from voxb_items where itemIdentifierValue=:itemIdentifierValue)");
      $data = $this->oci->fetch_into_assoc();
    } catch (ociException $e) {
      verbose::log(FATAL, "reportOffensiveContent(".__LINE__."):: OCI select error: " . $this->oci->get_error_string());
      return self::_error(FOUND_NO_INSTITUTIONID_FROM_GIVEN_ITEMIDENTIFIERVALUE);
    }

		$offender_institutionid = $data['INSTITUTIONID'];

    try {
      $this->oci->set_query("INSERT into voxb_complaints (OFFENDING_ITEMID, OFFENDER_INSTITUTIONID, OFFENDER_USERID, COMPLAINANT_USERID, STATUS) VALUES('$offending_itemId', '$offender_institutionid', $offending_userId, $complainant_userId, '".COMPLAINT_STATUS_NEW."')");
      $this->oci->commit();
    } catch (ociException $e) {
      verbose::log(FATAL, "reportOffensiveContent(".__LINE__."):: OCI insert/commit error: " . $this->oci->get_error_string());
      $this->oci->bind("complainant_userId", $complainant_userId);
      $result=$this->oci->fetch_into_assoc("select * from voxb_users where userid=:complainant_userId");
			if(empty($result)) {
      	return self::_error(COULD_NOT_CREATE_DATA_USERID_DID_NOT_EXIST);
			} 
      return self::_error(ERROR_INSERTING_COMPLAINT_INTO_DATABASE);
    }

    $this->_end_node($this->content, "userId", $params->userId->_value);
    return $this->_epilog($this->response);
  }



  /** searchData
					 *
   *
   * Statistics (logging)
   * - p1 = qualifier
   * - p2 = truncated (1=yes, 2=no)
   * - p3 = antal items
   * - text = searchString
   *
   */
  function searchData($params) {
    global $voxb_qualifiers;
    if ($error = $this->_prolog(__FUNCTION__) ) {
      return self::_error($error);
    }

    $qualifier = str_replace("'", "''", strtoupper(trim(strip_tags($params->qualifier->_value))));
    if (empty($qualifier)) {
      return self::_error(EMPTY_SEARCHSTRING);
    }

    $searchstring = str_replace("'", "''", trim(strip_tags($params->searchString->_value)));
    if (empty($searchstring)) {
      return self::_error(EMPTY_QUALIFIER);
    }
    $this->log->set_text($searchstring);  // Log search string

    $this->log->add_p1($voxb_qualifiers[$qualifier]);  // Log qualifier type
    switch ($qualifier) {
      case "TAG":
        $from = "FROM VOXB_ITEMS, VOXB_TAGS";
        $where = "WHERE itemidentifiervalue=itemid AND tag LIKE";
        $pattern = "%" . strtolower($searchstring) . "%";
        break;
      case "TITLE":
        $from = "FROM VOXB_ITEMS items, VOXB_OBJECTS objects";
        $where = "WHERE items.OBJECTID=objects.OBJECTID AND objects.OBJECTTITLE LIKE";
        $pattern = "%" . $searchstring . "%";
        break;
      case "CONTRIBUTOR":
        $from = "FROM VOXB_ITEMS items, VOXB_OBJECTS objects";
        $where = "WHERE items.OBJECTID=objects.OBJECTID AND objects.OBJECTCONTRIBUTORS LIKE";
        $pattern = "%" . $searchstring . "%";
        break;
      case "LOCAL":
      case "EAN":
      case "ISBN":
      case "ISSN":
        $from = "FROM VOXB_ITEMS items, VOXB_OBJECTS objects";
        $where = "WHERE items.OBJECTID=objects.OBJECTID AND objects.OBJECTIDENTIFIERTYPE='" . $qualifier . "' AND objects.OBJECTIDENTIFIERVALUE=";
        $pattern = $searchstring;
        break;
      case "TIMESTAMPMIN":
        $from = "FROM VOXB_ITEMS";
        $where = "WHERE CREATION_DATE >";
        $pattern = $searchstring;
        break;
      case "TIMESTAMPMAX":
        $from = "FROM VOXB_ITEMS";
        $where = "WHERE CREATION_DATE <";
        $pattern = $searchstring;
        break;
      case "USERID":
        $from = "FROM VOXB_ITEMS";
        $where = "WHERE USERID=";
        $pattern = $searchstring;
        break;
      case "VOXB":
        return self::_error(QUALIFIER_NOT_YET_IMPLEMENTED);
      default:
        return self::_error(UNKNOWN_QUALIFIER);
    }

    try {
      $this->oci->bind("pattern", $pattern);
      $this->oci->set_query("SELECT DISTINCT itemidentifiervalue $from $where :pattern AND disabled IS NULL");
      $i = 0;
      while ($data = $this->oci->fetch_into_assoc()) {
        $this->content->_value->itemList->_namespace = $this->xmlns['voxb'];
        $this->_set_value_ns($this->content->_value->itemList->_value->voxbIdentifier[$i], $data['ITEMIDENTIFIERVALUE']);
        $i++;
      }
      $this->log->add_p3($i);  // Log number of items
    } catch (ociException $e) {
      verbose::log(FATAL, "searchData(".__LINE__."):: OCI select error: " . $this->oci->get_error_string());
      return self::_error(ERROR_FETCHING_ITEMS_FROM_DATABASE);
    }

    if (empty($this->content->_value)) {
      return self::_error(COULD_NOT_FIND_ITEMS);
    }

    return $this->_epilog($this->response);
  }



  /** updateMyData
   *
   *
   * Statistics (logging)
   * - userId
   * - p1 = antal ratings (0 eller 1)
   * - p2 = antal tags
   * - p3 = antal reviews (0 eller 1)
   * - p4 = antal locals
   *
   */
  function updateMyData($params) {
    if ($error = $this->_prolog(__FUNCTION__) ) {
      return self::_error($error);
    }

    if (!is_numeric($params->voxbIdentifier->_value)) {
      return self::_error(VOXBIDENTIFIER_INVALID_MUST_BE_AN_INTEGER);
    }

    $voxbIdentifier = strip_tags($params->voxbIdentifier->_value);
    $institutionId = str_replace("'", "''", strip_tags($params->institutionId->_value));
    $this->oci->bind('voxbIdentifier', $voxbIdentifier);
    $this->oci->bind('institutionId', $institutionId);

    try {
      $this->oci->set_query("select ITEMIDENTIFIERVALUE,OBJECTID,USERID from voxb_items where ITEMIDENTIFIERVALUE=:voxbIdentifier AND disabled IS NULL AND userid IN (select userid from voxb_users where institutionId=:institutionId)");
      $data = $this->oci->fetch_into_assoc();
    } catch (ociException $e) {
      verbose::log(FATAL, "updateMyData(".__LINE__."):: OCI select error: " . $this->oci->get_error_string());
      return self::_error(ERROR_FETCHING_ITEM_FROM_DATABASE);
    }
    if (empty($data['ITEMIDENTIFIERVALUE'])) {
      return self::_error(COULD_NOT_FIND_ITEM);
    }
    $this->log->set_userId($data['USERID']);

    $objectid = $data['OBJECTID'];

    if (isset($params->item->_value->rating->_value)) {
      $u['rating'] = min(strip_tags($params->item->_value->rating->_value), 100);
    }

    if (isset($params->item->_value->tags->_value->tag)) {
      if (is_array($params->item->_value->tags->_value->tag)) {
        foreach ($params->item->_value->tags->_value->tag as $k=>$v) {
           $tags[] = str_replace("'", "''", strtolower(trim(strip_tags($v->_value))));
        }
      } else {
        $tags[] = str_replace("'", "''", strtolower(trim(strip_tags($params->item->_value->tags->_value->tag->_value))));
      }
    }

    if (isset($params->object->_value->objectContributors->_value)) {
      $ou['objectContributors'] = str_replace("'", "''", strip_tags($params->object->_value->objectContributors->_value));
    }
    if (isset($params->object->_value->objectIdentifierValue->_value)) {
      $ou['objectIdentifierValue'] = str_replace("'", "''", strip_tags($params->object->_value->objectIdentifierValue->_value));
    }
    if (isset($params->object->_value->objectIdentifierType->_value)) {
      $ou['objectIdentifierType'] = str_replace("'", "''", strip_tags($params->object->_value->objectIdentifierType->_value));
    }
    if (isset($params->object->_value->objectMaterialType->_value)) {
      $ou['objectMaterialType'] = str_replace("'", "''", strip_tags($params->object->_value->objectMaterialType->_value));
    }
    if (isset($params->object->_value->objectPublicationYear->_value)) {
      $ou['objectPublicationYear'] = str_replace("'", "''", strip_tags($params->object->_value->objectPublicationYear->_value));
    }
    if (isset($params->object->_value->objectTitle->_value)) {
      $ou['objectTitle'] = str_replace("'", "''", strip_tags($params->object->_value->objectTitle->_value));
    }

    if (is_array($u)) {
      foreach ($u as $k=>$v) {
        $targets[] = "$k='$v'";
      }
      $targets = implode($targets, ',');

      try {
        $this->oci->bind("voxbIdentifier", $voxbIdentifier);
        $query = "UPDATE voxb_items SET $targets where ITEMIDENTIFIERVALUE=:voxbIdentifier";
        $this->oci->set_query($query);
        $this->oci->commit();
      } catch (ociException $e) {
        verbose::log(FATAL, "updateMyData(".__LINE__."):: OCI update error: " . $this->oci->get_error_string());
        return self::_error(ERROR_UPDATING_ITEMS_IN_DATABASE);
      }
      $this->log->add_p1(1);  // Log number of ratings = 1
      unset($targets);
    }

    if (is_array($ou)) {
      foreach ($ou as $k=>$v) {
       $targets[] = "$k='$v'";
      }
      $targets = implode($targets, ',');
      try {
        $this->oci->bind("objectid", $objectid);
        $query = "UPDATE voxb_objects SET $targets where OBJECTID=:objectid";
        $this->oci->set_query($query);
        $this->oci->commit();
      } catch (ociException $e) {
        verbose::log(FATAL, "updateMyData(".__LINE__."):: OCI update error: " . $this->oci->get_error_string());
        return self::_error(ERROR_UPDATING_OBJECTS_IN_DATABASE);
      }
      unset($targets);
    }

    if (isset($params->item->_value->review)) {
      try {
        $this->oci->bind("voxbIdentifier", $voxbIdentifier);
        $this->oci->set_query("DELETE FROM voxb_reviews WHERE itemId=:voxbIdentifier" );
        $this->oci->set_query("INSERT INTO voxb_reviews (itemId, title, type, data) VALUES ('" .
                        $voxbIdentifier . "', '" .
                        str_replace("'", "''", substr(strip_tags($params->item->_value->review->_value->reviewTitle->_value), 0, 256)) . "', '" .
                        str_replace("'", "''", strip_tags($params->item->_value->review->_value->reviewType->_value)) . "', '" .
                        str_replace("'", "''", strip_tags($params->item->_value->review->_value->reviewData->_value)) . "')" );
        $this->oci->commit();
      } catch (ociException $e) {
        return self::_error(ERROR_UPDATING_REVIEW_IN_DATABASE);
      }
      $this->log->add_p3(1);  // Log number of reviews = 1
    }

    if (isset($params->item->_value->local)) {
      if (isset($params->item->_value->local->_value)) {
        $locals[] = $params->item->_value->local;
      } else {
        $locals = $params->item->_value->local;
      }
      try {
        $this->oci->bind("voxbIdentifier", $voxbIdentifier);
        $this->oci->set_query("DELETE FROM voxb_locals WHERE itemId=:voxbIdentifier" );
        if (is_array($locals)) {
          foreach ($locals as $local) {
            $this->oci->set_query("INSERT INTO voxb_locals (itemId, data, type, itemType) VALUES ('" .
                            $voxbIdentifier . "', '" .
                            str_replace("'", "''", strip_tags($local->_value->localData->_value)) . "', '" .
                            str_replace("'", "''", strip_tags($local->_value->localType->_value)) . "', '" .
                            str_replace("'", "''", strip_tags($local->_value->localItemType->_value)) . "')" );
          }
        }
        $this->oci->commit();
      } catch (ociException $e) {
        return self::_error(ERROR_UPDATING_LOCALS_IN_DATABASE);
      }
      $this->log->add_p4(count($locals));  // Log number of locals = 1
    }

    if (!empty($tags)) {
      try {
        $this->oci->bind("voxbIdentifier", $voxbIdentifier);
        $this->oci->set_query("DELETE FROM voxb_tags WHERE itemId=:voxbIdentifier" );
        if (is_array($tags)) {
          foreach ($tags as $tag) {
            $this->oci->set_query("INSERT INTO voxb_tags (itemId, tag) VALUES ('" . $voxbIdentifier . "', '" . $tag . "')" );
            $this->oci->commit();
          }
        }
      } catch (ociException $e) {
        return self::_error(ERROR_UPDATING_TAGS_IN_DATABASE);
      }
      $this->log->add_p2(count($tags));  // Log number of tags
    }

    $this->_end_node($this->content, "voxbIdentifier", $voxbIdentifier);
    return $this->_epilog($this->response);
  }



  /** updateUser
   *
   *
   * Statistics (logging)
   * - userId
   *
   */
  function updateUser($params) {
    if ($error = $this->_prolog(__FUNCTION__) ) {
      return self::_error($error);
    }

    if (!is_numeric($params->userId->_value)) {
      return self::_error(USERID_INVALID_MUST_BE_AN_INTEGER);
    }
    $this->log->set_userId($params->userId->_value);

		$institutionId = str_replace("'", "''", strip_tags($params->institutionId->_value));

    try {
      $this->oci->bind("userId", $params->userId->_value);
      $this->oci->bind("institutionId", $params->institutionId->_value);
      $this->oci->set_query("select userid from voxb_users where userId=:userId AND institutionId=:institutionId  AND disabled IS NULL");
      $data = $this->oci->fetch_into_assoc();
    } catch (ociException $e) {
      verbose::log(FATAL, "updateUser(".__LINE__."):: OCI select error: " . $this->oci->get_error_string());
      return self::_error(ERROR_FETCHING_USER_FROM_DATABASE);
    }

    if (empty($data['USERID'])) {
      return self::_error(COULD_NOT_FIND_USER);
    }

    $userid = $data['USERID'];
    if (isset($params->userAlias->_value->aliasName->_value))
      $u['alias_name'] = str_replace("'", "''", strip_tags($params->userAlias->_value->aliasName->_value));
    if (isset($params->userAlias->_value->profileLink->_value))
      $u['profileurl'] = str_replace("'", "''", strip_tags($params->userAlias->_value->profileLink->_value));

    $aFv = &$params->authenticationFingerprint->_value;
    if (isset($aFv->userIdentifierValue->_value))
      if ($aFv->userIdentifierType->_value=="CPR")
        $u['userIdentifierValue'] = md5($this->_normalize_cpr($aFv->userIdentifierValue->_value) . $this->config->get_value("salt", "setup"));
      else
        $u['userIdentifierValue'] = $aFv->userIdentifierValue->_value;

    if (isset($aFv->userIdentifierType->_value))
      $u['userIdentifierType'] = $aFv->userIdentifierType->_value;

    if (isset($aFv->identityProvider->_value))
      $u['identityProvider'] = $aFv->identityProvider->_value;

    if (isset($aFv->institutionId->_value))
      $u['institutionId'] = $aFv->institutionId->_value;

    if (empty($u)) {
      return self::_error(NO_FIELDS_TO_UPDATE);
    }

    if (is_array($u)) {
      foreach ($u as $k=>$v) {
        if ($k=='profileurl') {
            #if (empty($v)) {
            # $u[$k] = "#USERID=".$data['USERID'];
            #}
        }
        $targets[] = "$k='$v'";
      }
    }
    $targets = implode($targets, ',');

    try {
      $query = "UPDATE voxb_users SET $targets where userid=$userid AND institutionId=$institutionId";
      $this->oci->set_query($query);
      $this->oci->commit();
    } catch (ociException $e) {
      verbose::log(FATAL, "updateUser(".__LINE__."):: OCI update error: " . $this->oci->get_error_string());
      return self::_error(ERROR_UPDATING_OBJECTS_IN_DATABASE);
    }

    $this->_end_node($this->content, "userId", $userid);
    return $this->_epilog($this->response);
  }




//===========================
// Private methods

  private function _prolog($method_name) {
    self::$OpenXidUrl = $this->config->get_value("openxid_url", "setup");

    $this->log = new voxb_logger($this, $method_name);
    $response = $method_name . "Response";
    $this->content = &$this->response->$response;
    $this->content->_namespace = $this->xmlns['voxb'];

    $this->oci = new oci($this->config->get_value('ocilogon'));
    $this->oci->set_charset('UTF8');
    try { $this->oci->connect(); }
    catch (ociException $e) {
      verbose::log(FATAL, "$method_name :: OCI connect error: " . $this->oci->get_error_string());
      unset($this->oci);
      return COULD_NOT_REACH_DATABASE;
    }

    if (!$this->aaa->has_right("voxb", 500)) {
      return AUTHENTICATION_ERROR;
    }

    return null;
  }

  private function _epilog(&$response) {
    unset($this->log);
    return $response;
  }
  
  private function _error($error) {
    global $voxb_error;
    $this->_end_node($this->content, "error", $voxb_error[$error]);
    $this->log->set_error($error);
    return $this->_epilog($this->response);
  }

  private function _set_value_ns(&$arg, $val, $ns=null) {
    $arg->_value = $val;
    $arg->_namespace = isset($ns) ? $ns : $this->xmlns['voxb'];
  }

  private function _normalize_cpr($cpr) {
    // Remove all non-digits from the string
    return preg_replace('/[^0-9]/', '', $cpr);
  }

  private function _end_node(&$node, $tag, $value, $ns=null) {
    $endNode = &$node->_value->$tag;
    $endNode->_namespace = isset($ns) ? $ns : $this->xmlns['voxb'];
    $endNode->_value = $value;
  }
  
  private function _build_userItem(&$rUserItems, $item, $contentType) {
    $rUserAlias = &$rUserItems->_value->userAlias;
    $rUserAlias->_namespace = $this->xmlns['voxb'];
    $this->_end_node($rUserAlias, "aliasName", $item['USERDATA']['ALIAS_NAME']);
    if (!empty($item['USERDATA']['PROFILEURL'])) {
      $this->_end_node($rUserAlias, "profileLink", $item['USERDATA']['PROFILEURL']);
    }
    $this->_end_node($rUserItems, "userId", $item['USERID']);
    $this->_end_node($rUserItems, "voxbIdentifier", $item['ITEMIDENTIFIERVALUE']);
    if ($contentType['rating'] and !empty($item['RATING'])) {
      $this->_end_node($rUserItems, "rating", $item['RATING']);
      $this->log->add_p1(1);
    }
    if ($contentType['tags'] and !empty($item['TAGS'])) {
      $rTags = &$rUserItems->_value->tags;
      $rTags->_namespace = $this->xmlns['voxb'];
      if (is_array($item['TAGS'])) {
        foreach ($item['TAGS'] as $tag) {
          $this->_set_value_ns($rTags->_value->tag[], $tag);
        }
      }
      $this->log->add_p2(count($item['TAGS']));
    }
    if ($contentType['review'] and !empty($item['REVIEWS'])) {
      $rReview = &$rUserItems->_value->review;
      $rReview->_namespace = $this->xmlns['voxb'];
      $thisReview = $item['REVIEWS'][0];  //NB In this version only one review exist
      if (!empty($thisReview['TITLE'])) {
        $this->_end_node($rReview, "reviewTitle", $thisReview['TITLE']);
      }
      $this->_end_node($rReview, "reviewData", $thisReview['DATA']);
      $this->_end_node($rReview, "reviewType", $thisReview['TYPE']);
      $this->log->add_p3(1);
    }
    if ($contentType['local'] and is_array($item['LOCALS'])) {
      foreach ($item['LOCALS'] as $local) {
        $rLocal = &$rUserItems->_value->local[];
        $rLocal->_namespace = $this->xmlns['voxb'];
        $this->_end_node($rLocal, "localData", $local['DATA']);
        $this->_end_node($rLocal, "localType", $local['TYPE']);
        $this->_end_node($rLocal, "localItemType", $local['ITEMTYPE']);
      }
      $this->log->add_p4(count($item['LOCALS']));
    }
    $this->_end_node($rUserItems, "timestamp", $item['CREATION_DATE']);
    $this->_end_node($rUserItems, "timestampModified", $item['MODIFICATION_DATE']);
  }
}

?>
