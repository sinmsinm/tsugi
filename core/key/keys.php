<?php
// In the top frame, we use cookies for session.
define('COOKIE_SESSION', true);
require_once("../../config.php");
require_once($CFG->dirroot."/pdo.php");
require_once($CFG->dirroot."/lib/lms_lib.php");

if ( $CFG->providekeys === false || $CFG->owneremail === false ) { 
    $_SESSION['error'] = _("This service does not accept instructor requests for keys");
    header('Location: '.$CFG->wwwroot);
    return;
}

header('Content-Type: text/html; charset=utf-8');
session_start();

if ( ! ( isset($_SESSION['id']) || isAdmin() ) ) {
    $_SESSION['login_return'] = getUrlFull(__FILE__) . "/index.php";
    header('Location: '.$CFG->wwwroot.'/login.php');
    return;
}

$query_parms = false;
$searchfields = array("key_id", "key_key", "created_at", "updated_at", "user_id");
$sql = "SELECT key_id, key_key, secret, created_at, updated_at, user_id
        FROM {$CFG->dbprefix}lti_key";

if ( !isAdmin() ) {
    $sql .= "\nWHERE user_id = :UID";
    $query_parms = array(":UID" => $_SESSION['id']);
}

$newsql = pdoPagedQuery($sql, $query_parms, $searchfields);
// echo("<pre>\n$newsql\n</pre>\n");
$rows = pdoAllRowsDie($pdo, $newsql, $query_parms);
$newrows = array();
foreach ( $rows as $row ) {
    $newrow = $row;
    $newrow['secret'] = '****';
    $newrows[] = $newrow;
}

$OUTPUT->header();
$OUTPUT->bodyStart();
$OUTPUT->topNav();
$OUTPUT->flashMessages();
?>
<h1>LTI Keys</h1>
<p>
  <a href="index.php" class="btn btn-default">View Key Requests</a>
</p>
<?php if ( count($newrows) < 1 ) { ?>
<p>
You have no IMS LTI 1.1 Keys for this system.
</p>
<?php } else { 
    pdoPagedTable($newrows, $searchfields, false, "key-detail.php");
} 
if ( isAdmin() ) { ?>
<p>
<a href="key-add.php" class="btn btn-default">Add Key</a>
</p>
<?php } ?>

<?php
$OUTPUT->footer();

