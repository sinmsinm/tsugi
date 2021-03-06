<?php 
require_once 'config.php';
require_once 'pdo.php';
require_once 'lib/lms_lib.php';
require_once 'lib/lti_db.php';

// Pull LTI data out of the incoming $_POST and map into the same
// keys that we use in our database (i.e. like $row)
$post = ltiExtractPost();
if ( $post === false ) {
    $pdata = safe_var_dump($_POST);
    error_log("Missing post data: ".$pdata);
    require("lti/nopost.php");
    return;
}

if ( $post['key'] == '12345' && ! $CFG->DEVELOPER) {
    die_with_error_log('You can only use key 12345 in developer mode');
}

// We make up a Session ID Key because we don't want a new one
// each time the same user launches the same link.
$session_id = ltiGetCompositeKey($post, $CFG->sessionsalt);
session_id($session_id);
session_start();
header('Content-Type: text/html; charset=utf-8'); 

// Since we might reuse session IDs, clean everything out
session_unset();
$_SESSION['LAST_ACTIVITY'] = time(); // update last activity time stamp

// Read all of the data from the database with a very long
// LEFT JOIN and get all the data we have back in the $row variable
// $row = ltiLoadAllData($pdo, $CFG->dbprefix, false, $post);
$row = ltiLoadAllData($pdo, $CFG->dbprefix, $CFG->dbprefix."profile", $post);

$delta = 0;
if ( isset($_POST['oauth_timestamp']) ) {
    $server_time = $_POST['oauth_timestamp']+0;
    $delta = abs(time() -  $server_time);
    if ( $delta > 480 ) { // More than four minutes is getting close
        error_log("Warning: Time skew, delta=".$delta." sever_time=".$server_time." our_time=".time());
    }
}

// Use returned data to check the OAuth signature on the
// incoming data
$valid = lti_verify_key_and_secret($post['key'],$row['secret']);
if ( $valid !== true ) {
    print "<pre>\n";
    print_r($valid);
    print "</pre>\n";
    die_with_error_log("OAuth validation fail key=".$post['key']." delta=".$delta." error=".$valid[0]);
}

$actions = ltiAdjustAata($pdo, $CFG->dbprefix, $row, $post);

// If there is an appropriate role override variable, we use that role
if ( isset($row['role_override']) && isset($row['role']) && 
       $row['role_override'] > $row['role'] ) {
    $row['role'] = $row['role_override'];
}

// Put the information into the row variable
// TODO: do AES on the secret
$_SESSION['lti'] = $row;
$_SESSION['lti_post'] = $_POST;
if ( isset($_SERVER['HTTP_USER_AGENT']) ) $_SESSION['HTTP_USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'];
if ( isset($_SERVER['REMOTE_ADDR']) ) $_SESSION['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'];
$_SESSION['CSRF_TOKEN'] = uniqid();

// Check if we can auto-login the system user
if ( ltiGetCustom('dologin', false) && $pdo !== false ) loginSecureCookie($pdo);

// See if we have a custom assignment setting.
if ( ! isset($_POST['custom_assn'] ) ) {
    require("lti/noredir.php");
    return;
} else {
    $url = $_POST['custom_assn'];
    $_SESSION['assn'] = $_POST['custom_assn'];
}

$breadcrumb = "Launch,";
$breadcrumb .= isset($row['key_id']) ? $row['key_id'] : '';
$breadcrumb .= ',';
$breadcrumb .= isset($row['user_id']) ? $row['user_id'] : '';
$breadcrumb .= ',';
$breadcrumb .= isset($_POST['user_id']) ? str_replace(',',';', $_POST['user_id']) : '';
$breadcrumb .= ',';
$breadcrumb .= $session_id;
$breadcrumb .= ',';
$breadcrumb .= $url;
$breadcrumb .= ',';
$breadcrumb .= isset($_SESSION['email']) ? $_SESSION['email'] : '';
error_log($breadcrumb);


if ( isset($_POST['custom_due'] ) ) {
    $when = strtotime($_POST['custom_due']);
    if ( $when === false ) {
        echo("<p>Error, bad setting for custom_due=".htmlentities($_POST['custom_due'])."</p>");
        error_log("Bad custom_due=".$_POST['custom_due']);
        flush();
    } else {
        $_SESSION['due'] = $_POST['custom_due'];
    }
}

if ( isset($_POST['custom_timezone'] ) ) {
    $_SESSION['timezone'] = $_POST['custom_timezone'];
}

if ( isset($_POST['custom_penalty_time'] ) ) {
    if ( $_POST['custom_penalty_time'] + 0 == 0 ) {
        echo("<p>Error, bad setting for custom_penalty_time=".htmlentities($_POST['custom_penalty_time'])."</p>");
        error_log("Bad custom_penalty_time=".$_POST['custom_penalty_time']);
        flush();
    } else {
        $_SESSION['penalty_time'] = $_POST['custom_penalty_time'];
    }
}

if ( isset($_POST['custom_penalty_cost'] ) ) {
    if ( $_POST['custom_penalty_cost'] + 0 == 0 ) {
        echo("<p>Error, bad setting for custom_penalty_cost=".htmlentities($_POST['custom_penalty_cost'])."</p>");
        error_log("Bad custom_penalty_cost=".$_POST['custom_penalty_cost']);
        flush();
    } else {
        $_SESSION['penalty_cost'] = $_POST['custom_penalty_cost'];
    }
}

// Send us to where we are going next...
$query = false;
if ( isset($_SERVER['QUERY_STRING']) && strlen($_SERVER['QUERY_STRING']) > 0) {
    $query = true;
    $url .= '?' . $_SERVER['QUERY_STRING'];
}

$location = sessionize($url);
session_write_close();  // TO avoid any race conditions...

if ( headers_sent() ) {
    echo('<p><a href="'.$url.'">Click to continue</a></p>');
} else { 
    header('Location: '.$location);
}
?>
