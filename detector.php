<?php

/*
Pivotal Detector
Tait Pollack

Checks Magento database for changes to content rendered to user. If different alert the admin!

Tables to check: cms_page, cms_block, core_config_data

*/

include 'config.php';

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

    $cms_page = getHashofTable($con, "cms_page", $salt);
    $cms_block = getHashofTable($con, "cms_block", $salt);
    $core_config_data = getHashofTable($con, "core_config_data", $salt);
    
    $results = [];
    $results["cms_page"] = $cms_page;
    $results["cms_block"] = $cms_block;
    $results["core_config_data"] = $core_config_data;
    $json_string = json_encode($results);
    file_put_contents($hashes_file, $json_string);

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

    if ($count_differences > 0) {
        print "Different is...\n";
        $the_differences = var_export($difference, true);
        print $the_differences;
    }

    if ($count_differences > 0) {
        // $the_keys = array_keys($difference);
        // $keys_string = implode(",", $the_keys);
        $message = "Table Change Alert on $client_name website\n";
        $message .= "==========================================\n";
        $message .= $the_differences . "\n";
        print $message;
        sendMessage($chat_id, $message, $telegram_token);
    } else {
        print "No change, don't worry...\n";
        print "\n";
        //sendMessage($chat_id, "Nothing changed on the " . $client_name . " website...", $telegram_token);
    }

    $con->close();
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

function getHashofTable($con, $table, $salt) {

    $query = "SELECT * FROM " . $table . ";";
    $result = $con->query($query);

    $stringMash = [];
    $the_count = 0;
    while ($columns = $result->fetch_array(MYSQLI_NUM)) {

        //Get row ID (always first)
        $stringMash[$the_count][0] = $columns[0];

        //Get unique hash for each row
        array_shift($columns);
        $stringMash[$the_count][0] = $stringMash[$the_count][0] . ":" . hash( 'sha1', implode($salt, $columns));

        $the_count++;

    }

    // print_r($stringMash);
    return $stringMash;

}

function sendMessage($chatID, $messaggio, $token) {
    print "sending message to " . $chatID . "\n";

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
    return $result;
}
