<?php
#echo "1";
if ( $_POST["FORM"]=="ins" && $_POST["ins1"]!="" && $_POST["ins2"]!="" && $_POST["ENTRY_SUBMIT"]!="" )
{
        #echo "2";
	require_once("./control.php");
	require_once($FUNCTIONS);
	session_start();
	$name = $_POST["ins1"];
	$pass = $_POST["ins2"];
	if ( $db = databaseConnection($DATABASE) )
	{
		$login_passwd_hash = hashPasswd($name,$pass);
	    
	        echo "SELECT u.uid,u.login_name,g.group_name FROM users u, groups g WHERE u.login_name='" . $name . "' AND u.login_hash='" . $login_passwd_hash . "' AND u.gid=g.gid";
	    
		$user_query = mysql_query("SELECT u.uid,u.login_name,g.group_name FROM users u, groups g WHERE u.login_name='" . $name . "' AND u.login_hash='" . $login_passwd_hash . "' AND u.gid=g.gid") or die("you are not authorized.[1]");
		if ( mysql_affected_rows() )
		{
			$user_info = mysql_fetch_array($user_query);
			$_SESSION["uid"] =  $user_info["uid"];
			$_SESSION["login"] = $user_info["login_name"];
			$_SESSION["group"] =  $user_info["group_name"];
			if ( $RECORD_LOGIN=="ON" )
			{
				$log_query = mysql_query("SELECT ip,date FROM user_logins WHERE uid='" . $user_info["uid"] . "' ORDER BY -date LIMIT 1") or die("Log Query Fail.[1]");
				if ( mysql_affected_rows() ) 
				{ 
					$log_info = mysql_fetch_array($log_query);
					$_SESSION["last_date"] = $log_info["date"];
					$_SESSION["last_ip"] = $log_info["ip"];	
				} else { $_SESSION["firstlogin"] = "1"; }
				$date = time();	
				mysql_query("INSERT INTO user_logins(uid,ip,date) VALUES('" . $user_info["uid"] . "','" . $_SERVER["REMOTE_ADDR"] . "','" . $date . "')") or die("Recording Login Fail.[2]");
			}
			header("Location: " . $_POST["ENTRY_SUBMIT"]);
		} 
	        else { session_destroy(); header("Location: " . $BASE_HREF); }
	}
} 
else { header("Location: " . $BASE_HREF); }
?>
