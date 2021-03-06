<?php


function _compo2_preview_sort($a,$b) {
	return strcasecmp($a["title"],$b["title"]);
}

function _compo2_preview_me($params) {
	if ($params["uid"]) {
		$ce = compo2_entry_load($params["cid"],$params["uid"]);
		if ($ce["uid"]) {
			header("Location: ./?action=preview&uid=".intval($ce["uid"])); die;
		}
	}
	header("Location: ./?action=preview"); die;
}
	

function _compo2_preview($params,$_link="?action=preview") {
	if (isset($_REQUEST["uid"])) {
		_compo2_preview_show($params,intval($_REQUEST["uid"]));
		_compo2_show_comments($params["cid"],intval($_REQUEST["uid"]));
		return;
	}
	$cats = array(""=>"All Entries");
	foreach ($params["divs"] as $div) {
		$cats[$div] = "{$params["{$div}_title"]} Entries";
	}

	if (isset($params["uid"])) {
		$ce = compo2_entry_load($params["cid"],$params["uid"]);
		if (isset($ce["id"])) { echo "<p><a href='?action=edit'>Edit Entry</a> | <a href='?action=preview&uid={$ce["uid"]}'>View Entry</a></p>"; }
	}

	$etype = $_REQUEST["etype"];
	@$q = $_REQUEST["q"];
	$limit = 24;
	$start = 0;
	if (isset($_REQUEST["start"])) { $start = intval($_REQUEST["start"]); }
	$start = intval($start); $limit = intval($limit);
	
	if (($cres=compo2_cache_read($params["cid"],$ckey="_compo2_preview:$etype:$q:$start",5*60))!==false) { echo $cres; return; }
	ob_start();
	
	if (!strlen($q)) {
		$cnte = array_pop(compo2_query("select count(*) _cnt from c2_entry where etype like ? and cid = ? ".(!($params["state"]=="admin")?" and active=1":""),array("%$etype%",$params["cid"])));
	} else {
		$cnte = array_pop(compo2_query("select count(*) _cnt from c2_entry where (title like ? OR notes like ? OR links like ? OR get_user like ?) and  etype like ? and cid = ? ".(!($params["state"]=="admin")?" and active=1":""),array("%$q%","%$q%","%$q%","%$q%","%$etype%",$params["cid"])));
	}

	$cnt = $cnte["_cnt"];
	
	
	$sh = date("Y-m-d")."|"; // shuffle the entries every day .. seems a good compromise between having a user's search order get messed up if they are going through a bunch of entries, and having the same entries on the 1st page all the time.
	if (!strlen($q)) {
		$r = compo2_query("select *, md5(concat(?,id)) sh from c2_entry where etype like ? and cid = ? ".(!($params["state"]=="admin")?" and active=1":"")." order by sh limit $start,$limit",array($sh,"%$etype%",$params["cid"]));
	} else {
		$r = compo2_query("select *, md5(concat(?,id)) sh from c2_entry where (title like ? OR notes like ? OR links like ? OR get_user like ?) and etype like ? and cid = ? ".(!($params["state"]=="admin")?" and active=1":"")." order by sh limit $start,$limit",array($sh,"%$q%","%$q%","%$q%","%$q%","%$etype%",$params["cid"]));
	}
	usort($r,"_compo2_preview_sort");

	echo "<h2>".htmlentities($cats[$etype])." ($cnt)</h2>";
	if ( isset($params[$etype."_desc"]) ) {
		echo "<div>";
		echo $params[$etype."_desc"];
		echo "</div>";
	}
	
	ob_start();
	echo "<p>";
	$pre = "";
	if (count($params["divs"]) > 1) {
		foreach ($cats as $kk=>$vv) {
			echo "$pre<a href='?action=preview&etype=$kk'>$vv</a>"; $pre = " | ";
		}
	}
	echo "</p>";
	$links = ob_get_contents();
	ob_end_clean();
	echo $links;
	
	echo "<form style='text-align:left;margin:0px;'>";
	echo "<input type='hidden' name='action' value='preview'>";
	echo "<input type='text' name='q' value='".htmlentities($q)."'>";
	echo " <input type='submit' value='Search'>";
	echo "</form>";

	if (!$cnt) {
		echo "<p>No entries found.</p>";
	} else {

		ob_start();
		echo "<p>";
		if ($start > 0) {
			$i = max(0,$start-$limit);
			echo "<a href='?action=preview&q=".urlencode($q)."&etype=".urlencode($etype)."&start=$i'>Previous</a> ";
		}
		echo " [ ";
		$n=1;
		for ($i=0; $i<$cnt; $i+=$limit) {
			if ($i == $start) { echo "<b>$n</b> "; } else {
				echo "<a href='?action=preview&q=".urlencode($q)."&etype=".urlencode($etype)."&start=$i'>$n</a> ";
			}
			$n += 1;
		}
		echo " ] ";
		if ($start < ($cnt-$limit)) {
			$i = $start+$limit;
			echo "<a href='?action=preview&q=".urlencode($q)."&etype=".urlencode($etype)."&start=$i'>Next</a> ";
		}
		echo "</p>";
		$paging = ob_get_contents();
		ob_end_clean();
		
		echo $paging;
	
	
		$cols = 6;
		$n = 0;
		$row = 0;
		echo "<table class='preview'>";
		foreach ($r as $e) {
			if (($n%$cols)==0) { echo "<tr>"; $row += 1; } $n += 1;
			$klass = "class='alt-".(1+(($row)%2))."'";
			echo "<td valign=bottom align=center $klass>";
			$link = "$_link&uid={$e["uid"]}";
			echo "<div><a href='$link'>";
			$shots = unserialize($e["shots"]);
			echo "<img src='".compo2_thumb($shots["shot0"],120,90)."'>";
			echo "<div class='title'><i>".htmlentities($e["title"])."</i></div>";
			$ue = unserialize($e["get_user"]);
			echo $ue["display_name"];
			echo "</a></div>";
			if ($e["disabled"]) { echo "<div><i>disabled</i></div>"; }
			else { if (!$e["active"]) { echo "<div><i>inactive</i></div>"; } }
		}
		echo "</table>";
	
		echo $paging;
	}

	echo $links;

	$cres = ob_get_contents();
	ob_end_clean();
	compo2_cache_write($params["cid"],$ckey,$cres);
	
	echo $cres;

}

function compo2_strip($v) {
	return stripslashes($v);
}

function _compo2_preview_show_links($ce) {
//	$pre = "";
	$links = unserialize($ce["links"]);
	echo "<ul>";
	foreach ($links as $le) {
		// Validate //
		if (!strlen($le["title"])) { continue; }
		$link = $le["link"];
		if (strpos($link,"javascript:") === 0) { continue; }
		if (strpos($link,"?") === 0) { continue; }
		if (!preg_match("/^\w+\:\/\//",$link)) { continue; }
		echo "<li><a href='".htmlentities($link)."' target='_blank'>".htmlentities($le["title"])."</a></li>";		
//		echo "$pre<a href=\"".htmlentities($link)."\" target='_blank'>".htmlentities($le["title"])."</a>";
//		$pre = " | ";
	}
	echo "</ul>";
}

function get_gravatar( $email, $s = 80, $d = 'mm', $r = 'g', $img = false, $atts = array() ) {
	$url = 'http://www.gravatar.com/avatar/';
	$url .= md5( strtolower( trim( $email ) ) );
	$url .= "?s=$s&d=$d&r=$r";
	if ( $img ) {
		$url = '<img src="' . $url . '"';
		foreach ( $atts as $key => $val )
			$url .= ' ' . $key . '="' . $val . '"';
		$url .= ' />';
	}
	return $url;
}


function _compo2_preview_comments($params,$uid,$form=true) {
	if ($form) {
		if ($params["uid"]) {
			$comments = trim(compo2_strip($_REQUEST["comments"]));
			if (strlen($comments)) {
				$user = compo2_get_user($params["uid"]);
				compo2_insert("c2_comments",array(
					"cid"=>$params["cid"],
					"to_uid"=>$uid,
					"from_uid"=>$params["uid"],
					"ts"=>date("Y-m-d H:i:s"),
					"content"=>$comments,
					"get_user"=>serialize(array(
						"display_name"=>$user->display_name,
						"user_nicename"=>$user->user_nicename,
						"user_email"=>$user->user_email,
					)),
				));
				header("Location: ?action=preview&uid=$uid"); die;
			}
		}
	}
			
	$r = compo2_query("select * from c2_comments where cid = ? and to_uid = ? order by ts asc",array($params["cid"],$uid));
	
	echo "<h2>Comments</h2>";
	$pe = array();
	foreach ($r as $e) if (strlen(trim($e["content"]))) {
		// get rid of double posts.
		if (strcmp($e["from_uid"],$pe["from_uid"])==0 &&
			strcmp($e["content"],$pe["content"])==0) { continue; }
		$pe = $e;
		$user = unserialize($e["get_user"]);
		echo "<div class = 'comment'>";
		echo get_gravatar($user["user_email"],48,'mm','g',true,array("align"=>"right","class"=>"gravatar"));
		$cuid = intval($e["from_uid"]);
		echo "<div><strong><a href=\"?action=preview&uid=$cuid\">{$user["display_name"]}</a> says ...</strong></div>";
		echo "<div><small>".date("M j, Y @ g:ia",strtotime($e["ts"]))."</small></div>";
		echo "<p>".str_replace("\n","<br/>",htmlentities(trim($e["content"])))."</p>";
		echo "</div>";
	}
	if ($form) {
		if ($params["uid"]) {
			echo "<form method='post' action='?action=preview&uid=$uid'>";
			echo "<textarea name='comments' rows=4 cols=60></textarea>";
			echo "<p><input type='submit' value='Submit Comment'></p>";
		} else {
			echo "<p>You must sign in to comment.</p>";
		}
	}
}
		

function _compo2_preview_show($params,$uid,$comments=true) {
	$ce = compo2_entry_load($params["cid"],$uid);

	echo "<p>";
	echo "<a href='?action=preview'>Back to Browse Entries</a>";
	if (isset($params["uid"]) && $params["uid"]) {
		if ( current_user_can('edit_others_posts') ) {
//			$ce = compo2_entry_load($params["cid"],intval($_REQUEST["uid"]));
			echo " | <strong><a href='?action=edit&uid=" . $ce["uid"] . "&admin=1'>ADMIN EDIT</a></strong>";
		}
		if ( intval($params["uid"]) === intval($uid) ) {
			// If the entry exists //
			if ($ce["id"]) {
				echo " | <a href='?action=edit'>Edit Entry</a>";
			}
		}
	}
	echo "</p>";
			
	if (!$ce["id"]) {
		echo "<p>Sorry, this person does not have an Entry.</p>";
		return;
	}
	
	$shots = unserialize($ce["shots"]);
	$settings = unserialize($ce["settings"]);
	$is_nsfw = $settings["NSFL"];

	$baseurl = get_bloginfo("url")."/wp-content";

	$has_embed = isset($settings["EMBED"]["url"]) && $settings["EMBED"]["url"] !== "";
	
	if ( $has_embed ) {
		$url = $settings["EMBED"]["url"];
		$width = $settings["EMBED"]["width"];
		$height = $settings["EMBED"]["height"];

		$imagefile = array_values($shots)[0];
		$shot_url = c2_thumb($imagefile,900,500,false,true);
		
		$play_code = "<div id='embed' onclick='c2_play_game();' style='width:{$width}px;height:{$height}px'><img class='embed-icon' src='{$shot_url}'><div class='embed-overlay'></div><img class='embed-icon' src='/compo/wp-content/plugins/compo2/art/play.png' /></div>";
		
?>
		<script>
			function c2_toggle_fullscreen() {
				var elem = document.getElementById('embed');
					if (elem.requestFullscreen) {
						elem.requestFullscreen();
					} else if (elem.msRequestFullscreen) {
						elem.msRequestFullscreen();
					} else if (elem.mozRequestFullScreen) {
						elem.mozRequestFullScreen();
					} else if (elem.webkitRequestFullscreen) {
						elem.webkitRequestFullscreen();
				}
			}
			
			function c2_play_game() {
				var elm = document.getElementById('embed-frame');
				elm.innerHTML = "<?php echo "<iframe id='embed' src='{$url}' width='{$width}' height='{$height}' frameborder='0' allowfullscreen></iframe>"; ?>";
			}
			
			function c2_exit_game() {
				var elm = document.getElementById('embed-frame');
				elm.innerHTML = "<?php echo $play_code; ?>";
				//"<div id='embed' onclick='c2_play_game();'><?php echo "<img id='embed' src='{$shot_url}'>"; ?></div>";
			}
		</script>
		<style>
			#embed {
				margin:10px auto;
				display:block;
				position:relative;
				text-align:center;
			}
			
			:-webkit-full-screen #embed {
				width: 100%;
				height: 100%;
			}
			#embed:-ms-fullscreen {
				position:absolute;
				left:0px;
				top:0px;
				width: 100%;
				height: 100%;
			}					
			
			.embed-overlay {
				position:absolute;
				background:url('/compo/wp-content/plugins/compo2/art/TiledBars.png');
				left:0;
				top:0;
				width:100%;
				height:100%;
			}
			.embed-icon {
				position:absolute;
				left:50%;
				top:50%;
				-ms-transform: translate(-50%,-50);
				-webkit-transform: translate(-50%,-50%);
				transform: translate(-50%,-50%);				
			}
			
			.embed-controls {
				text-align:center;
			}
			.embed-controls img {
				cursor:pointer;
				opacity:0.3;
			}
			.embed-controls img:hover {
				opacity:1.0;
			}
		</style>
<?php	
		
		echo "<div id='embed-view'>";
		echo "<div id='embed-frame'>";
			echo $play_code;
		echo "</div>";
		echo "<div class='embed-controls'>";		
			if ( $settings["EMBED"]["fullscreen"] ) {	
				echo "<img src='{$baseurl}/plugins/compo2/art/Maximize.png' onclick='c2_toggle_fullscreen();'>";
			}	
			echo "<img src='{$baseurl}/plugins/compo2/art/Power.png' onclick='if (confirm(\"Are you sure you want to Shutdown the game? All progress will be lost!\")) { c2_exit_game(); }'>";
		echo "</div>";
		echo "</div>";
	}
	
	echo "
		<style>
			#shotview {
				text-align:center;
				margin-bottom:10px;
			}
			#shotview img {
				max-width:900px;
			}
		</style>
	";

	// Screenshot Viewer //
	echo "<div id='shotview' class='".($has_embed ? "hidden" : "")."'>";
		$imagefile = array_values($shots)[0];
		$link = $baseurl.'/compo2/'.$imagefile;
		$preview = c2_thumb($imagefile,900,500,false,true);
		echo "<a id='shotview_link' href='{$link}' target='_blank'><img id='shotview_img' src='{$preview}'></a>";
	echo "</div>";

	// Game Name and Developer //
	echo "<div style='overflow:auto;'>";
		echo "<div style='float:right;'>";
		$user = unserialize($ce["get_user"]);
		echo get_avatar($user['user_email'],'56');
		echo "</div>";	
		
		echo "<h2 style='font-size:28px'>".htmlentities($ce["title"])."</h2>";
		echo "by <a href=\"../author/{$user['user_nicename']}/\" target='_blank'><strong>{$user['display_name']}</strong></a>";
		$div = $ce["etype"];
		echo " - <i>{$params["{$div}_title"]} Entry</i>";
	echo "</div>";
	
?>
	<style>
		.shot-nav {
			text-align:center;
			width:940px;
			left:-20px;
			position:relative;
			margin-top:10px;
		}
		
		.shot-nav .sn-item {
			display:inline-block;
			opacity:0.4;
			padding:3px;
			position:relative;
		}
		.shot-nav .sn-item:hover {
			opacity:1.0;
		}

		.shot-nav .sn-item .sn-img {
			border-radius:7px;
			cursor:pointer;
		}
		
		.shot-nav .sn-item .sn-overlay {
			border-radius:7px;
			position:absolute;
			background:url('/compo/wp-content/plugins/compo2/art/TiledBars.png');
			left:0;
			top:0;
			width:100%;
			height:100%;
		}
		
		.shot-nav .sn-item .sn-icon {
			position:absolute;
			left:50%;
			top:50%;
			-ms-transform: translate(-50%,-50);
			-webkit-transform: translate(-50%,-50%);
			transform: translate(-50%,-50%);
		}
		
		.shot-nav .sn-selected {
			opacity:0.8;
			border:3px solid #000;
			background:#000;
			border-radius:10px;
			padding:0;
		}
		
		.links {
			margin:0;
		}
		
		.links ul {
			list-style:none;
			margin:0;
		}
		.links ul li:before {
			content:'';
		}
		
		#compo-nsfw {
			cursor:pointer;
			background:#900;
			border-radius:10px;
			padding:20px;
			color:#FF0;
			text-align:center;
		}
		
		#compo-nsfw h2 {
			text-align:center;
			font-size:40px;
			color:#F80;
		}
		
	</style>
	
	<script>
		function c2_addclass( el, className ) {
			if ( el ) {
				if (el.classList)
					el.classList.add(className);
				else
					el.className += ' ' + className;
			}
		}
		function c2_removeclass( el, className ) {
			if ( el ) {
				if (el.classList)
					el.classList.remove(className);
				else
					el.className = el.className.replace(new RegExp('(^|\\b)' + className.split(' ').join('|') + '(\\b|$)', 'gi'), ' ');
			}
		}

		// Global Variable //
		window.c2_ShotIndex = 0;
		
		function c2_show_embed() {
			c2_removeclass( document.getElementById('embed-view'), 'hidden');
		}
		function c2_hide_embed() {
			c2_addclass( document.getElementById('embed-view'), 'hidden');
		}
		
		function c2_show_image( _img, _link ) {
			c2_removeclass( document.getElementById('shotview'), 'hidden');
			document.getElementById('shotview_img').setAttribute('src', _img);
			document.getElementById('shotview_link').setAttribute('href', _link);
		}
		function c2_hide_image() {
			c2_addclass( document.getElementById('shotview'), 'hidden');
		}
		
		function c2_highlight_nav( id ) {
			c2_removeclass( document.getElementById('shot-nav-'+window.c2_ShotIndex), 'sn-selected');
			c2_addclass( document.getElementById('shot-nav-'+id), 'sn-selected');
			
			window.c2_ShotIndex = id;		
		}

		function c2_select_embed( id ) {
			c2_hide_image();
			
			c2_highlight_nav(id);
			
			c2_show_embed();
		}
		function c2_select_image( id, _img, _link ) {
			c2_hide_embed();
		
			c2_highlight_nav(id);

			c2_show_image( _img, _link );
		}
	</script>

<?php

	//echo "NSFW Test:".(($is_nsfw=='1')?"YES":"nope");
	//<script>window.compo_game_nsfw = true;</script>
	if ( $is_nsfw == '1' ) {
		echo "<script>window.compo_game_nsfw = true;</script>";
	}
	
	// Screenshots //
	echo "<div class='shot-nav'><span>";
	$idx = 0;
	if ( $has_embed ) {
		$imagefile = array_values($shots)[0];
		$link = $baseurl.'/compo2"/'.$imagefile;
		$thumb = c2_thumb($imagefile,180,140);
		$selected = ($idx === 0) ? "sn-selected" : "";
//		echo "<div class='sn-item {$selected}' id='shot-nav-{$idx}'><a href='' target='_blank'><img src='{$thumb}' width='180' height='140'></a></div>";
		echo "<div class='sn-item {$selected}' id='shot-nav-{$idx}' onclick='c2_select_embed({$idx});'>
			<img class='sn-img' src='{$thumb}' width='180' height='140'>
			<div class='sn-overlay'></div>
			<img class='sn-icon' id='embed-mode' src='/compo/wp-content/plugins/compo2/art/play-sm.png' />
		</div>";
		$idx++;
	}
	foreach ($shots as $imagefile) {
		if ( $idx === 5 ) break;
		$link = $baseurl.'/compo2/'.$imagefile;
		$thumb = c2_thumb($imagefile,180,140);
		$preview = c2_thumb($imagefile,900,500,false,true);
		$selected = ($idx === 0) ? "sn-selected" : "";
//		echo "<div class='sn-item {$selected}' id='shot-nav-{$idx}'><a href='{$link}' target='_blank'><img src='{$thumb}' width='180' height='140'></a></div>";
		echo "<div class='sn-item {$selected}' id='shot-nav-{$idx}'><img class='sn-img' src='{$thumb}' width='180' height='140' onclick='c2_select_image({$idx},\"{$preview}\",\"{$link}\");'></div>";
		$idx++;
	}
	echo "</span></div>";

	// Description //		
	echo "<p>".str_replace("\n","<br/>",htmlentities($ce["notes"]))."</p>";

	// Links and Downloads //
	echo "<h2>Downloads and Links</h2>";
	echo "<div class='links'>";
	_compo2_preview_show_links($ce);
	echo "</div>";

	echo "<br />";
	
//	$shots = unserialize($ce["shots"]);
///*	
//	$fname = array_shift($shots);
//	$firstshot = $fname;
//*/	
//	//$suffix = "?".strtotime($ce['stamp']);
//
//	$baseurl = get_bloginfo("url")."/wp-content/compo2";
//	foreach ($shots as $imagefile) {
//		$link = $baseurl.'/'.$imagefile;
//		$thumb = c2_thumb($imagefile,180,140);
//		$preview = c2_thumb($imagefile,900,600,false);
//		echo "<a href='{$link}' target='_blank'><img src='{$thumb}' width='180' height='140'></a>";
//	}

/*		
	echo "<table>";
	$cols = 4; $n = 0;
	$link = get_bloginfo("url")."/wp-content/compo2/{$fname}{$suffix}";
	echo "<tr><td colspan=$cols align=center><a href='{$link}' target='_blank'><img src='".c2_thumb($fname,900,600,false)."'></a>";
	$baseurl = get_bloginfo("url")."/wp-content/compo2";
	foreach ($shots as $fname) {
		if (($n%$cols)==0) { echo "<tr>"; } $n += 1;
		$link = "{$baseurl}/{$fname}";//{$suffix}";
		echo "<td><a href='{$link}' target='_blank'><img src='".c2_thumb($fname,180,140)."' width='180' height='140'></a>";
	}
	echo "</table>";
*/

/*	
	echo "<p>";
	if ($params["jcat"]) {
		$link = get_bloginfo("url")."/?category_name={$params["jcat"]}&author_name={$user["user_nicename"]}";
		echo "<a href='$link' target='_blank'>View {$user["display_name"]}'s journal.</a> | ";
	}
	echo "</p>";
*/
	
	// Twitter Card Meta //
	{
		echo '<meta name="twitter:card" content="summary_large_image" />';
		echo '<meta name="twitter:site" content="@ludumdare" />';
		
		$twitter = get_the_author_meta('twitter',(string)$uid);//get_the_author_meta('twitter', $user["ID"]); 
		if (($twitter != null) && ($twitter != '')) {
			echo '<meta name="twitter:creator" content="@'.$twitter.'" />';
		}
		
		echo '<meta name="twitter:title" content="'.substr(htmlentities($ce["title"]),0,70).'" />';
		echo '<meta name="twitter:description" content="'.substr(htmlentities($ce["notes"]),0,200).'" />';
		$imagefile = array_values($shots)[0];
		$link = $baseurl.'/compo2"/'.$imagefile;
		$thumb = c2_thumb($imagefile,280*2,150*2);
		echo '<meta name="twitter:image" content="'.htmlentities($thumb).'" />';
//		if (($firstshot != null) && ($firstshot != '')) {
//			//echo '<meta name="twitter:image" content="'.get_bloginfo("url").'/wp-content/compo2/'.htmlentities($firstshot).'" />';
//		}
		
		echo '<script>';
		echo 'var Elm = document.getElementsByTagName("title")[0];';
		echo 'Elm.innerHTML = "'.htmlentities($ce["title"]).' by ' . $user["display_name"] . ' | " + Elm.innerHTML;';
		echo '</script>';
	}
	
	// Rating Results //		
	if ($params["state"] == "results" || $params["state"] == "admin") {
    	if ( !isset($params["{$div}_judged"]) || ($params["{$div}_judged"] !== "0") ) {
			_compo2_results_ratings($params,$uid);
		}
	}
	
	// Comments ?? //
	if ($comments) {
		_compo2_preview_comments($params,$uid,true);
	}
}

?>
