<? echo "<?xml version=\"1.0\" encoding=\"utf-8\" ?>\n"; ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
<? if( $SITE_STATUS == "DEAD" && $page_type!="SITE_STATUS_PAGE" ) { header("Location: http://www.questlog.org/index.off.php"); } ?>
<head>
	<title><? if( $page_title != "" ) { $d = " / ";  } else { $d = ""; } echo $SITE_TITLE . $d .  $page_title; ?></title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="author" content="<? echo $ADMIN_EMAIL; ?>" />
	<meta name="generator" content="human" />
	<meta name="copyright" content="<? echo $COPYRIGHT; ?>" />
	<meta name="robots" content="noindex,nofollow" />
	<meta http-equiv="Pragma" content="no-cache" />
	<meta http-equiv="ROBOTS" content="NOINDEX, NOFOLLOW" />
	<link rel="shortcut icon" type="image/gif" href="img/icon.gif" />
<?	switch ($page_type)
	{
		case "frame":
			$leftmargin = "0";
			$rightmargin = "0";
			$topmargin = "0";
			$marginwidth = "0";
			$marginheight = "0";
			$bgcolor = $FRAME_BG_COLOR;
			$space = "";
			$page_refresh = ";url=" . $BASE_HREF . "frame.php";
			break;
		case "popup":
			$leftmargin = "10";
			$rightmargin = "10";
			$topmargin = "0";
			$marginwidth = "10";
			$marginheight = "0";
			$bgcolor = $POPUP_BG_COLOR;
			$space = "";
			break;
		case "post":
			$leftmargin = "5";
			$rightmargin = "0";
			$topmargin = "0";
			$marginwidth = "0";
			$marginheight = "0";
			$bgcolor = $POPUP_BG_COLOR;
			$space = "";
			break;
		default:
			$leftmargin = "30";
			$rightmargin = "30";
			$topmargin = "0";
			$marginwidth = "30";
			$marginheight = "0";
			$bgcolor = $PAGE_BG_COLOR;
			$space = "<br />";
	}
	if( isset($LOG_REFRESH) && ($page_type=="log" || isset($page_refresh)) )
	{
		echo "\t<meta http-equiv=\"REFRESH\" content=\"" . $LOG_REFRESH . $page_refresh . "\" />\n\n";
	}
	?>
	<!-- base href="<? echo $BASE_HREF; ?>" //-->
	<!--link rel="stylesheet" href="<? echo $CSS; ?>" type="text/css"-->
	<? require($STYLESHEET); ?>
	<? #$db = db_read_connect(); 
        #echo $DATABASE; ?>
	<!--Database Connection: <? if ( $db = databaseConnection("questlog") ) { echo "OK"; } ?> //-->
</head>
<body leftmargin="<? echo $leftmargin; ?>" rightmargin="<? echo $rightmargin; ?>" topmargin="<? echo $topmargin; ?>" marginwidth="<? echo $marginwidth; ?>" marginheight="<? echo $marginheight; ?>" bgcolor="<? echo $bgcolor; ?>" text="#ffffff" link="#ffffff" alink="#ffffff" vlink="#ffffff">
<basefont face="Verdana" color="<? echo $BASE_FONT_COLOR; ?>" size="-2" />
<a name="top"></a><? echo $space; ?><div class="main">