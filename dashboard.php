<?php

namespace YaleREDCap\AnnualReviewDashboard;

/** @var AnnualReviewDashboard $module */

$id = $module->login();

if ( !$id ) {
    exit;
}
$id = AnnualReviewDashboard::toLowerCase($id);

?>

<head>
    <title>YSM Annual Review Dashboard</title>
    <link rel="shortcut icon" type="image" href="<?= $module->framework->getUrl('assets/logos/favicon.ico') ?>" />
    
    <link rel="stylesheet" href="<?= $module->framework->getUrl('lib/FontAwesome/css/all.min.css'); ?>">
    <link href="https://cdn.datatables.net/v/dt/jq-3.7.0/jszip-3.10.1/dt-2.1.8/b-3.2.0/b-colvis-3.2.0/b-html5-3.2.0/sr-1.4.1/datatables.min.css" rel="stylesheet">
 
<script src="https://cdn.datatables.net/v/dt/jq-3.7.0/jszip-3.10.1/dt-2.1.8/b-3.2.0/b-colvis-3.2.0/b-html5-3.2.0/sr-1.4.1/datatables.min.js"></script>
    <style>
        body {
            font-family: 'Avenir Next Regular', Arial, Helvetica, sans-serif;
        }

        div.dashboard_container {
            margin: 25px;

        }

        a.dt-button {
            /* font-family: Lucida Console;
            Consolas;
            padding: 0.5em 0.75em; */
            /* background-color: #00356b; */
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
        <img class="logo" src="<?= $module->framework->getUrl('assets/logos/ysm.bmp'); ?>"></img>
        <hr class="header_line">
        <h3 class="header_h3">Yale School of Medicine Annual Review Dashboard</h3>
        <em>You may access any outstanding reviews assigned to you below. The use of this dashboard is strictly limited
            to authorized individuals, and you are not permitted to share files or any embedded content with other
            individuals.</em>
        <p style="font-size:small;">If you have comments, questions, or concerns, please reach out to <a
                href="mailto:fdaq@yale.edu">fdaq@yale.edu</a>.</p>
        <hr class="header_line">

        <?php

        $data = $module->getSubmissionData($id);
        $module->displayDataTable($data);
        ?>
    </div>