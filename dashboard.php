<?php

//$id = $module->login();
$id = "Ap2493";
$id = strtolower($id);
var_dump($id);
if ($id == FALSE) {
    //exit;
}

$data = $module->getData($id);
$module->displayDataTable($data);

