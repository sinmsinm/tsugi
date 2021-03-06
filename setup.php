<?php

// This is where we change the overall database version to trigger
// upgrade checking - don't change this unless you want to trigger
// database upgrade messages it should be the max of all versions in
// all database.php files.
$CFG->dbversion = 2014050500;

function die_with_error_log($msg, $extra=false, $prefix="DIE:") {
    error_log($prefix.' '.$msg.' '.$extra);
    print_stack_trace();
    die($msg); // with error_log
}

function echo_log($msg) {
    echo($msg);
    error_log(str_replace("\n"," ",$msg));
}

function session_safe_id() {
    $retval = session_id();
    if ( strlen($retval) > 10 ) return '**********'.substr($retval,5);
}

function print_stack_trace() {
    ob_start();
    debug_print_backtrace();
    $data = ob_get_clean();
    error_log($data);
}

if ( isset($CFG->upgrading) && $CFG->upgrading === true ) require_once("upgrading.php");

require_once $CFG->dirroot."/lib/lms_lib.php";  // During transition

// Check if we have been asked to do cookie or cookieless sessions
if ( defined('COOKIE_SESSION') ) {
    // Do nothing - let the session be in a cookie
} else {
    ini_set('session.use_cookies', '0');
    ini_set('session.use_only_cookies',0);
    ini_set('session.use_trans_sid',1);
}

if ( ! isset($CFG) ) die_with_error_log("Please configure this product using config.php");
if ( ! isset($CFG->staticroot) ) die_with_error_log('$CFG->staticroot not defined in config.php');
if ( ! isset($CFG->timezone) ) die_with_error_log('$CFG->timezone not defined in config.php');
if ( strpos($CFG->dbprefix, ' ') !== false ) die_with_error_log('$CFG->dbprefix cannot have spaces in it');

if ( !isset($CFG->ownername) ) $CFG->ownername = false; 
if ( !isset($CFG->owneremail) ) $CFG->owneremail = false; 
if ( !isset($CFG->providekeys) ) $CFG->providekeys = false;

// Set this to the temporary folder if not set - dev only
if ( ! isset($CFG->dataroot) ) {
    $tmp = sys_get_temp_dir();
    if (strlen($tmp) > 1 && substr($tmp, -1) == '/') $tmp = substr($tmp,0,-1);
    $CFG->dataroot = $tmp;
}

error_reporting(E_ALL & ~E_NOTICE);
error_reporting(E_ALL );
ini_set('display_errors', 1);

if ( isset($CFG->sessionlifetime) ) {
    ini_set('session.gc_maxlifetime', $CFG->sessionlifetime);
} else {
    $CFG->sessionlifetime = ini_get('session.gc_maxlifetime');
}

date_default_timezone_set($CFG->timezone);

function htmlspec_utf8($string) {
    return htmlspecialchars($string,ENT_QUOTES,$encoding = 'UTF-8');
}

function htmlent_utf8($string) {
    return htmlentities($string,ENT_QUOTES,$encoding = 'UTF-8');
}

// Makes sure a string is safe as an href
function safe_href($string) {
    return str_replace(array('"', '<'),
        array('&quot;',''), $string);
}

// Convienence method to wrap sha256
function lti_sha256($val) {
    return hash('sha256', $val);
}

function sessionize($url) {
    if ( ini_get('session.use_cookies') != '0' ) return $url;
    $parameter = session_name().'='.session_id();
    if ( strpos($url, $parameter) !== false ) return $url;
    $url = $url . (strpos($url,'?') > 0 ? "&" : "?");
    $url = $url . $parameter;
    return $url;
}

function reconstruct_query($baseurl, $newparms=false) {
    foreach ( $_GET as $k => $v ) {
        if ( $k == session_name() ) continue;
        if ( is_array($newparms) && array_key_exists($k, $newparms) ) continue;
        $baseurl = add_url_parm($baseurl, $k, $v);
    }
    if ( is_array($newparms) ) foreach ( $newparms as $k => $v ) {
        $baseurl = add_url_parm($baseurl, $k, $v);
    }

    return $baseurl;
}

function add_url_parm($url, $key, $val) {
    $url .= strpos($url,'?') === false ? '?' : '&';
    $url .= urlencode($key) . '=' . urlencode($val);
    return $url;
}

// Request headers for earlier version of PHP and nginx
// http://www.php.net/manual/en/function.getallheaders.php
if (!function_exists('apache_request_headers')) {
    function apache_request_headers() {
        foreach($_SERVER as $key=>$value) {
            if (substr($key,0,5)=="HTTP_") {
                $key=str_replace(" ","-",ucwords(strtolower(str_replace("_"," ",substr($key,5)))));
                $out[$key]=$value;
            } else {
                $out[$key]=$value;
            }
        }
        return $out;
    }
}

// Convience method, pattern borrowed from WP
function _e($message) {
    echo(_($message));
}

/**
 * A central store of information about the current page we are
 * generating in response to the user's request.
 *
 * @global moodle_page $PAGE
 * @name $PAGE
 */
global $PAGE;

/**
 * A class that contains the logic to format output pages
 *
 * @global object $OUTPUT
 * @name $OUTPUT
 */
global $OUTPUT;

/**
 * Holds the user table record for the current user. 
 *
 * Items found in the user object:
 *  - $USER->id - The integer primary key for this user in the 'lti_user' table.
 *  - $USER->sha256 - The string primary key for this user in the 'lti_user' table.
 *  - $USER->email - The user's email address.
 *  - $USER->displayname - The user's display name.
 *  TODO: - $USER->lang - The user's language choice.
 *
 * @global object $USER
 * @name $USER
 */
global $USER;

/**
 * Information about the context (i.e. site or course)
 *
 * Items found in the context object:
 *  - $CONTEXT->id - The integer primary key for this context in the 'lti_context' table.
 *  - $CONTEXT->sha256 - The string primary key for this context in the 'lti_context' table.
 *  - $CONTEXT->title - The context title
 *  TODO: - $CONTEXT->lang - The context language choice.
 *
 * @global object $CONTEXT
 * @name $CONTEXT
 */
global $CONTEXT;

/**
 * Information about the resource link (i.e. link within context)
 *
 * Items found in the context object:
 *  - $LINK->id - The integer primary key for this context in the 'lti_context' table.
 *  - $LINK->sha256 - The string primary key for this context in the 'lti_context' table.
 *  - $LINK->title - The context title
 *
 * @global object $LINK
 * @name $LINK
 */
global $LINK;



// No trailer
