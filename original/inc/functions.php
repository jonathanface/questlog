<?
############################################
#   questlog functions file  2003.12.24    #
############################################

function databaseConnection($db) /* open a connection to the selected mysql database. Database credentials are stored in a directory above the webroot, look into making this as hased db file or something more slick henry */
{
	$file = "/usr/home/www/db/" . $db . ".db";
	if ( $read = fopen($file, "r") )
	{
		$data = fread( $read, filesize($file) );
		fclose($read);
		$DBS = explode(":", $data);
		#if( $db_connect = @mysql_connect("", $DBS["0"], $DBS["1"]) )
		if( $db_connect = mysql_connect("", $DBS["0"], $DBS["1"]) )
	    {
			if( $db_select = mysql_select_db($db, $db_connect) )
			{
				return($db_connect);
			} else { print("database not found: " . mysql_error()); }
		} else { print("database access denied : " . mysql_error()); }
	} else { print("access file not found: " . mysql_error()); }
}

function hashPasswd($username,$passwd) /* create a hashed passwd to be stored someone safe */
{
  $hash_1 = hash('ripemd160', $passwd . $username);
  $hash_2 = hash('ripemd160', $passwd . $hash_1);
	#$hash_1 = bin2hex( mhash(MHASH_RIPEMD160, $passwd . $username) );
	#$hash_2 = bin2hex( mhash(MHASH_RIPEMD160, $passwd . $hash_1) );
	return($hash_2);
}

function roll_dice($post) /* Roll dice calls from a post, parce dice syntax *<num>d<dice_size>* if <num> does not exist assume value of 1, output: *** 1d20: 16 ****/
{
    eregi("^[0-9]*d(0-9a-z])*@[1-100]$");

}

function log2($LOG_FILE,$LOG_USER,$LOG_DATA="",$DATE_STRING="D M d H:i:s T Y") /* write LOG_DATA to LOG_FILE, advice for LOG_FILE: chown root:www,chmod 770,chflag schg */
{
	if ( @is_writeable(dirname($LOG_FILE)) && $log_fp = @fopen($LOG_FILE, "a") )
	{
	    if ( $LOG_DATA != "" ) { $LOG_DATA=$LOG_DATA . " - "; }
	    if ( fwrite($log_fp, $LOG_USER . " - " . gethostbyaddr($_SERVER["REMOTE_ADDR"]) ." - " . $LOG_DATA  . date($DATE_STRING, time()) . "\n") )
	    {
		fclose($log_fp);
		return TRUE;
	    }
	}
}

function log_error($LOG_DATA="",$ERROR_NUMBER="",$DATE_STRING="D M d H:i:s T Y") /* write LOG_DATA to control file ERROR_LOG, advice for LOG_FILE: chown root:www,chmod 770,chflag schg */
{
	if ( @is_writeable(dirname($ERROR_LOG)) && $log_fp = @fopen($ERROR_LOG, "a") )
	{
	    if ( $LOG_DATA != "" ) { $LOG_DATA=$LOG_DATA . " - "; }
	    if ( fwrite($log_fp, $_SERVER["SCRIPT_FILENAME"] . " - " . gethostbyaddr($_SERVER["REMOTE_ADDR"]) ." - " . $LOG_DATA  . date($DATE_STRING, time()) . "\n") )
	    {
		fclose($log_fp);
		return ( $PRINT_ERROR = $LOG_DATA . ".[" . $ERROR_NUMBER . "]" );
		#return $PRINT_ERROR;
	    }
	}
}

function readlog($LOG_FILE) /* read LOG_FILE and return the contence in an xhtml format */
{
	if ( @is_readable($LOG_FILE) )
	{
	    $log_fp = @fopen($LOG_FILE, "r");
	    if ( $FILE = nl2br(fread($log_fp, filesize($LOG_FILE))) )
	    {
		fclose($log_fp);
		return $FILE;
	    }
	}
}

function getCurrentDate() /* does what is fucking says */
{
	$getdate = getdate(time());
	$year = $getdate[year];
	$month = $getdate[mon];
	$day = $getdate[mday];
	$h = $getdate[hours];
	$m = $getdate[minutes];
	$s = $getdate[seconds];
	$datetime = "$year-$month-$day $h:$m:$s";
	return($datetime);
}

function unixStyleDate($timestamp)  /* formate timestamp like the unix date command */
{
	$date = date( "D M d H:i:s T Y", $timestamp );
	return($date);
}

function gethour() /* get the hour, this is used for logo rotation, not much else */
{
	$hour = date( "H", time() );
	return $hour;
}

function logout() /* destroy the session environment, check on the actual session file it looks like these might stay around and if so add lines to delete them. */
{
	# End everything. #
	session_unregister('users_id');
	session_unregister('users_name');
	session_unregister('users_email');
	session_unregister('users_group');
	# Destroy everything. #
	session_destroy();
}

function last2post($qid,$datecut="-9")  /* get the username and date of most recent post for a given quest */
{
	$cid = @mysql_fetch_row( @mysql_query("SELECT p.cid FROM posts p WHERE p.qid='" . $qid . "' ORDER BY -p.pid LIMIT 1") );
	if ( $cid["0"]=="0" )
	{
			$sql = "SELECT u.login_name,p.post_date FROM posts p,users u WHERE p.qid='" . $qid . "' AND p.uid=u.uid ORDER BY -p.pid LIMIT 1";
	}
	else {	$sql = "SELECT c.char_name,p.post_date FROM posts p,characters c WHERE p.qid='" . $qid . "' AND p.cid=c.cid ORDER BY -p.pid LIMIT 1";	}
	if ( $lastposter = @mysql_fetch_row( @mysql_query($sql) ) )
	{
		$date = substr($lastposter["1"], 0, $datecut);
		$last = $lastposter["0"];
		$last_date = array($last,$date);
	}
	else { $last_date = array('record not found','000-00-00'); }
	return $last_date;
}

function last_char_id($qid)  /* get the last character id to post to given quest */
{
	$cid = @mysql_fetch_row( @mysql_query("SELECT p.cid FROM posts p WHERE p.qid='" . $qid . "' ORDER BY -p.pid LIMIT 1") );
	return $cid["0"];
}

function read_login_log($recent="") /* $found = mysql_affected_rows(); */
{
	if ( $recent == "" ) { $log_query = mysql_query("SELECT u.login_name,l.ip,l.date FROM user_logins l,users u WHERE l.uid=u.uid"); } 
	else { 
		#$now = date("D M d H:i:s T Y", time()); 
		#$then = date("D M d H:i:s T Y", mktime(-24));
		$then = mktime(-24);
		if ( ! is_numeric($recent) ) { $recent=100; } 
 		$log_query = mysql_query("SELECT l.date,l.ip,u.login_name FROM user_logins l,users u WHERE l.date>'" . $then . "' AND l.uid=u.uid GROUP BY u.login_name ORDER BY l.date LIMIT " . $recent);
 		while ( $log_array = mysql_fetch_array($log_query) )
 		{
 			$users .= $log_array['login_name'] . ", ";
 		}
 		return $users;
 	}
 	#if ( mysql_affected_rows() ) { return $log_array = mysql_fetch_array($log_query); }
 	#if ( mysql_affected_rows() ) { return $log_query; }
}

function count_posts($id, $as="0") /* count the total number of posts made by given user, quest, or character id */
{
	if ( is_numeric($as) && is_numeric($id) && $as < 3 )
	{
		switch ($as)
		{
			case "0": $AS = "uid"; break;
			case "1": $AS = "cid"; break;
			case "2": $AS = "qid"; break;
			default: $AS = "uid";
		}
		$total_post_count = mysql_result( mysql_query("SELECT count(pid) FROM posts WHERE " . $AS . "='" . $id . "'"), 0 );
		return $total_post_count;
	}
}

function check_active_posts($qid,$n="5") /* Check the last N posts with quest id for frequent posting status. compare date-1, 1-2, 2-3, 3-4, 4-5 */ 
{ /*
	$post_check = mysql_fetch_array($query = mysql_query("SELECT post_date FROM posts WHERE qid=" . $qid . " ORDER BY -post_date LIMIT " . $n));
	$today = date( "Y-m-d H:i", time() );
	for ($p = 1; $p <= $n; $p++)
	{
   	#$date .= substr($post_check["post_date"], 0, -3) . ", ";
   	$find = array(' ', ':');
		$replace = array('-', '-');
   	$string = str_replace($find, $replace, substr($post_check["post_date"], 0, -3));
   	$array .= explode("-", $string);
	}
	print_array($array); */
	#echo $string . " (" . $today . ")";
} 

function alert_email() /* run from posting form. send an email or txt_msg when a user's member quests recieve a post, avoid their own posts, and check for alert setting */
{
/*    $qid = $_REQUEST["id"];
    
    mysql_query("SELECT q.quest_name,c.char_name FROM quests q,characters c WHERE c.uid='" . $_SESSION["uid"] . "' AND ")
    while ( $user_array = @mysql_fetch_array(@mysql_query("SELECT up.user_email,up.alert_email,q.quest_name,c.char_name FROM user_profiles up,quest_members qm,quests q,characters c WHERE qm.qid='" . $qid . "' AND qm.qid=q.qid  AND qm.cid=c.cid AND c.uid=up.uid AND up.uid!='" . $_SESSION["uid"] . "'")) )
    {
	if ( $user_array["alert_email"]=="" || !isset($user_array["alert_email"]) )
	{
	    $mail_to = $user_array["user_email"];
	}
	else { $mail_to = $user_array["alert_email"]; }
	
	$mailheaders = "From: postform@questlog.org \n";
	#$mailheaders .= "Reply-To: \n\n";
	$msg = $user_array["char_name"] . " has posted to " . $user_array["quest_name"];
	
	echo "Send email to: " . $mail_to . " " . $user_array["quest_name"] . " " . $msg . " " . $mailheaders;
	
	#@mail ($mail_to, $user_array["quest_name"], $msg, $mailheaders);
    }
    mysql_free_result ($user_array);
*/
 }

/*
function posts_by_quest($qid) 
{
	$total_post_count = mysql_result( mysql_query("SELECT count(pid) FROM posts WHERE qid='" . $qid . "'"), 0 );
	return $total_post_count;
}

function posts_by_user($uid) 
{
	$total_post_count = mysql_result( mysql_query("SELECT count(pid) FROM posts WHERE uid='" . $uid . "'"), 0 );
	return $total_post_count;
}

function posts_by_char($uid) 
{
	$total_post_count = mysql_result( mysql_query("SELECT count(pid) FROM posts WHERE cid='" . $cid . "'"), 0 );
	return $total_post_count;
}

function login_authorize($dbs)
{
	include a separate connection to the database
	which is not persistant and which is to be closed
	after the session is created.
while ( list($key, $val) = each($_SESSION) )
{
	if ( $val ) { break }
}
}
$quest_info = mysql_query("SELECT m.cid,c.char_name,q.quest_access FROM quest_members m,characters c,quests q WHERE m.qid='" . $qid . "' AND m.qid=q.qid AND m.cid=c.cid AND c.uid='" . $uid . "'")		
$member_check >= "1" || $uid == $quest_owner_id || $questinfo["quest_access"] == "ALL" || $_SESSION["group"] == "admin"
*/

function printCharacterMenu($uid, $qid) /* what the fuck, this doesn't look done at all */
{
	$sql = "SELECT m.cid,c.char_name FROM quest_members m, characters c WHERE m.qid='" . $qid . "' AND m.cid=c.cid AND c.uid='" . $uid . "'";
	$member_check = mysql_num_rows( mysql_query($sql) );
	if ( $member_check >= "1" )
	{
		return TRUE;
	}
}

function file_menu($PATH,$suffix,$prefix="") /* $PATH = "./adm/"; $prefix = "adm."; $suffix = ".php"; */
{
	$suffix_reg = "\\" . $suffix . "$";
	$prefix_reg = "^" . $prefix;
	if ($handle = opendir($PATH))
	{
   	while ( false !== ($files = readdir($handle)) ) 
		{
			if ( !is_readable($files) && $files!="." && $files!=".." && eregi($suffix_reg, $files) && eregi($prefix_reg, $files))
			{
				$file_1 = str_replace($prefix, "", $files);
				$file_2 = str_replace($suffix, "", $file_1);
				$filename = str_replace("_", "&nbsp;&nbsp;", $file_2);
        		print("<a href=\"javascript: script_window('" . $PATH . $files . "');\" onMouseOver=\"window.status='" . $filename . "'; return 0\" onMouseOut=\"window.status=''; return 0\">" . $filename . "</a><br />\n");
        	}
  		 }
   	 closedir($handle); 
	}
}

function check_length($STRING, $SIZE="40") /* check the length of a string, defaults to hash length */
{
	$length = strlen($STRING);
	if ( $length==$SIZE ) { return TRUE; }
}

function check_passwds($PASS1, $PASS2) /* check the length of a string, defaults to hash length */
{
	if ( $PASS1!="" && $PASS2!="" && $PASS1==$PASS2 ) { return TRUE; }
}

function check_passwd_strength($PASS) /* Varify password strength, cracklib */
{
	if ( $PASS!="" ) { return TRUE; }
}

function check_login() /* varify that the user is logged in and proper sessions variables are active. This is used alot. */
{
	if( $_SESSION["uid"]!="" && $_SESSION["login"]!="" && $_SESSION["group"]!="" )
	{
		$sql="SELECT u.user_status,g.group_name FROM users u, groups g WHERE uid='" . $_SESSION["uid"] . "' AND login_name='" . $_SESSION["login"] . "' AND u.gid=g.gid";
		$userinfo = @mysql_fetch_array( @mysql_query($sql) );
		if( $userinfo["user_status"]==0 && $_SESSION["group"]==$userinfo["group_name"] )
		{
			return TRUE;
		}
	}
}

function check_member($uid, $qid) /* varify membership */
{
	$sql = "SELECT m.cid,c.char_name FROM quest_members m,characters c WHERE m.qid='" . $qid . "' AND m.cid=c.cid AND c.uid='" . $uid . "'";
	$member_check = mysql_num_rows( mysql_query($sql) );
	if ( $member_check >= "1" )
	{
		return $member_check;
	}
	else{ return FALSE; }
}

function check_status($status, $uid="0", $qid="0") /* I don't like calling my own functions in my own functions. */
{
	if ( $status > 0 )
	{
		switch ($status)
		{
    		case 1: //access for logged in users only//
				if ( check_login() )
				{
					return TRUE;
				}
				break;
    		case 2: //access for quest members only//
				if ( check_member($uid,$qid) )
				{
					return TRUE;
				}
    	    	break;
			case 3: //no access for anyone, but still list quest//
				return FALSE;
				break;
			case 4: //no access for anyone and do not list quest//
				return FALSE;
				break;
			default:
				return FALSE;
		}
	}
	else { return TRUE; }
}


function check_session($GROUP="") 
{
	if ( $GROUP=="" ) { $GROUP=$_SESSION["group"]; }
	if ( isset($_SESSION["uid"]) && isset($_SESSION["login"]) && isset($_SESSION["group"]) )
	{
		if ( $_SESSION["group"]==$GROUP || $_SESSION["group"]=="admin" )
		{
			return "true";
		}
	}
}

function characters($qid,$cid="0") /* create available character listing and specify cid for posts table */
{
	$members = mysql_fetch_row(mysql_query("SELECT uid,post_access,read_access FROM quests WHERE qid='" . $qid . "'"));
	if ( $members["1"]!="ALL" && $members["0"]!=$_SESSION["uid"] )
	{
		$char_query = mysql_query("SELECT m.cid,c.char_name FROM quest_members m,characters c WHERE m.qid='" . $qid . "' AND m.cid=c.cid AND c.uid='" . $_SESSION["uid"] . "'");
	}
	else { $char_query = mysql_query("SELECT c.cid,c.char_name FROM characters c WHERE c.uid='" . $_SESSION["uid"] . "'"); }
	$char_count = mysql_num_rows($char_query);
	
	if ( $char_count == 1 && $members["1"]!="ALL" && $members["0"]!=$_SESSION["uid"] )
	{
		$char_array = mysql_fetch_array($char_query);
		echo "<input type=\"hidden\" name=\"cid\" value=\"" . $char_array["cid"] . "\" />\n";
	}
	elseif ( $char_count > 1 || $members["1"]=="ALL"  || $members["0"]==$_SESSION["uid"])
	{
		echo "<select name=\"cid\" class=\"field\">\n";
		if ( $char_count==0 ) { echo "<option value=\"0\">no characters found</option>\n"; }
		if ( $cid!="0" || $members["1"]=="ALL" || $_SESSION["uid"]=="admin" || $members["0"]==$_SESSION["uid"] ) { echo "<option value=\"" . $cid . "\">&nbsp;</option>\n"; }
		while ( $char_array = mysql_fetch_array($char_query) )
		{
			if ( $cid!=$char_array["cid"] ) { echo "<option value=\"" . $char_array["cid"] . "\">" . $char_array["char_name"] . "</option>\n"; }
		}
		echo "</select><br />\n";
	}
	else { echo "<input type=\"hidden\" name=\"cid\" value=\"0\" />\n"; }
}


function check_access($_type="read") /* check read or post rights */
{
	if ( isset($_SESSION["uid"]) && isset($_SESSION["group"]) )
	{
		if ( $_SESSION["group"]=="admin" && $_SESSION["uid"]=="1" ) { return TRUE; }
		else {
			$sql = "SELECT uid," . $_type . "_access FROM quests WHERE qid='" . $_REQUEST['id'] . "'";
			$access = @mysql_fetch_array(@mysql_query($sql));
			switch ( $access[$_type . "_access"] )
			{
				case "USERS"; /* report("Running Users Login Check","echo"); */
					return TRUE; 
					break;
				case "MEMBERS": /* report("Running Members DB Check","echo"); */
					if ( $_SESSION["uid"] == $access["uid"] ) { return TRUE; }
					else {
						$sql = "SELECT quest_name,char_name FROM quest_members m,quests q,characters c,users u WHERE u.uid='" . $_SESSION["uid"] . "' AND u.uid=c.uid AND c.cid=m.cid AND m.qid='" . $_REQUEST['id'] . "' AND m.qid=q.qid";
						$member = mysql_query($sql);
						$member_check = mysql_num_rows($member);
						if ( $member_check > 0 ) { return TRUE; } else { report(mysql_error(),"echo"); }
					}
					break;
				case "OWNER": report("Running GM Only Check","echo");
					$gm_check = mysql_num_rows(@mysql_query("SELECT uid FROM quests q WHERE q.uid='" . $_SESSION["uid"] . "' AND q.qid='" . $_REQUEST['id'] . "'"));
					if ( $gm_check > 0 ) { return TRUE; }
					break;
				default: report("Default False","echo");
					return FALSE;
			}
			#report($access[$_type . "_access"] . ": " . $sql,"echo");
		}
	}
}


function mk_array($DB_QUERY) /* makes an array from index 0 of a db query */
{
	$string = "0";
	while ( $rows = mysql_fetch_row($DB_QUERY) )
	{
		$string .= "," . $rows["0"];
	}
	$array = explode(",", $string);
	return $array;
}

function check_file($STRING, $FILE) /* check if name is not banned and return true if it is */
{
	if ( is_readable($FILE) && $file_open = @fopen($FILE, "r") )
	{
		$file_data = fread( $file_open, filesize($FILE) );
		fclose($file_open);
		if ( !stristr($file_data, $STRING) )
		{
			return TRUE;
		}
	}
}

function check_questname($NAME) /* check if quest $NAME is free and return true if it is */
{
	$query = @mysql_query("SELECT quest_name FROM quests WHERE quest_name='" . $NAME . "'");
	$name_check = mysql_num_rows($query);
	if ( $name_check < 1 )
	{
		return TRUE;
	}
	mysql_free_result($query);
}

function check_username($NAME) /* check if user/character $NAME is free and return true if it is */
{
	$query1 = @mysql_query("SELECT char_name FROM characters WHERE char_name='" . $NAME . "'");
	$name_check_1 = mysql_num_rows($query1);
	$query2 = @mysql_query("SELECT login_name FROM users WHERE login_name='" . $NAME . "'");
	$name_check_2 = mysql_num_rows($query2);
	if ( $name_check_1 < 1 && $name_check_2 < 1 )
	{
		return TRUE;
	}
	mysql_free_result($query1);
	mysql_free_result($query2);
}

function check_email($email) /* check syntax and mx records */
{
	if (eregi("^[0-9a-z_]([-_.]?[0-9a-z])*@[0-9a-z][-.0-9a-z]*\\.[a-z]{2,3}[.]?$", $email))
	{
		$host = substr(strstr($email, '@'), 1) . ".";
		if ( getmxrr($host, $validate_email_temp) || checkdnsrr($host, "ANY") )
		{
			return TRUE;
		}
	}
 }

function check_pending($UID,$QID) /* Users may have 1 pending post per quest, true means they may delay the current post. */
{
	$query = @mysql_query("SELECT pid FROM posts_pending WHERE qid=" . $QID . " AND uid=" . $UID . " LIMIT 1");
	$check = mysql_num_rows($query);
	if ( $check < 1 )
	{
		return TRUE;
	}
	mysql_free_result($query);
}

function get_top_users($top_users_limit="0") /* poll the database for users ordered by post count */
{
	$top_users_sql = "SELECT u.login_name,COUNT(p.pid) AS totalposts FROM users u,posts p WHERE u.uid=p.uid GROUP BY u.uid ORDER BY totalposts DESC";
	if ( $top_users_limit > 0 ) { $top_users_sql .= " LIMIT " . $top_users_limit; }
	$top_users_query = @mysql_query($top_users_sql);
	$top_users_array = @mysql_fetch_array($top_users_query);
	return $top_users_array;
	mysql_free_result($top_users_query);
}

function formatContent($CONTENT,$TYPE="0") /* format content for record in mysql */
{
	$html = array('<i><blockquote>', '</blockquote></i>', '<b>', '</b>', '<i>', '</i>', '<u>', '</u>', '<s>', '</s>', '<pre>', '</pre>', '<center>', '</center>', '<img border="0" src="', '" height="', '" width="', '" align="', '<a target="new" href="', '</a>', '">', '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', '<blockquote>', '</blockquote>');
	$code = array('[quote]', '[/quote]', '[b]', '[/b]', '[i]', '[/i]', '[u]', '[/u]', '[s]', '[/s]', '[pre]', '[/pre]', '[c]', '[/c]', '[img=', 'h=', 'w=', 'a=', '[url=', '[/url]', '/]', '[tab]', '[block]', '[/block]');
	if ( $TYPE == "0" ) {
		#$smartquotes = str_replace('�E, '"', $CONTENT); $smartquotes2 = str_replace('�E, '"', $smartquotes); $smartquotes3 = str_replace('�E, "'", $smartquotes2);
		$specials = htmlspecialchars($CONTENT, ENT_QUOTES);
		$trimming = rtrim($specials);
		$cleand20 = str_ireplace('[d20]', "", $trimming);
		$newlines = str_replace("\n", "<br />",  $cleand20);
		$codesubs = str_replace($code, $html, $newlines);
	} else {
    $trimming = rtrim($CONTENT);
		$newlines = eregi_replace('<br[[:space:]]*/?[[:space:]]*>', "\n", $trimming);
		$codesubs = str_replace($html, $code, $newlines);	
	}
	return $codesubs;
}

function privateMessageTag1($CONTENT,$G_ID,$P_ID)  /* scans the post blob much as formatContent(), but only looks for the private message tags */
{
	if( $_SESSION["uid"]!="" && $_SESSION["login"]!="" && $_SESSION["email"]!="" && $_SESSION["group"]!="" && $_SESSION["uid"] == $G_ID )
	{ 	#if ( $_SESSION["uid"] == $G_ID || $_SESSION["uid"] == $P_ID ) {
		$html = array('*** ', ' ***',);
		$code = array('[GM]', '[/GM]');
		$export_content = str_replace($code, $html, $CONTENT);
	}
	else { $export_content = eregi_replace('\[GM\]*\[/GM\]', "", $CONTENT); }
	return $export_content;
}

function post() /* */
{
  if ( $_POST["cid"] != last_char_id($_POST["id"]) )
  {
    $member_check_query = mysql_query("SELECT q.uid FROM quests q WHERE q.qid='" . $_POST["id"] . "'") or die("an error has occured while querying the database.[1]");
    $member_check = mysql_fetch_array($member_check_query);
    $quest_owner_id =  $member_check["uid"];
    if ( $LOG_IP=="ON" ) { $ip = $_SERVER["REMOTE_ADDR"]; } else { $ip = "0.0.0.0"; }
    $formated_content = formatContent($_POST["post_content"]);
    $datetime = getCurrentDate();
    $insert_sql = "INSERT INTO posts(qid,uid,cid,post_status,post_text,post_date,post_ip) VALUES('" . $_POST["id"] . "','" . $_SESSION["uid"] . "','" . $_POST["cid"] . "','0','" . $formated_content . "','" . $datetime . "','" . $ip . "')";
    $post_insert = mysql_query($insert_sql) or die("An error occured while posting to your quest.[2]");
    $post_id = mysql_insert_id();
      
    mysql_query("DELETE LOW_PRIORITY FROM reply_codes WHERE qid='" . $_POST['id'] . "' AND uid='" . $_SESSION["uid"] . "'") or die("An error occured while removing the reply code.[4]");
    reply_post_create($_SESSION["uid"], $post_id, $_POST["id"], $formated_content);
      
    echo $_SESSION["login"] . ", your post has been sent. <a href=\"javascript: opener.window.location.reload(); window.close();\">close window</a>";
    echo "<script language=\"JavaScript\" type=\"text/javascript\"> opener.window.location.reload(); window.setTimeout(\"opener.window.focus(); window.close();\", ". $POPUP_CLOSE_DELAY . ");</script>";
  }
}

function reply_post_create($_user_id, $_post_id, $_quest_id, $_post_message, $_roll="") /* // SELECT m.qid,q.quest_name,m.cid,c.char_name,p.uid,p.user_email FROM quest_members m, quests q, characters c, user_profiles p WHERE m.qid=q.qid AND m.cid=c.cid AND c.uid=p.uid; // SELECT m.qid,q.quest_name,m.cid,c.char_name,p.uid,p.user_email FROM quest_members m, quests q, characters c, user_profiles p WHERE m.qid='" . $_quest_id . "' AND m.qid=q.qid AND m.cid=c.cid AND c.uid=p.uid; */
{
  $poster_sql = "SELECT u.login_name,p.user_email FROM users u, user_profiles p WHERE u.uid='" . $_user_id . "' AND u.uid=p.uid;";
  $poster_data = mysql_query($poster_sql) or die("An error occured while looking up your user/character name.[3]"); 
  $poster = mysql_fetch_row($poster_data);
  
  $player_sql = "SELECT q.quest_name,p.uid,p.user_email FROM quest_members m, quests q, characters c, user_profiles p WHERE m.qid='" . $_quest_id . "' AND m.qid=q.qid AND m.cid=c.cid AND c.uid=p.uid AND p.uid!='" . $_user_id . "' AND p.alert_status='1' AND q.alert_status='1';";
  $player_data = mysql_query($player_sql) or die("An error occured while looking up the quest players.[4]"); 
  while ( $player = mysql_fetch_array($player_data) )
  {	#print_array($player);
    $code = generate_code("8");
    $code_sql = "INSERT INTO reply_codes(uid,pid,qid,code) VALUES('" . $player['uid'] . "','" . $_post_id . "','" . $_quest_id . "','" . $code . "')";
    $reply_post = "Post to " . $player['quest_name'] . " by " . $poster['0'] . "\n\n" . $_post_message;
    mysql_query("DELETE LOW_PRIORITY FROM reply_codes WHERE qid='" . $_quest_id . "' AND uid='" . $player['uid'] . "'") or die("An error occured while removing stale reply code.[4]");
    mysql_query($code_sql) or die("An error occured while generating the reply code.[5]");
    send_simple_email("posts@sa.ensu.us", $player['user_email'], $code, $reply_post);
    #send_simple_email("posts@sa.ensu.us", "srwadleigh@gmail.com", $code, $reply_post . "\n\n" . $player['user_email']);
  }
  
  $owner_sql = "SELECT q.quest_name,p.uid,p.user_email FROM quests q, user_profiles p WHERE q.qid='" . $_quest_id . "' AND q.uid=p.uid AND p.uid!='" . $_user_id . "' AND p.alert_status='1' AND q.alert_status='1';";
  $owner_data = mysql_query($owner_sql) or die("An error occured while looking up the quest owner.[6]"); 
  #$poster = mysql_fetch_row($owner_data);
  while ( $owner = mysql_fetch_array($owner_data) )
  { 
    $code = generate_code("8");
    $code_sql = "INSERT INTO reply_codes(uid,pid,qid,code) VALUES('" . $owner['uid'] . "','" . $_post_id . "','" . $_quest_id . "','" . $code . "')";
    $reply_post = "New Post to " . $owner['quest_name'] . " by " . $poster['0'] . "\n\n" . stripslashes($_post_message) . "\n\n" . $_roll;
    mysql_query($code_sql) or die("An error occured while generating the reply code.[7]");
    send_simple_email("posts@sa.ensu.us", $owner['user_email'], $code, $reply_post);
    #send_simple_email("posts@sa.ensu.us", "srwadleigh@gmail.com", $code, $reply_post . "\n\n" . $owner['user_email']);
  }
}

function d20_roll($d20_post) /* echo "*** " . $d20_die[$d20_roll] . " ***<br /><br />"; */
{
  if ( stristr($d20_post, '[d20]') ) 
  { 
    $d20_post = str_ireplace('[d20]', "", $d20_post);
    $d20_dice = array('1','2','2','3','3','3','4','4','4','5','5','5','6','6','6','7','7','7','8','8','8','9','9','9','10','10','10','11','11','11','12','12','12','13','13','13','14','14','14','15','15','16','16','16','17','17','17','18','18','18','19','19','20');
    $d20_roll = $d20_dice[array_rand($d20_dice)];
  }
  return $d20_output = array('roll' => $d20_roll, 'post' => $d20_post);
}

function d20_save($d20_post) /* echo "*** " . $d20_die[$d20_roll] . " ***<br /><br />"; */
{
  if ( stristr($d20_post, '[d20]') ) 
  { 
          if ( is_numeric($d20_post['roll']) ) {
        $roll_sql = "INSERT INTO rolls(rid,roll) VALUES('" . $post_id . "','" . $d20_post['roll'] . "')";
        $roll_insert = mysql_query($roll_sql) or die("An error occured while recording to your roll.[" . $roll_sql . "]");
      }
  }
}

#function reply_post_delete($_post_id) /* */
#{
#  mysql_query("DELETE LOW_PRIORITY FROM reply_codes WHERE pid='" . $_post_id . "'") or die("An error occured while removing the reply code.[4]");
#}

function reply_post_resend($_post_id, $_quest_id) /* */
{
  
}

### EOF ###
?>
