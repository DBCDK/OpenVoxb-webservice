<?php
//==============================================================================

/** \brief openXidWrapper
 *
 * This class takes care of packing and sending an openXId request, an receiving the
 * corresponding response to it, and unpack the response.
 * 
 * @param DOMNode $node Parent node, hvor alle børne elementer ønskes
 * @return DOMNode array af DOMElement's
 *
 */

class openXidWrapper {
  private function __construct() {}
  
  private function _buildGetIdsRequest($requestedIds) {
    $requestDom = new DOMDocument('1.0', 'UTF-8');
    $requestDom->formatOutput = true;
    $soapEnvelope = $requestDom->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'soapenv:Envelope');
    $soapEnvelope->setAttribute('xmlns:xid', 'http://oss.dbc.dk/ns/openxid');
    $requestDom->appendChild($soapEnvelope);
    $soapBody = $soapEnvelope->appendChild($requestDom->createElement('soapenv:Body'));
    $getIdsRequest = $soapBody->appendChild($requestDom->createElement('xid:getIdsRequest'));
    if (is_array($requestedIds)) foreach ($requestedIds as $requestedId) {
      $id = $getIdsRequest->appendChild($requestDom->createElement('xid:id'));
      if (strtolower($requestedId['idType']) == 'isbn') $requestedId['idType'] = 'ean';    // Convert from isbn to EAN
      $id->appendChild($requestDom->createElement('xid:idType', strtolower($requestedId['idType'])));
      $id->appendChild($requestDom->createElement('xid:idValue', $requestedId['idValue']));
    }
    return $requestDom->saveXML();
  }

  private function _parseGetIdsResponse($response) {
    @$dom = DOMDocument::loadXML($response,  LIBXML_NOERROR);
    if (empty($dom)) return "Error parsing the DOM Document";
    $getIdsResponse = $dom->getElementsByTagName('getIdsResponse')->item(0);
    if ($getIdsResponse->firstChild->localName == 'error') return $getIdsResponse->firstChild->nodeValue;
    foreach ($getIdsResponse->childNodes as $getIdResult) {
      $item = array();
      if ($getIdResult->localName != 'getIdResult') continue;  // Unexpected - take the next getIdResult
      $requestedId = $getIdResult->firstChild;
      if ($requestedId->localName != 'requestedId') continue;  // Unexpected - take the next getIdResult
      foreach ($requestedId->childNodes as $node) {
        if ($node->localName == 'idType') $item['requestedId']['idType'] = $node->nodeValue;
        if ($node->localName == 'idValue') $item['requestedId']['idValue'] = $node->nodeValue;
      }
      $next = $requestedId->nextSibling;
      if ($next->localName == 'ids') {
        $ids = $next;
        $id = $ids->childNodes;
        foreach ($id as $i) {
          $idItem = array();
          foreach ($i->childNodes as $child) {
            if ($child->localName == 'idType') $idItem['idType'] = $child->nodeValue;
            if ($child->localName == 'idValue') $idItem['idValue'] = $child->nodeValue;
          }
          $item['ids']['id'][] = $idItem;
        }
        $next = $ids->nextSibling;
      }
      if ($next->localName == 'error') {
        $item['error'] = $next->nodeValue;
      }
    $result[] = $item;
    }
    return $result;
  }

  function sendGetIdsRequest($url, $requestedIds) {
    $curl = new cURL();
    $curl->set_timeout(10);
    $curl->set_post_xml(self::_buildGetIdsRequest($requestedIds));
    $res = $curl->get($url);
    $curl->close();
    return self::_parseGetIdsResponse($res);
  }
  
}

//==============================================================================

?>