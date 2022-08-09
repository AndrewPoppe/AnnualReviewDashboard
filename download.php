<?php

// TODO: REMOVE THIS FOR PRODUCTION!!!
// ONLY USE CAS
$url_id = filter_input(INPUT_GET, 'id');

if (isset($url_id)) {
    $id = $url_id;
} else {
    $id = $module->login();
}

if ($id == FALSE) {
    exit;
}
$id = strtolower($id);


$record_id = filter_input(INPUT_GET, 'record_id');
if (!isset($record_id)) {
    exit;
}

function getPdfData($record_id, $id)
{
    $filterLogic = "([record_id] = '" . $record_id . "') AND (";
    $filterLogic .= " [departmental_leadership] = '" . $id . "'";
    $filterLogic .= " OR [mentor_name] = '" . $id . "'";
    $filterLogic .= " OR [division_chief_name] = '" . $id . "'";
    $filterLogic .= " OR [mentor_committee_1] = '" . $id . "'";
    $filterLogic .= " OR [mentor_committee_2] = '" . $id . "'";
    $filterLogic .= " OR [mentor_committee_3] = '" . $id . "'";
    $filterLogic .= " OR [mentor_committee_4] = '" . $id . "'";
    $filterLogic .= " OR [mentor_committee_5] = '" . $id . "'";
    $filterLogic .= ")";
    $params = array(
        "project_id" => $project_id,
        "fields" => array(
            "init_last_name"
        ),
        //"filterLogic" => '([record_id] = "' . $record_id . '") AND (([departmental_leadership] = "' . $id . '") OR ([mentor_name] = "' . $id . '") OR ([division_chief_name] = "' . $id . '"))'
        "filterLogic" => $filterLogic
    );
    $data = \REDCap::getData($params);
    if (!empty($data)) {
        $pdfcontent = \REDCap::getPDF($record_id, "chairs_comments_for_faculty_development_annual_que");

        // Set PHP headers to output the PDF to be downloaded as a file in the web browser
        header('Content-type: application/pdf');
        header('Content-disposition: attachment; filename="AnnualReview.pdf"');

        // Output the PDF content
        print $pdfcontent;
    } else {
        print "You do not have permission to view that file.";
    }
}


$data = getPdfData($record_id, $id);
