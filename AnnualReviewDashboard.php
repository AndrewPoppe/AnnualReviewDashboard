<?php

namespace YaleREDCap\AnnualReviewDashboard;

use ExternalModules\Framework;

/**
 * Main EM class
 *
 * @author Andrew Poppe
 * @property Framework $framework
 * @see Framework
 */
class AnnualReviewDashboard extends \ExternalModules\AbstractExternalModule
{

    const SESSION_COOKIE = "AnnualReviewDashboard_session";
    const USERNAME_COOKIE = "AnnualReviewDashboard_username";

    public function redcap_save_record($projectId, $record, $instrument, $eventId, $groupId, $surveyHash, $responseId, $repeatInstance)
    {
        if ( $instrument !== $this->framework->getProjectSetting('initial-questionnaire-form', $projectId) ) {
            return;
        }


        // Only look for a file if there isn't already one
        try {
            $params     = array(
                'project_id' => $projectId,
                'records'    => array( $record ),
                'fields'     => 'teaching_evaluations',
            );
            $evalsExist = sizeof(\REDCap::getData($params));
        } catch ( \Throwable $e ) {
            $this->framework->log($e->getMessage());
            return;
        }
        if ( $evalsExist ) {
            return;
        }

        // Search for an eval file
        $this->findEvalFile($projectId, $record, $eventId);

    }

    private function getRecords($projectId)
    {
        $records    = [];
        $data_table = method_exists('\REDCap', 'getDataTable')
            ? \REDCap::getDataTable($projectId) : "redcap_data";
        $sql        = "SELECT DISTINCT(record) record FROM $data_table WHERE project_id = ?";
        $result     = $this->framework->query($sql, [ $projectId ]);
        while ( $row = $result->fetch_assoc() ) {
            $records[] = $row['record'];
        }
        return $records;
    }

    public function redcap_module_ajax($action, $payload, $project_id, $record, $instrument, $event_id, $repeatInstance, $surveyHash, $responseId, $surveyQueueHash, $page, $pageFull, $userId, $groupId)
    {
        $projectId = $payload['project_id'];
        $eventId   = $this->framework->getEventId();
        if ( $action == "dataImport" ) {
            $records = $this->getRecords($projectId);

            // Only look for a file if there isn't already one
            try {
                $params = array(
                    'project_id' => $projectId,
                    'fields'     => 'teaching_evaluations',
                    'records'    => $records
                );
                $data   = \REDCap::getData($params);
                foreach ( $records as $thisRecord ) {
                    $evalsExist = sizeof($data[(string) $thisRecord]);
                    if ( $evalsExist ) {
                        continue;
                    }

                    // Search for an eval file
                    $this->findEvalFile($projectId, $thisRecord, $eventId);
                }
            } catch ( \Throwable $e ) {
                $this->framework->log($e->getMessage());
                return;
            }
        } elseif ( $action === "authenticate" ) {
            $accessToken = $payload["token"];
            $username    = $this->getUserData($accessToken);
            $result      = [ "username" => $username ];
            if ( !empty($username) ) {
                $this->initSession();
                \Session::saveCookie(self::SESSION_COOKIE, session_id(), 0, true);
                $_SESSION[self::USERNAME_COOKIE] = $username;
                $result["success"]               = true;
            }
            return $result;
        }
    }

    public function getUserData(string $accessToken) : string
    {
        $username = "";
        try {
            //Fetching the basic user information that is likely needed by your application
            $options = array(
                "http" => array(  //Use "http" even if you send the request with https
                    "method" => "GET",
                    "header" => "Accept: application/json\r\n" .
                        "Authorization: Bearer " . $accessToken . "\r\n"
                )
            );
            $context = stream_context_create($options);
            $json    = file_get_contents("https://graph.microsoft.com/v1.0/me?\$select=onPremisesSamAccountName", false, $context);
            if ( $json === false ) {
                $this->framework->log('Error received during user data fetch.');
                return $username;
            }

            $userdata = json_decode($json, true);  //This should now contain your logged on user information
            if ( isset($userdata["error"]) ) {
                $this->framework->log('User data fetch contained an error.');
                return $username;
            }

            $username       = $userdata["onPremisesSamAccountName"] ?? "";
            $username_clean = AnnualReviewDashboard::toLowerCase($username);
            return $username_clean;
        } catch ( \Throwable $e ) {
            $this->framework->log($e->getMessage());
            return $username;
        }
    }

    public function validateSettings($settings)
    {
        $project_id = $this->framework->getProjectId();
        if ( empty($project_id) ) {
            return;
        }

        $proj = $this->framework->getProject($project_id);

        // Check that the Initial Faculty Development Annual Questionnaire instrument has necessary fields
        $init_fdaq_setting         = $this->framework->getProjectSetting('initial-questionnaire-form');
        $init_fdaq                 = $proj->getForm($init_fdaq_setting);
        $init_fdaq_fields          = $init_fdaq->getFieldNames();
        $init_fdaq_required_fields = [
            'init_first_name',
            'init_last_name',
            'init_department',
            'init_ladder_track',
            'init_rank',
            'init_netid',
            'review_type',
            'mentor_name',
            'mentor_committee_1',
            'mentor_committee_2',
            'mentor_committee_3',
            'mentor_committee_4',
            'mentor_committee_5',
            'division_chief_name',
            'departmental_leadership',
            'teaching_evaluations',
            'exclusion_reason'
        ];
        $init_fdaq_missing_fields  = array_diff($init_fdaq_required_fields, $init_fdaq_fields);
        if ( !empty($init_fdaq_missing_fields) ) {
            return 'The selected Initial Faculty Development Annual Questionnaire instrument (' . $init_fdaq_setting . ') is missing the following fields: ' . implode(', ', $init_fdaq_missing_fields);
        }
    }


    public function redcap_every_page_top()
    {
        $this->framework->initializeJavascriptModuleObject();
        ?>
        <script>
            const ARD = <?= $this->framework->getJavascriptModuleObjectName() ?>;
            $(function () {
                if (ARD.isImportPage() && $('#center > .green > b').eq(0).text() === 'Import Successful!') {
                    console.log('dataImport');
                    ARD.ajax('dataImport', {
                        project_id: ARD.getUrlParameter('pid')
                    }).then(resp => console.log(resp));
                }
            });
        </script>
        <?php
    }

    private function findEvalFile($projectId, $record, $eventId)
    {
        try {
            $params = array(
                'project_id' => $projectId,
                'records'    => array( $record ),
                'fields'     => 'init_netid',
            );
            $data   = \REDCap::getData($params);
        } catch ( \Throwable $e ) {
            $this->framework->log($e->getMessage());
            return;
        }

        $netid    = $data[$record][$eventId]['init_netid'];
        $folderId = $this->framework->getProjectSetting('eval-folder-id');

        $sql    = 'SELECT m.doc_id
FROM redcap_edocs_metadata m
LEFT JOIN redcap_docs_to_edocs d2e
ON m.doc_id = d2e.doc_id
LEFT JOIN redcap_docs_folders_files f
ON f.docs_id = d2e.docs_id
WHERE f.folder_id = ?
AND m.doc_name like ?';
        $params = [ $folderId, '%' . $netid . '%' ];

        $result = $this->framework->query($sql, $params);
        $row    = $result->fetch_assoc();

        if ( empty($row) ) {
            $this->framework->log('No eval file found', [
                'project' => $projectId,
                'record'  => $record,
                'netid'   => $netid
            ]);
            return;
        }

        $this->importFile($projectId, $record, $row['doc_id']);

    }

    private function importFile($projectId, $record, $docId)
    {
        try {
            $newDocId = \REDCap::copyFile($docId, $projectId);
            \REDCap::addFileToField($newDocId, $projectId, $record, 'teaching_evaluations');
            \REDCap::logEvent('Teaching evaluations imported', "Record: $record", null, $record, null, $projectId);

        } catch ( \Throwable $e ) {
            $this->framework->log('Error importing file', [ 'project' => $projectId, 'record' => $record, 'error' => $e->getMessage() ]);
        }
    }

    /**
     * Initiate authentication
     *
     * @return string|boolean username of authenticated user (false if not authenticated)
     */
    private function authenticate()
    {
        $this->framework->initializeJavascriptModuleObject();
        $clientId    = $this->framework->getSystemSetting('entraid-client-id');
        $adTenant    = $this->framework->getSystemSetting('entraid-ad-tenant');
        $redirectUrl = $this->framework->getSystemSetting('entraid-redirect-url');
        ?>
        <script src="https://alcdn.msauth.net/browser/2.38.2/js/msal-browser.min.js"
            integrity="sha384-hhkHFODse2T75wPL7oJ0RZ+0CgRa74LNPhgx6wO6DMNEhU3/fSbTZdVzxsgyUelp"
            crossorigin="anonymous"></script>
        <script>
            var myMsal;
            function ready(fn) {
                if (document.readyState !== 'loading') {
                    fn();
                    return;
                }
                document.addEventListener('DOMContentLoaded', fn);
            }
            ready(function () {
                const module = <?= $this->framework->getJavascriptModuleObjectName() ?>;
                const config = {
                    auth: {
                        clientId: "<?= $clientId ?>",
                        authority: "https://login.microsoftonline.com/<?= $adTenant ?>",
                        redirectUri: "<?= $redirectUrl ?>"
                    }
                };

                const loginRequest = {
                    scopes: ["User.Read"]
                };

                myMsal = new msal.PublicClientApplication(config);

                function authenticate(loginResponse) {
                    const token = loginResponse.accessToken;
                    module.ajax('authenticate', { token: token })
                        .then((result) => {
                            if (result.success) {
                                window.location.reload();
                            }
                        })
                        .catch((error) => {
                            console.error(error);
                        });
                }

                function singleAccount(account) {
                    const accessTokenRequest = {
                        scopes: ["User.Read"],
                        account: account
                    }
                    myMsal
                        .acquireTokenSilent(accessTokenRequest)
                        .then(authenticate)
                        .catch((error) => {
                            console.log(error);
                        });
                }

                function general() {
                    myMsal
                        .loginPopup(loginRequest)
                        .then(authenticate)
                        .catch((error) => {
                            console.log(error);
                        });
                }

                function handleResponse(response) {
                    const currentAccounts = myMsal.getAllAccounts();
                    if (currentAccounts.length !== 1) {
                        general();
                    } else if (currentAccounts.length === 1) {
                        const account = currentAccounts[0];
                        singleAccount(account);
                    }
                }

                const currentAccounts = myMsal.getAllAccounts();
                if (currentAccounts.length > 1) {
                    general();
                } else if (currentAccounts.length === 1) {
                    const account = currentAccounts[0];
                    singleAccount(account);
                } else {
                    myMsal.handleRedirectPromise().then(handleResponse);
                }
            });
        </script>
        <?php
    }


    /**
     * Get url to file with provided edoc ID.
     *
     * @param string $edocId ID of the file to find
     *
     * @return string path to file in edoc folder
     */
    private function getFile(string $edocId)
    {
        if ( $edocId === NULL ) {
            return "";
        }
        $result   = $this->framework->query('SELECT stored_name FROM redcap_edocs_metadata WHERE doc_id = ?', [ $edocId ]);
        $filename = $result->fetch_assoc()["stored_name"];
        return EDOC_PATH . $filename;
    }

    public function initSession()
    {
        $session_id = $_COOKIE[self::SESSION_COOKIE];

        if ( !empty($session_id) && $session_id !== session_id() ) {
            \Session::destroy(session_id());
            session_destroy();
            session_id($session_id);
        }
        session_start();
    }

    public function login() : string
    {
        $this->initSession();

        if ( !empty($_SESSION[self::USERNAME_COOKIE]) ) {
            return $_SESSION[self::USERNAME_COOKIE];
        }
        try {
            $this->authenticate();
        } catch ( \Throwable $e ) {
            $this->framework->log($e->getMessage());
        } finally {
            return "";
        }
    }

    private function getStatus($id, &$record)
    {
        // 0 - pending faculty submission (initial form completed only)
        // 1 - pending mentor review
        // 2 - pending division chief review
        // 3 - pending chair review (but display it as completed to 1st stage reviewer)
        // 4 - ready for mentor review (show first stage link)
        // 5 - ready for division chief review (show first stage link)
        // 6 - ready for final review (show final link)
        // 7 - final review completed
        // 8 - pending mentorship committee review
        // 9 - ready for mentorship committee review (show first stage link)
        $chair_completed      = $record[$this->framework->getProjectSetting('department-leader-review-form') . "_complete"] == 2;
        $faculty_completed    = $record[$this->framework->getProjectSetting('current-year-form') . "_complete"] == 2;
        $first_stage_complete = $record[$this->framework->getProjectSetting('first-stage-review-form') . "_complete"] == 2;
        $review_type          = $record["review_type"];
        $mentor               = strtolower($record["mentor_name"]);
        $division_chief       = strtolower($record["division_chief_name"]);
        $chair                = strtolower($record["departmental_leadership"]);
        $mentorship_committee = [
            strtolower($record["mentor_committee_1"]),
            strtolower($record["mentor_committee_2"]),
            strtolower($record["mentor_committee_3"]),
            strtolower($record["mentor_committee_4"]),
            strtolower($record["mentor_committee_5"])
        ];

        $userIsMentor        = $mentor == $id;
        $userIsDivisionChief = $division_chief == $id;
        $userOnCommittee     = in_array($id, $mentorship_committee, true);
        $userIsChair         = $chair == $id;

        $status = 0;
        if ( $chair_completed ) {
            $status = 7;
        } else if ( !$faculty_completed ) {
            $status = 0;
        } else if ( ($first_stage_complete && $userIsChair) || ($review_type == 1 && $userIsChair) ) {
            $status = 6;
        } else if ( $review_type == 3 && $userIsDivisionChief && !$first_stage_complete ) {
            $status = 5;
        } else if ( $review_type == 4 && $userIsMentor && !$first_stage_complete ) {
            $status = 4;
        } else if ( $review_type == 2 && $userOnCommittee && !$first_stage_complete ) {
            $status = 9;
        } else if ( $first_stage_complete || $review_type == 1 ) {
            $status = 3;
        } else if ( $review_type == 3 ) {
            $status = 2;
        } else if ( $review_type == 4 ) {
            $status = 1;
        } else if ( $review_type == 2 ) {
            $status = 8;
        }
        return $status;
    }

    private function getStatusText($status)
    {
        // 0 - pending faculty submission (initial form completed only)
        // 1 - pending mentor review
        // 2 - pending division chief review
        // 3 - pending chair review (but display it as completed to 1st stage reviewer)
        // 4 - ready for mentor review (show first stage link)
        // 5 - ready for division chief review (show first stage link)
        // 6 - ready for final review (show final link)
        // 7 - final review completed
        // 8 - pending mentorship committee review
        // 9 - ready for mentorship committee review (show first stage link)
        switch (strval($status)) {
            case "1":
                $status_text = "<span class='fa-solid fa-circle-pause' style='color:grey;'></span> Pending Mentor Review";
                break;
            case "2":
                $status_text = "<span class='fa-solid fa-circle-pause' style='color:grey;'></span> Pending Division Chief Review";
                break;
            case "3":
                $status_text = "<span class='fa-solid fa-circle-check' style='color:green;'></span> Review Complete";
                break;
            case "4":
            case "5":
            case "6":
            case "9":
                $status_text = "<span class='fa-solid fa-circle-exclamation' style='color:tomato;'></span> Ready for Review";
                break;
            case "7":
                $status_text = "<span class='fa-solid fa-circle-check' style='color:green;'></span> Review Complete";
                break;
            case "8":
                $status_text = "<span class='fa-solid fa-circle-pause' style='color:grey;'></span> Pending Mentorship Committee Review";
                break;
            default:
                $status_text = "<span class='fa-solid fa-circle-pause' style='color:orange;'></span> Pending Faculty Submission";
                break;
        }
        return $status_text;
    }

    private function getLink($record_id, $status, $id)
    {
        $link = "";
        if ( $status == 6 ) {
            $survey_link = \REDCap::getSurveyLink($record_id, $this->framework->getProjectSetting('department-leader-review-form'));
            $link        = '<a target="_blank" href="' . $survey_link . '">Start Review</a>';
        } else if ( $status == 4 || $status == 5 || $status == 9 ) {
            $survey_link = \REDCap::getSurveyLink($record_id, $this->framework->getProjectSetting('first-stage-review-form'));
            $link        = '<a target="_blank" href="' . $survey_link . '">Start Review</a>';
        } else if ( $status == 7 ) {
            $link = "<a href='" . $this->framework->getUrl("download.php?record_id=" . $record_id . "&id=" . $id . "&type=2", true) . "' target='_blank'>Download Review</button>";
        } else if ( $status == 3 ) {
            $link = "<a href='" . $this->framework->getUrl("download.php?record_id=" . $record_id . "&id=" . $id . "&type=1", true) . "' target='_blank'>Download Review</button>";
        }
        return $link;
    }

    /**
     * @param mixed $id - the primary id of the user
     *
     * @return array of netids that the primary id has access to view (including the primary)
     */
    public function getValidIDs($id)
    {
        $ids      = array( $id );
        $aliases  = $this->framework->getProjectSetting("parent");
        $children = $this->framework->getProjectSetting("child");
        foreach ( $aliases as $alias_n => $alias ) {
            $these_children = $children[$alias_n];
            foreach ( $these_children as $this_child ) {
                if ( $id == $this_child ) {
                    array_push($ids, strtolower($alias));
                }
            }
        }
        return $ids;
    }

    private function getReviewType($review_type_raw, $id, $chair)
    {
        if ( $id != $chair || $review_type_raw == "One Stage" ) {
            return $review_type_raw;
        } else {
            return "Two Stage";
        }
    }

    public function getSubmissionData($id)
    {
        $ids     = $this->getValidIDs($id);
        $alldata = array();
        $pid     = $this->framework->getProjectId() ?? $this->framework->getProject()->getProjectId();

        $labels = array(
            "init_department"   => $this->framework->getChoiceLabels("init_department"),
            "init_ladder_track" => $this->framework->getChoiceLabels("init_ladder_track"),
            "init_rank"         => $this->framework->getChoiceLabels("init_rank"),
            "review_type"       => $this->framework->getChoiceLabels("review_type")
        );

        foreach ( $ids as $id ) {

            $filterLogic = "([departmental_leadership] = '" . $id . "'";
            $filterLogic .= " OR [mentor_name] = '" . $id . "'";
            $filterLogic .= " OR [division_chief_name] = '" . $id . "'";
            $filterLogic .= " OR [mentor_committee_1] = '" . $id . "'";
            $filterLogic .= " OR [mentor_committee_2] = '" . $id . "'";
            $filterLogic .= " OR [mentor_committee_3] = '" . $id . "'";
            $filterLogic .= " OR [mentor_committee_4] = '" . $id . "'";
            $filterLogic .= " OR [mentor_committee_5] = '" . $id . "')";
            $filterLogic .= " AND [exclusion_reason] <> 1";
            $params      = array(
                "project_id"     => $pid,
                "fields"         => array(
                    "init_first_name",
                    "init_last_name",
                    "init_department",
                    "init_ladder_track",
                    "init_rank",
                    "review_type",
                    "mentor_name",
                    "mentor_committee_1",
                    "mentor_committee_2",
                    "mentor_committee_3",
                    "mentor_committee_4",
                    "mentor_committee_5",
                    "division_chief_name",
                    "departmental_leadership",
                    $this->framework->getProjectSetting('current-year-form') . "_complete",
                    $this->framework->getProjectSetting('first-stage-review-form') . "_complete",
                    $this->framework->getProjectSetting('department-leader-review-form') . "_complete"
                ),
                "filterLogic"    => $filterLogic,
                "exportAsLabels" => true
            );
            $data        = $this->framework->escape(\REDCap::getData($params));

            foreach ( $data as $recordid => $record ) {
                $eid       = $this->getEventId();
                $newRecord = $record[$eid];

                $init_department   = $labels["init_department"][$newRecord["init_department"]];
                $init_ladder_track = $labels["init_ladder_track"][$newRecord["init_ladder_track"]];
                $init_rank         = $labels["init_rank"][$newRecord["init_rank"]];
                $review_type_raw   = $labels["review_type"][$newRecord["review_type"]];
                $review_type       = $this->getReviewType(
                    $review_type_raw,
                    $id,
                    strtolower($newRecord["departmental_leadership"])
                );

                $status      = $this->getStatus($id, $newRecord);
                $link        = $this->getLink($recordid, $status, $id);
                $status_text = $this->getStatusText($status);

                $data[$recordid] = array(
                    "record_id"         => $recordid,
                    "init_first_name"   => $newRecord["init_first_name"],
                    "init_last_name"    => $newRecord["init_last_name"],
                    "init_department"   => $init_department,
                    "init_ladder_track" => $init_ladder_track,
                    "init_rank"         => $init_rank,
                    "review_type"       => $review_type,
                    "link"              => $link,
                    "status"            => $status,
                    "status_text"       => $status_text
                );
            }
            $alldata = array_unique(array_merge($alldata, $data), SORT_REGULAR);
        }
        return $alldata;
    }

    public function displayDataTable($data)
    {
        if ( empty($data) ) {
            echo "No data yet";
            return;
        }
        ?>
        <div class="dashboard_container">
            <table id="dashboard_table" class="table stripe hover row-border">
                <thead>
                    <tr>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Department</th>
                        <th>Ladder Track</th>
                        <th>Rank</th>
                        <th>Review Type</th>
                        <th>Status</th>
                        <th>Link</th>
                    </tr>
                </thead>
                <?php foreach ( $data as $record ) { ?>

                    <tr data-status="<?= $record['status'] ?>" data-record="<?= $record["record_id"] ?>">
                        <td>
                            <?= $this->framework->escape($record["init_first_name"]) ?>
                        </td>
                        <td>
                            <?= $this->framework->escape($record["init_last_name"]) ?>
                        </td>
                        <td>
                            <?= $this->framework->escape($record["init_department"]) ?>
                        </td>
                        <td>
                            <?= $this->framework->escape($record["init_ladder_track"]) ?>
                        </td>
                        <td>
                            <?= $this->framework->escape($record["init_rank"]) ?>
                        </td>
                        <td>
                            <?= $this->framework->escape($record["review_type"]) ?>
                        </td>
                        <td>
                            <?= $record["status_text"] ?>
                        </td>
                        <td>
                            <?= $record["link"] ?>
                        </td>
                    </tr>

                <?php } ?>
            </table>
        </div>
        <script>
            $(document).ready(function () {
                $('#dashboard_table').DataTable({
                    dom: 'rf<"clear">Bti',
                    stateSave: true,
                    buttons: [{
                        text: 'Show Complete Reviews',
                        action: function (e, dt, node, config) {
                            if ($.fn.dataTable.ext.search.length) {
                                $.fn.dataTable.ext.search.pop();
                                $(this.node()).html('Hide Complete Reviews');
                                dt.draw();
                            } else {
                                $.fn.dataTable.ext.search.push(
                                    function (settings, data, dataIndex) {
                                        $status = $(dt.row(dataIndex).node()).attr('data-status');
                                        return $status != 3 && $status != 7;
                                    }
                                );
                                $(this.node()).text('Show Complete Reviews');
                                dt.draw();
                            }
                        }
                    },
                        'colvis',
                    {
                        text: 'Refresh Table',
                        action: function () {
                            window.location.reload();
                        }
                    }
                    ],
                    initComplete: function (settings, json) {
                        const dt = this.DataTable();
                        $.fn.dataTable.ext.search.push(
                            function (settings, data, dataIndex) {
                                $status = $(dt.row(dataIndex).node()).attr('data-status');
                                return $status != 3 && $status != 7;
                            }
                        );
                        dt.draw();
                    },
                    order: [
                        [6, 'desc']
                    ],
                    paging: false,
                    scrollY: `calc(100vh - 200px)`,
                    scrollCollapse: true
                });
            });
        </script>
        <?php
    }

    /**
     * Return lower-case version of string input
     * @param string $string
     * @return string
     */
    public static function toLowerCase(string $string) : string
    {
        if ( extension_loaded('mbstring') ) {
            return mb_strtolower($string);
        }
        return strtolower($string);
    }
}