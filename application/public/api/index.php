<?php

/*                                                 
 * API BACKEND                               
 */

include("../../lib/libmuscari.php");
include("../../lib/libapi.php");

Muscari::init();

$RETURN=array();

$REQ["group"]=$_GET["group"];
$REQ["action"]=$_GET["action"];
$REQ["type"]=$_GET["type"];
$REQ["property"]=$_GET["property"];

$REQ["id"]=$_GET["id"];
$REQ["content"]=$_GET["content"];

$full_request=$REQ["group"].":"
             .$REQ["action"]."-"
             .$REQ["type"];

if($REQ["property"] != ""){
    $full_request.="(".$REQ["property"].")";
}



echo json_encode($RETURN);

Muscari::close();                                                                                     

?>
