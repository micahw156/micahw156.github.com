<?php

/**
 * @file
 * Import legacy podcast into nodes. (Drupal 6)
 *
 * Fetch a podcast feed and import all missing episodes as nodes.
 */

// We only want this to run from the command line.
// Prevent this from running under apache:
if (array_key_exists('REQUEST_METHOD', $_SERVER)) {
  echo 'nope.  not executing except from the command line.';
  exit(1);
}

// set some server variables so Drupal doesn't freak out
$_SERVER['SCRIPT_NAME'] = '/ignited-import.php';
$_SERVER['SCRIPT_FILENAME'] = '/ignited-import.php';
$_SERVER['HTTP_HOST'] = 'my.cornerstonehighland.com';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'POST';

// act as the first user
global $user;
$user->uid = 1;

// change to the Drupal directory
chdir('/path/to/docroot');

// Drupal bootstrap throws some errors when run via command line
//  so we tone down error reporting temporarily
error_reporting(E_ERROR | E_PARSE);

// Bootstrap Drupal
require_once './includes/bootstrap.inc';
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

$feedurl = "http://ignited.org/podcasts/podcast.php";

echo "Getting podcast feed...\n";
$response = drupal_http_request($feedurl);

if ($response->code == '200') {
  $xml = simplexml_load_string($response->data);
}
else {
  echo "Error " . $response->code . ": " . check_plain($response->error) . "\n";
  return FALSE;
}

$count = 0;

echo "Processing items...\n";
$items = $xml->channel->item;
foreach($items as $item) {
  //print_r($item);
  $title = trim($item->title);
  $pubDate = trim($item->pubDate);
  $location = trim($item->guid);
  $directory = file_directory_path() . "/audio";
  $file_destination = $directory . "/" . basename($location);
  $alt_file = $file_destination;
  if (module_exists('transliteration')) {
    require_once (drupal_get_path('module', 'transliteration') . '/transliteration.inc');
    $newname = transliteration_clean_filename(basename($location));
    $newname = str_replace(',', '', $newname);
    $newname = str_replace("'", '', $newname);
    $file_destination = $directory . "/" . $newname;
  }

  print "Title: " . $title ."\n";
  print "Date:  " . $pubDate ."\n";
  print "URL:   " . $location ."\n";
  print "File:  " . $file_destination ."\n";

  if (!file_exists($file_destination) && !file_exists($alt_file)) {
    echo "Downloading file...\n";
    $result = drupal_http_request($location);
    if ($result->code == 200 ) {
      //check that the files directory is writable
      if (file_check_directory($directory, FILE_CREATE_DIRECTORY)) {
        file_save_data($result->data, $file_destination, FILE_EXISTS_REPLACE);
        echo "Building node...\n";
        $details = stat($file_destination);
        $filesize = $details['size'];
        $mtime = $details['mtime'];
        $date_value = date('Y-m-d\T00:00:00', $mtime);
        $name = basename($file_destination);

        $file_obj = new stdClass();
        $file_obj->filename = $name;
        $file_obj->filepath = $file_destination;
        $file_obj->filemime =  file_get_mimetype($name);
        $file_obj->filesize = $filesize;
        $file_obj->filesource = $name;
        $file_obj->uid = $uid;
        $file_obj->status = FILE_STATUS_TEMPORARY;
        $file_obj->timestamp = $mtime; // time();
        $file_obj->list = 0;
        $file_obj->new = TRUE;

        // save file to database
        drupal_write_record('files', $file_obj);

        $node = new StdClass();
        $node->type = 'podcast';
        $node->status = 1;
        $node->promote = 0;
        $node->comment = 0;
        $node->uid = 1;
        $node->name = 'admin';
        $node->format = FILTER_FORMAT_DEFAULT;
        $node->language = 'en';
        $node->date = $pubDate;
        $node->updated = $pubDate;
        $node->title = $title;

        // Migrate the audio file to filefield
        $node->field_audio_file[] = array(
          'fid' => $file_obj->fid,
          'list' => 1, // Set to 1 to display on the node or 0 to hide.
          'uid' => $file_obj->uid,
          'filename' => $file_obj->filename,
          'filepath' => $file_obj->filepath,
          'filemime' => $file_obj->filemime,
          'filesize' => $file_obj->filesize,
          'status' => $file_obj->status,
          'timestamp' => $file_obj->timestamp,
        );

        $fileinfo = mystone_get_id3_data($file_destination);
        $comments = preg_split('/ /',$fileinfo['comments'], 2);
        $node->field_podcast_speaker[] = array('value' => $fileinfo['artist']);
        $node->field_podcast_scripture[] = array('value' => $comments[1]);
        $node->field_podcast_duration[] = array('value' => $fileinfo['duration']);
        $node->field_podcast_podcast[] = array('nid' => 331, 'view' => 'Ignited Podcast');

        // print_r($node);

        /*
         * Save the node using the API.
         */
        node_invoke_nodeapi($node, 'ignited_import');
        node_validate($node);
        if ($errors = form_get_errors()) {
          echo $errors;
        }
        $newnode = node_submit($node);
        node_save($newnode);

        /*
         * Save attached files to the node.
         * Why doesn't this fire automatically???
         */
        if ($node->files) {
          upload_save($node);
        }

        echo "Created node " . $node->nid . ".\n\n";

        $count++;
        //if ($count == 10) {
        //  echo "\nStopping after ten imports.\n\n";
        //  exit;
        //}
      }
      else {
        print "Cannot write to files directory. Aborting\n";
        exit;
      }
    }
    else {
      print "Could not retrieve " . $location . ". Aborting\n";
      exit;
    }
  }
  else {
    print "FILE EXISTS. SKIPPING!\n\n";
  }
}
