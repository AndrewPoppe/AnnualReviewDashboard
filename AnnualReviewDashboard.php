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

    function getData($id) {
        $params = array(
            "project_id" => $project_id,
            "fields" => array(
                "init_first_name", 
                "init_last_name",
                "init_department",
                "init_ladder_track",
                "init_rank",
                "link_to_faculty_survey"
            ),
            "filterLogic" => '([departmental_leadership] = "18") AND ([faculty_development_annual_questionnaire_2022_complete] = "2") AND ([chairs_comments_for_faculty_development_annual_que_complete] = "0")',
            "exportAsLabels" => "TRUE"        
        );
        $data = \REDCap::getData($params);
        foreach ($data as &$record) {
            $event = $record[$this->getEventId()];
            
            $record["init_first_name"] = $event["init_first_name"] ;
            $record["init_last_name"] = $event["init_last_name"];
            $record["init_department"] = $this->getChoiceLabel("init_department", $event["init_department"]);
            $record["init_ladder_track"] = $this->getChoiceLabel("init_ladder_track", $event["init_ladder_track"]);
            $record["init_rank"] = $this->getChoiceLabel("init_rank", $event["init_rank"]);
            $record["link_to_faculty_survey"] = $event["link_to_faculty_survey"];
        }
        return $data;
    }

    function displayDataTable($data) {
        if (empty($data)) {
            echo "No data yet";
            return;
        }
        ?>
        <table>
            <tr>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Department</th>
                <th>Ladder Track</th>
                <th>Rank</th>
                <th>Survey Link</th>
            </tr>
            <?php foreach ($data as $record) { ?>

                <tr>
                    <td><?=\REDCap::escapeHtml($record["init_first_name"])?></td>
                    <td><?=\REDCap::escapeHtml($record["init_last_name"])?></td>
                    <td><?=\REDCap::escapeHtml($record["init_department"])?></td>
                    <td><?=\REDCap::escapeHtml($record["init_ladder_track"])?></td>
                    <td><?=\REDCap::escapeHtml($record["init_rank"])?></td>
                    <td><a href="<?=\REDCap::escapeHtml($record["link_to_faculty_survey"])?>">link</a></td>
                </tr>
                
            <?php } ?>
        </table>
        <?php
        var_dump($data);
    }

}

