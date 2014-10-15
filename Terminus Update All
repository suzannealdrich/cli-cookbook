<?php

// YOUR LOGIN INFO HERE:
$email = '';
$password = '';

// helper function
function terminus_json($command) {
  return json_decode(`drush $command --json`, TRUE);
}

echo `drush pauth $email --password=$password`;

$sites = terminus_json('psites');
$candidates = array();

foreach ($sites as $site_uuid => $data) {
  // Check framework and upstream url to see if it's a Drupal site
  if ((isset($data['information']['framework']) &&
      $data['information']['framework'] == 'drupal') ||
      (isset($data['information']['upstream']['url']) &&
       strpos($data['information']['upstream']['url'], 'drops-7') !== FALSE)) {
    $candidates[$site_uuid] = $data['information'];
  }
}
echo "\n\n";
echo "Found ". count($candidates) . " Drupal 7.x sites:\n";
foreach ($candidates as $site_uuid => $info) {
  echo $info['name'] ."\n";
}
echo "\n\n";
echo "*** WARNING ***\n";
echo "Continuing with this update will blow away any uncommitted changes.\n";
echo "Any uncommited changes will be permanently LOST.\n\n";
echo "This will also deploy changes to the LIVE environment for your sites.\n\n"
echo "Are you sure you want to do this?  Type 'yes' to continue: ";
$handle = fopen ("php://stdin","r");
$line = fgets($handle);
if(trim($line) != 'yes'){
    echo "ABORTING!\n";
    exit;
}
echo "\n";
echo "Thank you, continuing...\n\n";

foreach ($candidates as $site_uuid => $info) {
  echo "\n\n";
  echo "Starting on ". $info['name'] ."\n";
  echo "Fetching update status...";
  $status = terminus_json("psite-upstream-updates $site_uuid");
  if ($status['dev']['is_up_to_date_with_upstream'] !== TRUE) {
    echo "Found updates to apply from upstream\n";
    echo `drush psite-cmode $site_uuid dev git`;
    echo `drush psite-upstream-updates-apply $site_uuid`;
  }
  if ($status['test']['is_up_to_date_with_upstream'] !== TRUE) {
    echo "Deploying to test\n";
    echo `drush psite-deploy $site_uuid test -y`;
    // Short pause for git tags to propogate.
    sleep(10);
  }
  if ($status['live']['is_up_to_date_with_upstream'] !== TRUE) {
    echo "Deploying to live\n";
    echo `drush psite-deploy $site_uuid live -y`;
  }
}
