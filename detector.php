<?php

/*
Pivotal SQL Injection Detector
Developer: Tait Pollack

Light weight, very simple, very useful tool. Checks Magento 2 database for changes to content rendered to user. If different alert the admin!

Tables to check: cms_page, cms_block, core_config_data

*/

$client_name = "Client Name";
$chat_id = ""; //Telegram Chat ID (Get this from the @cid_bot)
$telegram_token = ""; //Telegram Bot API Key Here (Get this from BoTFather)
$mage_directory = __DIR__ . "/../httpdocs"; //Relative location of Magento website root
$hashes_file = __DIR__ . "/detector.json";

$magento_vars = fetchArray($mage_directory . "/app/etc/env.php");

$host = $magento_vars["db"]["connection"]["default"]["host"];
$dbname = $magento_vars["db"]["connection"]["default"]["dbname"];
$username = $magento_vars["db"]["connection"]["default"]["username"];
$password = $magento_vars["db"]["connection"]["default"]["password"];

print "\nPivotal Table Change Detector";
print "\n=============================\n";

print "Directory: " . __DIR__ . "\n";

$con = mysqli_connect($host, $username, $password, $dbname);

// Check connection
if (!$con) {

    die("Connection failed: " . mysqli_connect_error());

} else {

    print "Connected to " . $dbname . "...\n";
    print "\n";

    $previous_json = "";
    if (is_file($hashes_file)) {
        $previous_json = file_get_contents($hashes_file);
    } else {
        print "Nothing to compare yet...\n";
    }

    $cms_page = getHashofTable($con, "cms_page");
    $cms_block = getHashofTable($con, "cms_block");
    $core_config_data = getHashofTable($con, "core_config_data");
    
    $results = [];
    $results["cms_page"] = $cms_page;
    $results["cms_block"] = $cms_block;
    $results["core_config_data"] = $core_config_data;
    $json_string = json_encode($results);
    print "Output: " . $json_string;
    file_put_contents($hashes_file, $json_string);
    print "\n\n";

    if ($previous_json != $json_string) {
        print "Something changed! Alert!\n";
        sendMessage($chat_id, "Something changed on the " . $client_name . " website! Alert!", $telegram_token);
    } else {
        print "No change, don't worry...\n";
        //sendMessage($chat_id, "Nothing changed on the " . $client_name . " website...", $telegram_token);
    }
    $con->close();
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

function getHashofTable($con, $table) {

    $query = "SELECT * FROM " . $table . ";";
    $result = $con->query($query);

    $stringMash = "";
    while ($columns = $result->fetch_array(MYSQLI_NUM)) {
        $stringMash .= implode("[pi>otal]", $columns);
    }

    $hash = hash( 'sha1', $stringMash );
    return $hash;

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
