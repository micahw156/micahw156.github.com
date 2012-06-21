<?php

/**
 * @file
 * This script imports data from the legacy ACSSoftwareList table into Drupal 6 nodes.
 *
 * Created by Micah Webner <micahw156@40138.no-reply.drupal.org>
 * @see https://dvc.hfcc.net/webadmin/issues/issue244
 */

// prevent this from running under apache:
if (array_key_exists('REQUEST_METHOD', $_SERVER)) {
  echo 'nope.  not executing except from the command line.';
  exit(1);
}

// set some server variables so Drupal doesn't freak out
$_SERVER['SCRIPT_NAME'] = '/acs-software-import.php';
$_SERVER['SCRIPT_FILENAME'] = '/acs-software-import.php';
$_SERVER['HTTP_HOST'] = 'dvc.hfcc.net';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'POST';

// act as the first user
global $user;
$user->uid = 1;

// Bootstrap Drupal
require 'includes/bootstrap.inc';
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

$dept_old = array('/^CAMPUS$/', '/^ASSESSMENT$/', '/^ELECT$/');
$dept_new = array('ANY', 'ASSESS', 'ELEC');

$query = 'SELECT * FROM {ACSSoftwareList} ORDER BY AppID';

$result = db_query($query);
while ($item = db_fetch_object($result)) {
  $tt = explode("-", $item->Title, 2);
  $department = preg_replace($dept_old, $dept_new, check_plain(trim($tt[0])));
  $title = $tt[1];

  printf("%-15s %-10s %s\n", $item->AppID, $department, $title);

  $install = trim($item->HowTo);
  $detail = trim($item->HowToDetail);
  if ($install == $detail) { $detail = ''; }

  $install = str_replace('<b>', '<strong>', $install);
  $install = str_replace('</b>', '</strong>', $install);

  $detail = str_replace('<b>', '<strong>', $detail);
  $detail = str_replace('</b>', '</strong>', $detail);

  $node = new StdClass();
  $node->type = 'acs_software';
  $node->private = 1;
  $node->status = 1;
  $node->promote = 0;
  $node->sticky = 0;
  $node->uid = 1;
  $node->name = 'admin';
  $node->comment = variable_get('comment_' . $node->type, 2);
  $node->revision = 0;

  $node->title = $title;
  $node->field_sw_appid[0]['value'] = trim($item->AppID);
  $node->field_sw_dept[0]['value'] = $department;

  if($install) { $node->field_sw_install[0]['value'] = $install; }
  if($detail) { $node->field_sw_detail[0]['value'] = $detail; }

  // node_save($node);
}
