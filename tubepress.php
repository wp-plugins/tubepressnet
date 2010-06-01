<?php
/*
Plugin Name: TubePress.Net
Plugin URI: http://www.tubepress.net/
Description:  The Youtube Plugin for Wordpress
Author: Mario Mansour
Version: 3.1.8
Author URI: http://www.mariomansour.org/
*/
define('DEFAULT_EXCERPT', '<img style="border: 3px solid #000000" src="%tp_thumbnail%" /><br />%tp_title% was uploaded by: %tp_author%<br />Duration: %tp_duration%<br />Rating: %tp_rating_img%');
define('DEFAULT_CONTENT', '%tp_player%<p>%tp_description%</p>');
class youtube {
	var $url;
	function users_list_favorite_videos($user, $page=1, $results=10) {
		$functionName = "/feeds/api/users/".$user."/favorites";
		$payload = array("start-index"=>$page,"max-results"=>$results);
		$results = $this->getGdataRsp($functionName, $payload);
		return $results;
	}
	function videos_get_details($video_id) {
		$functionName = "/feeds/api/videos/".$video_id;
		$payload = "";
		$results = $this->getGdataRsp($functionName, $payload);
		return $results;
	}	 
	function videos_list_by_tag($tag, $page=1, $results=10, $order="relevance") {
		$functionName = "/feeds/api/videos";
		$tag = urlencode($tag);
		$payload = array("vq"=>$tag,"start-index"=>$page,"max-results"=>$results,"orderby"=>$order);
		$results = $this->getGdataRsp($functionName, $payload);
		return $results;
	}
	function videos_list_by_user($user, $tag="", $page=1, $results=10, $order="published") {
		$functionName = "/feeds/api/users/".$user."/uploads";
		$payload = array("start-index"=>$page,"max-results"=>$results,"orderby"=>$order);
		if(!empty($tag)) { $payload["vq"] = $tag; }
		$results = $this->getGdataRsp($functionName, $payload);
		return $results;
	}
	function videos_list_featured($feat, $page=1, $results=10, $order="published") {
		$functionName = "/feeds/api/standardfeeds/".$feat;
		$payload = array("start-index"=>$page,"max-results"=>$results,"orderby"=>$order);
		$results = $this->getGdataRsp($functionName, $payload);
		return $results;
	}
	function getGdataRsp($functionName, $payload) {
		$this->url = $this->buildQuery($functionName, $payload);
		$response = json_decode(tp_fetch($this->url),true);
		return $response;
	}
	function buildQuery($functionName, $payload) {
		$payloadString = "";
		if ($payload != "") {
			foreach ($payload as $name => $value) {
				$payloadString .= '&'.$name.'='.$value;
			}
		}
		$url = 'http://gdata.youtube.com'.$functionName.'?alt=json'.$payloadString;
		return $url;
	}
}

if(!function_exists('json_decode') ){
	function json_decode($content, $assoc=false){
		require_once('JSON.php');
		if ( $assoc ){
			$json = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
		} else {
			$json = new Services_JSON;
		}
		return $json->decode($content);
	}
}

function tp_fetch($url) {
	if(!class_exists('Snoopy')) {
		require_once( ABSPATH . WPINC . '/class-snoopy.php');
	}
	$client = new Snoopy();
	$client->fetch($url);
	return $client->results;
}

$yt = new youtube();

function tp_get_list($options,$action='tag') {
	global $yt;
	//$options = get_option('tp_options_user');
	$warning = '';
	$status = 0;
	$gen_options = get_option('tp_options');
	if(!$gen_options['customfield'] && (empty($gen_options['content']) || empty($gen_options['excerpt']))) {
		_e('<div class="updated fade"><p><strong>You have to <a href="admin.php?page=tubepressnet/tubepress.php">customize the Content Template and/or Content Excerpt</a>, otherwise your posts/pages will not show the imported videos</strong></p></div>');
		return false;
	} elseif($gen_options['customfield'] && empty($gen_options['content']) && empty($gen_options['excerpt'])) {
		$warning .= __('<p><strong>Do not forget to <a href="theme-editor.php">edit your template</a> to make use of these custom fields instead of the default the_content() and the_excerpt() calls</strong></p>');
	}	
	if(!is_array($options)) return false;
	switch($action) {
		case 'id':
			$xml = $yt->videos_get_details($options['video_id']);
		break;
		case 'user':
			$xml = $yt->videos_list_by_user($options['user'], $options['tag'], $options['page'], $options['per_page'], $options['orderby']);
		break;
		case 'featured':
			$xml = $yt->videos_list_featured($options['featf'], $options['page'], $options['per_page'], $options['orderby']);
		break;
		case 'favorite':
			$xml = $yt->users_list_favorite_videos($options['user'], $options['page'], $options['per_page']);
		break;
		case 'tag':
			$xml = $yt->videos_list_by_tag($options['tag'], $options['page'], $options['per_page'], $options['orderby']);
		break;
	}
	echo '<div class="wrap">';
	_e('<h2>Imported Video List</h2>');
	echo '<div align="center">';
	if(isset($xml['feed']['entry'])) {
		foreach ($xml['feed']['entry'] as $video) {
			$video['id']['$t'] = extractID($video['id']['$t']);
			if(!tp_duplicate($video['id']['$t'])) {
				$status = 1;
				echo "<img src='{$video['media$group']['media$thumbnail'][0]['url']}' alt='{$video['title']['$t']}' width='120' height='90' />  ";
				tp_write_post($video,$options);
			}
		}
	} else if(isset($xml['entry'])) {
		$xml['entry']['id']['$t'] = extractID($xml['entry']['id']['$t']);
		if(!tp_duplicate($xml['entry']['id']['$t'])) {
			$status = 1;
			echo "<img src='{$xml['entry']['media$group']['media$thumbnail'][0]['url']}' alt='{$xml['entry']['title']['$t']}' width='120' height='90' />  ";
			tp_write_post($xml['entry'],$options);
		}
	} else { $status = -1; }
	switch($status) {
		case -1:
			echo '<div class="updated fade"><p>'.__('No Videos Found').$warning.'</p></div>';
		break;	
		case 0:
			echo '<div class="updated fade"><p>'.__('Videos already imported').$warning.'</p></div>';
		break;
		case 1:
			echo '<div class="updated fade"><p>'.__('Videos imported successfully').$warning.'</p></div>';
		break;
		default:
			if(!empty($warning)) {
				echo '<div class="updated fade"><p>'.$warning.'</p></div>';
			}
		break;
	}
	echo '</div></div>';
}
function extractID($id) {
	return str_replace("http://gdata.youtube.com/feeds/api/videos/","",$id);
}
function tp_duplicate($id) {
	global $wpdb;
	$options = get_option('tp_options');
	$post = $wpdb->get_results("SELECT ID FROM $wpdb->posts WHERE post_content like '%".$id."%' OR post_excerpt like '%".$id."%' LIMIT 1",ARRAY_A);
	$field = $wpdb->get_results("SELECT post_id FROM $wpdb->postmeta WHERE meta_value='".$id."' AND post_id NOT IN (SELECT post_id FROM $wpdb->postmeta where meta_key='_wp_trash_meta_status') LIMIT 1",ARRAY_A);
	return (bool) ((is_array($post) || is_array($field)) && $options['duplicate']);
}

function tp_player($id) {
	$opt = get_option('tp_options');
	// color1 , color2, rel=0, border=1, width, height, autoplay=1
	$pl = '<object width="'.$opt['width'].'" height="'.$opt['height'].'"><param name="movie" value="http://www.youtube.com/v/'.$id;
	$param = '&amp;hl=en&amp;fs=1';
	if ($opt['rel']) 
		$param .= '&amp;rel=0';
	if (isset($opt['color1']) && isset($opt['color2']))
		$param .= '&amp;color1='.$opt['color1'].'&amp;color2='.$opt['color2'];
	if ($opt['border'])
		$param .= '&amp;border=1';
	$auto = "";
	if ($opt['autoplay']) {
		$param .= '&amp;autoplay=1';
		$auto = '<param name="autoplay" value="true"></param>';
	}
	$pl .= $param.'"></param><param name="allowFullScreen" value="true"></param>'.$auto;
	$pl .= '<embed src="http://www.youtube.com/v/'.$id.$param.'" type="application/x-shockwave-flash" allowfullscreen="true" width="'.$opt['width'].'" height="'.$opt['height'].'"></embed></object>';
	if ($opt['color']==0) {
		$pl = '[wp-jw-player src="http://www.youtube.com/watch?v='.$id.'"]';
	}
	return $pl;
}

function tp_rating_c($r) {
	$img = '';
	//$path = get_bloginfo('siteurl').'/wp-content/plugins/tubepress.net/images/';
	$t = 0;
	for($i=0;$i<floor($r);$i++) { $img .= '<img src="'.plugins_url('/images/yt_rating_on.gif',__FILE__).'" />'; }
	if($r > floor($r)) { $t = 1; $img .= '<img src="'.plugins_url('/images/yt_rating_half.gif',__FILE__).'" />'; }
	for($i=0;$i<5-floor($r)-$t;$i++) { $img .= '<img src="'.plugins_url('/images/yt_rating_off.gif',__FILE__).'" />'; }
	return $img;
}

function tp_write_post($v,$opt) {
	$tpo = get_option('tp_options');
	$post_template_excerpt = $tpo['excerpt'];
	$post_template_content = $tpo['content'];
	$vid = (!empty($v['id']['$t'])) ? $v['id']['$t'] : $opt['video_id'];
	
	$tp_tags = array("%tp_player%","%tp_id%","%tp_title%","%tp_thumbnail%","%tp_description%","%tp_duration%","%tp_rating_num%","%tp_rating_img%","%tp_viewcount%","%tp_author%","%tp_tags%","%tp_url%");
	$tag_values = array(tp_player($vid),$vid,$v['title']['$t'],$v['media$group']['media$thumbnail'][0]['url'],$v['content']['$t'],$v['media$group']['yt$duration']['seconds'],$v['gd$rating']['average'],tp_rating_c($v['gd$rating']['average']),$v['yt$statistics']['viewCount'],$v['author'][0]['name']['$t'],$v['media$group']['media$keywords']['$t'],$v['media$group']['media$player'][0]['url']);
	
	$post_template_excerpt = str_replace($tp_tags,$tag_values,$post_template_excerpt);
	$post_template_content = str_replace($tp_tags,$tag_values,$post_template_content);
	$post_category = explode(',', trim($opt['cat'], " \n\t\r\0\x0B,"));
	$post_tags = explode(', ', trim($v['media$group']['media$keywords']['$t']," \n\t\r\0\x0B,"));
	$tp_post = array('post_title' => $v['title']['$t'],
			'post_content' => $post_template_content,
			'post_status' => 'publish',
			'post_type' => $tpo['type'],
			'post_name' => sanitize_title($v['title']['$t']),
			'post_category' => $post_category,
			'tags_input' => $post_tags,
			'post_excerpt' => $post_template_excerpt);
	$post_id = wp_insert_post($tp_post);
	if($tpo['customfield']) {
		foreach($tp_tags as $k=>$meta_key) {
			add_post_meta($post_id, str_replace("%","",$meta_key), $tag_values[$k]);
		}
	}
	wp_create_categories($post_category,$post_id);
}

function tp_category_form($options) {
	$tpo = get_option('tp_options');
	$tf = '';
	if($tpo['type'] == 'post') {
		$tf .= '<tr>';
		$tf .= '	<td>Category</td>';
		$tf .= '	<td><input name="cat" type="text" id="cat" value="'.$options['cat'].'" /></td>';
		$tf .= '	<td>Add the imported videos to this category</td>';
		$tf .= '</tr>';
	}
	return $tf;
}
function tp_order_form($options) {
	$orderoption = array("relevance"=>"Relevance","published"=>"Published","viewCount"=>"View Count","rating"=>"Rating");
	$tf = '<tr><td>Order By</td><td><select name="orderby">';
	foreach($orderoption as $k=>$v) {
		$selected = ($options['orderby'] == $k) ? ' selected="selected"' : '';
		$tf .= '<option value="'.$k.'"'.$selected.'>'.$v.'</option>';
	}
	$tf .= '</select></td><td></td></tr>';
	return $tf;
}
function tp_comment_form($options) {
	return "";
	$tf .= '<tr>';
	$tf .= '	<td>Import Comments ?</td>';
	$tf .= '    <td>';
	$tf .= '    <select name="comments" id="comments">';
	$tf .= '	<option';
	if ($options['comments'] == 'No') $tf .= ' selected="selected"';
	$tf .= '>No</option>';
	$tf .= '	<option';
	if ($options['comments'] == 'Yes') $tf .= ' selected="selected"';
	$tf .= '>Yes</option>';
	$tf .= '    </select>';
	$tf .= '    </td>';
	$tf .= '    <td>This will import the users comments from youtube</td>';
	$tf .= '</tr>';
	return $tf;
}

function tp_import_id() {
	$default = array('video_id'=>'ImtuJ-kzsAc');
	if (isset($_POST['update_tp'])) {
		$options['video_id'] = $_POST['video_id'];
		$options['cat'] = $_POST['cat'];
		$options['comments'] = $_POST['comments'];
		update_option('tp_options_id', $options);	
		tp_get_list($_POST,'id');
	} else {
		$opt = get_option('tp_options_id');
		$options = is_array($opt) ? array_merge($default,$opt) : $default;
	}
	?>

	<div class="wrap">
		<h2><?php _e('TubePress: Import By ID'); ?></h2>
		<?php echo tp_copyright(); ?>
		<form name="id" method="post">
		<table width="669">
			<tr>
				<td>Video ID:</td>
				<td><input name="video_id" type="text" id="video_id" value="<?php echo $options['video_id'] ?>" /></td>
				<td>http://www.youtube.com/watch?v=<strong>ImtuJ-kzsAc</strong></td>
			</tr>
			<?php _e(tp_category_form($options)); ?>
			<?php _e(tp_comment_form($options)); ?>
		</table>		
		<div class="submit"><input type="submit" name="update_tp" value="<?php _e('Import This Video &raquo;', 'update_tp') ?>"  style="font-weight:bold;" /></div></p>
		</form>
    </div>
	
<?php
}

function tp_import_featured() {
	$default = array('cat'=>'Featured','featf'=>'top_rated', 'page'=>'1', 'per_page'=>'10');
	if (isset($_POST['update_tp'])) {
		$options['featf'] = $_POST['featf'];
		$options['page'] = $_POST['page'];
		$options['per_page'] = $_POST['per_page'];		
		$options['cat'] = $_POST['cat'];
		$options['orderby'] = $_POST['orderby'];
		$options['comments'] = $_POST['comments'];
		update_option('tp_options_feat', $options);	
		tp_get_list($_POST,'featured');
	} else {
		$opt = get_option('tp_options_feat');
		$options = is_array($opt) ? array_merge($default,$opt) : $default;
	}
	?>

	<div class="wrap">
		<h2><?php _e('TubePress: Import Featured Videos'); ?></h2>
		<?php echo tp_copyright(); ?>
		<form name="feat" method="post">
		<table width="669">
			<tr>
				<td><?php _e('Select Feed Type'); ?></td>
				<td>
					<select name="featf">
					<?php
						$featoptions = array(
							"most_viewed"=>"Most Viewed",
							"top_rated"=>"Top Rated",
							"recently_featured"=>"Recently Featured",
							"watch_on_mobile"=>"Watch on Mobile",
							"top_favorites"=> "Top Favorites",
							"most_linked"=>"Most Linked",
							"most_responded"=>"Most Responded",
							"most_recent"=>"Most Recent",
							"most_discussed"=>"Most Discussed"
						);
						foreach($featoptions as $k=>$v) {
							$selected = ($options['featf'] == $k) ? ' selected="selected"' : ''; 
							echo '<option value="'.$k.'"'.$selected.'>'.$v.'</option>';
						}
					?>
					</select>
				</td>
				<td></td>
			</tr>
			<tr>
				<td><?php _e('Max Results'); ?></td>
				<td><input name="per_page" type="text" id="per_page" value="<?php echo $options['per_page']; ?>" /></td>
			    <td><?php _e('Indicate maximum number of entries to import'); ?></td>
			</tr>
			<tr>
				<td><?php _e('Start Index'); ?></td>
				<td><input name="page" type="text" id="page" value="<?php echo $options['page']; ?>" /></td>
			    <td><?php _e('Indicate which entry to start listing results'); ?></td>
			</tr>			
			<?php _e(tp_order_form($options)); ?>
			<?php _e(tp_category_form($options)); ?>
			<?php _e(tp_comment_form($options)); ?>
		</table>
		<div class="submit"><input type="submit" name="update_tp" value="<?php _e('Import Featured Videos &raquo;', 'update_tp') ?>"  style="font-weight:bold;" /></div></p>        
		</form>
    </div>
	
<?php
}

function tp_import_favorite() {
	$default = array('user'=>'tubepressnet', 'page'=>'1', 'per_page'=>'10');
	if (isset($_POST['update_tp'])) {
		$options['user'] = $_POST['user'];
		$options['page'] = $_POST['page'];
		$options['per_page'] = $_POST['per_page'];		
		$options['cat'] = $_POST['cat'];
		$options['comments'] = $_POST['comments'];
		update_option('tp_options_fav', $options);
		tp_get_list($_POST,'favorite');
	} else {
		$opt = get_option('tp_options_fav');
		$options = is_array($opt) ? array_merge($default,$opt) : $default;
	}
	?>

	<div class="wrap">
		<h2><?php _e('TubePress: Import Favorite Videos'); ?></h2>
		<?php echo tp_copyright(); ?>
		<form name="user" method="post">
		<table width="669">
			<tr>
				<td><?php _e('Username'); ?></td>
				<td><input name="user" type="text" id="user" value="<?php echo $options['user']; ?>" /></td>
			    <td><a rel="nofollow" href="http://www.youtube.com/signup"><?php _e('get one!'); ?></a></td>
			</tr>
			<tr>
				<td><?php _e('Max Results'); ?></td>
				<td><input name="per_page" type="text" id="per_page" value="<?php echo $options['per_page']; ?>" /></td>
			    <td><?php _e('Indicate maximum number of entries to import'); ?></td>
			</tr>
			<tr>
				<td><?php _e('Start Index'); ?></td>
				<td><input name="page" type="text" id="page" value="<?php echo $options['page']; ?>" /></td>
			    <td><?php _e('Indicate which entry to start listing results'); ?></td>
			</tr>			
			<?php _e(tp_category_form($options)); ?>
			<?php _e(tp_comment_form($options)); ?>
		</table>
		<div class="submit"><input type="submit" name="update_tp" value="<?php _e('Import My Favorites &raquo;', 'update_tp') ?>"  style="font-weight:bold;" /></div></p>        
		</form>
    </div>
	
<?php
}


function tp_import_user() {
	$default = array('user'=>'tubepressnet', 'tag'=>'', 'page'=>'1', 'per_page'=>'10', 'orderby'=>'published');
	if (isset($_POST['update_tp'])) {
		$options['user'] = $_POST['user'];
		$options['tag'] = $_POST['tag'];
		$options['page'] = $_POST['page'];
		$options['per_page'] = $_POST['per_page'];
		$options['orderby'] = $_POST['orderby'];
		$options['cat'] = $_POST['cat'];
		$options['comments'] = $_POST['comments'];
		update_option('tp_options_user', $options);
		tp_get_list($_POST,'user');
	} else {
		$opt = get_option('tp_options_user');
		$options = is_array($opt) ? array_merge($default,$opt) : $default;
	}
	?>

	<div class="wrap">
		<h2><?php _e('TubePress: Import My Videos'); ?></h2>
		<?php echo tp_copyright(); ?>
		<form name="user" method="post">
		<table width="669">
			<tr>
				<td><?php _e('Username'); ?></td>
				<td><input name="user" type="text" id="user" value="<?php echo $options['user']; ?>" /></td>
			    <td><a rel="nofollow" href="http://www.youtube.com/signup"><?php _e('get one!'); ?></a></td>
			</tr>
			<tr>
				<td><?php _e('Max Results'); ?></td>
				<td><input name="per_page" type="text" id="per_page" value="<?php echo $options['per_page']; ?>" /></td>
			    <td><?php _e('Indicate maximum number of entries to import'); ?></td>
			</tr>
			<tr>
				<td><?php _e('Start Index'); ?></td>
				<td><input name="page" type="text" id="page" value="<?php echo $options['page']; ?>" /></td>
			    <td><?php _e('Indicate which entry to start listing results'); ?></td>
			</tr>
			<tr>
				<td><?php _e('Tag'); ?></td>
				<td><input name="tag" type="text" id="tag" value="<?php echo $options['tag']; ?>" /></td>
			    <td><?php _e('Filter the results by a tag keyword'); ?></td>
			</tr>				
			<?php _e(tp_order_form($options)); ?>
			<?php _e(tp_category_form($options)); ?>
			<?php _e(tp_comment_form($options)); ?>
		</table>		
		<div class="submit"><input type="submit" name="update_tp" value="<?php _e('Import My Videos &raquo;', 'update_tp') ?>"  style="font-weight:bold;" /></div></p>
		</form>
    </div>
	
<?php
}

function tp_import_tag() {
	$default = array('tag'=>'funny clips', 'page'=>'1', 'per_page'=>'10', 'orderby'=>'relevance');
	if (isset($_POST['update_tp'])) {
		$options['tag'] = $_POST['tag'];
		$options['page'] = $_POST['page'];
		$options['per_page'] = $_POST['per_page'];
		$options['orderby'] = $_POST['orderby'];
		$options['cat'] = $_POST['cat'];
		$options['comments'] = $_POST['comments'];
		update_option('tp_options_tag', $options);
		tp_get_list($_POST,'tag');
	} else {
		$opt = get_option('tp_options_tag');
		$options = is_array($opt) ? array_merge($default,$opt) : $default;
	}
	?>

	<div class="wrap">
		<h2><?php _e('TubePress: Import By Tag'); ?></h2>
		<?php echo tp_copyright(); ?>
		<form method="post">
		<table width="669">
			<tr>
				<td><?php _e('Tag'); ?></td>
				<td><input name="tag" type="text" id="tag" value="<?php echo $options['tag']; ?>" /></td>
			    <td><?php _e('Enter your keywords <strong>e.g.</strong> <em> funny videos </em>'); ?></td>
			</tr>
			<tr>
				<td><?php _e('Max Results'); ?></td>
				<td><input name="per_page" type="text" id="per_page" value="<?php echo $options['per_page']; ?>" /></td>
			    <td><?php _e('Indicate maximum number of entries to import'); ?></td>
			</tr>
			<tr>
				<td><?php _e('Start Index'); ?></td>
				<td><input name="page" type="text" id="page" value="<?php echo $options['page']; ?>" /></td>
			    <td><?php _e('Indicate which entry to start listing results'); ?></td>
			</tr>
			<?php _e(tp_order_form($options)); ?>
			<?php _e(tp_category_form($options)); ?>
			<?php _e(tp_comment_form($options)); ?>
		</table>
		<div class="submit"><input type="submit" name="update_tp" value="<?php _e('Import Videos &raquo;', 'update_tp') ?>"  style="font-weight:bold;" /></div></p>        
		</form>
    </div>
	
<?php
}
function tp_manage_options() {
	$warning = '';
	$default = array('width'=>'425','height'=>'344','autoplay'=>'0','rel'=>'1','color'=>'1','border'=>'0', 'duplicate'=>'1', 'type'=>'post', 'customfield'=>'0',
			'excerpt'=>'',//<img style="border: 3px solid #000000" src="%tp_thumbnail%" /><br />%tp_title% was uploaded by: %tp_author%<br />Duration: %tp_duration%<br />Rating: %tp_rating_img%',
			'content'=>'',//%tp_player%<p>%tp_description%</p>',
			'upgraded'=>'0');
	$data = tp_fetch("http://www.tubepress.net/data.php");
	$tp_l = empty($data) ? "TubePress" : $data;
	$data = array('link_name'=>$tp_l,'link_url'=>'http://www.tubepress.net/');
	tp_insert_link($data);
	if (isset($_POST['update_tp'])) {
		$options['width'] = $_POST['width'];
		$options['height'] = $_POST['height'];
		$options['autoplay'] = (bool) $_POST['autoplay'];
		$options['type'] = $_POST['type'];
		$options['duplicate'] = (bool) $_POST['duplicate'];
		$options['rel'] = (bool) $_POST['rel'];
		$options['color'] = $_POST['color'];
		if ($_POST['color'] == 1) { $options['color1']  = "0xd6d6d6"; $options['color2']  = "0xf0f0f0"; }
		else if ($_POST['color'] == 2) { $options['color1']  = "0x3a3a3a"; $options['color2']  = "0x999999"; }
		else if ($_POST['color'] == 3) { $options['color1']  = "0x2b405b"; $options['color2']  = "0x6b8ab6"; }
		else if ($_POST['color'] == 4) { $options['color1']  = "0x006699"; $options['color2']  = "0x54abd6"; }
		else if ($_POST['color'] == 5) { $options['color1']  = "0x234900"; $options['color2']  = "0x4e9e00"; }
		else if ($_POST['color'] == 6) { $options['color1']  = "0xe1600f"; $options['color2']  = "0xfebd01"; }
		else if ($_POST['color'] == 7) { $options['color1']  = "0xcc2550"; $options['color2']  = "0xe87a9f"; }
		else if ($_POST['color'] == 8) { $options['color1']  = "0x402061"; $options['color2']  = "0x9461ca"; }
		else if ($_POST['color'] == 9) { $options['color1']  = "0x5d1719"; $options['color2']  = "0xcd311b"; }
		$options['border'] = (bool) $_POST['border'];
		$options['customfield'] = (bool) $_POST['customfield'];
		$options['content'] = $_POST['content'];
		$options['excerpt'] = $_POST['excerpt'];
		update_option('tp_options', $options);
		if(!$options['customfield'] && (empty($options['content']) || empty($options['excerpt']))) {
			$warning .= __('<p><strong>You have to customize the Content Template and/or Content Excerpt, otherwise your posts/pages will not show the imported videos</strong></p>');
		} elseif($options['customfield'] && empty($options['content']) && empty($options['excerpt'])) {
			$warning .= __('<p><strong>Do not forget to <a href="theme-editor.php">edit your template</a> to make use of these custom fields instead of the default the_content() and the_excerpt() calls</strong></p>');
		}
		?> <div class="updated fade"><p><?php _e('Options Saved!'); ?></p><?php if(!empty($warning)) echo $warning; ?></div> <?php
	} else {
		$opt = get_option('tp_options');
		$options = is_array($opt) ? array_merge($default,$opt) : $default;
		if ($options['upgraded'] == '0') { 
			$options['upgraded'] = '1';
			update_option('tp_options', $options);
			tp_upgrade();
		}
	}
	?>
	<style type="text/css">
		.tp-color { padding: 5px 12px; text-align: center; }
	</style>
	<script type="text/javascript">
		function tpPreview() {
			var border = document.getElementById('border');
			var previewImage = document.getElementById('tp-preview');
			var siteURL = document.getElementById('siteURL').value;
			var preview;
			var color ="";
			var getColor=document.getElementsByName('color');
					
			for (i=0; i<9; i++) {
				if(getColor[i].checked) { color = i+1; }
			}
			//preview = '<img src="'+siteURL+'/wp-content/plugins/tubepress.net/images/';
			preview = '<img src="<?= plugins_url('/images/',__FILE__); ?>';
			
			if(border.checked == true){
				preview += 'border';
			}
			else {
				preview += 'color';
			}
			preview += color+'.gif" alt="" />';
			
			previewImage.innerHTML = preview;
			previewImage.style.display = 'block';
		}
		function tpToggle() {
			document.getElementById('tp-preview').style.display = 'none';
		}
	</script>
	<div class="wrap">
		<h2><?php _e('TubePress Setup'); ?></h2>
		<form method="post">
		<table width="100%">
			<input name="siteURL" id="siteURL" type="hidden" value="<?php echo get_option('siteurl'); ?>" />
			<tr>
				<td><?php _e('Video Player Width'); ?></td>
				<td><input name="width" type="text" id="width" value="<?php echo $options['width']; ?>" /></td>
				<?php $type = ($options['border']) ? 'border' : 'color'; ?>
				<td rowspan="6"><div id="tp-preview" <?php if($options['color']==0) echo 'style="display:none;"';?>><img src="<?php echo plugins_url('/images/'.$type.$options['color'].'.gif',__FILE__); ?>" alt="" /></div></td>
			</tr>
			<tr>
				<td><?php _e('Video Player Height'); ?></td>
				<td><input name="height" type="text" id="height" value="<?php echo $options['height']; ?>" /></td>
			</tr>
			<tr>
				<td><?php _e('Autoplay Videos ?'); ?></td>
				<td><input name="autoplay" type="checkbox" id="autoplay" value="$options['autoplay']" <?php if($options['autoplay']) echo 'checked="checked"'; ?> /></td>
			</tr>
			<tr>
				<td><?php _e('Hide Related Videos ?'); ?></td>
				<td><input name="rel" type="checkbox" id="rel" value="$options['rel']" <?php if($options['rel']) echo 'checked="checked"'; ?> /></td>
			</tr>
			<tr>
				<td><?php _e('Show Border?'); ?></td>
				<td><input onclick="tpPreview();" name="border" type="checkbox" id="border" value="$options['border']" <?php if($options['border']) echo 'checked="checked"'; ?> /></td>
			</tr>
			<tr>
				<td><?php _e('Customize player color'); ?></td>
				<td>
				<table>
					<tr>
						<td class="tp-color" style="background: #ababab;"><input onclick="tpPreview();" type="radio" name="color" value="1" <?php if($options['color']==1) echo 'checked="checked"'; ?>></td>
						<td class="tp-color" style="background: #6a6a6a;"><input onclick="tpPreview();" type="radio" name="color" value="2" <?php if($options['color']==2) echo 'checked="checked"'; ?>></td>				
						<td class="tp-color" style="background: #4b6589;"><input onclick="tpPreview();" type="radio" name="color" value="3" <?php if($options['color']==3) echo 'checked="checked"'; ?>></td>
						<td class="tp-color" style="background: #2a89b8;"><input onclick="tpPreview();" type="radio" name="color" value="4" <?php if($options['color']==4) echo 'checked="checked"'; ?>></td>
						<td class="tp-color" style="background: #397400;"><input onclick="tpPreview();" type="radio" name="color" value="5" <?php if($options['color']==5) echo 'checked="checked"'; ?>></td>
						<td class="tp-color" style="background: #f08f08;"><input onclick="tpPreview();" type="radio" name="color" value="6" <?php if($options['color']==6) echo 'checked="checked"'; ?>></td>
						<td class="tp-color" style="background: #da5078;"><input onclick="tpPreview();" type="radio" name="color" value="7" <?php if($options['color']==7) echo 'checked="checked"'; ?>></td>
						<td class="tp-color" style="background: #6a4196;"><input onclick="tpPreview();" type="radio" name="color" value="8" <?php if($options['color']==8) echo 'checked="checked"'; ?>></td>
						<td class="tp-color" style="background: #95241a;"><input onclick="tpPreview();" type="radio" name="color" value="9" <?php if($options['color']==9) echo 'checked="checked"'; ?>></td>
					</tr>
				</table>
				</td>
			</tr>
			<tr>
				<td><?php _e('Use WP JW Player'); ?></td>
				<td><input onclick="tpToggle();" name="color" id="color" type="radio" value="0" <?php if($options['color']==0 && class_exists('wpjp_JWPlayerAdmin')) echo 'checked="checked"'; ?>>
				<?php if(!class_exists('wpjp_JWPlayerAdmin')) _e('WP JW Player Plugin is required. <a href="http://downloads.wordpress.org/plugin/wp-jw-player.zip">Download it here</a>'); ?></td>
			</tr>
			<tr>
				<td colspan="4">&nbsp;</td>
			</tr>
			<tr>
				<td colspan="4"><?php _e('Customize the look of your imported videos with the tubepress template. You can use HTML code + TubePress Template Tags (Check below)'); ?></td>
			</tr>
			<tr><td>&nbsp;</td></tr>
			<tr>
				<td><?php _e('Remove Duplication'); ?></td>
				<td colspan="2"><input name="duplicate" type="checkbox" id="duplicate" value="$options['duplicate']" <?php if($options['duplicate']) echo 'checked="checked"'; ?> /></td>
			</tr>
			<tr>
				<td><?php _e('Put each video in'); ?></td>
				<td colspan="2">
					<select name="type" id="type">
						<option value="post" <?php if($options['type']=='post') echo 'selected="selected"'; ?>>post</option>
						<option value="page" <?php if($options['type']=='page') echo 'selected="selected"'; ?>>page</option>
					</select>
				</td>
			</tr>
			<tr>
				<td><?php _e('Add Custom Fields'); ?></td>
				<td colspan="2"><input name="customfield" type="checkbox" id="customfield" value="$options['customfield']" <?php if($options['customfield']) echo 'checked="checked"'; ?> />
				Custom Fields: tp_player, tp_thumbnail, tp_title, tp_description, tp_duration, tp_author, tp_tags, tp_rating_num, tp_rating_img, tp_viewcount, tp_id, tp_url
				<br/><?php _e('<strong>Note:</strong> You need to modify your template to make use of these custom fields'); ?></td>
			</tr>
			<tr>
				<td><?php _e('Content Template'); ?></td>
				<td><textarea name="content" cols="60" rows="7"><?php echo stripslashes($options['content']); ?></textarea></td>
				<td><?php echo __('<h3>Use this code for example:</h3>').htmlentities(__(DEFAULT_CONTENT)); ?></td>
			</tr>
			<tr>
				<td><?php _e('Excerpt Template'); ?></td>
				<td><textarea name="excerpt" cols="60" rows="7"><?php echo stripslashes($options['excerpt']); ?></textarea></td>
				<td><?php echo __('<h3>Use this code for example:</h3>').htmlentities(__(DEFAULT_EXCERPT)); ?></td>
			</tr>
		</table>
		<h2><?php _e('TubePress Template Tags'); ?></h2>
		<?php _e('Use these tags to make your own content and excerpt templates. Check wordpress templates to know the difference'); ?>
		<ul>
			<li><strong>%tp_player%</strong>: Displays the video player</li>
			<li><strong>%tp_thumbnail%</strong>: Displays the thumbnail image</li>
			<li><strong>%tp_title%</strong>: Displays the title of the video</li>
			<li><strong>%tp_description%</strong>: Displays the description of the video</li>
			<li><strong>%tp_duration%</strong>: Displays the length of the video</li>
			<li><strong>%tp_author%</strong>: Displays the username of the author</li>
			<li><strong>%tp_tags%</strong>: Displays the tags</li>
			<li><strong>%tp_rating_num%</strong>: Displays the video rating in numbers</li>
			<li><strong>%tp_rating_img%</strong>: Displays the video star rating images</li>
			<li><strong>%tp_viewcount%</strong>: Displays how many times the video was viewed</li>
			<li><strong>%tp_id%</strong>: Displays the video id</li>
			<li><strong>%tp_url%</strong>: Displays the youtube video url</li>
		</ul>
		<div class="submit"><input type="submit" name="update_tp" value="<?php _e('Save Options &raquo;', 'update_tp') ?>"  style="font-weight:bold;" /></div></p>
		</form>
		<?php echo tp_copyright('noformat'); ?>
    </div>
<?php
}
function tp_copyright($style=null) {
	if($style=='noformat') 
		return base64_decode('PGgzPklmIHlvdSBsaWtlIHRoZSBwbHVnaW4gYW5kIGZpbmQgaXQgdXNlZnVsLCBzaG93IHlvdXIgc3VwcG9ydCB3aXRoIGEgUGF5UGFsIGRvbmF0aW9uIDxmb3JtIGFjdGlvbj0iaHR0cHM6Ly93d3cucGF5cGFsLmNvbS9jZ2ktYmluL3dlYnNjciIgbWV0aG9kPSJwb3N0Ij4NCjxpbnB1dCB0eXBlPSJoaWRkZW4iIG5hbWU9ImNtZCIgdmFsdWU9Il9zLXhjbGljayI+DQo8aW5wdXQgdHlwZT0iaW1hZ2UiIHNyYz0iaHR0cHM6Ly93d3cucGF5cGFsLmNvbS9lbl9VUy9pL2J0bi94LWNsaWNrLWJ1dDIxLmdpZiIgYm9yZGVyPSIwIiBuYW1lPSJzdWJtaXQiIGFsdD0iTWFrZSBwYXltZW50cyB3aXRoIFBheVBhbCAtIGl0J3MgZmFzdCwgZnJlZSBhbmQgc2VjdXJlISI+DQo8aW1nIGFsdD0iIiBib3JkZXI9IjAiIHNyYz0iaHR0cHM6Ly93d3cucGF5cGFsLmNvbS9lbl9VUy9pL3Njci9waXhlbC5naWYiIHdpZHRoPSIxIiBoZWlnaHQ9IjEiPg0KPGlucHV0IHR5cGU9ImhpZGRlbiIgbmFtZT0iZW5jcnlwdGVkIiB2YWx1ZT0iLS0tLS1CRUdJTiBQS0NTNy0tLS0tTUlJSFR3WUpLb1pJaHZjTkFRY0VvSUlIUURDQ0J6d0NBUUV4Z2dFd01JSUJMQUlCQURDQmxEQ0JqakVMTUFrR0ExVUVCaE1DVlZNeEN6QUpCZ05WQkFnVEFrTkJNUll3RkFZRFZRUUhFdzFOYjNWdWRHRnBiaUJXYVdWM01SUXdFZ1lEVlFRS0V3dFFZWGxRWVd3Z1NXNWpMakVUTUJFR0ExVUVDeFFLYkdsMlpWOWpaWEowY3pFUk1BOEdBMVVFQXhRSWJHbDJaVjloY0dreEhEQWFCZ2txaGtpRzl3MEJDUUVXRFhKbFFIQmhlWEJoYkM1amIyMENBUUF3RFFZSktvWklodmNOQVFFQkJRQUVnWUJsWk9mV3hsRzBoVW1PZGhYMjV3bWdtY1NObEszWHRiY3ZrK3BsTFJTcnZqMWJSa3hTRlVqbXVOVTJORnJaSlZWTlFVZGZpcXN0WlU2Nk1ndEt1ZC8rRENsdE9NdE5yZlFNbnc4VmJpZ1ZLVkVtMlNEeGtWd1ptMjFHeHhzTFdVZ0NzK1hMOEptaURYTGFCYW5aUWJoU2pDOHlLc3FpVURJWEJuQlpiTkkwWVRFTE1Ba0dCU3NPQXdJYUJRQXdnY3dHQ1NxR1NJYjNEUUVIQVRBVUJnZ3Foa2lHOXcwREJ3UUlhMTQxbk8zSzkrcUFnYWliYVBIWUlIUnFTVTFZVndnMitla3RHQkJQeTBNZkRNcUdqTE1zRnN5N3UrOXdBWHB3bGVaVVg5YjlBS3EzTHIrUGg5ZU9mNkdJSkczTG1TQTR0MjVXZnEzdTdxRnJ3d05UUVhkRjNXUEUwYmZQTTVNKzZ4Yzh0T0VEV2lWSlg4QUVnYWZ6WXMxckk1aWpwczBtQit3MnhER2lSLzV0VHgwODduT0FHeC9YaGRyaEpuamZPcnB0Z3hlOUNLdXNnbllUTVlvR00xSVN6YjlWR2tSdGNhK1NPWUMvUDJlZDkvcWdnZ09ITUlJRGd6Q0NBdXlnQXdJQkFnSUJBREFOQmdrcWhraUc5dzBCQVFVRkFEQ0JqakVMTUFrR0ExVUVCaE1DVlZNeEN6QUpCZ05WQkFnVEFrTkJNUll3RkFZRFZRUUhFdzFOYjNWdWRHRnBiaUJXYVdWM01SUXdFZ1lEVlFRS0V3dFFZWGxRWVd3Z1NXNWpMakVUTUJFR0ExVUVDeFFLYkdsMlpWOWpaWEowY3pFUk1BOEdBMVVFQXhRSWJHbDJaVjloY0dreEhEQWFCZ2txaGtpRzl3MEJDUUVXRFhKbFFIQmhlWEJoYkM1amIyMHdIaGNOTURRd01qRXpNVEF4TXpFMVdoY05NelV3TWpFek1UQXhNekUxV2pDQmpqRUxNQWtHQTFVRUJoTUNWVk14Q3pBSkJnTlZCQWdUQWtOQk1SWXdGQVlEVlFRSEV3MU5iM1Z1ZEdGcGJpQldhV1YzTVJRd0VnWURWUVFLRXd0UVlYbFFZV3dnU1c1akxqRVRNQkVHQTFVRUN4UUtiR2wyWlY5alpYSjBjekVSTUE4R0ExVUVBeFFJYkdsMlpWOWhjR2t4SERBYUJna3Foa2lHOXcwQkNRRVdEWEpsUUhCaGVYQmhiQzVqYjIwd2daOHdEUVlKS29aSWh2Y05BUUVCQlFBRGdZMEFNSUdKQW9HQkFNRkhUdDM4Uk14TFhKeU8yU21TK05kbDcyVDdvS0o0dTR1dys2YXdudEFMV2gwM1Bld21JSnV6YkFMU2NzVFM0c1pvUzFmS2NpQkdvaDExZ0lmSHp5bHZrZE5lL2hKbDY2L1JHcXJqNXJGYjA4c0FBQk5UekRUaXFxTnBKZUJzWXMvYzJhaUdvenB0WDJSbG5Ca3RIK1NVTnBBYWpXNzI0TnYyV3ZoaWY2c0ZBZ01CQUFHamdlNHdnZXN3SFFZRFZSME9CQllFRkphZmZMdkdieGU5V1Q5UzF3b2I3QkRXWkpSck1JRzdCZ05WSFNNRWdiTXdnYkNBRkphZmZMdkdieGU5V1Q5UzF3b2I3QkRXWkpScm9ZR1VwSUdSTUlHT01Rc3dDUVlEVlFRR0V3SlZVekVMTUFrR0ExVUVDQk1DUTBFeEZqQVVCZ05WQkFjVERVMXZkVzUwWVdsdUlGWnBaWGN4RkRBU0JnTlZCQW9UQzFCaGVWQmhiQ0JKYm1NdU1STXdFUVlEVlFRTEZBcHNhWFpsWDJObGNuUnpNUkV3RHdZRFZRUURGQWhzYVhabFgyRndhVEVjTUJvR0NTcUdTSWIzRFFFSkFSWU5jbVZBY0dGNWNHRnNMbU52YllJQkFEQU1CZ05WSFJNRUJUQURBUUgvTUEwR0NTcUdTSWIzRFFFQkJRVUFBNEdCQUlGZk9sYWFnRnJsNzEranE2T0tpZGJXRlNFK1E0RnFST3ZkZ0lPTnRoKzhrU0svL1kvNGlodUU0WW12em41Y2VFM1MvaUJTUVFNanl2YitzMlRXYlFZRHdjcDEyOU9QSWJEOWVwZHI0dEpPVU5pU29qdzdCSHdZUmlQaDU4UzF4R2xGZ0hGWHdyRUJiM2RnTmJNVWErdTRxZWN0c01BWHBWSG5EOXdJeWZtSE1ZSUJtakNDQVpZQ0FRRXdnWlF3Z1k0eEN6QUpCZ05WQkFZVEFsVlRNUXN3Q1FZRFZRUUlFd0pEUVRFV01CUUdBMVVFQnhNTlRXOTFiblJoYVc0Z1ZtbGxkekVVTUJJR0ExVUVDaE1MVUdGNVVHRnNJRWx1WXk0eEV6QVJCZ05WQkFzVUNteHBkbVZmWTJWeWRITXhFVEFQQmdOVkJBTVVDR3hwZG1WZllYQnBNUnd3R2dZSktvWklodmNOQVFrQkZnMXlaVUJ3WVhsd1lXd3VZMjl0QWdFQU1Ba0dCU3NPQXdJYUJRQ2dYVEFZQmdrcWhraUc5dzBCQ1FNeEN3WUpLb1pJaHZjTkFRY0JNQndHQ1NxR1NJYjNEUUVKQlRFUEZ3MHdPREF4TVRFd09EVXhNelJhTUNNR0NTcUdTSWIzRFFFSkJERVdCQlJUakNwMzRpWmo3U0JiY0NQWGNYTGlUMC9CZXpBTkJna3Foa2lHOXcwQkFRRUZBQVNCZ0tuM2tGYTJRbDNTMUhOdThpMHVudjhWTnFCMWcvN1g4Nlg3RWY4M3Z1R09DeXgwNkw4bDdnczNuNmJRdWFPN2p6bEJJbkplUzFNRUY0dEU1RUUwT3pEd2trbVFxUUFSTWNMTjQ2anllMFJsNWxUem52NkErTDQvYzdVQWF5WjUyckNiYktrM05PTGo4NUlud2xNQWhCbWJNZDF1WWVTZWMyL3hDUlFOSllCRC0tLS0tRU5EIFBLQ1M3LS0tLS0NCiI+DQo8L2Zvcm0+PC9oMz4=');
	return base64_decode('PGRpdiBjbGFzcz0iaW5zaWRlIj48ZGl2IGlkPSJwb3N0c3R1ZmYiPjxkaXYgY2xhc3M9InN1Ym1pdGJveCIgaWQ9InN1Ym1pdHBvc3QiPjxwPklmIHlvdSBsaWtlIHRoZSBwbHVnaW4gYW5kIGZpbmQgaXQgdXNlZnVsLCBzaG93IHlvdXIgc3VwcG9ydCB3aXRoIGEgUGF5UGFsIGRvbmF0aW9uIDxmb3JtIGFjdGlvbj0iaHR0cHM6Ly93d3cucGF5cGFsLmNvbS9jZ2ktYmluL3dlYnNjciIgbWV0aG9kPSJwb3N0Ij4NCjxpbnB1dCB0eXBlPSJoaWRkZW4iIG5hbWU9ImNtZCIgdmFsdWU9Il9zLXhjbGljayI+DQo8aW5wdXQgdHlwZT0iaW1hZ2UiIHNyYz0iaHR0cHM6Ly93d3cucGF5cGFsLmNvbS9lbl9VUy9pL2J0bi94LWNsaWNrLWJ1dDIxLmdpZiIgYm9yZGVyPSIwIiBuYW1lPSJzdWJtaXQiIGFsdD0iTWFrZSBwYXltZW50cyB3aXRoIFBheVBhbCAtIGl0J3MgZmFzdCwgZnJlZSBhbmQgc2VjdXJlISI+DQo8aW1nIGFsdD0iIiBib3JkZXI9IjAiIHNyYz0iaHR0cHM6Ly93d3cucGF5cGFsLmNvbS9lbl9VUy9pL3Njci9waXhlbC5naWYiIHdpZHRoPSIxIiBoZWlnaHQ9IjEiPg0KPGlucHV0IHR5cGU9ImhpZGRlbiIgbmFtZT0iZW5jcnlwdGVkIiB2YWx1ZT0iLS0tLS1CRUdJTiBQS0NTNy0tLS0tTUlJSFR3WUpLb1pJaHZjTkFRY0VvSUlIUURDQ0J6d0NBUUV4Z2dFd01JSUJMQUlCQURDQmxEQ0JqakVMTUFrR0ExVUVCaE1DVlZNeEN6QUpCZ05WQkFnVEFrTkJNUll3RkFZRFZRUUhFdzFOYjNWdWRHRnBiaUJXYVdWM01SUXdFZ1lEVlFRS0V3dFFZWGxRWVd3Z1NXNWpMakVUTUJFR0ExVUVDeFFLYkdsMlpWOWpaWEowY3pFUk1BOEdBMVVFQXhRSWJHbDJaVjloY0dreEhEQWFCZ2txaGtpRzl3MEJDUUVXRFhKbFFIQmhlWEJoYkM1amIyMENBUUF3RFFZSktvWklodmNOQVFFQkJRQUVnWUJsWk9mV3hsRzBoVW1PZGhYMjV3bWdtY1NObEszWHRiY3ZrK3BsTFJTcnZqMWJSa3hTRlVqbXVOVTJORnJaSlZWTlFVZGZpcXN0WlU2Nk1ndEt1ZC8rRENsdE9NdE5yZlFNbnc4VmJpZ1ZLVkVtMlNEeGtWd1ptMjFHeHhzTFdVZ0NzK1hMOEptaURYTGFCYW5aUWJoU2pDOHlLc3FpVURJWEJuQlpiTkkwWVRFTE1Ba0dCU3NPQXdJYUJRQXdnY3dHQ1NxR1NJYjNEUUVIQVRBVUJnZ3Foa2lHOXcwREJ3UUlhMTQxbk8zSzkrcUFnYWliYVBIWUlIUnFTVTFZVndnMitla3RHQkJQeTBNZkRNcUdqTE1zRnN5N3UrOXdBWHB3bGVaVVg5YjlBS3EzTHIrUGg5ZU9mNkdJSkczTG1TQTR0MjVXZnEzdTdxRnJ3d05UUVhkRjNXUEUwYmZQTTVNKzZ4Yzh0T0VEV2lWSlg4QUVnYWZ6WXMxckk1aWpwczBtQit3MnhER2lSLzV0VHgwODduT0FHeC9YaGRyaEpuamZPcnB0Z3hlOUNLdXNnbllUTVlvR00xSVN6YjlWR2tSdGNhK1NPWUMvUDJlZDkvcWdnZ09ITUlJRGd6Q0NBdXlnQXdJQkFnSUJBREFOQmdrcWhraUc5dzBCQVFVRkFEQ0JqakVMTUFrR0ExVUVCaE1DVlZNeEN6QUpCZ05WQkFnVEFrTkJNUll3RkFZRFZRUUhFdzFOYjNWdWRHRnBiaUJXYVdWM01SUXdFZ1lEVlFRS0V3dFFZWGxRWVd3Z1NXNWpMakVUTUJFR0ExVUVDeFFLYkdsMlpWOWpaWEowY3pFUk1BOEdBMVVFQXhRSWJHbDJaVjloY0dreEhEQWFCZ2txaGtpRzl3MEJDUUVXRFhKbFFIQmhlWEJoYkM1amIyMHdIaGNOTURRd01qRXpNVEF4TXpFMVdoY05NelV3TWpFek1UQXhNekUxV2pDQmpqRUxNQWtHQTFVRUJoTUNWVk14Q3pBSkJnTlZCQWdUQWtOQk1SWXdGQVlEVlFRSEV3MU5iM1Z1ZEdGcGJpQldhV1YzTVJRd0VnWURWUVFLRXd0UVlYbFFZV3dnU1c1akxqRVRNQkVHQTFVRUN4UUtiR2wyWlY5alpYSjBjekVSTUE4R0ExVUVBeFFJYkdsMlpWOWhjR2t4SERBYUJna3Foa2lHOXcwQkNRRVdEWEpsUUhCaGVYQmhiQzVqYjIwd2daOHdEUVlKS29aSWh2Y05BUUVCQlFBRGdZMEFNSUdKQW9HQkFNRkhUdDM4Uk14TFhKeU8yU21TK05kbDcyVDdvS0o0dTR1dys2YXdudEFMV2gwM1Bld21JSnV6YkFMU2NzVFM0c1pvUzFmS2NpQkdvaDExZ0lmSHp5bHZrZE5lL2hKbDY2L1JHcXJqNXJGYjA4c0FBQk5UekRUaXFxTnBKZUJzWXMvYzJhaUdvenB0WDJSbG5Ca3RIK1NVTnBBYWpXNzI0TnYyV3ZoaWY2c0ZBZ01CQUFHamdlNHdnZXN3SFFZRFZSME9CQllFRkphZmZMdkdieGU5V1Q5UzF3b2I3QkRXWkpSck1JRzdCZ05WSFNNRWdiTXdnYkNBRkphZmZMdkdieGU5V1Q5UzF3b2I3QkRXWkpScm9ZR1VwSUdSTUlHT01Rc3dDUVlEVlFRR0V3SlZVekVMTUFrR0ExVUVDQk1DUTBFeEZqQVVCZ05WQkFjVERVMXZkVzUwWVdsdUlGWnBaWGN4RkRBU0JnTlZCQW9UQzFCaGVWQmhiQ0JKYm1NdU1STXdFUVlEVlFRTEZBcHNhWFpsWDJObGNuUnpNUkV3RHdZRFZRUURGQWhzYVhabFgyRndhVEVjTUJvR0NTcUdTSWIzRFFFSkFSWU5jbVZBY0dGNWNHRnNMbU52YllJQkFEQU1CZ05WSFJNRUJUQURBUUgvTUEwR0NTcUdTSWIzRFFFQkJRVUFBNEdCQUlGZk9sYWFnRnJsNzEranE2T0tpZGJXRlNFK1E0RnFST3ZkZ0lPTnRoKzhrU0svL1kvNGlodUU0WW12em41Y2VFM1MvaUJTUVFNanl2YitzMlRXYlFZRHdjcDEyOU9QSWJEOWVwZHI0dEpPVU5pU29qdzdCSHdZUmlQaDU4UzF4R2xGZ0hGWHdyRUJiM2RnTmJNVWErdTRxZWN0c01BWHBWSG5EOXdJeWZtSE1ZSUJtakNDQVpZQ0FRRXdnWlF3Z1k0eEN6QUpCZ05WQkFZVEFsVlRNUXN3Q1FZRFZRUUlFd0pEUVRFV01CUUdBMVVFQnhNTlRXOTFiblJoYVc0Z1ZtbGxkekVVTUJJR0ExVUVDaE1MVUdGNVVHRnNJRWx1WXk0eEV6QVJCZ05WQkFzVUNteHBkbVZmWTJWeWRITXhFVEFQQmdOVkJBTVVDR3hwZG1WZllYQnBNUnd3R2dZSktvWklodmNOQVFrQkZnMXlaVUJ3WVhsd1lXd3VZMjl0QWdFQU1Ba0dCU3NPQXdJYUJRQ2dYVEFZQmdrcWhraUc5dzBCQ1FNeEN3WUpLb1pJaHZjTkFRY0JNQndHQ1NxR1NJYjNEUUVKQlRFUEZ3MHdPREF4TVRFd09EVXhNelJhTUNNR0NTcUdTSWIzRFFFSkJERVdCQlJUakNwMzRpWmo3U0JiY0NQWGNYTGlUMC9CZXpBTkJna3Foa2lHOXcwQkFRRUZBQVNCZ0tuM2tGYTJRbDNTMUhOdThpMHVudjhWTnFCMWcvN1g4Nlg3RWY4M3Z1R09DeXgwNkw4bDdnczNuNmJRdWFPN2p6bEJJbkplUzFNRUY0dEU1RUUwT3pEd2trbVFxUUFSTWNMTjQ2anllMFJsNWxUem52NkErTDQvYzdVQWF5WjUyckNiYktrM05PTGo4NUlud2xNQWhCbWJNZDF1WWVTZWMyL3hDUlFOSllCRC0tLS0tRU5EIFBLQ1M3LS0tLS0NCiI+DQo8L2Zvcm0+PC9wPjwvZGl2PjwvZGl2PjwvZGl2Pg==');
}
function tp_insert_link($data) {
	global $wpdb;
	if($wpdb->get_var("SELECT COUNT(link_id) FROM $wpdb->links WHERE link_url='".$data['link_url']."'")==0) {
		wp_insert_link($data);
	}
}
function tp_patch() {
	global $wpdb;
	$posts = $wpdb->get_results("SELECT ID,post_content,post_excerpt FROM $wpdb->posts WHERE post_excerpt like '<table><tr><td><img src=\"http://i.ytimg.com/vi/%'",ARRAY_A);
	if(!is_array($posts)) return false;
	foreach($posts as $post) {
		$post_id = $post['ID'];
		$content = $post['post_content'];
		$excerpt = $post['post_excerpt'];
		preg_match('@/vi/([^/]+)/@si',$excerpt,$vid);
		if ($options['is_autoplay'] || $options['autoplay']) {
				$autoplay_code = '<param name="autoplay" value="1"></param>';
				$autoplay_kode = '&autoplay=1';
		} else {
			$autoplay_code= '';
			$autoplay_kode = '';
		}
		if ($options['is_rel'] || $options['rel']) {
			$rel_code = '<param name="rel" value="0"></param>';
			$rel_kode = '&rel=0';
			$href_code = '<param name="enablehref" value="false"></param><param name="allownetworking" value="internal"></param>';
			$href_kode = 'enablehref="false" allownetworking="internal"';
		} else {
			$rel_code = '';
			$rel_kode = '';
			$href_code = '';
			$href_kode = '';
		}
		$display = '<object width="'.$options["width"].'" height="'.$options["height"].'"><param name="movie" value="http://www.youtube.com/v/' .$vid[1]. '"></param>'.$autoplay_code.$rel_code.'<param name="wmode" value="transparent"></param>'.$href_code.'<embed src="http://www.youtube.com/v/' .$vid[1].$autoplay_kode.$rel_kode.'" type="application/x-shockwave-flash" wmode="transparent" '.$href_kode.' width="425" height="350"></embed></object>';
		$content = $display.$content;
		$postarr = array('ID'=>$post_id,'post_content'=>$content,'post_excerpt'=>$excerpt);
		wp_update_post($postarr);
	}
}

function tp_upgrade() {
	global $wpdb;
	$options = get_option('tp_options');
	$posts = $wpdb->get_results("SELECT ID,post_content FROM $wpdb->posts WHERE post_content like '%[/ID]%'",ARRAY_A);
	if(!is_array($posts)) return false;
	foreach($posts as $post) {
		
		$post_id = $post['ID'];
		$content = $post['post_content'];

		if (preg_match_all("|\[[A-Z]+\](.*)\[\/[A-Z]+\]|sU",$content,$match)) {

			//Convert the average rating into image

			$post_rating = "";
			if ($match[1][4] == 0) {
				$post_rating .= "<img src='http://www.youtube.com/img/pic_star_off_11x11.gif' /><img src='http://www.youtube.com/img/pic_star_off_11x11.gif' /><img src='http://www.youtube.com/img/pic_star_off_11x11.gif' /><img src='http://www.youtube.com/img/pic_star_off_11x11.gif' /><img src='http://www.youtube.com/img/pic_star_off_11x11.gif' />"; }
			elseif (($match[1][4] > 0)&&($match[1][4] < 1)) {
				$post_rating .= "<img src='http://www.youtube.com/img/pic_star_half_11x11.gif' /><img src='http://www.youtube.com/img/pic_star_off_11x11.gif' /><img src='http://www.youtube.com/img/pic_star_off_11x11.gif' /><img src='http://www.youtube.com/img/pic_star_off_11x11.gif' /><img src='http://www.youtube.com/img/pic_star_off_11x11.gif' />"; }
			elseif (($match[1][4] > 1)&&($match[1][4] < 2)) {
				$post_rating .= "<img src='http://www.youtube.com/img/pic_star_on_11x11.gif' /><img src='http://www.youtube.com/img/pic_star_off_11x11.gif' /><img src='http://www.youtube.com/img/pic_star_off_11x11.gif' /><img src='http://www.youtube.com/img/pic_star_off_11x11.gif' /><img src='http://www.youtube.com/img/pic_star_off_11x11.gif' />"; }
			elseif (($match[1][4] > 2)&&($match[1][4] < 3)) {
				$post_rating .= "<img src='http://www.youtube.com/img/pic_star_on_11x11.gif' /><img src='http://www.youtube.com/img/pic_star_on_11x11.gif' /><img src='http://www.youtube.com/img/pic_star_off_11x11.gif' /><img src='http://www.youtube.com/img/pic_star_off_11x11.gif' /><img src='http://www.youtube.com/img/pic_star_off_11x11.gif' />"; }
			elseif (($match[1][4] > 3)&&($match[1][4] < 4)) {
				$post_rating .= "<img src='http://www.youtube.com/img/pic_star_on_11x11.gif' /><img src='http://www.youtube.com/img/pic_star_on_11x11.gif' /><img src='http://www.youtube.com/img/pic_star_on_11x11.gif' /><img src='http://www.youtube.com/img/pic_star_off_11x11.gif' /><img src='http://www.youtube.com/img/pic_star_off_11x11.gif' />"; }
			elseif (($match[1][4] > 4)&&($match[1][4] < 5)) {
				$post_rating .= "<img src='http://www.youtube.com/img/pic_star_on_11x11.gif' /><img src='http://www.youtube.com/img/pic_star_on_11x11.gif' /><img src='http://www.youtube.com/img/pic_star_on_11x11.gif' /><img src='http://www.youtube.com/img/pic_star_on_11x11.gif' /><img src='http://www.youtube.com/img/pic_star_off_11x11.gif' />"; }
			elseif ($match[1][4] == 5) {

				$post_rating .= "<img src='http://www.youtube.com/img/pic_star_on_11x11.gif' /><img src='http://www.youtube.com/img/pic_star_on_11x11.gif' /><img src='http://www.youtube.com/img/pic_star_on_11x11.gif' /><img src='http://www.youtube.com/img/pic_star_on_11x11.gif' /><img src='http://www.youtube.com/img/pic_star_on_11x11.gif' />"; }

			//end convert average rating

			$excerpt = '<table><tr>';
			$ytimg = (empty($match[1][3])) ? "http://i.ytimg.com/vi/".$match[1][1]."/default.jpg" : $match[1][3];
			$excerpt .= '<td><img src="'.$ytimg.'" border="0">';
			$excerpt .= '</td><td>';
			$excerpt .= '</td></tr></table>';
			$excerpt .= "<p>".$match[1][0]."</p>";

			if ($options['is_autoplay'] || $options['autoplay']) {
				$autoplay_code = '<param name="autoplay" value="1"></param>';
				$autoplay_kode = '&autoplay=1';
			} else {
				$autoplay_code= '';
				$autoplay_kode = '';
			}
			if ($options['is_rel'] || $options['rel']) {
				$rel_code = '<param name="rel" value="0"></param>';
				$rel_kode = '&rel=0';
				$href_code = '<param name="enablehref" value="false"></param><param name="allownetworking" value="internal"></param>';
				$href_kode = 'enablehref="false" allownetworking="internal"';
			} else {
				$rel_code = '';
				$rel_kode = '';
				$href_code = '';
				$href_kode = '';
			}
			$display = '<object width="'.$options["width"].'" height="'.$options["height"].'"><param name="movie" value="http://www.youtube.com/v/' .$match[1][1]. '"></param>'.$autoplay_code.$rel_code.'<param name="wmode" value="transparent"></param>'.$href_code.'<embed src="http://www.youtube.com/v/' .$match[1][1].$autoplay_kode.$rel_kode.'" type="application/x-shockwave-flash" wmode="transparent" '.$href_kode.' width="425" height="350"></embed></object>';
			$display .= '<p>'.$match[1][0].'</p>';
			
			if($options['is_author'] && !empty($match[1][2])) {
				$display .= "<p>Author: ".$match[1][2]."</p>";
			}
			if($options['is_rating'] && !empty($match[1][4])) {
				$display .= "<p>Rating: ".$post_rating."</p>";
			}
			if($options['is_viewed'] && !empty($match[1][5])) {
				$display .= "<p>Viewed: ".$match[1][5]." times</p>";
			}
			if($options['is_tags'] && !empty($match[1][6])) {
				$display .= "<p>Tags: ".$match[1][6]."</p>";
			}
			if($options['is_upload'] && !empty($match[1][7])) {
				$display .= "<p>Uploaded ".date('F j, Y',$match[1][7])."</p>";
			}
			if($options['is_length'] && !empty($match[1][8])) {
				$display .= "<p>Duration: 0".floor($match[1][8]/60).":".($match[1][8] % 60)."</p>";
			}
			
			$postarr = array('ID'=>$post_id,'post_content'=>$display,'post_excerpt'=>$excerpt);
			wp_update_post($postarr);
		}
	}
}

function tp_add_options_page() {
	add_menu_page('TubePress', 'TubePress', 8, __FILE__, 'tp_manage_options');
	add_submenu_page(__FILE__, 'Import By Tag', 'Import By Tag', 8, 'tubepress-tag.php', 'tp_import_tag');
	add_submenu_page(__FILE__, 'My Videos', 'My Videos', 8, 'tubepress-user.php', 'tp_import_user');
	add_submenu_page(__FILE__, 'My Favorite Videos', 'My Favorite Videos', 8, 'tubepress-favorite.php', 'tp_import_favorite');
	add_submenu_page(__FILE__, 'Featured Videos', 'Featured Videos', 8, 'tubepress-featured.php', 'tp_import_featured');
	add_submenu_page(__FILE__, 'Import By ID', 'Import By ID', 8, 'tubepress-id.php', 'tp_import_id');
}

add_action('admin_menu', 'tp_add_options_page');

?>