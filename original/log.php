<?
session_start();
$page_title = "log";
$page_type = "log";
require("./inc/control.php");
require($HTMLHEADER);
if ( !check_session() ) { session_destroy(); }
$md = "&nbsp;&nbsp;|&nbsp;&nbsp;"; // define the menu delimeter //

	if ( $db )
	{
		if( $_GET["id"]!="" )
		{
			$questinfo_query = mysql_query("SELECT u.login_name,q.uid,q.quest_name,q.quest_status,q.read_access FROM users u,quests q WHERE q.qid='" . $_GET["id"] . "' AND q.uid=u.uid") or die("an error has occured while querying the database.[1]");
			$quest_info = mysql_fetch_array($questinfo_query);
			$gamemaster_name = $quest_info["login_name"];
			$gamemaster_id = $quest_info["uid"];
			$quest_name = $quest_info["quest_name"];
			$quest_members = $quest_info["quest_members"];
			$quest_status = $quest_info["quest_status"];
			#$total_post_count = mysql_result( mysql_query("SELECT count(pid) FROM posts WHERE qid='" . $_GET["id"] . "'"), 0 );
			$total_post_count = count_posts($_GET["id"], 2);
			
			$post_query_sql = "SELECT p.pid,p.uid,p.cid,p.post_text,p.post_date,u.login_name FROM posts p,users u WHERE p.qid='" . $_GET["id"] . "' AND p.uid=u.uid";
		} 
		
	 	include($JAVASCRIPT_PATH);
		include($BODYHEAD);
		
		if( ( !isset($_GET["id"]) || $_GET["id"]=="" ) && ( $_GET['mode']="search" && $_POST['search_keyword']!="" ) )
		{
			$post_query_sql = "SELECT p.pid,p.uid,p.cid,p.post_text,p.post_date,q.quest_name,u.login_name FROM posts p,quests q,users u WHERE p.uid=u.uid AND p.qid=q.qid";
		}
		
		if ( $_GET['mode']=="search" ) {
			$post_query_sql .= " AND MATCH(p.post_text) AGAINST('" . $_POST['search_keyword'] . "')";
		} 
		
		if ( !isset($_GET["sort"]) || $_GET["sort"]==0 )
		{
			$sort_order = "-post_date";
			$sort_sql="&sort=0";
			$new_sort_sql="&sort=1";
		}
		else {
			$sort_order = "post_date";
			$sort_sql="&sort=1";
			$new_sort_sql="&sort=0";
		}

		$post_query_sql .= " ORDER BY " . $sort_order;
		
		if ( isset($LOG_LIMIT) && $_GET['mode']!="search" )
		{
			if( !isset($_GET["offset"]) || $_GET["offset"] < 0 ) { $_GET["offset"]="0"; }
			$new_offset = $_GET["offset"] + $LOG_LIMIT;
			$neg_offset = $_GET["offset"] - $LOG_LIMIT;
			$post_query_sql .= " LIMIT " . $_GET["offset"] . ", " . $LOG_LIMIT;
		}
		
		if( $_GET["id"]!="" || ( $_GET['mode']="search" && $_POST['search_keyword']!="" ) ) { $post_query = mysql_query($post_query_sql) or die("an error has occured while querying the database.[" . $post_query_sql . "]"); }
		
		
		//
		// OPEN log menu //
		if ( check_session() )
		{
				echo "&nbsp;&nbsp;<a href=\"" . $ENTRY_SUBMIT . "\" onMouseOver=\"window.status='back'; return 0\" onMouseOut=\"window.status=''; return 0\">&laquo;&nbsp;home</a>";
				echo $md . "<a href=\"./" . $LOGOUT . "\" onMouseOver=\"window.status='logout'; return 0\" onMouseOut=\"window.status=''; return 0\">&laquo;&nbsp;logout</a>";
				echo $md . "<a href=\"./" . $PLAINLOG . "?id=" . $_GET["id"] . "\" onMouseOver=\"window.status='story view'; return 0\" onMouseOut=\"window.status=''; return 0\">story view</a>";
				echo $md . "<a href=\"./" . $QUESTLOG . "?id=" . $_GET["id"] . $new_sort_sql . "\" onMouseOver=\"window.status='reverse post order'; return 0\" onMouseOut=\"window.status=''; return 0\">order</a>";
				if ( isset($LOG_LIMIT) ) // if there is a post per page limit print the page controls //
				{
					echo $md . "<a href=\"" . $QUESTLOG . "?id=" . $_GET['id'] . "&offset=" . $neg_offset . $sort_sql . "\">&laquo;&nbsp;last&nbsp;page</a>";
					echo $md . "<a href=\"" . $QUESTLOG . "?id=" . $_GET['id'] . $sort_sql . "\">begining</a>";
					echo $md . "<a href=\"" . $QUESTLOG . "?id=" . $_GET['id'] . "&offset=" . $new_offset . $sort_sql . "\">next&nbsp;page&nbsp;&raquo;</a>";
				}
				if ( $_GET['mode']!="search" ) { 
					echo $md . "<a href=\"javascript: window.location.reload();\">refresh</a>";
				} else { 
					echo $md . "<a href=\"./log.php?id=". $_GET['id'] . "\">clear</a>";
				}
				
				if ( check_access("post") && $_GET['mode']!="search" )
				{
					echo $md . "<a href=\"javascript: post_window('./post.php?script=insert&id=" . $_GET["id"] . "');\" onMouseOver=\"window.status='post to this log'; return 0\" onMouseOut=\"window.status=''; return 0\">post</a>";
				}
		} else {
				echo "&nbsp;&nbsp;<a href=\"./index.php\">&laquo;&nbsp;home</a>";
				echo $md . "<a href=\"./" . $QUESTLOG . "?id=" . $_GET["id"] . $new_sort_sql . "\" onMouseOver=\"window.status='reverse post order'; return 0\" onMouseOut=\"window.status=''; return 0\">order</a>";
				if ( isset($LOG_LIMIT) && $_GET['mode']!="search" )
				{
					echo $md . "<a href=\"" . $QUESTLOG . "?id=" . $_GET['id'] . "&offset=" . $neg_offset . $sort_sql . "\">&laquo;&nbsp;last&nbsp;page</a>";
					echo $md . "<a href=\"" . $QUESTLOG . "?id=" . $_GET['id'] . $sort_sql . "\">begining</a>";
					echo $md . "<a href=\"" . $QUESTLOG . "?id=" . $_GET['id'] . "&offset=" . $new_offset . $sort_sql . "\">next&nbsp;page&nbsp;&raquo;</a>";
				}
				if ( $_GET['mode']!="search" ) { 
					echo $md . "<a href=\"javascript: window.location.reload();\">refresh</a>";
				} else { 
					echo $md . "<a href=\"./log.php?id=". $_GET['id'] . "\">clear</a>";
				}
		}
	
		if ( check_access() )
		{
			$search = "./log.php?mode=search"; if ( is_numeric($_GET['id']) ) { $search .= "&id=" . $_GET['id']; } ?>
			<!-- START Search Form -->

			<!-- END Search Form -->
			<!-- START Filter Form -->
<? 			$filter_list_query = mysql_query("SELECT c.uid,c.cid,c.char_name FROM characters c,quest_members m WHERE m.qid='" . $_REQUEST["id"] . "' AND m.cid=c.cid") or die("Done fucked up");
			$num = mysql_num_rows($filter_list_query);
			if ( $num > 1 ) { ?>			
			<div id="search" style="margin-top: 10px;" class="controls">
				<form method="POST" action="<? echo $filter ?>" id="filter_posts_form" name="filter_posts_form">
				<label class="caption">filter posts	: 
					<select name="ship_id" class="field" onchange="this.form.submit();">
						<option value="">All posts are currently displayed</option>
<?						while ( $filter_list = mysql_fetch_array($filter_list_query) )
						{			
							echo "<option value=\"" . $filter_list['uid'] . "\">" . $filter_list['char_name'] . "</option>";
						} ?>
					</select>
				<? echo "&nbsp;&nbsp;There are currently " . $num . " members of this quest."; ?>
				</lable>
				<input type="hidden" id="qid" name="qid" value="<? echo $_GET['id']; ?>" />
				</form>
			</div>
			<!-- END Filter Form -->
<?			} ?>
			<hr />
<?			if ( ( !isset($_GET["id"]) || $_GET["id"]=="" ) && ( $_GET['mode']="search" && $_POST['search_keyword']!="" ) || ( $_GET["id"]!="" && check_status($quest_status, $_SESSION["uid"], $_GET["id"]) ) )
			{ ?>
				<!-- OPEN main log table //-->
				<table border="0" cellpadding="0" cellspacing="0" width="600" class="post-table">
<?				$post_count = $total_post_count;
				if ( isset($_GET["c"]) ){ $post_count = $_GET["c"]; }
				while ( $all_posts = mysql_fetch_array($post_query) )
				{
				    $pid = $all_posts["pid"];
				    $player_id = $all_posts["uid"];
				    $cid = $all_posts["cid"];
				    $post_date = $all_posts["post_date"];
				    $player_name = $all_posts["login_name"];
				    $player_email = $all_posts["user_email"];
				    if ( ( !isset($_GET['id']) || $_GET['id']=="" ) && $_GET['mode']=="search" ) { $quest_name_2 = "to&nbsp;" . $all_posts["quest_name"] . "&nbsp;"; }
				    
				    $post_text = $all_posts["post_text"];
				    #$post_text = privateMessageTag( $all_posts["post_text"], $quest_info["uid"], $all_posts["uid"] );
							
				    if ( $player_id == $gamemaster_id ) { $player_name .= " - " . $OWNER_SUFFIX; $player_post_count = count_posts($all_posts["uid"]); } else { $player_post_count = count_posts($all_posts["cid"], "1"); }
				    if ( $cid!="0" )
				    {
					$char_query = mysql_query("SELECT c.char_name FROM characters c,posts p WHERE p.pid='" . $pid . "' AND p.cid='" . $cid . "' AND p.cid=c.cid");
					$player_name = mysql_result($char_query, 0);
					mysql_free_result( $char_query );
			 	   }
			 	   #if ( check_session() && $player_email!=$_SESSION["email"] )
			 	   #{
			 	   #	$player_name_email = "<a href=\"javascript:post_window('./inc/contact.php?q=" . $quest_name . "&m=" . $player_email . "&c=" . $player_name . "')\" class=\"postlink\">" . $player_name . "</a>";
			 	   #}
			 	   #else { $player_name_email = $player_name; } 
			 	   if($post_count % 2) { $row_bg = $ROW_1_COLOR; } else {  $row_bg = $ROW_2_COLOR; } ?>
		      	  	<tr valign="top" align="left">
					<td background="<? echo $BAR_BG_1; ?>" bgcolor="<? echo $ROW_HEADING_COLOR; ?>" width="450" align="left">
					<div class="txtposthd">
						<? echo "&nbsp;&nbsp;" . $post_count . "&nbsp;&nbsp;Posted&nbsp;on&nbsp;" . $post_date . "&nbsp;" . $quest_name_2 . "by&nbsp;<b>"; if ( $cid!="0" ) { echo "<a href=\"javascript: script_window('./player.php?script=character_listing&title=Characters&char=$cid');\" class=\"logtitle\">" . $player_name . "</a>"; } else { echo $player_name; }; echo "</b>&nbsp;"; if ( $PRINT_POST_TOTALS == "ON" ) { echo "(" . $player_post_count . ")&nbsp;"; } ?>
					</div>
       					</td>
					<td background="<? echo $BAR_BG_1; ?>" bgcolor="<? echo $ROW_HEADING_COLOR; ?>" width="150" class="top" align="right">
					<div class="imgposthd">&nbsp;
					<!-- OPEN edit and delete links -->
<?						if ($_SESSION["uid"] == $player_id || $_SESSION["uid"] == $gamemaster_id || $_SESSION["group"] == "admin")
						{ ?>
			 		       	<a href="javascript:post_window('./post.php?script=edit&id=<? echo $_GET[id]; ?>&post=<? echo $pid; ?>')" onMouseOver="window.status='edit post id<? echo $pid; ?>'; return 0" onMouseOut="window.status=''; return 0" class="postlink"><img src="./img/icon.edit_dark.gif" width="9" height="9" alt="edit" border="0"></a>
							<a href="javascript:post_window('./post.php?script=delete&id=<? echo $_GET[id]; ?>&post=<? echo $pid; ?>')" onMouseOver="window.status='delete post id<? echo $pid; ?>'; return 0" onMouseOut="window.status=''; return 0" class="postlink"><img src="./img/icon.delete_dark.gif" width="9" height="9" alt="delete" border="0"></a>&nbsp;
<?						} ?>
					<!-- CLOSE edit and delete links -->
					</div>
			   	     </td>
					</tr>
					<tr valign="top" align="left"><td bgcolor="<? echo $row_bg; ?>" class="post-cell" colspan="2">
            <div class="post-text">
            <?  echo $post_text . "\n<br /><br />\n";
                if ( $_SESSION["uid"] == $gamemaster_id ) { 
                  $roll = mysql_fetch_row(mysql_query("SELECT roll FROM rolls WHERE rid='" . $pid . "'"));
                  if ( $roll['0'] > 0 ) { echo "<strong>*** d20: " . $roll['0'] . " ***</strong>\n<br /><br />\n"; }
                } ?>
            </div>
          </td></tr>
<?			 	  $post_count--;
		    	    } ?>
				</table><br />
				<!-- CLOSE main log table //-->
			<!-- OPEN footer log table //-->
			<table border="0" cellpadding="0" cellspaceing="0" width="700" class="main">
			<tr valign="top">
				<td align="left" width="200"><a href="./<? echo $QUESTLOG; ?>?id=<? echo $_GET['id']; ?>#top">TOP</a></td>
				<td align="right" width="500">
<?				if ( isset($LOG_LIMIT) )
				{
					if ( isset($_GET["c"]) && $_GET["c"] < $total_post_count )
					{
						$last_count = $_GET["c"] + $LOG_LIMIT;
						echo "<a href=\"" . $QUESTLOG . "?id=" . $_GET['id'] . "&offset=" . $neg_offset . $sort_sql . "&c=" . $last_count . "\">&laquo;&nbsp;last&nbsp;page</a>";
						echo "&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;";
						echo "<a href=\"" . $QUESTLOG . "?id=" . $_GET['id'] . $sort_sql . "\">begining</a>";
					}
					if ( $post_count != 0 )
					{
						echo "&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;";
						echo "<a href=\"" . $QUESTLOG . "?id=" . $_GET['id'] . "&offset=" . $new_offset . $sort_sql . "&c=" . $post_count . "\">next&nbsp;page&nbsp;&raquo;</a>";
					}
				} ?>
				</td>
			</tr>
			</table>
			<!-- CLOSE footer log table //-->
<?			}
			else { echo "<br /><br /><br />" . $ERROR_THREAD_ACCESS . "<br /><br /><br />"; }
		}
		else { echo "<br /><br /><br />" . $ERROR_THREAD_ACCESS . "<br /><br /><br />"; }
	}
	else { echo "<br /><br /><br />" . $ERROR_DB_OFFLINE . "<br /><br /><br />"; }

include($COPYRIGHT);
check_include($HTMLFOOTER);
exit; ?>
