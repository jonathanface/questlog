<?	if ( $_POST["new_user_group"]!="" && $_POST["new_login_name"]!="" && $_POST["new_user_email"]!="" && (check_passwds($_POST["new_passwd_1"],$_POST["new_passwd_2"]) || check_length($_POST["hash"])) )
	{
		if ( check_username($_POST["new_login_name"]) && check_file($_POST["new_login_name"], $NAMEDENY) )
		{
			if ( $_POST["hash"]!="" ) { $new_passwd_hash = $_POST["hash"]; }
			else { $new_passwd_hash = hashPasswd($_POST["new_login_name"],$_POST["new_passwd_1"]); }
			
		        echo "INSERT INTO users(gid,login_name,login_hash,user_email) VALUES('" . $_POST["new_user_group"] . "','" . $_POST["new_login_name"] . "','" . $new_passwd_hash . "','" . $_POST["new_user_email"] . "')";
			echo "INSERT INTO logins(uid,login_count,last_ip) VALUES('" . $newid . "','0','0.0.0.0')";
		    
		        mysql_query("INSERT INTO users(gid,login_name,login_hash,user_email) VALUES('" . $_POST["new_user_group"] . "','" . $_POST["new_login_name"] . "','" . $new_passwd_hash . "','" . $_POST["new_user_email"] . "')") or die ("an error has occured while querying the database.[2]");
			$newid = mysql_insert_id();
			mysql_query("INSERT INTO logins(uid,login_count,last_ip) VALUES('" . $newid . "','0','0.0.0.0')") or die ("an error has occured while querying the database.[3]");
			
			log2($ACTION_LOG, $_SESSION["login"], "Admin Add User: " . $_POST["new_login_name"]);
			
			echo  "user " . $_POST["new_login_name"] . " has been successfully added to the database. <a href=\"" . $POST_TO . "\">Add another user</a> or <a href=\"" . $CLOSE . "\">close window</a>.";
		}
		else { echo $ERROR_NAME; }
	}
	else { ?>
		<form action="<? echo $POST_TO; ?>" method="POST" class="form">
			user name: <input type="text" name="new_login_name" size="25" class="field"><br />
			email: <input type="text" name="new_user_email" size="25" class="field"><br />
			<br />
			passwd: <input type="password" name="new_passwd_1" size="25" class="field"><br />
			confirm: <input type="password" name="new_passwd_2" size="25" class="field"><br />
			<br />
			hash overrides the passwd feilds<br />
			hash: <input type="text" name="hash" size="50" class="field"><br />
			<br />
			groups:
			<select name="new_user_group" class="field">
<?			$db_query = mysql_query("SELECT g.gid, g.group_name FROM groups g ORDER BY -g.gid") or die("an error has occured while querying the database.[1]");
			while($groups = mysql_fetch_array($db_query))
			{
    			$gid =  $groups["gid"];
				$group_name =  $groups["group_name"]; ?>
				<option value="<? echo $gid; ?>"><? echo $group_name; ?></option>
<?			} ?>
			</select><br /><br /><br />
			<input type="submit" name="submit" value="&nbsp;&nbsp;&nbsp;submit&nbsp;&raquo;&nbsp;&nbsp;&nbsp;" class="button">
		</form>	
<?	} ?>
