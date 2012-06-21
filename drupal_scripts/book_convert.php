<?php

/**
 * @file
 * Convert book nodes to page nodes. (Drupal 6)
 *
 * This is an ugly little command-line script to convert all book nodes
 * on a site to page nodes and remove their book module information.
 *
 * It would probably be better to just used the nodetype module and
 * then disable and uninstall book module. The change could also be
 * made with a simple update query against the node table in the database.
 *
 * @see http://drupal.org/project/nodetype
 */

// set some server variables so Drupal doesn't freak out
$_SERVER['SCRIPT_NAME'] = '/bbconvert.php';
$_SERVER['SCRIPT_FILENAME'] = '/bbconvert.php';
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
require 'includes/bootstrap.inc';
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

// Query all the audio nodes.
$result = db_query("SELECT nid FROM {node} WHERE type = 'book'");

// Loop through all the audio nodes.
while ($nid = db_fetch_object($result)) {
  // Load the node.
  $node = node_load($nid->nid);

  // Change the node type
  $node->type = 'page';

  unset($node->book);

  // Save the changes
  node_save($node);
  // print_r($node);
  // return;
}
