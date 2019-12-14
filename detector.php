<?php

/*
Pivotal Detector
Tait Pollack

Checks Magento database for changes to content rendered to user. If different alert the admin!

Tables to check: cms_page, cms_block, core_config_data, admin_user

*/

include 'config.php';

//Often found after sqlinjection
$alert_on = Array("getscript", "ajax.request", ".js");

$hashes_file = __DIR__ . "/detector.json";
$magento_vars = fetchArray($mage_directory . "/app/etc/env.php");

$host = $magento_vars["db"]["connection"]["default"]["host"];
$dbname = $magento_vars["db"]["connection"]["default"]["dbname"];
$username = $magento_vars["db"]["connection"]["default"]["username"];
$password = $magento_vars["db"]["connection"]["default"]["password"];

print "\nPivotal Table Change Detector";
print "\n=============================\n";

print "Directory: " . __DIR__ . "\n";

// print "Host: " . $host . "\n";
// print "Database Name: " . $dbname . "\n";
// print "Username: " . $username . "\n";
// print "Password: " . $password . "\n";

$con = mysqli_connect($host, $username, $password, $dbname);
// Check connection
if (!$con) {

    die("Connection failed: " . mysqli_connect_error());

} else {

    print "Connected to " . $dbname . "...\n";
    print "\n";

    $previous_json = "";
    if (is_file($hashes_file)) {
        $previous_json_string = file_get_contents($hashes_file);
        $previous_json = (array) json_decode($previous_json_string);
    } else {
        print "Nothing to compare yet...\n";
    }

    $cms_page = getHashofTable($con, "cms_page", $salt, $alert_on);
    $cms_block = getHashofTable($con, "cms_block", $salt, $alert_on);
    $core_config_data = getHashofTable($con, "core_config_data", $salt, $alert_on);
    $admin_user = getHashofTable($con, "admin_user", $salt, null, 4); //4 = username
    
    $results = [];
    $results["cms_page"] = $cms_page;
    $results["cms_block"] = $cms_block;
    $results["core_config_data"] = $core_config_data;
    $results["admin_user"] = $admin_user;
    $json_string = json_encode($results);
    //file_put_contents($hashes_file, $json_string);

    // print "json_string Output:\n";
    // print_r($results);
    // print "\n\n";

    // print "previous_json Output:\n";
    // print_r($previous_json);
    // print "\n\n";


    // $difference = array_diff_assoc($results, $previous_json);

    $difference = arrayRecursiveDiff($results, $previous_json);

    $count_differences = count($difference);

    print "There are $count_differences different tables...\n";
    print "\n";

    if ($count_differences > 0) {
        //print "Different is...\n";
        $plain_text_message = plainTextDifferences($difference);
        $the_differences = var_export($difference, true);
        //print $the_differences;
    }

    if ($count_differences > 0) {
        // $the_keys = array_keys($difference);
        // $keys_string = implode(",", $the_keys);
        $message = "$client_name Table Change\n";
        $message .= "========================\n";
        $message .= $plain_text_message . "\n";
        print "\n";
        print "Plain Text...\n";
        print plainTextDifferences($difference);
        print "\n";
        print "Array Structure...\n";
        print $the_differences;
        print "\n";
        print "\n";
        //print $message;

        //$chat_id_arr = explode(",", $chat_id);
        //foreach($chat_id_arr as $chat_id) {
            sendMessage($chat_ids, $message, $telegram_token);
            print "\n";
        //}

        //Update new hashes after sending message. Comment out while debugging to keep getting same results.
        file_put_contents($hashes_file, $json_string);
    } else {
        print "No change since last check...\n";
        print "\n";
        //sendMessage($chat_ids, "Nothing changed on the " . $client_name . " website...", $telegram_token);
    }

    $con->close();
}

function plainTextDifferences($arr) {
    $plain_text = "";
    foreach ($arr as $table_name => $table_contents) {
        $plain_text .= "Table " . $table_name . "\n";
        foreach ($table_contents as $table_content) {
            $row_data = explode(":", $table_content[0]);
            $plain_text .= "ID: " . $row_data[0];
            //if ($found = $row_data[2]) {
                if ($field_checked = $row_data[3]) {
                    $field_string = " - " . $field_checked;
                }
                $plain_text .= " detected " . $row_data[2] . $field_string . "\n";
            //} else {
                //$plain_text .= "\n";
            //}
        }
        $plain_text .= "\n";
    }
    return $plain_text;
}

function arrayRecursiveDiff($aArray1, $aArray2) {
    $aReturn = array();

    foreach ($aArray1 as $mKey => $mValue) {
        if (array_key_exists($mKey, $aArray2)) {
            if (is_array($mValue)) {
                $aRecursiveDiff = arrayRecursiveDiff($mValue, $aArray2[$mKey]);
                if (count($aRecursiveDiff)) { $aReturn[$mKey] = $aRecursiveDiff; }
            } else {
                if ($mValue != $aArray2[$mKey]) {
                    $aReturn[$mKey] = $mValue;
                }
            }
        } else {
            $aReturn[$mKey] = $mValue;
        }
    }

    return $aReturn;
}


function fetchArray($in)
{
  if (is_file($in)) {
    print "\n";  
    print $in . " is a file...\n";
    return include $in;
  } else {
    print "\n";
    print $in . " is not a file...\n";
    return false;
  }
}

function getHashofTable($con, $table, $salt, $alert_on = null, $extract_field = null) {

    $query = "SELECT * FROM " . $table . ";";
    $result = $con->query($query);

    $stringMash = [];
    $the_count = 0;
    while ($columns = $result->fetch_array(MYSQLI_NUM)) {

        //Get row ID (always first)
        $stringMash[$the_count][0] = $columns[0];
        
        //Get extracted field if requested
        $extracted_field = "";
        if ($extract_field) {
            $extracted_field = ":" . $columns[$extract_field];
        }

        //Get unique hash for each row
        array_shift($columns);
        $imploded_columns = implode($salt, $columns);

        //Check for things to alert on
        $alert_string = ":change";
        $found_alert_arr = [];
        if ($alert_on) {
            foreach ($alert_on as $alert) {
                if (strpos(strtolower($imploded_columns), strtolower($alert)) !== false) {
                    $found_alert_arr[] = $alert;
                    print "Found $alert in $table: " . $stringMash[$the_count][0] . "\n";
                    print "\n";
                }
            }
            if (count($found_alert_arr) > 0) {
                $alert_string = ":" . implode(",", $found_alert_arr);
            } 
        }

        $stringMash[$the_count][0] = $stringMash[$the_count][0] . ":" . hash( 'sha1', $imploded_columns) . $alert_string . $extracted_field;
        
        //debug only
        //print $table . " row: " . $stringMash[$the_count][0] . ":" . implode("<|split|>", $columns) . "\n";

        $the_count++;

    }

    //prettier when debugging
    // print "\n";
    // print "\n";

    // print_r($stringMash);
    return $stringMash;

}

function sendMessage($chatIDs, $messaggio, $token) {

    $chat_id_arr = explode(",", $chatIDs);
    foreach ($chat_id_arr as $chatID) {

        print "sending message to " . $chatID . "\n";
        print "\n";

        $url = "https://api.telegram.org/bot" . $token . "/sendMessage?chat_id=" . $chatID;
        $url = $url . "&text=" . urlencode($messaggio);
        $ch = curl_init();
        $optArray = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true
        );
        curl_setopt_array($ch, $optArray);
        $result = curl_exec($ch);
        curl_close($ch);
    }

    //return $result;
}