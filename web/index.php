<?php
require_once('oci_class.php');
require_once('inifile_class.php');


$config = new inifile('voxb.ini');

$voxb_oci_login = $config->get_value("ocilogon", "setup");

$oci_voxb = new oci($voxb_oci_login);
$oci_voxb->set_charset('UTF8');
$oci_voxb->connect();

$sql = "select institutionid, institutionname, contactperson_name, contactperson_email, contactperson_phone, moderator_name, moderator_email, creation_date from voxb_institutions";


$oci_voxb->set_query($sql);
$result = $oci_voxb->fetch_all_into_assoc();
$res = array('INSTITUTIONID' => '000000', "INSTITUTIONNAME" => 'ALLE ');
$result[] = $res;
$head = $result[0];
$cols = array();
foreach ($head as $coltitle => $value)
    $cols[] = $coltitle;
?>
<style>
    td { text-align: center;}
</style>
<meta charset="UTF-8">
<body class="flowers">
    <link rel='stylesheet' type='text/css' href='styles.css' />
    <div class='center overskrift screen'>
        <fieldset>
            <legend>Hvad ligger der i VOXB</legend>
            <table border="0">

                <?php
                foreach ($result as $row) {
                    echo "<tr>";
                    foreach ($cols as $col) {
                        echo "<th>$col</th>\n";
                    }
                    echo "</tr>";
                    echo "<tr>\n";
                    foreach ($cols as $col) {
                        if ($col == 'INSTITUTIONID') {
                            $instid = $row[$col];
                            echo "<td><ab href=users.php/?instid=$instid>$instid</ab></td>";
                        } else {
                            echo "<td>" . $row[$col] . "</td>\n";
                        }
                    }
                    echo "</tr>\n";
                    echo "<tr><td></td><td colspan=7>";
                    PrintInstitution($oci_voxb, $instid);
                    echo "</td></tr><tr><td style='height:30px;'></td></tr>\n";
                }
                ?>
            </table>
        </fieldset>
    </div>
</body>

<?php

function PrintInstitution($oci_voxb, $instid) {
//    $sql = "select * from voxb_institutions where institutionid = $instid";
////$sql = "select * from voxb_users where institutionid = $instid and rownum < 20";
//
//    $oci_voxb->set_query($sql);
//    $result = $oci_voxb->fetch_all_into_assoc();
//    $head = $result[0];
//    $cols = array();
//    foreach ($head as $coltitle => $value)
//        $cols[] = $coltitle;

    if ($instid == '000000') {
        $where = "";
    } else {
        $where = "where institutionid = $instid";
    }
    $sql = "select count(USERIDENTIFIERVALUE) U1, count(distinct useridentifiervalue) U2 "
            . "from VOXB_USERS $where";
    $oci_voxb->set_query($sql);
    $userCounts = $oci_voxb->fetch_all_into_assoc();

    $sql = "select rating, count(*) cnt from VOXB_ITEMS where userid in "
            . "(select USERID from VOXB_USERS "
            . "$where)"
            . "and disabled is null "
            . "and rating is not null "
            . "group by rating";
    $oci_voxb->set_query($sql);
    $rows = $oci_voxb->fetch_all_into_assoc();
    $ratings = array();
    $totalRatings = 0;
    foreach ($rows as $row) {
        $ratings[$row['RATING']] = $row['CNT'];
        $totalRatings += $row['CNT'];
    }
    ksort($ratings);
    if ($instid == '000000') {
        $ratings = array();
    }

    $sql = "select count(*) CNT from VOXB_REVIEWS "
            . "where ITEMID in "
            . "(select ITEMIDENTIFIERVALUE from VOXB_ITEMS "
            . "where USERID in "
            . "(select USERID from VOXB_USERS "
            . "$where "
            . ") "
            . "and disabled is null)";

    $oci_voxb->set_query($sql);
    $rows = $oci_voxb->fetch_all_into_assoc();
    $totalReviews = $rows[0]['CNT'];

    $sql = "select count(*) CNT from VOXB_TAGS "
            . "where ITEMID in "
            . "(select ITEMIDENTIFIERVALUE from VOXB_ITEMS "
            . "where USERID in "
            . "(select USERID from VOXB_USERS "
            . "$where "
            . ") "
            . "and disabled is null )";

    $oci_voxb->set_query($sql);
    $rows = $oci_voxb->fetch_all_into_assoc();
    $totalTags = $rows[0]['CNT'];

    $sql = "select count(*) CNT from VOXB_LOCALS "
            . "where ITEMID in "
            . "(select ITEMIDENTIFIERVALUE from VOXB_ITEMS "
            . "where USERID in "
            . "(select USERID from VOXB_USERS "
            . "$where "
            . ") "
            . "and disabled is null )";

    $oci_voxb->set_query($sql);
    $rows = $oci_voxb->fetch_all_into_assoc();
    $totalLocals = $rows[0]['CNT'];
    ?>



    <table border="0">

        <tr>
            <th>Antal brugere</th>
            <th>Antal unikke brugere</th>
        </tr>
        <tr>
            <td style='text-align: center;'><?php echo $userCounts[0]['U1']; ?></td>
            <td style='text-align: center;'><?php echo $userCounts[0]['U2']; ?></td>
        </tr>
    </table>
    <table border="0">
        <tr>
            <?php
            echo "<th>Total Ratings</th>";
            echo "<th>Total Reviews</th>";
            echo "<th>Total Tags</th>";
            echo "<th>Total Locals</th>";
            foreach ($ratings as $key => $val)
                echo "<th style='width:60px'>$key</th>";
            ?>
        </tr>
        <tr>
            <?php
            echo "<td>$totalRatings</td>";
            echo "<td>$totalReviews</td>";
            echo "<td>$totalTags</td>";
            echo "<td>$totalLocals</td>";
            foreach ($ratings as $key => $val)
                echo "<td>$val</td>";
            ?>
        </tr>
    </table>
    <?php
}
