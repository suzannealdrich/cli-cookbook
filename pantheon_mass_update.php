<?php

// IF UPDATING ALL SITES FOR A PANTHEON ONE "ORGANIZATION": UNCOMMENT AND ADD ITS UUID HERE
// $organization_uuid = 'your-organization-uuid-here';

echo "RECIPE:   Pantheon Mass Update\n";
echo "PURPOSE:  Updates sites with the latest upstream changes.\n";
echo "REQUIRES: Drush 6, Terminus\n\n";
echo "WARNING:  Loses all uncommitted changes on sites in SFTP mode.\n";
echo "WARNING:  Deploys all pending code updates straight to production.\n";
echo "WARNING:  Must be authenticated with drush pauth command.\n\n";

echo "CONFIRM:  Are you SURE you want to continue?  Type 'yes' to proceed: ";
$handle = fopen ("php://stdin","r");
$line = fgets($handle);
if(trim($line) != 'yes'){
    echo "ABORTING!\n";
    exit;
}
echo "\n";
echo "Confirmed, proceeding...\n\n";

// helper function
function terminus_json($command) {
  return json_decode(`drush $command --json`, TRUE);
}

$candidates = array();

// Sadly the API returns differently nested data for orgs vs user site lists.
// So we have some mucky munge code.
if (isset($organization_uuid)) {
  echo "\n\nRUNNING FOR ORGANIZATION $organization_uuid\n\n";
  $sites = terminus_json("porg-sites $organization_uuid");
  foreach ($sites as $site_uuid => $data) {
    // Check framework and upstream url to see if it's a Drupal site
    $site_framework = $data['framework'];
    $site_upstream_url = $data['upstream']['url'];
    $site_is_drupal7 = strpos($site_upstream_url, 'drops-7');

    if ((isset($site_framework) && $site_framework == 'drupal') &&
        (isset($site_upstream_url) && $site_is_drupal7 !== FALSE)) {
      $candidates[$site_uuid] = $data;
    }
  }
}
else {
  $sites = terminus_json('psites');
  foreach ($sites as $site_uuid => $data) {
    // Check framework and upstream url to see if it's a Drupal site
    $site_framework = $data['information']['framework'];
    $site_upstream_url = $data['information']['upstream']['url'];
    $site_is_drupal7 = strpos($site_upstream_url, 'drops-7');

    if ((isset($site_framework) && $site_framework == 'drupal') &&
        (isset($site_upstream_url) && $site_is_drupal7 !== FALSE)) {
      $candidates[$site_uuid] = $data['information'];
    }
  }
}

echo "\n\n";
echo "Found ". count($candidates) . " Drupal 7.x sites:\n";
foreach ($candidates as $site_uuid => $info) {
  echo "SITE NAME: " . $info['name'] . "\n";
  echo "UPSTREAM:  " . $info['upstream']['url'] . "\n\n";
}
echo "\n\n";
echo "*** WARNING ***\n";
echo "You must verify that each site has had any important changes committed.\n";
echo "ANY uncommitted changes will be permanently LOST.\n\n";
echo "Are you SURE you want to do this?  Type 'yes' to continue: ";
$handle = fopen ("php://stdin","r");
$line = fgets($handle);
if(trim($line) != 'yes'){
    echo "ABORTING!\n";
    exit;
}
echo "\n";
echo "Confirmed, proceeding...\n\n";

foreach ($candidates as $site_uuid => $info) {
  echo "\n\n-----------  ";
  echo "Starting on ". $info['name'];
  echo "  -----------\n";
  if (isset($organization_uuid)) {
    echo "NOTE: You may safely ignore these error messages on site deploy:\n";
    echo "\"No site found for UUID '$site_uuid'.\"\n\n";
  }
  echo "Fetching update status...";
  $status = terminus_json("psite-upstream-updates $site_uuid");
  if ($status['dev']['is_up_to_date_with_upstream'] !== TRUE) {
    echo "Found updates to apply from upstream\n";
    echo `drush psite-cmode $site_uuid dev git`;
    echo `drush psite-upstream-updates-apply $site_uuid`;
  }
  if (array_key_exists('test', $status) &&
    is_array($status['test']) &&
    array_key_exists('is_up_to_date_with_upstream', $status['test']) &&
    $status['test']['is_up_to_date_with_upstream'] !== TRUE) {
      echo "Deploying to test\n";
      echo `drush psite-deploy $site_uuid test --update --cc -y`;
      // Short pause for git tags to propogate.
      sleep(10);
  }
  if (array_key_exists('live', $status) &&
    is_array($status['live']) &&
    array_key_exists('is_up_to_date_with_upstream', $status['live']) &&
    $status['live']['is_up_to_date_with_upstream'] !== TRUE) {
      echo "Deploying to live\n";
      echo `drush psite-deploy $site_uuid live --update --cc -y`;
  }
  echo "\nNo more to do for ". $info['name'] ."\n";
}

