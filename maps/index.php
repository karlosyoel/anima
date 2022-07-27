<?php 

$op = $_POST['op'];

$success = false;
$data = [];
$msg = "Success";

switch($op){
    case 'load':{
        $files = glob("*.json");

        $success = true;
        $data = $files;
        break;
    }
}
$resp["success"] = $success;
$resp["data"] = $data;
$resp["msg"] = $msg;

die(json_encode($resp));

?>