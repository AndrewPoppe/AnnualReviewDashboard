<?php

namespace YaleREDCap\AnnualReviewDashboard;

/**
 * Main EM class
 * 
 * @author Andrew Poppe
 */
class AnnualReviewDashboard extends \ExternalModules\AbstractExternalModule {
/**
     * Initiate CAS authentication
     * 
     * 
     * @return string|boolean username of authenticated user (false if not authenticated)
     */
    function authenticate() {

        require_once __DIR__ . '/vendor/jasig/phpcas/CAS.php';

        $cas_host = $this->getSystemSetting("cas-host");
        $cas_context = $this->getSystemSetting("cas-context");
        $cas_port = (int) $this->getSystemSetting("cas-port");
        $cas_server_ca_cert_id = $this->getSystemSetting("cas-server-ca-cert-pem");
        $cas_server_ca_cert_path = $this->getFile($cas_server_ca_cert_id);
        $server_force_https = $this->getSystemSetting("server-force-https");

        // Enable https fix
        if ($server_force_https == 1) {
            $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
            $_SERVER['HTTP_X_FORWARDED_PORT'] = 443;
            $_SERVER['HTTPS'] = 'on';
            $_SERVER['SERVER_PORT'] = 443;
        }
        
        // Initialize phpCAS
        \phpCAS::client(CAS_VERSION_2_0, $cas_host, $cas_port, $cas_context);

        // Set the CA certificate that is the issuer of the cert
        // on the CAS server
        \phpCAS::setCasServerCACert($cas_server_ca_cert_path);

        // Don't exit, let me handle instead
        \CAS_GracefullTerminationException::throwInsteadOfExiting();

        // force CAS authentication
        \phpCAS::forceAuthentication();
        
        // Return authenticated username
        return \phpCAS::getUser();
    }


    /**
     * Get url to file with provided edoc ID.
     * 
     * @param string $edocId ID of the file to find
     * 
     * @return string path to file in edoc folder
     */
    private function getFile(string $edocId) {
        if ($edocId === NULL) {
            return "";
        }
        $result = $this->query('SELECT stored_name FROM redcap_edocs_metadata WHERE doc_id = ?', $edocId);
        $filename = $result->fetch_assoc()["stored_name"];
        return EDOC_PATH . $filename;
    }

    function login() {
        try {
            $id = $this->authenticate();
        }
        catch (\CAS_GracefullTerminationException $e) {
            if ($e->getCode() !== 0) {
                $this->log($e->getMessage());
            }
        }
        catch (\Exception $e) {
            $this->log($e->getMessage());
        }
        finally {
            //if (empty($id)) {
            //    $this->log('Could not log in');
            //}
            return $id;
        }
    }

    function getStatus($id, $record) {
        // 0 - pending faculty submission (initial form completed only)
        // 1 - pending mentor review
        // 2 - pending division chief review
        // 3 - pending chair review
        // 4 - ready for mentor review (show first stage link)
        // 5 - ready for division chief review (show first stage link)
        // 6 - ready for final review (show final link)
        // 7 - final review completed
        $chair_completed = $record["chairs_comments_for_faculty_development_annual_que_complete"] == 2;
        $faculty_completed = $record["faculty_development_annual_questionnaire_2022_complete"] == 2;
        $first_stage_complete = $record["first_stage_review_comments_for_faculty_developmen_complete"] == 2;
        $review_type = $record["review_type"];
        $mentor = $record["mentor_name"];
        $division_chief = $record["division_chief_name"];
        $chair = $record["departmental_leadership"];

        $userIsMentor = $mentor == $id;
        $userIsDivisionChief = $division_chief == $id;
        $userIsChair = $chair == $id;

        $status = 0;
        if ($chair_completed) {
            $status = 7;
        } else if (!$faculty_completed) {
            $status = 0;
        } else if (($first_stage_complete && $userIsChair) || ($review_type == 1 && $userIsChair)) {
            $status = 6;
        } else if ($review_type == 3 && $userIsDivisionChief && !$first_stage_complete) {
            $status = 5;
        } else if ($review_type == 4 && $userIsMentor && !$first_stage_complete) {
            $status = 4;
        } else if ($first_stage_complete || $review_type == 1) {
            $status = 3;
        } else if ($review_type == 3) {
            $status = 2;
        } else if ($review_type == 4) {
            $status = 1;
        }
        return $status;
    }

    function getStatusText($status) {
        // 0 - pending faculty submission (initial form completed only)
        // 1 - pending mentor review
        // 2 - pending division chief review
        // 3 - pending chair review
        // 4 - ready for mentor review (show first stage link)
        // 5 - ready for division chief review (show first stage link)
        // 6 - ready for final review (show final link)
        // 7 - final review completed
        switch (strval($status)) {
            case "1":
                $status_text = "Pending Mentor Review";
                break;
            case "2":
                $status_text = "Pending Division Chief Review";
                break;
            case "3":
                $status_text = "Pending Chair Review";
                break;
            case "4":
                $status_text = "Ready for First Review";
                break;
            case "5":
                $status_text = "Ready for First Review";
                break;
            case "6":
                $status_text = "Ready for Final Review";
                break;
            case "7":
                $status_text = "Final Review Completed";
                break;
            default:
                $status_text = "Pending Faculty Submission";
                break;
        }
        return $status_text;
    }

    function getLink($record) {
        $link = "";
        if ($record["status"] == 6) {
            $link = $record["link_to_faculty_survey"];
        } else if ($record["status"] == 4 || $record["status"] == 5) {
            $link = $record["link_to_faculty_survey_first_stage"];
        }
        return $link;
    }

    function getData($id) {
        $params = array(
            "project_id" => $project_id,
            "fields" => array(
                "init_first_name", 
                "init_last_name",
                "init_department",
                "init_ladder_track",
                "init_rank",
                "review_type",
                "mentor_name",
                "division_chief_name",
                "departmental_leadership",
                "faculty_development_annual_questionnaire_2022_complete",
                "first_stage_review_comments_for_faculty_developmen_complete",
                "chairs_comments_for_faculty_development_annual_que_complete",
                "link_to_faculty_survey",
                "link_to_faculty_survey_first_stage"
            ),
            //"filterLogic" => '([departmental_leadership] = "'.$id.'") AND ([faculty_development_annual_questionnaire_2022_complete] = "2") AND ([chairs_comments_for_faculty_development_annual_que_complete] = "0")',
            "filterLogic" => '([departmental_leadership] = "'.$id.'") OR ([mentor_name] = "'.$id.'") OR ([division_chief_name] = "'.$id.'")',
            "exportAsLabels" => "TRUE"        
        );
        $data = \REDCap::getData($params);
        foreach ($data as &$record) {
            $record = $record[$this->getEventId()];
            
            //$record["init_first_name"] = $event["init_first_name"] ;
            //$record["init_last_name"] = $event["init_last_name"];
            $record["init_department"] = $this->getChoiceLabel("init_department", $record["init_department"]);
            $record["init_ladder_track"] = $this->getChoiceLabel("init_ladder_track", $record["init_ladder_track"]);
            $record["init_rank"] = $this->getChoiceLabel("init_rank", $record["init_rank"]);
            $record["status"] = $this->getStatus($id, $record);
            $record["link"] = $this->getLink($record);
            $record["status_text"] = $this->getStatusText($record["status"]);
            //$record["link_to_faculty_survey"] = $event["link_to_faculty_survey"];
        }
        return $data;
    }

    function displayDataTable($data) {
        if (empty($data)) {
            echo "No data yet";
            return;
        }
        ?>
        <div style="width:75%;">
        <table id="dashboard_table">
            <thead><tr>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Department</th>
                <th>Ladder Track</th>
                <th>Rank</th>
                <th>Review Type</th>
                <th>Status</th>
                <th>Survey Link</th>
            </tr></thead>
            <?php foreach ($data as $record) { ?>

                <tr>
                    <td><?=\REDCap::escapeHtml($record["init_first_name"])?></td>
                    <td><?=\REDCap::escapeHtml($record["init_last_name"])?></td>
                    <td><?=\REDCap::escapeHtml($record["init_department"])?></td>
                    <td><?=\REDCap::escapeHtml($record["init_ladder_track"])?></td>
                    <td><?=\REDCap::escapeHtml($record["init_rank"])?></td>
                    <td><?=\REDCap::escapeHtml($this->getChoiceLabel('review_type', $record["review_type"]))?></td>
                    <td><?=\REDCap::escapeHtml($record["status_text"])?></td>
                    <td>
                        <?php if ($record["link"] !== "") { ?>
                            <a target="_blank" href="<?=\REDCap::escapeHtml($record["link"])?>">link</a>
                        <?php } ?>
                    </td>
                </tr>
                
            <?php } ?>
        </table>
        </div>
        <script>
            $(document).ready( function () {
                $('#dashboard_table').DataTable();
            } );
        </script>
        <?php
        //var_dump($data);
    }

}

