<?
if($_POST["SET"]=="1" && $_POST["qid"]!="")
{
        $preface_info = mysql_fetch_array( mysql_query("SELECT q.uid, q.quest_name, b.qpid, b.preface_text FROM quests q, quest_prefaces b WHERE b.qid='" . $_POST["qid"] . "' AND b.qid=q.qid") ) or die ($ERROR_DB_QUERY . ".[2]");
	if ( $preface_info["uid"] == $_SESSION["uid"] )
	{
		$formated_preface_text = formatContent($preface_info["preface_text"], "1"); ?>
		<form action="<? echo $POST_TO; ?>" method="POST" class="form">
		        Preface text for quest <b><? echo $preface_info["quest_name"]; ?></b>:<br />
			<textarea name="updated_preface" cols="59" rows="30" class="field"><? echo $formated_preface_text; ?></textarea><br />
			<input type="hidden" name="qid" value="<? echo $_POST["qid"]; ?>">
			<input type="hidden" name="qpid" value="<? echo $preface_info["qpid"]; ?>">
			<input type="hidden" name="quest_name" value="<? echo $preface_info["quest_name"]; ?>">
			<input type="hidden" name="SET" value="2">
			<input type="submit" name="submit" value="&nbsp;&nbsp;&nbsp;submit&nbsp;&raquo;&nbsp;&nbsp;&nbsp;" class="button">
		</form>	
<?	}
	else { echo "You are not the gamemaster of this quest."; exit; }
}
elseif ( $_POST["SET"]=="2" && isset($_POST["qid"]) && isset($_POST["qpid"]) && isset($_POST["updated_preface"]) )
{
	if ( $_POST["qid"]!="" && $_POST["updated_preface"]!="" )
	{
		$formated_preface_text = formatContent($_POST["updated_preface"]);
		mysql_query("UPDATE quest_prefaces SET preface_text='" . $formated_preface_text . "' WHERE qid='" . $_POST["qpid"] . "'") or die($ERROR_DB_QUERY . ".[4]");
			
		log2($ACTION_LOG, $_SESSION["login"], "GM Edit Quest Preface : " . $_POST["quest_name"]);
			
		echo  "the preface has been successfully updated, <a href=\"" . $POST_TO . "\">edit another preface</a> or <a href=\"" . $CLOSE . "\">close window</a>.";
	}
	else { echo "a required feild has been left blank, <a href=\"" . $POST_TO . "\">try again</a>.[1]"; exit; }
}
else {
	$quests_query = mysql_query("SELECT q.qid, q.quest_name, q.quest_status FROM quests q WHERE q.uid='" . $_SESSION["uid"] . "'") or die($ERROR_DB_QUERY . ".[1]");
	if($check_query = mysql_num_rows($quests_query)!="0")
	{ ?>
		<form action="<? echo $POST_TO; ?>" method="POST" class="form">
			Select preface to edit:
			<select name="qid" class="field">
<?			while($quests = mysql_fetch_array($quests_query))
			{ 
    			$qid =  $quests["qid"];
				$quest_name =  $quests["quest_name"];
				$quest_status =  $quests["quest_status"]; ?>
				<option value="<? echo $qid; ?>"><? echo $quest_name; if($quest_status!=0){ echo " *"; } ?></option>
<?			} ?>
			</select><br /><br />
			<input type="hidden" name="SET" value="1">
			<input type="submit" name="submit" value="&nbsp;&nbsp;&nbsp;next&nbsp;&raquo;&nbsp;&nbsp;&nbsp;" class="button">
		</form>	
<?	}
	else { echo "you do not have any quests registered in the database, you must <a href=\"./adm/gm.tmp.php\">Host a Quest</a> before you can edit a preface."; } 
} ?>
