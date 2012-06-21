<?php

/**
 * @file
 * This script imports data from the legacy ACSLabs table into Drupal 6 nodes.
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
$_SERVER['SCRIPT_NAME'] = '/acs-lab-import.php';
$_SERVER['SCRIPT_FILENAME'] = '/acs-lab-import.php';
$_SERVER['HTTP_HOST'] = 'dvc.hfcc.net';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'POST';

// act as the first user
global $user;
$user->uid = 1;

// Bootstrap Drupal
require 'includes/bootstrap.inc';
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

$dept_old = array('/^BBA$/', '/^ARTS$/', '/^TAP$/');
$dept_new = array('BUS', 'FINE', 'TAE');


$query = 'SELECT * FROM {ACSLabs} ORDER BY Lab';

$result = db_query($query);
while ($item = db_fetch_object($result)) {
  $title = $item->Lab;
  $department = preg_replace($dept_old, $dept_new, check_plain(trim($item->Dept)));

  printf("%-10s %-4s (%d Stations)\n", $title, $department, $item->STATIONS);

  $node = new StdClass();
  $node->type = 'acs_lab';
  $node->private = 1;
  $node->status = 1;
  $node->promote = 0;
  $node->sticky = 0;
  $node->uid = 1;
  $node->name = 'admin';
  $node->comment = variable_get('comment_' . $node->type, 2);
  $node->revision = 0;

  $node->title = $title;

  $node->field_lab_dept[0]['value'] = $department;
  $node->field_lab_stations[0]['value'] = trim($item->STATIONS);
  $node->field_lab_remote[0]['value'] = trim($item->RemoteControl);
  $node->field_lab_comments[0]['value'] = trim($item->Comments);
  $node->field_lab_vendor[0]['value'] = check_plain(trim($item->VENDOR));
  $node->field_lab_stage[0]['value'] = strtotime($item->LASTSTAGE);

  $node->field_lab_os[0]['value'] = check_plain(trim($item->OS));
  $node->field_lab_cpu[0]['value'] = check_plain(trim($item->CPU));
  $node->field_lab_ram[0]['value'] = check_plain(trim($item->RAM));
  $node->field_lab_hdd[0]['value'] = check_plain(trim($item->HDD));
  $node->field_lab_nic[0]['value'] = check_plain(trim($item->NIC));
  $node->field_lab_video[0]['value'] = check_plain(trim($item->VIDEO));
  $node->field_lab_monitor[0]['value'] = check_plain(trim($item->MONITOR));

  $node->field_lab_color[0]['value'] = check_plain(trim($item->COLOR));
  $node->field_lab_laser[0]['value'] = check_plain(trim($item->LASER));
  $node->field_lab_scanner[0]['value'] = check_plain(trim($item->SCANNER));

  $loads = db_query("SELECT AppID FROM {ACSLoads} WHERE LOWER(Lab) = LOWER('%s') ORDER BY AppID", $title);
  while ($app = db_fetch_object($loads)) {
    $sw = db_fetch_object(db_query("SELECT nid FROM {content_type_acs_software} WHERE field_sw_appid_value='%s'", $app->AppID));
    $node->field_lab_software[]['nid'] = $sw->nid;
    print "  " . $app->AppID . " " . $sw->nid . "\n";
  }

  //print_r($node);
  //node_save($node);
  //exit;
}
