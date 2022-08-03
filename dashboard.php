<?php

//$id = $module->login();
$id = "Ap2493";
$id = strtolower($id);
var_dump($id);
if ($id == FALSE) {
    //exit;
}

?>
<script
    src="https://code.jquery.com/jquery-3.6.0.slim.min.js"
    integrity="sha256-u7e5khyithlIdTpu22PHhENmPcRdFiHRjhAuHcs05RI="
    crossorigin="anonymous">
</script>
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.12.1/css/jquery.dataTables.css">  
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.js"></script>

<?php

$data = $module->getData($id);
$module->displayDataTable($data);

?>


