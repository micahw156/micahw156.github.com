<?php

/**
 * @file
 * Import Moveable Type blog to Drupal 6.
 *
 * This script is based on scripts found in http://drupal.org/node/860
 * for converting MovableType data to Drupal. In theory it could pull
 * data from any appropriately formatted RDF file.
 *
 * Original by James Andres.
 * Updates by Jonathan Woytek [woytek (at) dryrose (dot) com].
 * further update by Chris Andrichak
 * file import based on code by Jamie McClelland
 * uses even more code snippets from all over the place.
 * @author James Andres/Jonathan Woytek/Chris Andrichak/Jamie McClelland/Micah Webner
 * @version 2009-06-24
 *
 * This script is intended to run stand-alone from the *nix command line
 * and not from within a Drupal page or module. As such, it has header
 * and initialization information not found in other examples on d.o#860.
 */

// prevent this from running under apache:
if (array_key_exists('REQUEST_METHOD', $_SERVER)) {
  echo 'nope.  not executing except from the command line.';
  exit(1);
}

// set some server variables so Drupal doesn't freak out
$_SERVER['SCRIPT_NAME'] = '/netadmin2drupal.php';
$_SERVER['SCRIPT_FILENAME'] = '/netadmin2drupal.php';
$_SERVER['HTTP_HOST'] = 'my.hfcc.edu';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'POST';

// act as the first user
global $user;
$user->uid = 1;

// change to the Drupal directory
chdir('/dvcweb');

// Drupal bootstrap throws some errors when run via command line
//  so we tone down error reporting temporarily
error_reporting(E_ERROR | E_PARSE);

// Bootstrap Drupal
require 'includes/bootstrap.inc';
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

//******Change the 'drupal.rdf' line to the location of your MT XML export ******
$xml = simplexml_load_file('/tmp/files/netadmin/content/export/site-netadmin-export.xml');

// Set oldurl to the old URL to your mt blog (fully-qualified).
$oldurl = 'https://dvc.hfcc.net/';

// Set tagVid to the vocabulary ID for your freeform tag taxonomy.
$tagVid = 1;

// Set nodeType to the type of entry you want to create (page, story, blog, etc.).
$nodeType = 'story';

foreach($xml->item as $item) {
  $comment_count = count($item->comments->comment);
  //Create a node
  //$node = (object) array();
  $node = new StdClass();
  $node->type = $nodeType;

  $node_type_default = variable_get('node_options_'.$node->type, array('status', 'promote'));
  $node->private = 1;
  $node->status = 1;
  $node->promote = 1; // in_array('promote', $node_type_default);
  $node->sticky = 0;
  $node->comment = variable_get('comment_'.$node->type, 2);
  $node->revision = in_array('revision', $node_type_default);
  // $node->uid = $user->uid;
  // $node->name = $user->name;
  // This works if users have been created in Drupal. Otherwise sets author uid=1.
  $node->uid = db_result(db_query("SELECT uid from {users} WHERE LOWER(name) = '%s' LIMIT 1",$item->creator));
  $node->name = $item->creator;
  if ($node->uid == 0) {
    $node->uid = 1;
    $node->name = 'admin';
  }
  $node->format = FILTER_FORMAT_DEFAULT;
  $node->language = 'en';
  $node->date = $item->date;
  $node->updated = $node->date;
  $node->title = $item->title;
  $node->body = check_markup(str_replace($oldurl, '/', $item->entrybody));
  if (strlen($item->entrymore)) {
    $node->body .= "\n<!--break-->\n". check_markup(str_replace($oldurl, '/', $item->entrymore));
  }
  if ($item->subject) {
    // Save the tags
    foreach ($item->subject as $subject) {
      $term = taxonomy_get_term_by_name($subject);
      //if the term already exists, update the node
      if($term && $term[0]->name == $subject) {
        $term = current($term);
        $node->taxonomy[] = $term->tid;
        $node->taxonomy_term = (string) $subject;
      } else {
        //else create a new category and update the node
        $newEntry = (string) str_replace("'", "''", $subject);
        $term = array('vid' => $tagVid, 'name' => $newEntry);
        $status = taxonomy_save_term($term);
        $newtermID = $term['tid'];
        $node->taxonomy[] = $newtermID;
        $node->taxonomy_term = (string) $subject;
      }
    }
  }

  echo str_replace($oldurl, '', $item->link) ." \"$item->title\" by $node->name (uid=$node->uid)\n";

  /*
   * Let's parse the body and find interesting stuff to import as files.
   */
  $filedir = '/tmp/files';

  $dom = new domDocument;
  $dom->strictErrorChecking = FALSE;
  $dom->preserveWhiteSpace = FALSE;
  $dom->loadHTML($node->body);
  $dom_images = $dom->getElementsByTagname('img');
  foreach ($dom_images as $image) {
    if ($filename = $image->getAttribute('src')) {
      if (file_exists($filedir . $filename)) {
        echo "  Image: ". $filename;
        if ($file_obj = mt2file($filedir . $filename, $node->uid)) {
          $node->files[$file_obj->fid] = $file_obj;
          echo " -- created ". $file_obj->fid ." at ". $file_obj->filepath ."\n";
          $image->setAttribute('src', "/". $file_obj->filepath);
        }
        else {
          echo " -- IMPORT FAILED\n";
        }
      }
    }
  }
  $dom_links = $dom->getElementsByTagname('a');
  foreach ($dom_links as $link) {
    if ($filename = $link->getAttribute('href')) {
      if (file_exists($filedir . $filename)) {
        echo "  Link:  ". $filename;
        if ($file_obj = mt2file($filedir . $filename, $node->uid)) {
          $node->files[$file_obj->fid] = $file_obj;
          echo " -- created ". $file_obj->fid ." at ". $file_obj->filepath ."\n";
          $link->setAttribute('href', "/". $file_obj->filepath);
        }
        else {
          echo " -- IMPORT FAILED\n";
        }
      }
    }
  }

  if ($node->files) {
    // Use rewritten URLs for images and links
    $node->body = check_markup($dom->saveHTML());
  }

  /*
   * Save the node using the API.
   */
  node_invoke_nodeapi($node, 'mtimport');
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

  /*
   * Set a new path alias using the old URL.
   * Kevin Hankens
   * http://acquia.com/blog/migrating-drupal-way-part-ii-saving-those-old-urls
   */
  if ($node->nid) {
    path_set_alias('node/' . $node->nid, str_replace($oldurl, '', $item->link), NULL, 'en');
  }

  /*
   * Save the comments.
   */
  foreach($item->comments->comment as $comment) {
    $edit = array();
    $edit['pid'] = 0;
    $edit['nid'] = $newnode->nid;
    $edit['uid'] = (strtolower($comment->author) == strtolower($user->name) ? $user->uid : 0 );
    $edit['timestamp'] = strtotime($comment->date);
    $edit['name'] = $comment->author;
    // note:  mt4 apparently doesn't have the MTCommentTitle attribute
    $edit['subject'] = $comment->title;
    $edit['comment'] = $comment->body;
    $edit['format'] = 2;
    $edit['mail'] = $comment->email;
    $edit['homepage'] = $comment->homepage;
    $cid = comment_save($edit);
  }
  // echo "Saved \"$item->title\" by $newnode->name (uid=$newnode->uid) with $comment_count comments.\n";
}

/**
 * This portion of the code based on the example by Jamie McClelland.
 * @see http://current.workingdirectory.net/posts/2009/attach-file-to-node-drupal-6/
 */
function mt2file($file, $uid) {
  $details = stat($file);
  $filesize = $details['size'];
  $mtime = $details['mtime'];
  $date_value = date('Y-m-d\T00:00:00', $mtime);

  $dest = file_directory_path();
  if (file_copy($file, $dest, FILE_EXISTS_REPLACE)) {
    $name = basename($file);

    // build file object
    $file_obj = new stdClass();
    $file_obj->filename = $name;
    $file_obj->filepath = $file;
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
  }
  else {
    echo "Failed to move file: $file\n";
    $file_obj = NULL;
  }
  return $file_obj;
}
