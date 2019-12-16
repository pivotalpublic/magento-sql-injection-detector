<?php

//Digest this: https://github.com/magento/magento2/releases.atom and pull out latest releases and minor releases. Then compare client Magento version and alert if out of date.

// print "Getting Magento release versions";

$patched = date("Ymd") . ":" . "true";

//https://en.wikipedia.org/wiki/Software_versioning

//MAJOR.MINOR.PATCH = 2.2.10 (10 being a patch)

function getMagentoReleases($github_username, $github_password) 
{
    $ch = curl_init('https://api.github.com/repos/magento/magento2/releases');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/vnd.github.v3+json',
        'Authorization: token ' . $github_password,
        'User-Agent: GitHub-' . $github_username
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $json = curl_exec($ch);
    curl_close($ch);
    return json_decode($json);
}

$releases_json = getMagentoReleases($github_username, $github_password); // This outputs the proper JSON.

$releases_arr = Array();
foreach($releases_json as $releases) {
    $releases_arr[] = $releases->tag_name;
}

//Such a cool function!
usort($releases_arr, 'version_compare');

$magento_composer_json_file = $mage_directory . "/composer.json";
$magento_composer_json = "";
if (file_exists($magento_composer_json_file)) {
    
    $magento_composer_json = file_get_contents($magento_composer_json_file);
    $composer_vars = json_decode($magento_composer_json);
    $testing_version = $composer_vars->version;

    //$testing_version = "2.0.1";
    $testing_version_parts = explode(".", $testing_version);

    //Step through to get patches
    $patch_higher = "NA";
    foreach($releases_arr as $release) {
        $version_parts = explode(".", $release);
        if ($version_parts[0] == $testing_version_parts[0]) {
            if ($version_parts[1] == $testing_version_parts[1]) {
                if ($version_parts[2] > $testing_version_parts[2]) {
                    $patch_higher = $version_parts[0] . "." . $version_parts[1] . "." . $version_parts[2];
                    // print "There is a new patch version: " . $patch_higher;
                }
            }
        }
    }

    if ($patch_higher != "NA") {
        print "\n";
        print $patched = date("Ymd") . ":" . $patch_higher . "\n";
        $patched = date("Ymd") . ":" . $patch_higher;
        print "You're on $testing_version. There is a newer patch version: " . $patch_higher . "\n";
        print "\n";
    }

} else {
    print "Can't get version number from composer.json...\n\n";
}
