<?php

namespace YaleREDCap\AnnualReviewDashboard;

/** @var AnnualReviewDashboard $module */

$id        = $module->login();
$projectID = $module->framework->getProjectId();

if ( !$id ) {
    exit;
}
$id = AnnualReviewDashboard::toLowerCase($id);

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
    $module->log('type', [ 'type' => $type ]);
    $result = false;
    if ($type == 2) {
        // First check if the user is a departmental leader or their delegate and can access the final review
        foreach ( $ids as $thisId ) {
            $result = getFinalPDF($record_id, $thisId);
            if ( $result ) {
                return;
            }
        }
    } 
    // If not, check if they are a mentor or committee member and can access the first stage review
    foreach ( $ids as $thisId ) {
        $result = getFirstStagePDF($record_id, $thisId);
        if ( $result ) {
            return;
        }
    }
    
    // If neither, they do not have permission to view the file
    print "You do not have permission to view that file.";
}

function getFirstStagePDF($record_id, $id)
{
    global $projectId, $module;
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
        $pdfcontent = \REDCap::getPDF($record_id, $module->framework->getProjectSetting('first-stage-review-form', $projectId));

        // Set PHP headers to output the PDF to be downloaded as a file in the web browser
        header('Content-type: application/pdf');
        header('Content-disposition: attachment; filename="AnnualReview.pdf"');

        // Output the PDF content
        print $pdfcontent;

        return true;
    }
}

function getFinalPDF($record_id, $id)
{
    global $projectId, $module;
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
        $pdfcontent = \REDCap::getPDF($record_id, $module->framework->getProjectSetting('department-leader-review-form', $projectId));

        // Set PHP headers to output the PDF to be downloaded as a file in the web browser
        header('Content-type: application/pdf');
        header('Content-disposition: attachment; filename="AnnualReview.pdf"');

        // Output the PDF content
        print $pdfcontent;

        return true;
    }
}


getPdfData($record_id, $id, $type);