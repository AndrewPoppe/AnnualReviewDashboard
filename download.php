<?php

namespace YaleREDCap\AnnualReviewDashboard;

/** @var AnnualReviewDashboard $module */

$id        = $module->login();
$projectID = $module->framework->getProjectId();

if ( !$id ) {
    exit;
}
$id = strtolower($id);

$record_id = filter_input(INPUT_GET, 'record_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if ( !isset($record_id) ) {
    exit;
}

$type = filter_input(INPUT_GET, 'type', FILTER_VALIDATE_INT);
if ( !isset($type) ) {
    exit;
}


function getPdfData($record_id, $id, $type)
{
    global $module;
    $ids = $module->getValidIDs($id);
    foreach ( $ids as $thisId ) {
        if ( $type == 1 ) {
            getFirstStagePDF($record_id, $thisId);
        } elseif ( $type == 2 ) {
            getFinalPDF($record_id, $thisId);
        }
    }
    print "You do not have permission to view that file.";
}

function getFirstStagePDF($record_id, $id)
{
    global $projectId;
    $filterLogic = "([record_id] = '" . $record_id . "') AND (";
    $filterLogic .= " [mentor_name] = '" . $id . "'";
    $filterLogic .= " OR [division_chief_name] = '" . $id . "'";
    $filterLogic .= " OR [mentor_committee_1] = '" . $id . "'";
    $filterLogic .= " OR [mentor_committee_2] = '" . $id . "'";
    $filterLogic .= " OR [mentor_committee_3] = '" . $id . "'";
    $filterLogic .= " OR [mentor_committee_4] = '" . $id . "'";
    $filterLogic .= " OR [mentor_committee_5] = '" . $id . "'";
    $filterLogic .= ")";
    $params      = array(
        "project_id"  => $projectId,
        "fields"      => array(
            "init_last_name"
        ),
        "filterLogic" => $filterLogic
    );
    $data        = \REDCap::getData($params);
    if ( !empty($data) ) {
        $pdfcontent = \REDCap::getPDF($record_id, "first_stage_review_comments_for_faculty_developmen");

        // Set PHP headers to output the PDF to be downloaded as a file in the web browser
        header('Content-type: application/pdf');
        header('Content-disposition: attachment; filename="AnnualReview.pdf"');

        // Output the PDF content
        print $pdfcontent;

        exit;
    }
}

function getFinalPDF($record_id, $id)
{
    global $projectId;
    $filterLogic = "[record_id] = '" . $record_id . "' AND [departmental_leadership] = '" . $id . "'";
    $params      = array(
        "project_id"  => $projectId,
        "fields"      => array(
            "init_last_name"
        ),
        "filterLogic" => $filterLogic
    );
    $data        = \REDCap::getData($params);
    if ( !empty($data) ) {
        $pdfcontent = \REDCap::getPDF($record_id, "chairs_comments_for_faculty_development_annual_que");

        // Set PHP headers to output the PDF to be downloaded as a file in the web browser
        header('Content-type: application/pdf');
        header('Content-disposition: attachment; filename="AnnualReview.pdf"');

        // Output the PDF content
        print $pdfcontent;

        exit;
    }
}


getPdfData($record_id, $id, $type);