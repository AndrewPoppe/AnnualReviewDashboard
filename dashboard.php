<?php

//$id = $module->login();
$id = "Ap2493";
$id = strtolower($id);
if ($id == FALSE) {
    exit;
}

?>
<head>
<title>YSM Annual Review Dashboard</title>
<link rel="shortcut icon" type="image" href="<?= $module->getUrl('assets/logos/favicon.ico') ?>"/>
<script
    src="https://code.jquery.com/jquery-3.6.0.slim.min.js"
    integrity="sha256-u7e5khyithlIdTpu22PHhENmPcRdFiHRjhAuHcs05RI="
    crossorigin="anonymous">
</script>
<link rel="stylesheet" href="<?=$module->getUrl('lib/FontAwesome/css/all.min.css');?>">
<link rel="stylesheet" type="text/css" href="<?=$module->getUrl('lib/DataTables/datatables.min.css');?>">  
<script type="text/javascript" src="<?=$module->getUrl('lib/DataTables/datatables.min.js');?>"></script>
<style>
    body {
        font-family:'Avenir Next Regular', Arial, Helvetica, sans-serif;
    }
    div.dashboard_container {
        margin: 25px;
        
    }
    a.dt-button {
        font-family: Lucida Console; Consolas;
        padding: 0.5em 0.75em;
        background-color: #00356b;
    }
    img.logo {
        vertical-align: middle;
        margin-top: 7.5px;
    }
    div.title_container {
        text-align: left;
    }
    div.accent_bar {
        padding: 7.5px;
        background-color: #00356b;
    }
    div.page_container {
        padding-left: 8%;
        padding-right: 10%;
        margin-left: auto;
        margin-right: auto;
    }
    hr.header_line {
        max-width: initial;
        margin: 1rem auto;
        border-bottom: solid #666 2px;
    }
    h3.header_h3 {
        font-size: 1.1875rem;
        font-weight: bold;        
    }
</style>
</head>
<body>
<br>
<div class="page_container">
    <div class="accent_bar"></div>
    <img class="logo" src="<?=$module->getUrl('assets/logos/ysm.bmp');?>"></img>
    <hr class="header_line">
    <h3 class="header_h3">Yale School of Medicine Annual Review Dashboard</h3>
    <em>You may access any outstanding reviews assigned to you below. The use of this dashboard is strictly limited to authorized individuals, and you are not permitted to share files or any embedded content with other individuals.</em>
    <p style="font-size:small;">If you have comments, questions, or concerns, please reach out to <a href="mailto:opssd@yale.edu">opssd@yale.edu</a>.</p>
    <hr class="header_line">

<?php

$data = $module->getSubmissionData($id);
$module->displayDataTable($data);

?>
</div>

