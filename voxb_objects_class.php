<?php

class voxb_objects {

    private $oci;
    private $error;

    public function __construct($oci) {
        $this->oci = $oci;
    }

    public function getError() {
        return $this->error;
    }

    /** Get objectIds from given identifiers
     *
     * @param array $objectidentifiers [idValue,idType]
     * @param bool $useXID; whether to lookup additional identifiers on OpenXID
     */
    public function getObjectIdsFromIdentifiers($objectIdentifiers, $useXID = TRUE) {
        if ($useXID) {
            $XIDS = $this->getFromOpenXID($objectIdentifiers);
            $objectIdentifiers = $this->arrayMergeValues($XIDS, $objectIdentifiers);
        }

        $whereClause = $this->objectSqlWhereClause($objectIdentifiers);
        $ret = $this->getObjectIdsAndIdentifiers($whereClause, $objectIdentifiers);

        return $ret;
    }

    /** Merge ids array in XID array if it is not already there.
     *
     * NOTE this method emerged from the fact that OpenXid returns an error
     * on some ids and thus we need to preserve the original object identifiers
     */
    private function arrayMergeValues($XIDs, $ids) {
        $objid = array();
        foreach ($XIDs as $key => $id) {
            if (strtoupper($id['idType']) == 'EAN')
                $id['idType'] = 'ISBN';
            $objid[$id['idValue']] = strtoupper($id['idType']);
        }
        foreach ($ids as $key => $id) {
            if ($id['idType'] == 'EAN')
                $id['idTYpe'] = 'ISBN';
            $objid[$id['idValue']] = $id['idType'];
        }
        return $objid;
    }

    /** Check if needle is in multidimensional array
     *
     */
    private function in_multiarray($needle, $array) {
        foreach ($array as $key => $value) {
            if ($value == $needle) {
                return true;
            } elseif (is_array($value)) {
                if ($this->in_multiarray($needle, $value)) {
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
    private function getObjectIdsAndIdentifiers($whereClause, $objectindentifiers) {
        $ids = array();
        try {
            $sql = 'SELECT objectid, OBJECTIDENTIFIERVALUE, OBJECTIDENTIFIERTYPE FROM voxb_objects where ' . $whereClause;
            verbose::log(DEBUG, "sql:$sql");
            $this->oci->set_query($sql);
            while ($data = $this->oci->fetch_into_assoc()) {
                if ($data['OBJECTIDENTIFIERTYPE'] == 'EAN')
                    $data['OBJECTIDENTIFIERTYPE'] = 'ISBN';

                if ($objectindentifiers[$data['OBJECTIDENTIFIERVALUE']] == $data['OBJECTIDENTIFIERTYPE']) {
                    $id = $data['OBJECTID'];
                    $ids[$id] = $data;
                }
            }
        } catch (ociException $e) {
            verbose::log(FATAL, "voxb_objects(" . __LINE__ . "):: OCI select error: " . $this->oci->get_error_string());
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
    private function objectSqlWhereClause($objectIdentifiers) {
        $where = "OBJECTIDENTIFIERVALUE in (";
        foreach ($objectIdentifiers as $id => $type) {
            $where .= "'" . $id . "',";
        }
        $where = rtrim($where, ",") . ")";
        return $where;
    }

    /** Get additional object identifiers via OpenXID
     *
     * @param array $objectIdentifiers [idType, idValue]
     * return array [idType, idValue]
     *
     * @TODO error handling
     */
    private function getFromOpenXID($objectIdentifiers) {
        $url = voxb::$OpenXidUrl . '/';
        $identifiers = openXidWrapper::sendGetIdsRequest($url, $objectIdentifiers);
        return $this->parseOpenXID($identifiers);
    }

    private function parseOpenXID($identifiers) {
        $ret = array();
        if ($identifiers) {
            foreach ($identifiers as $id) {
                if (!isset($id['error'])) {
                    $ret = array_merge($ret, $id['ids']['id']);
                }
            }
        }

        return $ret;
    }

}

?>