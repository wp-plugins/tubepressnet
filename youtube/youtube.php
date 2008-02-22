<?php
/*
Plugin Name: TubePress
Plugin URI: http://www.tubepress.net/
Description: Wordpress plugin to import Youtube Videos into Wordpress blog
Author: Mario Mansour
Version: 2.6
Author URI: http://www.mariomansour.com/
*/ 

// General Messages
define('TP_VERSION', '2.6');
define('TP_LANG', 'en');
define('TP_FOOTER_MSG', 'TubePress '.TP_VERSION.'<br><a href="http://www.tubepress.net/">Wordpress Plugins</a>');

include('lang/en.inc.php');
include('lang/'.TP_LANG.'.inc.php');

function getVideoByID($id) {
	
	require_once ('class.youtube.php');

	global $wpdb;

	$cat_ID = $wpdb->get_var("SELECT cat_ID FROM $wpdb->categories WHERE cat_name='".$wpdb->escape(__('Special Videos'))."'");

	if ($cat_ID == NULL) {
		$wpdb->query("INSERT INTO $wpdb->categories (cat_name, category_nicename, category_count, category_description) VALUES ('".$wpdb->escape(__('Special Videos'))."', '".sanitize_title(__('Special Videos'))."', '0', '')");
		$cat_ID = (int) $wpdb->insert_id;
	}

	echo '<div class="wrap">';

	//skip the video if it already exists in the database
	$res = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_content like '%".$id."%'");
	if ($res == NULL) {
		video_info($id, $cat_ID, $options);
	} else {
		 _e('<h2>'.TP_IMPORT_EXIST_MSG.'</h2>');
	}

	//Update category_count
	$wpdb->query("UPDATE $wpdb->categories SET category_count = category_count + 1 WHERE cat_ID = $cat_ID");
	echo '</div>';
}

function getFeaturedVideos() {

	require_once ('class.youtube.php');

	$options = get_option('tp_options_feat');
	$gen_options = get_option('tp_options');

	$youtube = new youtubeService($gen_options['devid']);

	$videos = $youtube->videos_list_featured();

	global $wpdb;

	$cat_ID = $wpdb->get_var("SELECT cat_ID FROM $wpdb->categories WHERE cat_name='".$wpdb->escape(__('Featured Videos'))."'");

	if ($cat_ID == NULL) {
		$wpdb->query("INSERT INTO $wpdb->categories (cat_name, category_nicename, category_count, category_description) VALUES ('".$wpdb->escape(__('Featured Videos'))."', '".sanitize_title(__('Featured Videos'))."', '0', '')");
		$cat_ID = (int) $wpdb->insert_id;
	}

	echo '<div class="wrap">';
	_e('<h2>'.TP_IMPORT_LIST_MSG.'</h2>');
	echo '<div align="center">';
	foreach ($videos->video_list->video as $video) {

		//skip the video if it already exists in the database
		$res = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_content like '%".$video->id."%'");
		if ($res == NULL) {
			echo "<img alt='$video->title' width='130' height='97' src='$video->thumbnail_url' />  ";
			video_info($video->id, $cat_ID, $options);
		} 
	}
	//Update category_count
	$wpdb->query("UPDATE $wpdb->categories SET category_count = category_count + ".sizeof($videos->video_list)." WHERE cat_ID = $cat_ID");
	echo '</div></div>';
}

function getVideoByFavorite() {

	require_once ('class.youtube.php');

	$options = get_option('tp_options_fav');
	$gen_options = get_option('tp_options');

	$youtube = new youtubeService($gen_options['devid']);

	$videos = $youtube->users_list_favorite_videos($options['user'], $options['page'], $options['per_page']);

	global $wpdb;

	$cat_ID = $wpdb->get_var("SELECT cat_ID FROM $wpdb->categories WHERE cat_name='".$wpdb->escape(__($options['user']))."'");

	if ($cat_ID == NULL) {
		$wpdb->query("INSERT INTO $wpdb->categories (cat_name, category_nicename, category_count, category_description) VALUES ('".$wpdb->escape(__($options['user']))."', '".sanitize_title(__($options['user']))."', '0', '')");
		$cat_ID = (int) $wpdb->insert_id;
	}

	echo '<div class="wrap">';
	_e('<h2>'.TP_IMPORT_LIST_MSG.'</h2>');
	echo '<div align="center">';

	foreach ($videos->video_list->video as $video) {

		//skip the video if it already exists in the database
		$res = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_content like '%".$video->id."%'");
		if ($res == NULL) {
			echo "<img alt='$video->title' width='130' height='97' src='$video->thumbnail_url' />  ";
			video_info($video->id, $cat_ID, $options);
		} 
	}
	//Update category_count
	$wpdb->query("UPDATE $wpdb->categories SET category_count = category_count + ".sizeof($videos->video_list)." WHERE cat_ID = $cat_ID");
	echo '</div></div>';
}

function getVideoByUser() {

	require_once ('class.youtube.php');
	$options = get_option('tp_options_user');
	$gen_options = get_option('tp_options');

	$youtube = new youtubeService($gen_options['devid']);

	$videos = $youtube->videos_list_by_user($options['user'], $options['page'], $options['per_page']);

	global $wpdb;

	$cat_ID = $wpdb->get_var("SELECT cat_ID FROM $wpdb->categories WHERE cat_name='".$wpdb->escape(__($options['user']))."'");

	if ($cat_ID == NULL) {
		$wpdb->query("INSERT INTO $wpdb->categories (cat_name, category_nicename, category_count, category_description) VALUES ('".$wpdb->escape(__($options['user']))."', '".sanitize_title(__($options['user']))."', '0', '')");
		$cat_ID = (int) $wpdb->insert_id;
	}

	echo '<div class="wrap">';
	_e('<h2>'.TP_IMPORT_LIST_MSG.'</h2>');
	echo '<div align="center">';

	foreach ($videos->video_list->video as $video) {

		//skip the video if it already exists in the database
		$res = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_content like '%".$video->id."%'");
		if ($res == NULL) {
			echo "<img alt='$video->title' width='130' height='97' src='$video->thumbnail_url' />  ";
			video_info($video->id, $cat_ID, $options);
		}
	}
	//Update category_count
	$wpdb->query("UPDATE $wpdb->categories SET category_count = category_count + ".sizeof($videos->video_list)." WHERE cat_ID = $cat_ID");
	echo '</div></div>';
}

function getVideoByTag() {

	require_once ('class.youtube.php');
	$options = get_option('tp_options_tag');
	$gen_options = get_option('tp_options');

	$youtube = new youtubeService($gen_options['devid']);

	$tags = str_replace(" ","+",$options['tag']);
	$videos = $youtube->videos_list_by_tag($tags, $options['page'], $options['per_page']);

	global $wpdb;

	$cat_ID = $wpdb->get_var("SELECT cat_ID FROM $wpdb->categories WHERE cat_name='".$wpdb->escape(__($options['tag']))."'");

	if ($cat_ID == NULL) {
		$wpdb->query("INSERT INTO $wpdb->categories (cat_name, category_nicename, category_count, category_description) VALUES ('".$wpdb->escape(__($options['tag']))."', '".sanitize_title(__($options['tag']))."', '0', '')");
		$cat_ID = (int) $wpdb->insert_id;
	}

	echo '<div class="wrap">';
	_e('<h2>'.TP_IMPORT_LIST_MSG.'</h2>');
	echo '<div align="center">';

	foreach ($videos->video_list->video as $video) {

		//skip the video if it already exists in the database
		$res = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_content like '%".$video->id."%'");
		if ($res == NULL) {
			echo "<img alt='$video->title' width='130' height='97' src='$video->thumbnail_url' />  ";
			video_info($video->id, $cat_ID, $options);
		} 
	}
	//Update category_count
	$wpdb->query("UPDATE $wpdb->categories SET category_count = category_count + ".sizeof($videos->video_list)." WHERE cat_ID = $cat_ID");
	echo '</div></div>';
}

function video_info($videoid, $catid, $options) {

	require_once ('class.youtube.php');
	//global $tp_options;
	$gen_options = get_option('tp_options');

	$youtube = new youtubeService($gen_options['devid']);

	$video = $youtube->videos_get_details($videoid);

	$post_content = "[DESC]".$video->video_details->description."[/DESC]\n\n";
	$post_content .= "[ID]".$videoid."[/ID]\n";
	$post_content .= "[AUTHOR]".$video->video_details->author."[/AUTHOR]\n";
	$post_content .= "[IMG]".$video->video_details->thumbnail_url."[/IMG]\n";
	$post_content .= "[RATING]".$video->video_details->rating_avg."[/RATING]\n";
	$post_content .= "[VIEW]".$video->video_details->view_count."[/VIEW]\n";
	$post_content .= "[TAGS]".$video->video_details->tags."[/TAGS]\n";
	$post_content .= "[UPLOADTIME]".$video->video_details->upload_time."[/UPLOADTIME]\n";
	$post_content .= "[LENGTH]".$video->video_details->length_seconds."[/LENGTH]\n";

	//write to database
	global $wpdb;
	$post_title = $video->video_details->title;
	$post_name = sanitize_title($post_title);
	$now = date('Y-m-d H:i:s');
	$now_gmt = gmdate('Y-m-d H:i:s');
	$wpdb->query("INSERT INTO $wpdb->posts (post_author, post_date, post_date_gmt, post_content, post_excerpt, post_title, post_category, post_name, post_modified, post_modified_gmt, comment_count, to_ping, pinged, post_content_filtered) VALUES ('1', '$now', '$now_gmt', '".$wpdb->escape(__($post_content))."', '', '".$wpdb->escape(__($post_title))."', '$catid', '".$wpdb->escape(__($post_name))."', '$now', '$now_gmt', '0', '', '', '')");
	$post_ID = (int) $wpdb->insert_id;

	$wpdb->query( "INSERT INTO $wpdb->post2cat (`post_id`, `category_id`) VALUES ($post_ID, $catid)" );

	//insert all tags as categories
	if ($options['is_cat']) {
		$tag_all = explode(" ", $video->video_details->tags);
		foreach ($tag_all as $cat_tag) {
			$cid = $wpdb->get_var("SELECT cat_ID FROM $wpdb->categories WHERE cat_name='".$wpdb->escape(__($cat_tag))."'");

			if ($cid == NULL) {
				$wpdb->query("INSERT INTO $wpdb->categories (cat_name, category_nicename, category_count, category_description) VALUES ('".$wpdb->escape(__($cat_tag))."', '".sanitize_title(__($cat_tag))."', '0', '')");
			$cid = (int) $wpdb->insert_id;
			$wpdb->query( "INSERT INTO $wpdb->post2cat (`post_id`, `category_id`) VALUES ($post_ID, $cid)" );
			}
		}
		
	}

	//import comments if selected
	if ($options['comments'] == 'Yes') {
		if ($video->video_details->comment_list->comment) {
			foreach ($video->video_details->comment_list->comment as $c) {
				$wpdb->query("INSERT INTO $wpdb->comments (comment_post_ID, comment_author, comment_author_email, comment_author_url, comment_date, comment_date_gmt, comment_content) VALUES ('$post_ID', '".$wpdb->escape(__($c->author))."', '', '".get_settings('siteurl')."', '".date('Y-m-d H:i:s',$c->time)."', '".gmdate('Y-m-d H:i:s',$c->time)."', '".$wpdb->escape(__($c->text))."')");
			} 
		} 
        //update comment count in post
        $wpdb->query("UPDATE $wpdb->posts SET comment_count = comment_count + ".sizeof($video->video_details->comment_list->comment)." WHERE ID=$post_ID");
	}

}

function tp_import_id() {

	if (isset($_POST['update_tp'])) {
		$options['id'] = $_POST['id'];
		$options['is_cat'] = (bool) $_POST['is_cat'];
		$options['comments'] = $_POST['comments'];
		update_option('tp_options_id', $options);	
		?> <div class="updated"><p><?php _e(TP_SUCCESS_MSG); ?></p></div> <?php
		getVideoByID($_POST['id']);
	} else {
		$options = get_option('tp_options_id');
		update_option('tp_options_id', $options);
	}
	?>

	<div class="wrap">
		<h2><?php _e(TP_ID_TITLE); ?></h2>
		<form name="id" method="post">
		<fieldset class="options">
		<table width="669">
			<tr>
				<td>Video ID:</td>
				<td><input name="id" type="text" id="id" value="<?php $options['id'] ?>" /></td>
				<td>http://youtube.com/watch?v=<strong>QGQMyN75LFQ</strong></td>
			</tr>
			<tr>
				<td><?php _e(TP_CAT_MSG); ?></td>
				<td><input name="is_cat" type="checkbox" id="is_cat" value="$options['is_cat']" <?php if($options['is_cat']) echo 'checked="checked"'; ?> /></td>
			    <td><?php _e(TP_CAT_DESC); ?></td>
			</tr>
			<tr>
				<td><?php _e(TP_COMMENTS_MSG); ?></td>
			    <td>
			    <select name="comments" id="comments">
				<option <?php if ($options['comments'] == 'No') { echo 'selected="selected"'; } ?>>No</option>
				<option <?php if ($options['comments'] == 'Yes') { echo 'selected="selected"'; } ?>>Yes</option>
		        </select>
			    </td>
			    <td><?php _e(TP_COMMENTS_DESC); ?></td>
			</tr>
		</table>
		</fieldset>

		<p><?php _e(TP_FOOTER_MSG); ?><br />
		<div class="submit"><input type="submit" name="update_tp" value="<?php _e(TP_ID_BTN_MSG, 'update_tp') ?>"  style="font-weight:bold;" /></div></p>
        
		</form>       
		
    </div>
	
<?php
}

function tp_import_featured() {

	if (isset($_POST['update_tp'])) {
		$options['is_cat'] = (bool) $_POST['is_cat'];
		$options['comments'] = $_POST['comments'];
		update_option('tp_options_feat', $options);	
		?> <div class="updated"><p><?php _e(TP_SUCCESS_MSG); ?></p></div> <?php
		getFeaturedVideos();
	} else {
		$options = get_option('tp_options_feat');
		update_option('tp_options_feat', $options);
	}
	?>

	<div class="wrap">
		<h2><?php _e(TP_FEAT_TITLE); ?></h2>
		<form name="feat" method="post">
		<fieldset class="options">
		<table width="669">
			<tr>
				<td><?php _e(TP_CAT_MSG); ?></td>
				<td><input name="is_cat" type="checkbox" id="is_cat" value="$options['is_cat']" <?php if($options['is_cat']) echo 'checked="checked"'; ?> /></td>
			    <td><?php _e(TP_CAT_DESC); ?></td>
			</tr>
			<tr>
				<td><?php _e(TP_COMMENTS_MSG); ?></td>
			    <td>
			    <select name="comments" id="comments">
				<option <?php if ($options['comments'] == 'No') { echo 'selected="selected"'; } ?>>No</option>
				<option <?php if ($options['comments'] == 'Yes') { echo 'selected="selected"'; } ?>>Yes</option>
		        </select>
			    </td>
			    <td><?php _e(TP_COMMENTS_DESC); ?></td>
			</tr>
		</table>
		</fieldset>

		<p><?php _e(TP_FOOTER_MSG); ?><br />
		<div class="submit"><input type="submit" name="update_tp" value="<?php _e(TP_FEAT_BTN_MSG, 'update_tp') ?>"  style="font-weight:bold;" /></div></p>
        
		</form>       
		
    </div>
	
<?php
}

function tp_import_favorite() {

	if (isset($_POST['update_tp'])) {
		$options['user'] = $_POST['user'];
		$options['is_cat'] = (bool) $_POST['is_cat'];
		$options['comments'] = $_POST['comments'];
		update_option('tp_options_fav', $options);
		?> <div class="updated"><p><?php _e(TP_SUCCESS_MSG); ?></p></div> <?php
		getVideoByFavorite();
	} else {
		$options = get_option('tp_options_fav');
		if (empty($options['user'])) { $options['user'] = 'tubepressnet'; }
		update_option('tp_options_fav', $options);
	}
	?>

	<div class="wrap">
		<h2><?php _e(TP_FAV_TITLE); ?></h2>
		<form name="user" method="post">
		<fieldset class="options">
		<table width="669">
			<tr>
				<td><?php _e(TP_USERNAME_MSG); ?></td>
				<td><input name="user" type="text" id="user" value="<?php echo $options['user']; ?>" /></td>
			    <td><a rel="nofollow" href="http://www.youtube.com/signup"><?php _e(TP_USERNAME_DESC); ?></a></td>
			</tr>
			<tr>
				<td><?php _e(TP_CAT_MSG); ?></td>
				<td><input name="is_cat" type="checkbox" id="is_cat" value="$options['is_cat']" <?php if($options['is_cat']) echo 'checked="checked"'; ?> /></td>
			    <td><?php _e(TP_CAT_DESC); ?></td>
			</tr>
			<tr>
				<td><?php _e(TP_COMMENTS_MSG); ?></td>
			    <td>
			    <select name="comments" id="comments">
				<option <?php if ($options['comments'] == 'No') { echo 'selected="selected"'; } ?>>No</option>
				<option <?php if ($options['comments'] == 'Yes') { echo 'selected="selected"'; } ?>>Yes</option>
		        </select>
			    </td>
			    <td><?php _e(TP_COMMENTS_DESC); ?></td>
			</tr>
		</table>
		</fieldset>

		<p><?php _e(TP_FOOTER_MSG); ?><br />
		<div class="submit"><input type="submit" name="update_tp" value="<?php _e(TP_FAV_BTN_MSG, 'update_tp') ?>"  style="font-weight:bold;" /></div></p>
        
		</form>       
		
    </div>
	
<?php
}


function tp_import_user() {

	if (isset($_POST['update_tp'])) {
		$options['user'] = $_POST['user'];
		$options['page'] = $_POST['page'];
		$options['per_page'] = $_POST['per_page'];
		$options['is_cat'] = (bool) $_POST['is_cat'];
		$options['comments'] = $_POST['comments'];
		update_option('tp_options_user', $options);
		?> <div class="updated"><p><?php _e(TP_SUCCESS_MSG); ?></p></div> <?php
		getVideoByUser();
	} else {
		$options = get_option('tp_options_user');
		if (empty($options['user'])) { $options['user'] = 'tubepressnet'; }
		if (empty($options['page'])) { $options['page'] = '1'; }
		if (empty($options['per_page'])) { $options['per_page'] = '10'; }
		update_option('tp_options_user', $options);
	}
	?>

	<div class="wrap">
		<h2><?php _e(TP_USER_TITLE); ?></h2>
		<form name="user" method="post">
		<fieldset class="options">
		<table width="669">
			<tr>
				<td><?php _e(TP_USERNAME_MSG); ?></td>
				<td><input name="user" type="text" id="user" value="<?php echo $options['user']; ?>" /></td>
			    <td><a rel="nofollow" href="http://www.youtube.com/signup"><?php _e(TP_USERNAME_DESC); ?></a></td>
			</tr>
			<tr>
				<td><?php _e(TP_PER_PAGE_MSG); ?></td>
				<td><input name="per_page" type="text" id="per_page" value="<?php echo $options['per_page']; ?>" /></td>
			    <td><?php _e(TP_PER_PAGE_DESC); ?></td>
			</tr>
			<tr>
				<td><?php _e(TP_PAGE_MSG); ?></td>
				<td><input name="page" type="text" id="page" value="<?php echo $options['page']; ?>" /></td>
			    <td><?php _e(TP_PAGE_DESC); ?></td>
			</tr>
			<tr>
				<td><?php _e(TP_CAT_MSG); ?></td>
				<td><input name="is_cat" type="checkbox" id="is_cat" value="$options['is_cat']" <?php if($options['is_cat']) echo 'checked="checked"'; ?> /></td>
			    <td><?php _e(TP_CAT_DESC); ?></td>
			</tr>
			<tr>
				<td><?php _e(TP_COMMENTS_MSG); ?></td>
			    <td>
			    <select name="comments" id="comments">
				<option <?php if ($options['comments'] == 'No') { echo 'selected="selected"'; } ?>>No</option>
				<option <?php if ($options['comments'] == 'Yes') { echo 'selected="selected"'; } ?>>Yes</option>
		        </select>
			    </td>
			    <td><?php _e(TP_COMMENTS_DESC); ?></td>
			</tr>
		</table>
		</fieldset>

		<p><?php _e(TP_FOOTER_MSG); ?><br />
		<div class="submit"><input type="submit" name="update_tp" value="<?php _e(TP_USER_BTN_MSG, 'update_tp') ?>"  style="font-weight:bold;" /></div></p>
        
		</form>       
		
    </div>
	
<?php
}

function tp_import_tag() {

	if (isset($_POST['update_tp'])) {
		$options['tag'] = $_POST['tag'];
		$options['page'] = $_POST['page'];
		$options['per_page'] = $_POST['per_page'];
		$options['is_cat'] = (bool) $_POST['is_cat'];
		$options['comments'] = $_POST['comments'];
		update_option('tp_options_tag', $options);
		?> <div class="updated"><p><?php _e(TP_SUCCESS_MSG); ?></p></div> <?php
		getVideoByTag();
	} else {
		$options = get_option('tp_options_tag');
		if (empty($options['tag'])) { $options['tag'] = 'wordpress'; }
		if (empty($options['page'])) { $options['page'] = '1'; }
		if (empty($options['per_page'])) { $options['per_page'] = '20'; }
		update_option('tp_options_tag', $options);
	}
	?>

	<div class="wrap">
		<h2><?php _e(TP_TAG_TITLE); ?></h2>
		<form method="post">
		<fieldset class="options">
		<table width="669">
			<tr>
				<td><?php _e(TP_TAG_MSG); ?></td>
				<td><input name="tag" type="text" id="tag" value="<?php echo $options['tag']; ?>" /></td>
			    <td><?php _e(TP_TAG_DESC); ?></td>
			</tr>
			<tr>
				<td><?php _e(TP_PER_PAGE_MSG); ?></td>
				<td><input name="per_page" type="text" id="per_page" value="<?php echo $options['per_page']; ?>" /></td>
			    <td><?php _e(TP_PER_PAGE_DESC); ?></td>
			</tr>
			<tr>
				<td><?php _e(TP_PAGE_MSG); ?></td>
				<td><input name="page" type="text" id="page" value="<?php echo $options['page']; ?>" /></td>
			    <td><?php _e(TP_PAGE_DESC); ?></td>
			</tr>
			<tr>
				<td><?php _e(TP_CAT_MSG); ?></td>
				<td><input name="is_cat" type="checkbox" id="is_cat" value="$options['is_cat']" <?php if($options['is_cat']) echo 'checked="checked"'; ?> /></td>
			    <td><?php _e(TP_CAT_DESC); ?></td>
			</tr>
			<tr>
				<td><?php _e(TP_COMMENTS_MSG); ?></td>
			    <td>
			    <select name="comments" id="comments">
				<option <?php if ($options['comments'] == 'No') { echo 'selected="selected"'; } ?>>No</option>
				<option <?php if ($options['comments'] == 'Yes') { echo 'selected="selected"'; } ?>>Yes</option>
		        </select>
			    </td>
			    <td><?php _e(TP_COMMENTS_DESC); ?></td>
			</tr>
		</table>
		</fieldset>

		<p><?php _e(TP_FOOTER_MSG); ?><br />
		<div class="submit"><input type="submit" name="update_tp" value="<?php _e(TP_TAG_BTN_MSG, 'update_tp') ?>"  style="font-weight:bold;" /></div></p>
        
		</form>       
		
    </div>
	
<?php
}

function tp_activate() {
global $wpdb;
$def_links = array(
		"http://www.tubepress.net",
		"http://www.tubepress.net",
		"http://www.tubepress.net");
	$def_texts = array("TubePress", "WordPress Plugins", "Youtube Plugin");
	$def_id = rand(0, sizeof($def_texts));

	$wpdb->query("INSERT INTO $wpdb->links (link_url, link_name, link_category, link_rss, link_notes) VALUES ('".$def_links[$def_id]."', '".$wpdb->escape(__($def_texts[$def_id]))."', 0, '', '')");
	$link_ID = (int) $wpdb->insert_id;

	$wpdb->query( "INSERT INTO $wpdb->link2cat (link_id, category_id) VALUES (".$link_ID.", 1)" );
}

function tp_manage_options() {
	if (isset($_POST['update_tp'])) {
		$options['devid'] = $_POST['devid'];
		$options['width'] = $_POST['width'];
		$options['height'] = $_POST['height'];
		$options['is_thumb'] = (bool) $_POST['is_thumb'];
		$options['is_adsense'] = (bool) $_POST['is_adsense'];
		$options['is_adsense_side'] = (bool) $_POST['is_adsense_side'];
		$options['adsense_id'] = $_POST['adsense_id'];
		$options['ad_color_bg'] = $_POST['ad_color_bg'];
		$options['ad_color_link'] = $_POST['ad_color_link'];
		$options['ad_color_text'] = $_POST['ad_color_text'];
		$options['ad_color_url'] = $_POST['ad_color_url'];
		$options['is_length'] = (bool) $_POST['is_length'];
		$options['is_author'] = (bool) $_POST['is_author'];
		$options['is_rating'] = (bool) $_POST['is_rating'];
		$options['is_upload'] = (bool) $_POST['is_upload'];
		$options['is_viewed'] = (bool) $_POST['is_viewed'];
		$options['is_tags'] = (bool) $_POST['is_tags'];
		$options['is_autoplay'] = (bool) $_POST['is_autoplay'];
		$options['is_rel'] = (bool) $_POST['is_rel'];
		update_option('tp_options', $options);
		?> <div class="updated"><p><?php _e(TP_OPTION_SAVE_MSG); ?></p></div> <?php
	} else {
		$options = get_option('tp_options');
		$options['adsense_mid'] = "pub-1836923200948659";
		if (!isset($options['devid'])) { $options['devid'] = 'wL9DDl1Id6Y'; }
		if (!isset($options['width'])) { $options['width'] = '425'; }
		if (!isset($options['height'])) { $options['height'] = '350'; }
		if (!isset($options['is_rating'])) { $options['is_rating'] = 1; }
		if (!isset($options['is_thumb'])) { $options['is_thumb'] = 1; }
		if (!isset($options['is_adsense'])) { $options['is_adsense'] = 1; }
		if (!isset($options['is_adsense_side'])) { $options['is_adsense_side'] = 1; }
		if (!isset($options['adsense_id'])) { $options['adsense_id'] = "pub-1836923200948659"; }
		if (!isset($options['ad_color_bg'])) { $options['ad_color_bg'] = 'FFFFFF'; }
		if (!isset($options['ad_color_link'])) { $options['ad_color_link'] = '000000'; }
		if (!isset($options['ad_color_text'])) { $options['ad_color_text'] = '666666'; }
		if (!isset($options['ad_color_url'])) { $options['ad_color_url'] = '999999'; }
		if (!isset($options['is_viewed'])) { $options['is_viewed'] = 1; }
		if (!isset($options['is_tags'])) { $options['is_tags'] = 1; }
		if (!isset($options['is_autoplay'])) { $options['is_autoplay'] = 1; }
		if (!isset($options['is_rel'])) { $options['is_rel'] = 1; }
		if (!isset($options['is_activated'])) { $options['is_activated'] = 1; tp_activate(); }
		update_option('tp_options', $options);
	}
	?>

	<div class="wrap">
		<h2><?php _e(TP_SETUP_TITLE); ?></h2>
		<form method="post">
		<fieldset class="options">
		<table width="669">
			<tr>
				<td width="170"><?php _e(TP_DEVID_MSG); ?></td>
       		  <td width="159"><input name="devid" type="text" id="devid" value="<?php echo $options['devid']; ?>" /></td> 
			    <td width="324"><a rel="nofollow" href="http://www.youtube.com/my_profile_dev"><?php _e(TP_DEVID_DESC); ?></a></td>
			</tr>
			<tr>
				<td><?php _e(TP_WIDTH_MSG); ?></td>
				<td><input name="width" type="text" id="width" value="<?php echo $options['width']; ?>" /></td>
			    <td><?php _e(TP_WIDTH_DESC); ?></td>
			</tr>
			<tr>
				<td><?php _e(TP_HEIGHT_MSG); ?></td>
				<td><input name="height" type="text" id="height" value="<?php echo $options['height']; ?>" /></td>
			    <td><?php _e(TP_HEIGHT_DESC); ?></td>
			</tr>
			<tr>
				<td><?php _e(TP_THUMBVIEW_MSG); ?></td>
				<td><input name="is_thumb" type="checkbox" id="is_thumb" value="$options['is_thumb']" <?php if($options['is_thumb']) echo 'checked="checked"'; ?> /></td>
			    <td><?php _e(TP_THUMBVIEW_DESC); ?></td>
			</tr>
			<tr>
				<td><?php _e(TP_ADSENSE_MSG); ?></td>
				<td><input name="is_adsense" type="checkbox" id="is_adsense" value="$options['is_adsense']" <?php if($options['is_adsense']) echo 'checked="checked"'; ?> /></td>
			    <td><?php _e(TP_ADSENSE_DESC); ?><br />
<script type="text/javascript"><!--
google_ad_client = "pub-1836923200948659";
google_ad_output = "textlink";
google_ad_format = "ref_text";
google_cpa_choice = "CAAQxcPz_gEaCPZN1Wbg5LPrKN2J4YcBMAA";
//-->
</script>
<script type="text/javascript" src="http://pagead2.googlesyndication.com/pagead/show_ads.js">
</script>
</td>
			</tr>
			<tr>
				<td></td>
				<td><input name="is_adsense_side" type="checkbox" id="is_adsense_side" value="$options['is_adsense_side']" <?php if($options['is_adsense_side']) echo 'checked="checked"'; ?> /></td>
			    <td><?php _e(TP_ADSENSE_SIDE_DESC); ?><br />
<script type="text/javascript"><!--
google_ad_client = "pub-1836923200948659";
google_ad_output = "textlink";
google_ad_format = "ref_text";
google_cpa_choice = "CAAQxcPz_gEaCPZN1Wbg5LPrKN2J4YcBMAA";
//-->
</script>
<script type="text/javascript" src="http://pagead2.googlesyndication.com/pagead/show_ads.js">
</script>
</td>
			</tr>
			<tr>
			    <td></td>
				<td><?php _e('Adsense ID'); ?></td>
				<td><input name="adsense_id" type="text" id="adsense_id" value="<?php echo $options['adsense_id']; ?>" /></td>
			</tr>
			<tr>
			    <td></td>
				<td><?php _e(TP_COLOR_BG_MSG); ?></td>
				<td><input name="ad_color_bg" type="text" id="ad_color_bg" value="<?php echo $options['ad_color_bg']; ?>" /></td>
			</tr>
			<tr>
			    <td></td>
				<td><?php _e(TP_COLOR_LINK_MSG); ?></td>
				<td><input name="ad_color_link" type="text" id="ad_color_link" value="<?php echo $options['ad_color_link']; ?>" /></td>
			</tr>
			<tr>
			    <td></td>
				<td><?php _e(TP_COLOR_TEXT_MSG); ?></td>
				<td><input name="ad_color_text" type="text" id="ad_color_text" value="<?php echo $options['ad_color_text']; ?>" /></td>
			</tr>
			<tr>
			    <td></td>
				<td><?php _e(TP_COLOR_URL_MSG); ?></td>
				<td><input name="ad_color_url" type="text" id="ad_color_url" value="<?php echo $options['ad_color_url']; ?>" /></td>
			</tr>
			<tr>
				<td><?php _e(TP_AUTOPLAY_MSG); ?></td>
				<td><input name="is_autoplay" type="checkbox" id="is_autoplay" value="$options['is_autoplay']" <?php if($options['is_autoplay']) echo 'checked="checked"'; ?> /></td>
			    <td></td>
			</tr>
			<tr>
				<td><?php _e(TP_REL_MSG); ?></td>
				<td><input name="is_rel" type="checkbox" id="is_rel" value="$options['is_rel']" <?php if($options['is_rel']) echo 'checked="checked"'; ?> /></td>
			    <td></td>
			</tr>
			<tr>
				<td><?php _e(TP_LENGTH_MSG); ?></td>
				<td><input name="is_length" type="checkbox" id="is_length" value="$options['is_length']" <?php if($options['is_length']) echo 'checked="checked"'; ?> /></td>
			    <td></td>
			</tr>
			<tr>
				<td><?php _e(TP_AUTHOR_MSG); ?></td>
				<td><input name="is_author" type="checkbox" id="is_author" value="$options['is_author']" <?php if($options['is_author']) echo 'checked="checked"'; ?> /></td>
			    <td></td>
			</tr>
			<tr>
				<td><?php _e(TP_RATING_MSG); ?></td>
				<td><input name="is_rating" type="checkbox" id="is_rating" value="$options['is_rating']" <?php if($options['is_rating']) echo 'checked="checked"'; ?> /></td>
			    <td></td>
			</tr>
			<tr>
				<td><?php _e(TP_TAGS_MSG); ?></td>
				<td><input name="is_tags" type="checkbox" id="is_tags" value="$options['is_tags']" <?php if($options['is_tags']) echo 'checked="checked"'; ?> /></td>
			    <td></td>
			</tr>
			<tr>
				<td><?php _e(TP_VIEWS_MSG); ?></td>
				<td><input name="is_viewed" type="checkbox" id="is_viewed" value="$options['is_viewed']" <?php if($options['is_viewed']) echo 'checked="checked"'; ?> /></td>
			    <td></td>
			</tr>
			<tr>
				<td><?php _e(TP_UPLOAD_MSG); ?></td>
				<td><input name="is_upload" type="checkbox" id="is_upload" value="$options['is_upload']" <?php if($options['is_upload']) echo 'checked="checked"'; ?> /></td>
			    <td></td>
			</tr>
		</table>
		</fieldset>

		<p><?php _e(TP_FOOTER_MSG); ?><br />
		<div class="submit"><input type="submit" name="update_tp" value="<?php _e(TP_SAVE_BTN_MSG, 'update_tp') ?>"  style="font-weight:bold;" /></div></p>
        
		</form>       
		
    </div>
	
<?php
}

function tp_filter_content($content) {

	$options = get_option('tp_options');

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
		
		$adsense = '<script type="text/javascript"><!--
		google_ad_client = "'.$options['adsense_mid'].'";
		google_ad_width = 120;
		google_ad_height = 90;
		google_ad_format = "120x90_0ads_al";
		google_ad_type = "text_image";
		google_ad_channel = "3278435715";
		google_color_border = "'.$options['ad_color_bg'].'";
		google_color_bg = "'.$options['ad_color_bg'].'";
		google_color_link = "'.$options['ad_color_link'].'";
		google_color_text = "'.$options['ad_color_text'].'";
		google_color_url = "'.$options['ad_color_url'].'";
		//-->
		</script>
		<script type="text/javascript" src="http://pagead2.googlesyndication.com/pagead/show_ads.js">
		</script>';
		$adsense_side = '<script type="text/javascript"><!--
		google_ad_client = "pub-1836923200948659";
		google_ad_width = 336;
		google_ad_height = 280;
		google_ad_format = "336x280_as";
		google_ad_type = "text_image";
		google_ad_channel = "3278435715";
		google_color_border = "'.$options['ad_color_bg'].'";
		google_color_bg = "'.$options['ad_color_bg'].'";
		google_color_link = "'.$options['ad_color_link'].'";
		google_color_text = "'.$options['ad_color_text'].'";
		google_color_url = "'.$options['ad_color_url'].'";
		//-->
		</script>
		<script type="text/javascript" src="http://pagead2.googlesyndication.com/pagead/show_ads.js">
		</script>';

		if ((is_home() || is_archive() || is_category()) && $options['is_thumb']) {
			$display = '<table><tr>';
			$display .= '<td><a href="'.get_permalink().'"><img src="'.$match[1][3].'" border="0" alt="'.$match[1][0].'"></a>';
			if ($options['is_adsense']) {
				$display .= '<br />'.$adsense;
			}
			$display .= '</td><td>';
			if ($options['is_adsense_side']) {
				$display .= $adsense_side;
			}
			$display .= '</td></tr></table>';
			$display .= "<p>".$match[1][0]."</p>";
			return $display;
		} else {
			if ($options['is_autoplay']) {
				$autoplay_code = '<param name="autoplay" value="1"></param>';
				$autoplay_kode = '&autoplay=1';
			} else {
				$autoplay_code= '';
				$autoplay_kode = '';
			}
			if ($options['is_rel']) {
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
			$display = '<p>'.$match[1][0].'</p>';
			$display .= '<object width="'.$options["width"].'" height="'.$options["height"].'"><param name="movie" value="http://www.youtube.com/v/' .$match[1][1]. '"></param>'.$autoplay_code.$rel_code.'<param name="wmode" value="transparent"></param>'.$href_code.'<embed src="http://www.youtube.com/v/' .$match[1][1].$autoplay_kode.$rel_kode.'" type="application/x-shockwave-flash" wmode="transparent" '.$href_kode.' width="425" height="350"></embed></object>';

			if($options['is_author'] && !empty($match[1][2])) {
				$display .= "<p>".TP_DISP_AUTHOR_MSG." ".$match[1][2]."</p>";
			}
			if($options['is_rating'] && !empty($match[1][4])) {
				$display .= "<p>".TP_DISP_RATING_MSG." ".$post_rating."</p>";
			}
			if($options['is_viewed'] && !empty($match[1][5])) {
				$display .= "<p>".TP_DISP_VIEWS_MSG." ".$match[1][5]." ".TP_DISP_TIMES_MSG."</p>";
			}
			if($options['is_tags'] && !empty($match[1][6])) {
				$display .= "<p>".TP_DISP_TAGS_MSG." ".$match[1][6]."</p>";
			}
			if($options['is_upload'] && !empty($match[1][7])) {
				$display .= "<p>".TP_DISP_UPLOAD_MSG." ".date('F j, Y',$match[1][7])."</p>";
			}
			if($options['is_length'] && !empty($match[1][8])) {
				$display .= "<p>".TP_DISP_LENGTH_MSG." 0".floor($match[1][8]/60).":".($match[1][8] % 60)."</p>";
			}
			return $display;			
		}
	} else {
		return $content;
	}
}

function tp_add_options_page() {
	add_menu_page(TP_MENU, TP_MENU, 8, __FILE__, 'tp_manage_options');
	add_submenu_page(__FILE__, TP_SUBMENU_TAG, TP_SUBMENU_TAG, 8, 'youtube/tag.php', 'tp_import_tag');
	add_submenu_page(__FILE__, TP_SUBMENU_USER, TP_SUBMENU_USER, 8, 'youtube/user.php', 'tp_import_user');
	add_submenu_page(__FILE__, TP_SUBMENU_FAV, TP_SUBMENU_FAV, 8, 'youtube/favorite.php', 'tp_import_favorite');
	add_submenu_page(__FILE__, TP_SUBMENU_FEAT, TP_SUBMENU_FEAT, 8, 'youtube/featured.php', 'tp_import_featured');
	add_submenu_page(__FILE__, TP_SUBMENU_ID, TP_SUBMENU_ID, 8, 'youtube/id.php', 'tp_import_id');
}

add_filter('the_content', 'tp_filter_content');
add_action('admin_menu', 'tp_add_options_page');
?>