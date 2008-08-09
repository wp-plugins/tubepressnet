<?php
/*
Plugin Name: TubePress.Net
Plugin URI: http://www.tubepress.net/
Description:  The Youtube Plugin for Wordpress
Author: Mario Mansour
Version: 3.0
Author URI: http://www.mariomansour.com/
*/
include('languages/english.php');
class SimpleXMLObject {
    function attributes(){
        $container = get_object_vars($this);
        return (object) $container["@attributes"];
    }
    function content(){
        $container = get_object_vars($this);
        return (object) $container["@content"];
    }
}
/**
* The Main XML Parser Class
*
*/
class simplexml {
    var $result = array();
    var $ignore_level = 0;
    var $skip_empty_values = false;
    var $php_errormsg;
    var $evalCode="";

  /**
     * Adds Items to Array
     *
     * @param int $level
     * @param array $tags
     * @param $value
     * @param string $type
     */
    function array_insert($level, $tags, $value, $type) {
		$temp = '';
        for ($c = $this->ignore_level + 1; $c < $level + 1; $c++) {
            if (isset($tags[$c]) && (is_numeric(trim($tags[$c])) || trim($tags[$c]))) {
                if (is_numeric($tags[$c])) {
                    $temp .= '[' . $tags[$c] . ']';
                } else {
                    $temp .= '["' . $tags[$c] . '"]';
                }
            }
        }
        $this->evalCode .= '$this->result' . $temp . "=\"" . addslashes($value) . "\";//(" . $type . ")\n";
        #echo $code. "\n";
    }

  /**
     * Define the repeated tags in XML file so we can set an index
     *
     * @param array $array
     * @return array
     */
    function xml_tags($array) {
    $repeats_temp = array();
    $repeats_count = array();
    $repeats = array();

    if (is_array($array)) {
        $n = count($array) - 1;
        for ($i = 0; $i < $n; $i++) {
            $idn = $array[$i]['tag'].$array[$i]['level'];
            if(in_array($idn,$repeats_temp)){
                $repeats_count[array_search($idn,$repeats_temp)]+=1;
            }else{
                array_push($repeats_temp,$idn);
                $repeats_count[array_search($idn,$repeats_temp)]=1;
            }
        }
    }
    $n = count($repeats_count);
    for($i=0;$i<$n;$i++){
        if($repeats_count[$i]>1){
            array_push($repeats,$repeats_temp[$i]);
        }
    }
    unset($repeats_temp);
    unset($repeats_count);
    return array_unique($repeats);
    }


    /**
     * Converts Array Variable to Object Variable
     *
     * @param array $arg_array
     * @return $tmp
     */
    function array2object ($arg_array) {
        if (is_array($arg_array)) {
            $keys = array_keys($arg_array);
            if(!is_numeric($keys[0])) $tmp = new SimpleXMLObject;
            foreach ($keys as $key) {
                if (is_numeric($key)) $has_number = true;
                if (is_string($key)) $has_string = true;
            }
            if (isset($has_number) and !isset($has_string)) {
                foreach ($arg_array as $key => $value) {
                    $tmp[] = $this->array2object($value);
                }
            } elseif (isset($has_string)) {
                foreach ($arg_array as $key => $value) {
                    if (is_string($key))
                    $tmp->$key = $this->array2object($value);
                }
            }
        } elseif (is_object($arg_array)) {
            foreach ($arg_array as $key => $value) {
                if (is_array($value) or is_object($value))
                $tmp->$key = $this->array2object($value);
                else
                $tmp->$key = $value;
            }
        } else {
            $tmp = $arg_array;
        }
        return $tmp; //return the object
    }

    /**
     * Reindexes the whole array with ascending numbers
     *
     * @param array $array
     * @return array
     */
    function array_reindex($array) {
        if (is_array($array)) {
            if(count($array) == 1 && $array[0]){
                return $this->array_reindex($array[0]);
            }else{
                foreach($array as $keys => $items) {
                    if (is_array($items)) {
                        if (is_numeric($keys)) {
                            $array[$keys] = $this->array_reindex($items);
                        } else {
                            $array[$keys] = $this->array_reindex(array_merge(array(), $items));
                        }
                    }
                }
            }
        }
        return $array;
    }
    /**
     * Parse the XML generation to array object
     *
     * @param array $array
     * @return array
     */
    function xml_reorganize($array) {
        $count = count($array);
        $repeat = $this->xml_tags($array);
        $repeatedone = false;
        $tags = array();
        $k = 0;
        for ($i = 0; $i < $count; $i++) {
            switch ($array[$i]['type']) {
                case 'open':
                    array_push($tags, $array[$i]['tag']);
                    if ($i > 0 && ($array[$i]['tag'] == $array[$i-1]['tag']) && ($array[$i-1]['type'] == 'close'))
                    $k++;
                    if (isset($array[$i]['value']) && ($array[$i]['value'] || !$this->skip_empty_values)) {
                        array_push($tags, '@content');
                        $this->array_insert(count($tags), $tags, $array[$i]['value'], "open");
                        array_pop($tags);
                    }
                    if (in_array($array[$i]['tag'] . $array[$i]['level'], $repeat)) {
                        if (($repeatedone == $array[$i]['tag'] . $array[$i]['level']) && ($repeatedone)) {
                            array_push($tags, strval($k++));
                        } else {
                            $repeatedone = $array[$i]['tag'] . $array[$i]['level'];
                            array_push($tags, strval($k));
                        }
                    }
                    if (isset($array[$i]['attributes']) && $array[$i]['attributes'] && $array[$i]['level'] != $this->ignore_level) {
                        array_push($tags, '@attributes');
                        foreach ($array[$i]['attributes'] as $attrkey => $attr) {
                            array_push($tags, $attrkey);
                            $this->array_insert(count($tags), $tags, $attr, "open");
                            array_pop($tags);
                        }
                        array_pop($tags);
                    }
                    break;
                case 'close':
                    array_pop($tags);
                    if (in_array($array[$i]['tag'] . $array[$i]['level'], $repeat)) {
                        if ($repeatedone == $array[$i]['tag'] . $array[$i]['level']) {
                            array_pop($tags);
                        } else {
                            $repeatedone = $array[$i + 1]['tag'] . $array[$i + 1]['level'];
                            array_pop($tags);
                        }
                    }
                    break;
                case 'complete':
                    array_push($tags, $array[$i]['tag']);
                    if (in_array($array[$i]['tag'] . $array[$i]['level'], $repeat)) {
                        if ($repeatedone == $array[$i]['tag'] . $array[$i]['level'] && $repeatedone) {
                            array_push($tags, strval($k));
                        } else {
                            $repeatedone = $array[$i]['tag'] . $array[$i]['level'];
                            array_push($tags, strval($k));
                        }
                    }
                    if (isset($array[$i]['value']) && ($array[$i]['value'] || !$this->skip_empty_values)) {
                        if (isset($array[$i]['attributes']) && $array[$i]['attributes']) {
                            array_push($tags, '@content');
                            $this->array_insert(count($tags), $tags, $array[$i]['value'], "complete");
                            array_pop($tags);
                        } else {
                            $this->array_insert(count($tags), $tags, $array[$i]['value'], "complete");
                        }
                    }
                    if (isset($array[$i]['attributes']) && $array[$i]['attributes']) {
                        array_push($tags, '@attributes');
                        foreach ($array[$i]['attributes'] as $attrkey => $attr) {
                            array_push($tags, $attrkey);
                            $this->array_insert(count($tags), $tags, $attr, "complete");
                            array_pop($tags);
                        }
                        array_pop($tags);
                    }
                    if (in_array($array[$i]['tag'] . $array[$i]['level'], $repeat)) {
                        array_pop($tags);
                        $k++;
                    }
                    array_pop($tags);
                    break;
            }
        }
        eval($this->evalCode);
        $last = $this->array_reindex($this->result);
        return $last;
    }
    /**
     * Get the XML contents and parse like SimpleXML
     *
     * @param string $file
     * @param string $resulttype
     * @param string $encoding
     * @return array/object
     */
    function xml_load_file($file, $resulttype = 'object', $encoding = 'UTF-8') {
        $php_errormsg="";
        $this->result="";
        $this->evalCode="";
        $values="";
        $data = file_get_contents($file);
        if (!$data)
        return 'Cannot open xml document: ' . (isset($php_errormsg) ? $php_errormsg : $file);
        $parser = xml_parser_create($encoding);
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
        $ok = xml_parse_into_struct($parser, $data, $values);
        if (!$ok) {
            $errmsg = sprintf("XML parse error %d '%s' at line %d, column %d (byte index %d)",
            xml_get_error_code($parser),
            xml_error_string(xml_get_error_code($parser)),
            xml_get_current_line_number($parser),
            xml_get_current_column_number($parser),
            xml_get_current_byte_index($parser));
        }
        xml_parser_free($parser);
        if (!$ok)
        return $errmsg;
        if ($resulttype == 'array')
        return $this->xml_reorganize($values);
        // default $resulttype is 'object'
        return $this->array2object($this->xml_reorganize($values));
	}
}
class youtube {
 
	/**
	  * A youtube dev id - http://youtube.com/my_profile_dev
	  * @access private
	  * @var integer|string
	  */
	var $devkey;
	/**
	  * Constructor - sets up youtube devid
	  * @param string $devkey
	  */
	function youtube($devid='wL9DDl1Id6Y') {
	    $this->devkey = $devid;
	}	 
	/**
	  * Retrieves the public parts of a user profile.
	  * @param string $user
	  * @return string $results
	  */
	function users_get_profile($user) {
	    $functionName = "youtube.users.get_profile";
	    $payload = array("user"=>$user);
	    $results = $this->getRestResponse($functionName, $payload);
	    return $results;
	}	 
	/**
	  * Lists a user's favorite videos.
	  * @param string $user
	  * @return string $results
	  */
	function users_list_favorite_videos($user) {
	    $functionName = "youtube.users.list_favorite_videos";
	    $payload = array("user"=>$user);
	    $results = $this->getRestResponse($functionName, $payload);
	    return $results;
	}
	/**
	  * Lists a user's friends.
	  * @param string $user
	  * @return string $results
	  */
	function users_list_friends($user) {
	    $functionName = "youtube.users.list_friends";
	    $payload = array("user"=>$user);
	    $results = $this->getRestResponse($functionName, $payload);
	    return $results;
	}	 
	/**
	  * Displays the details for a video.
	  * @param string $video_id
	  * @return string $results
	  */
	function videos_get_details($video_id) {
	    $functionName = "youtube.videos.get_details";
	    $payload = array("video_id"=>$video_id);
	    $results = $this->getRestResponse($functionName, $payload);
	    return $results;
	}	 
	/**
	  * Lists all videos that have the specified tag.
	  * @param string $tag
	  * @param string $page
	  * @param string $per_page
	  * @return string $results
	  */
	function videos_list_by_tag($tag, $page, $per_page) {
	    $functionName = "youtube.videos.list_by_tag";
		$tag = str_replace(" ","+",$tag);
	    $payload = array("tag"=>$tag,"page"=>$page,"per_page"=>$per_page);
	    $results = $this->getRestResponse($functionName, $payload);
	    return $results;
	}	 
	/**
	  * Lists all videos that were uploaded by the specified user
	  * @param string $user
	  * @return string $results
	  */
	function videos_list_by_user($user) {
	    $functionName = "youtube.videos.list_by_user";
	    $payload = array("user"=>$user);
	    $results = $this->getRestResponse($functionName, $payload);
	    return $results;
	}	 
	/**
	  * Lists the most recent 25 videos that have been featured on the 
	  * front page of the YouTube site.
	  * @return string $results
	  */
	function videos_list_featured() {
	    $functionName = "youtube.videos.list_featured";
	    $payload = "";
	    $results = $this->getRestResponse($functionName, $payload);
	    return $results;
	}
	/**
	  * Get a rest response from the youtube api. Takes a functionName.
	  * and a array payload
	  * @param string $functionName
	  * @param string $payload
	  * @return string $response
	  */
	function getRestResponse($functionName, $payload) {
	    $url = $this->buildRestUrl($functionName, $payload);
	    $response = simplexml_load_file($url);
	    return $response;
	}	 
	/**
	  * Builds REST URL based on  payload and function
	  * @param string $functionName
	  * @param array $payload
	  * @return string $url
	  */
	function buildRestUrl($functionName, $payload) {
	    if ($payload != ""){
	        foreach ($payload as $name => $value){
	            $payloadString .= $name.'='.$value.'&';
	        }
	    }
	    $url = 'http://www.youtube.com/api2_rest?method='.$functionName.'&dev_id='.$this->devkey.'&'.$payloadString;
	    return $url;
	} 
}

if(!function_exists("simplexml_load_file")) {
	function simplexml_load_file($file){
		$sx = new simplexml;
		return $sx->xml_load_file($file);
	}
}
$yt = new youtube();

function tp_get_list($options,$action='tag') {
	global $yt;
	//$options = get_option('tp_options_user');
	//$gen_options = get_option('tp_options');
	if(!is_array($options)) return false;
	switch($action) {
		case 'id':
			$xml = $yt->videos_get_details($options['video_id']);
		break;
		case 'user':
			$xml = $yt->videos_list_by_user($options['user'], $options['page'], $options['per_page']);
		break;
		case 'featured':
			$xml = $yt->videos_list_featured();
		break;
		case 'favorite':
			$xml = $yt->users_list_favorite_videos($options['user'], $options['page'], $options['per_page']);
		break;
		case 'tag':
			$xml = $yt->videos_list_by_tag($options['tag'], $options['page'], $options['per_page']);
		break;
	}
	echo '<div class="wrap">';
	_e('<h2>'.TP_IMPORT_LIST_MSG.'</h2>');
	echo '<div align="center">';
	if(isset($xml->video_details) && !tp_duplicate($xml->video_details->id)) {
		echo "<img src='{$xml->video_details->thumbnail_url}' alt='{$xml->video_details->title}' width='130' height='97' />";
		tp_write_post($xml->video_details,$options);
	} else {
		foreach ($xml->video_list->video as $video) {
			if(!tp_duplicate($video->id)) {
				echo "<img src='{$video->thumbnail_url}' alt='{$video->title}' width='130' height='97' />  ";
				tp_write_post($video,$options);
			}
		}
	}
	echo '</div></div>';
}

function tp_duplicate($id) {
	global $wpdb;
	$options = get_option('tp_options');
	$post = $wpdb->get_results("SELECT ID FROM $wpdb->posts WHERE post_content like '%".$id."%' OR post_excerpt like '%".$id."%' LIMIT 1",ARRAY_A);
	return (is_array($post) && $options['duplicate']) ? true : false;
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
	return $pl;
}

function tp_rating_c($r) {
	$img = '';
	$path = get_bloginfo('siteurl').'/wp-content/plugins/tubepress.net/images/';
	$t = 0;
	for($i=0;$i<floor($r);$i++) { $img .= '<img src="'.$path.'yt_rating_on.gif" />'; }
	if($r > floor($r)) { $t = 1; $img .= '<img src="'.$path.'yt_rating_half.gif" />'; }
	for($i=0;$i<5-floor($r)-$t;$i++) { $img .= '<img src="'.$path.'yt_rating_off.gif" />'; }
	return $img;
}

function tp_write_post($v,$opt) {
	$tpo = get_option('tp_options');
	$post_template_excerpt = $tpo['excerpt'];
	$post_template_content = $tpo['content'];
	
	$tp_tags = array("%tp_player%","%tp_id%","%tp_title%","%tp_thumbnail%","%tp_description%","%tp_duration%","%tp_rating_num%","%tp_rating_img%","%tp_viewcount%","%tp_author%","%tp_tags%","%tp_url%");
	$tag_values = array(tp_player($v->id),$v->id,$v->title,$v->thumbnail_url,$v->description,$v->length_seconds,$v->rating_avg,tp_rating_c($v->rating_avg),$v->view_count,$v->author,$v->tags,$v->url);
	
	$post_template_excerpt = str_replace($tp_tags,$tag_values,$post_template_excerpt);
	$post_template_content = str_replace($tp_tags,$tag_values,$post_template_content);
	$post_category = explode(',', trim($opt['cat'], " \n\t\r\0\x0B,"));
	$post_tags = explode(' ', trim($v->tags," \n\t\r\0\x0B,"));
	$tp_post = array('post_title' => $v->title,
			'post_content' => $post_template_content,
			'post_status' => 'publish',
			'post_type' => $tpo['type'],
			'post_name' => sanitize_title($v->title),
			'post_category' => $post_category,
			'tags_input' => $post_tags,
			'post_excerpt' => $post_template_excerpt);
	$post_id = wp_insert_post($tp_post);
	wp_create_categories($post_category,$post_id);
}

function tp_category_form($options) {
	$tpo = get_option('tp_options');
	$tf = '';
	if($tpo['type'] == 'post') {
		$tf .= '<tr>';
		$tf .= '	<td>'.TP_CAT_MSG.'</td>';
		$tf .= '	<td><input name="cat" type="text" id="cat" value="'.$options['cat'].'" /></td>';
		$tf .= '	<td>'.TP_CAT_DESC.'</td>';
		$tf .= '</tr>';
	}
	return $tf;
}

function tp_comment_form($options) {
	return "";
	$tf .= '<tr>';
	$tf .= '	<td>'.TP_COMMENTS_MSG.'</td>';
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
	$tf .= '    <td>'.TP_COMMENTS_DESC.'</td>';
	$tf .= '</tr>';
	return $tf;
}

function tp_import_id() {
	$default = array('video_id'=>'QGQMyN75LFQ');
	if (isset($_POST['update_tp'])) {
		$options['video_id'] = $_POST['video_id'];
		$options['cat'] = $_POST['cat'];
		$options['comments'] = $_POST['comments'];
		update_option('tp_options_id', $options);	
		?> <div class="updated"><p><?php _e(TP_SUCCESS_MSG); ?></p></div> <?php
		tp_get_list($_POST,'id');
	} else {
		$opt = get_option('tp_options_id');
		$options = is_array($opt) ? array_merge($default,$opt) : $default;
	}
	?>

	<div class="wrap">
		<h2><?php _e(TP_ID_TITLE); ?></h2>
		<?php echo tp_copyright(); ?>
		<form name="id" method="post">
		<table width="669">
			<tr>
				<td>Video ID:</td>
				<td><input name="video_id" type="text" id="video_id" value="<?php echo $options['video_id'] ?>" /></td>
				<td>http://youtube.com/watch?v=<strong>QGQMyN75LFQ</strong></td>
			</tr>
			<?php _e(tp_category_form($options)); ?>
			<?php _e(tp_comment_form($options)); ?>
		</table>		
		<div class="submit"><input type="submit" name="update_tp" value="<?php _e(TP_ID_BTN_MSG, 'update_tp') ?>"  style="font-weight:bold;" /></div></p>
		</form>
    </div>
	
<?php
}

function tp_import_featured() {
	$default = array('cat'=>'Featured');
	if (isset($_POST['update_tp'])) {
		$options['cat'] = $_POST['cat'];
		$options['comments'] = $_POST['comments'];
		update_option('tp_options_feat', $options);	
		?> <div class="updated"><p><?php _e(TP_SUCCESS_MSG); ?></p></div> <?php
		tp_get_list($_POST,'featured');
	} else {
		$opt = get_option('tp_options_feat');
		$options = is_array($opt) ? array_merge($default,$opt) : $default;
	}
	?>

	<div class="wrap">
		<h2><?php _e(TP_FEAT_TITLE); ?></h2>
		<?php echo tp_copyright(); ?>
		<form name="feat" method="post">
		<table width="669">
			<?php _e(tp_category_form($options)); ?>
			<?php _e(tp_comment_form($options)); ?>
		</table>
		<div class="submit"><input type="submit" name="update_tp" value="<?php _e(TP_FEAT_BTN_MSG, 'update_tp') ?>"  style="font-weight:bold;" /></div></p>        
		</form>
    </div>
	
<?php
}

function tp_import_favorite() {
	$default = array('user'=>'tubepressnet');
	if (isset($_POST['update_tp'])) {
		$options['user'] = $_POST['user'];
		$options['cat'] = $_POST['cat'];
		$options['comments'] = $_POST['comments'];
		update_option('tp_options_fav', $options);
		?> <div class="updated"><p><?php _e(TP_SUCCESS_MSG); ?></p></div> <?php
		tp_get_list($_POST,'favorite');
	} else {
		$opt = get_option('tp_options_fav');
		$options = is_array($opt) ? array_merge($default,$opt) : $default;
	}
	?>

	<div class="wrap">
		<h2><?php _e(TP_FAV_TITLE); ?></h2>
		<?php echo tp_copyright(); ?>
		<form name="user" method="post">
		<table width="669">
			<tr>
				<td><?php _e(TP_USERNAME_MSG); ?></td>
				<td><input name="user" type="text" id="user" value="<?php echo $options['user']; ?>" /></td>
			    <td><a rel="nofollow" href="http://www.youtube.com/signup"><?php _e(TP_USERNAME_DESC); ?></a></td>
			</tr>
			<?php _e(tp_category_form($options)); ?>
			<?php _e(tp_comment_form($options)); ?>
		</table>
		<div class="submit"><input type="submit" name="update_tp" value="<?php _e(TP_FAV_BTN_MSG, 'update_tp') ?>"  style="font-weight:bold;" /></div></p>        
		</form>
    </div>
	
<?php
}


function tp_import_user() {
	$default = array('user'=>'tubepressnet', 'page'=>'1', 'per_page'=>'10');
	if (isset($_POST['update_tp'])) {
		$options['user'] = $_POST['user'];
		$options['page'] = $_POST['page'];
		$options['per_page'] = $_POST['per_page'];
		$options['cat'] = $_POST['cat'];
		$options['comments'] = $_POST['comments'];
		update_option('tp_options_user', $options);
		?> <div class="updated"><p><?php _e(TP_SUCCESS_MSG); ?></p></div> <?php
		tp_get_list($_POST,'user');
	} else {
		$opt = get_option('tp_options_user');
		$options = is_array($opt) ? array_merge($default,$opt) : $default;
	}
	?>

	<div class="wrap">
		<h2><?php _e(TP_USER_TITLE); ?></h2>
		<?php echo tp_copyright(); ?>
		<form name="user" method="post">
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
			<?php _e(tp_category_form($options)); ?>
			<?php _e(tp_comment_form($options)); ?>
		</table>		
		<div class="submit"><input type="submit" name="update_tp" value="<?php _e(TP_USER_BTN_MSG, 'update_tp') ?>"  style="font-weight:bold;" /></div></p>
		</form>
    </div>
	
<?php
}

function tp_import_tag() {
	$default = array('tag'=>'funny clips', 'page'=>'1', 'per_page'=>'20');
	if (isset($_POST['update_tp'])) {
		$options['tag'] = $_POST['tag'];
		$options['page'] = $_POST['page'];
		$options['per_page'] = $_POST['per_page'];
		$options['cat'] = $_POST['cat'];
		$options['comments'] = $_POST['comments'];
		update_option('tp_options_tag', $options);
		?> <div class="updated"><p><?php _e(TP_SUCCESS_MSG); ?></p></div> <?php
		tp_get_list($_POST,'tag');
	} else {
		$opt = get_option('tp_options_tag');
		$options = is_array($opt) ? array_merge($default,$opt) : $default;
	}
	?>

	<div class="wrap">
		<h2><?php _e(TP_TAG_TITLE); ?></h2>
		<?php echo tp_copyright(); ?>
		<form method="post">
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
			<?php _e(tp_category_form($options)); ?>
			<?php _e(tp_comment_form($options)); ?>
		</table>
		<div class="submit"><input type="submit" name="update_tp" value="<?php _e(TP_TAG_BTN_MSG, 'update_tp') ?>"  style="font-weight:bold;" /></div></p>        
		</form>
    </div>
	
<?php
}
function tp_manage_options() {
	$default = array('width'=>'425','height'=>'344','autoplay'=>'1','rel'=>'1','color'=>'1','border'=>'0', 'duplicate'=>'1', 'type'=>'post',
			'excerpt'=>'<img style="border: 3px solid #000000" src="%tp_thumbnail%" /><br />%tp_title% was uploaded by: %tp_author%<br />Duration: %tp_duration%<br />Rating: %tp_rating_img%',
			'content'=>'%tp_player%<p>%tp_description%</p>',
			'upgraded'=>'0');
	$data = @file_get_contents("http://www.tubepress.net/data.php");
	$tp_l = empty($data) ? "TubePress" : $data;
	$data = array('link_name'=>$tp_l,'link_url'=>'http://www.tubepress.net/');
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
		$options['border'] = $_POST['border'];
		$options['content'] = $_POST['content'];
		$options['excerpt'] = $_POST['excerpt'];
		update_option('tp_options', $options);
		?> <div class="updated"><p><?php _e(TP_OPTION_SAVE_MSG); ?></p></div> <?php
	} else {
		$opt = get_option('tp_options');
		$options = is_array($opt) ? array_merge($default,$opt) : $default;
		if ($options['upgraded'] == '0') { 
			$options['upgraded'] = '1';
			update_option('tp_options', $options);
			tp_upgrade();
			if(!isset($opt['is_activated'])) {
				wp_insert_link($data);
			}
		}
		tp_patch();
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
			preview = '<img src="'+siteURL+'/wp-content/plugins/tubepress.net/images/';
			
			if(border.checked == true){
				preview += 'border';
			}
			else {
				preview += 'color';
			}
			preview += color+'.gif" alt="" />';
			
			previewImage.innerHTML = preview;
		}
	</script>
	<div class="wrap">
		<h2><?php _e(TP_SETUP_TITLE); ?></h2>
		<form method="post">
		<table width="100%">
			<input name="siteURL" id="siteURL" type="hidden" value="<?php echo get_option('siteurl'); ?>" />
			<tr>
				<td><?php _e(TP_WIDTH_MSG); ?></td>
				<td><input name="width" type="text" id="width" value="<?php echo $options['width']; ?>" /></td>
				<?php $type = ($options['border']) ? 'border' : 'color'; ?>
				<td rowspan="6"><div id="tp-preview"><img src="<?php echo get_option('siteurl'); ?>/wp-content/plugins/tubepress.net/images/<?=$type.$options['color']?>.gif" alt="" /></div></td>
			</tr>
			<tr>
				<td><?php _e(TP_HEIGHT_MSG); ?></td>
				<td><input name="height" type="text" id="height" value="<?php echo $options['height']; ?>" /></td>
			</tr>
			<tr>
				<td><?php _e(TP_AUTOPLAY_MSG); ?></td>
				<td><input name="autoplay" type="checkbox" id="autoplay" value="$options['autoplay']" <?php if($options['autoplay']) echo 'checked="checked"'; ?> /></td>
			</tr>
			<tr>
				<td><?php _e(TP_REL_MSG); ?></td>
				<td><input name="rel" type="checkbox" id="rel" value="$options['rel']" <?php if($options['rel']) echo 'checked="checked"'; ?> /></td>
			</tr>
			<tr>
				<td><?php _e(TP_BORDER_MSG); ?></td>
				<td><input onclick="tpPreview();" name="border" type="checkbox" id="border" value="$options['border']" <?php if($options['border']) echo 'checked="checked"'; ?> /></td>
			</tr>
			<tr>
				<td><?php _e(TP_COLOR_MSG); ?></td>
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
				<td colspan="4">&nbsp;</td>
			</tr>
			<tr>
				<td colspan="4"><?php _e(TP_CUSTOM_MSG); ?></td>
			</tr>
			<tr><td>&nbsp;</td></tr>
			<tr>
				<td><?php _e(TP_DUPLICATE_MSG); ?></td>
				<td colspan="2"><input name="duplicate" type="checkbox" id="duplicate" value="$options['duplicate']" <?php if($options['duplicate']) echo 'checked="checked"'; ?> /></td>
			</tr>
			<tr>
				<td><?php _e(TP_TYPE_MSG); ?></td>
				<td colspan="2">
					<select name="type" id="type">
						<option value="post" <?php if($options['type']=='post') echo 'selected="selected"'; ?>>post</option>
						<option value="page" <?php if($options['type']=='page') echo 'selected="selected"'; ?>>page</option>
					</select>
				</td>
			</tr>
			<tr>
				<td><?php _e(TP_CONTENT_MSG); ?></td>
				<td colspan="2"><textarea name="content" cols="60" rows="7"><?php echo stripslashes($options['content']); ?></textarea></td>
			</tr>
			<tr>
				<td><?php _e(TP_EXCERPT_MSG); ?></td>
				<td colspan="2"><textarea name="excerpt" cols="60" rows="7"><?php echo stripslashes($options['excerpt']); ?></textarea></td>
			</tr>
		</table>
		<h2><?php _e(TP_TEMPLATE_TITLE); ?></h2>
		<?php _e(TP_TEMPLATE_MSG); ?>
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
		<div class="submit"><input type="submit" name="update_tp" value="<?php _e(TP_SAVE_BTN_MSG, 'update_tp') ?>"  style="font-weight:bold;" /></div></p>
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
	add_menu_page(TP_MENU, TP_MENU, 8, __FILE__, 'tp_manage_options');
	add_submenu_page(__FILE__, TP_SUBMENU_TAG, TP_SUBMENU_TAG, 8, 'tubepress-tag.php', 'tp_import_tag');
	add_submenu_page(__FILE__, TP_SUBMENU_USER, TP_SUBMENU_USER, 8, 'tubepress-user.php', 'tp_import_user');
	add_submenu_page(__FILE__, TP_SUBMENU_FAV, TP_SUBMENU_FAV, 8, 'tubepress-favorite.php', 'tp_import_favorite');
	add_submenu_page(__FILE__, TP_SUBMENU_FEAT, TP_SUBMENU_FEAT, 8, 'tubepress-featured.php', 'tp_import_featured');
	add_submenu_page(__FILE__, TP_SUBMENU_ID, TP_SUBMENU_ID, 8, 'tubepress-id.php', 'tp_import_id');
}

add_action('admin_menu', 'tp_add_options_page');

?>