<?php

/**
 * @file
 * Convert Audio Nodes (Drupal 6)
 *
 * This script was used to convert audio nodes from the Drupal 5 Audio module
 * to Drupal 6 with CCK and Filefield Module.
 *
 * This work is based on a post at GeeksAndGod.com by Matt Farina.
 *
 * @see http://geeksandgod.com/tutorials/computers/cms/drupal/drupal-converting-audio-module-filefield-module
 */

// This script was made to run with php-cli, so
// set some server variables so Drupal doesn't freak out
$_SERVER['SCRIPT_NAME'] = '/audio_convert.php';
$_SERVER['SCRIPT_FILENAME'] = '/audio_convert.php';
$_SERVER['HTTP_HOST'] = 'example.com';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'POST';

// act as the first user
global $user;
$user->uid = 1;

// change to the Drupal directory
chdir('/docroot');

// Drupal bootstrap throws some errors when run via command line
//  so we tone down error reporting temporarily
error_reporting(E_ERROR | E_PARSE);

// Bootstrap Drupal
require 'includes/bootstrap.inc';
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

// Query all the audio nodes.
$result = db_query("SELECT nid FROM {node} WHERE type = 'audio'");

// Loop through all the audio nodes.
while ($nid = db_fetch_object($result)) {
  // Load the node.
  $node = node_load($nid->nid);

  // Change the node type
  $node->type = 'audionew';

  // Migrate the audio file to filefield
  $node->field_mp3_file[] = array(
    'fid' => $node->audio['file']->fid,
    'list' => 1, // Set to 1 to display on the node or 0 to hide.
    'uid' => $node->audio['file']->uid,
    'filename' => $node->audio['file']->filename,
    'filepath' => $node->audio['file']->filepath,
    'filemime' => $node->audio['file']->filemime,
    'filesize' => $node->audio['file']->filesize,
    'status' => $node->audio['file']->status,
    'timestamp' => $node->audio['file']->timestamp,
  );
  if ($node->audio_tags['artist']) { $node->field_audio_artist[0]['value'] = $node->audio_tags['artist']; }
  if ($node->audio_tags['ccli']) { $node->field_audio_ccli[0]['value'] = $node->audio_tags['ccli']; }
  if ($node->audio_tags['soloist']) { $node->field_audio_soloist[0]['value'] = $node->audio_tags['soloist']; }
  if ($node->audio_tags['key']) { $node->field_audio_key[0]['value'] = $node->audio_tags['key']; }
  if ($node->audio_tags['composer']) { $node->field_audio_composer[0]['value'] = $node->audio_tags['composer']; }
  if ($node->audio_tags['copyright']) { $node->field_audio_copyright[0]['value'] = $node->audio_tags['copyright']; }
  $node->field_audio_duration[0]['value'] = $node->audio['playtime'];
  $node->field_audio_date = $node->field_date;
  // Remove the audio module informaion.
  unset($node->audio);
  unset($node->audio_tags);
  unset($node->url_play);
  unset($node->url_download);
  unset($node->field_date);

  // Save the changes
  node_save($node);
  // print_r($node);
  // return;
}
