<?php
session_start();
$page_title = "entry";
$page_type = "sindex";
require("./inc/control.php");
include($HTMLHEADER);
include($JAVASCRIPT_PATH);
if( check_session() )
{
	$last_date = unixStyleDate($_SESSION["last_date"]);
	include($BODYHEAD); ?>
<table border="0" cellpadding="0" cellspacing="0" class="table" width="700" class="main">
<tr valign="top">
	<td width="580">
		<!-- BEGIN main body area-->
		<!-- BEGIN quest tables -->
<?		if ( $db )
		{
			if( isset($_SESSION["uid"]) )
			{
				require($INCLUDE_PATH . "quests.player.php");
				if( $_SESSION["group"]=="gamemaster" || $_SESSION["group"]=="admin" ) { require($INCLUDE_PATH . "quests.gm.php"); }
				require($INCLUDE_PATH . "quests.all.php");
			}
			else { require($INCLUDE_PATH . "quests.all.php"); }
		}
		else { echo "<br />the database server appears to be offline, you should <a href=\"./logout.php\">logout</a>.<br /><br />"; } ?>
		<!-- END quest tables -->
		<!-- END main body area -->
	</td>
	<td width="120" align="right">
		<!-- BEGIN tools nav bar -->
		<?	if ( $db ) { require($INCLUDE_PATH . "menus.php"); } ?>
		<img src="./img/px.gif" width="120" height="1" border="0" /><br />
		<!-- END tools nav bar -->
	</td>
</tr>
</table>
<?
} else { 
	session_destroy();
	echo "<br /><br />You are not logged in, try again.<br /><br /><table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"150\"><tr><td align=\"right\">";
	$SUBMITPATH = "quests.entry.php"; 
	require($LOGIN_FORM); 
	echo "</td></tr></table><br /><br />";
}
include($COPYRIGHT);
check_include($HTMLFOOTER);
exit; ?>
