<?php

namespace YaleREDCap\AnnualReviewDashboard;

/** @var AnnualReviewDashboard $module */

$projectId = $module->framework->getProjectId();
$eventId   = $module->framework->getEventId();
$result = $module->linkEvaluations($projectId, $eventId);

if ( $result === false ) {
    print "There was an error linking the evaluations.";
} else {
    print "The evaluations have been successfully linked. You may now close this tab.";
}
