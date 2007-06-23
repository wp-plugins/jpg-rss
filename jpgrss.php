<?php
/*
Plugin Name: jpgrss
Plugin URI: http://invalidresponse.com
Description: Allows you to integrate the photos from a JPG rss feed into your site. Based on <a href="http://eightface.com">Dave Kellam's</a> <a href="http://eightface.com/wordpress/flickrRSS/">FlickrRSS plugin</a>.
Version: 1.0
License: GPL
Author: Devin Hayes
Author URI: http://jpgmag.com
*/

function get_jpgrss($args=array()) {
	
	extract($args);
	
	/*	Over-ride configuration for multiple instances
	
		$getArgs = array('num_items'=>5,
						 'rss_url'=>'http://jpgmag.com/photos/rss',
						 'image_size'=>'small',
						 'before_image'=>'<li class="jpg-thumb">',
						 'after_image'=>'</li>');
		
		get_jpgrss($getArgs);
		
	*/
	
  	if (!isset($num_items)) $num_items = get_option('jpgrss_display_numitems');
  	if (!isset($rss_url)) $rss_url = get_option('jpgrss_feed_url');
  	if (!isset($image_size)) $image_size = get_option('jpgrss_display_imagesize'); 
  	if (!isset($before_image)) $before_image = stripslashes(get_option('jpgrss_before'));
  	if (!isset($after_image)) $after_image = stripslashes(get_option('jpgrss_after'));
        
	# use image cache & set location
	$useImageCache = (int)get_option('jpgrss_use_image_cache');
	if($useImageCache){
		$cachePath = get_option('jpgrss_image_cache_uri');
		$fullPath = get_option('jpgrss_image_cache_dest'); 
	}

	if (!function_exists('MagpieRSS')) { // Check if another plugin is using RSS, may not work
		include_once (ABSPATH . WPINC . '/rss.php');
		error_reporting(E_ERROR);
	}

	if($rss_url==''){
		$rss_url = 'http://jpgmag.com/photos/submitted/rss'; 
	}

	# get rss file
	$rss = @ fetch_rss($rss_url);

	if($rss){
    	
    	$imgurl = '';
    	# specifies number of pictures
		$items = array_slice($rss->items, 0, $num_items);

	    # builds html from array
    	foreach ($items as $item) {
       		if(preg_match('<img src="([^"]*)" [^/]*/>',$item['description'],$imgUrlMatches)){
           		$imgurl = $imgUrlMatches[1];

				#change image size         
				// t, m, p (thumb,small,photo)
				switch($image_size){
					case 'large': $imgurl = str_replace("m.jpg", "p.jpg", $imgurl); break;
					case 'medium': break;
					default:
					case 'small': $imgurl = str_replace("m.jpg", "t.jpg", $imgurl); break;
				}
			   
			   $title = htmlspecialchars(stripslashes($item['title']),ENT_QUOTES,'UTF-8');
			   $url = $item['link'];
		
			   preg_match('#http://photos.jpgmag.com/([a-z0-9_]+\.jpg)#i', $imgurl, $jpgSlugMatches);
			   $jpgSlug = $jpgSlugMatches[1];
			   
			   if($jpgSlug=='') continue;
			   
			   # cache images 
			   if ($useImageCache and (is_dir($fullPath) and is_writable($fullPath))) {						  
				   # check if file already exists in cache
				   # if not, grab a copy of it
				   if (!file_exists("$fullPath$jpgSlug")) {   
					 	if (function_exists('curl_init') ) { // check for CURL, if not use fopen
							$curl = curl_init();
							$localimage = fopen("$fullPath$jpgSlug",'wb');
							curl_setopt($curl, CURLOPT_URL, $imgurl);
							curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 1);
							curl_setopt($curl, CURLOPT_FILE, $localimage);
							curl_exec($curl);
							curl_close($curl);
							fclose($localimage);
					   	} else {
							$filedata = "";
							$remoteimage = fopen($imgurl,'rb');
							if ($remoteimage) {
								 while(!feof($remoteimage)) {
									$filedata.= fread($remoteimage,1024*8);
								 }
							}
							fclose($remoteimage);
							$localimage = fopen("$fullPath$jpgSlug",'wb');
							fwrite($localimage,$filedata);
							fclose($localimage);
					 	} // end CURL check
					} // end file check
					# use cached image
					echo $before_image . "<a href=\"$url\" title=\"$title\"><img src=\"$cachePath$jpgSlug\" alt=\"$title\" /></a>" . $after_image;
            } else {
                # grab image direct from jpg
                echo $before_image . "<a href=\"$url\" title=\"$title\"><img src=\"$imgurl\" alt=\"$title\" /></a>" . $after_image;      
            } // end use imageCache
       } // end pregmatch
     } // end foreach
  } 
} # end get_jpgrss() function

function widget_jpgrss_init() {
	if (!function_exists('register_sidebar_widget')) return;

	function widget_jpgrss($args) {
		
		extract($args);

		$options = get_option('widget_jpgrss');
		$title = $options['title'];
		
		echo $before_widget . $before_title . $title . $after_title;
		get_jpgrss();
		echo $after_widget;
	}

	function widget_jpgrss_control() {
		$options = get_option('widget_jpgrss');
		if (!is_array($options) )
			$options = array('title'=>'');
		if ( $_POST['jpgrss-submit'] ) {
			$options['title'] = strip_tags(stripslashes($_POST['jpgrss-title']));
			update_option('widget_jpgrss', $options);
		}

		$title = htmlspecialchars($options['title'], ENT_QUOTES);
		
		echo '<p style="text-align:right;"><label for="jpgrss-title">Title: <input style="width: 200px;" id="gsearch-title" name="jpgrss-title" type="text" value="'.$title.'" /></label></p>';
		echo '<input type="hidden" id="jpgrss-submit" name="jpgrss-submit" value="1" />';
	}		

	register_sidebar_widget('jpgrss', 'widget_jpgrss');
	register_widget_control('jpgrss', 'widget_jpgrss_control', 300, 100);
}

function jpgrss_subpanel() {
	if($_POST){
		foreach($_POST as $k=>$v){
			$_POST[$k] = trim($v);
		}
	}
	$img_sizes = array('small','medium','large');
	if(isset($_POST['update_jpgrss'])) {
		update_option('jpgrss_feed_url',$_POST['feed_url']);
		update_option('jpgrss_display_numitems',(int)$_POST['display_numitems']);
		if(!in_array(strtolower($_POST['display_imagesize']),$img_sizes)){
			$_POST['display_imagesize'] = $img_sizes['0'];
		}
		update_option('jpgrss_display_imagesize',strtolower($_POST['display_imagesize']));
		update_option('jpgrss_before',$_POST['before_image']);
		update_option('jpgrss_after',$_POST['after_image']);
		echo '<div id="message" class="updated fade"><p>Options changes saved.</p></div>';
	} elseif(isset($_POST['save_cache_settings'])) {
		if(substr($_POST['image_cache_uri'], -1, 1)!='/'){
			$_POST['image_cache_uri'] = $_POST['image_cache_uri'].'/';
		}
		if(substr($_POST['image_cache_dest'], -1, 1)!='/'){
			$_POST['image_cache_dest'] = $_POST['image_cache_dest'].'/';
		}
		if(!is_dir($_POST['image_cache_dest']) OR !is_writable($_POST['image_cache_dest'])){
			$_POST['use_image_cache'] = 0;
		}
		update_option('jpgrss_use_image_cache',$_POST['use_image_cache']);
		update_option('jpgrss_image_cache_uri',$_POST['image_cache_uri']);
		update_option('jpgrss_image_cache_dest',$_POST['image_cache_dest']);
		if($_POST['clear_cache'] and is_dir($_POST['image_cache_dest'])){
			if($handle = @opendir($_POST['image_cache_dest'])) {
				$ignore = array('.','..','index.html');
				while(($file = readdir($handle)) !== false) {
					if(!in_array($file,$ignore)){
						@unlink($_POST['image_cache_dest'].$file);
					}
				}
			}
			closedir($handle);	
		}
       	echo '<div id="message" class="updated fade"><p>Cache settings saved.</p></div>';
     }

	?>

<div class="wrap">
	<h2>JPG RSS Options</h2>
	<form method="post">
		<fieldset class="options">
			<table>
				<tr>
					<td><p><strong>Feed URL:</strong></p></td>
					<td><input type="text" name="feed_url" value="<?=get_option('jpgrss_feed_url');?>" style="width:350px;" /> <em>eg: http://jpgmag.com/photos/rss</em> <a href="http://jpgmag.com/blog/2007/04/jpg_loves_rss.html" title="JPG RSS Feeds">[more]</a></td>
				</tr>
				<tr>
					<td><p><strong>Display:</strong></p></td>
					<td>
						<select name="display_numitems" id="display_numitems">
						<?php
							$num = get_option('jpgrss_display_numitems');
							for($i=1; $i<21; ++$i){
								$sel = '';
								if($i==$num) $sel = ' selected="selected"';
								echo '<option value="'.$i.'"'.$sel.'>'.$i.'</option>';
							}
						?>
						</select>
						<select name="display_imagesize" id="display_imagesize">
						<?php
							$sel_size = get_option('jpgrss_display_imagesize');
							foreach($img_sizes as $val){
								$sel = '';
								if($val==$sel_size) $sel = ' selected="selected"';
								echo '<option value="'.$val.'"'.$sel.'>'.ucwords($val).'</option>';
							}
						?>
						</select>
						<label for="mediumImages">images</label></p>
					</td> 
				</tr>
				<tr>
					<td><p><strong><label for="before_image">Before</label>/<label for="after_image">After</label>:</strong></p></td>
					<td>
						<input name="before_image" type="text" id="before_image" value="<?php echo htmlspecialchars(stripslashes(get_option('jpgrss_before'))); ?>" size="10" /> / 
						<input name="after_image" type="text" id="after_image" value="<?php echo htmlspecialchars(stripslashes(get_option('jpgrss_after'))); ?>" size="10" /> <em> e.g. &lt;li&gt;&lt;/li&gt;, &lt;p&gt;&lt;/p&gt;</em></p>
					</td>
				</tr>
			</table>
		</fieldset>
		<p><div class="submit"><input type="submit" name="update_jpgrss" value="<?php _e('Update jpgrss', 'update_jpgrss') ?>"  style="font-weight:bold;" /></div></p>
	</form>       
</div>

<div class="wrap">   
	<h2>Cache Settings</h2>
	<form method="post" onsubmit="if(this.clear_cache.checked==true){ return confirm('\nAre you sure you want to delete all of your image cache?\n'); }">
		<fieldset class="options">
			<table>
				<tr>
					<td><p><strong><label for="image_cache_uri">URL:</label></strong></td>
					<td><input name="image_cache_uri" type="text" id="image_cache_uri" value="<?php echo get_option('jpgrss_image_cache_uri'); ?>" size="50" /> <em>e.g. http://url.com/jpgrss/cache/</em></p>
				</tr>
				<tr>
					<td><p><strong><label for="image_cache_dest">Full Path:</label></strong></td>
					<?php
						$cache_dest = get_option('jpgrss_image_cache_dest');
						$warn = false;
						if((!is_dir($cache_dest) OR !is_writable($cache_dest)) and $cache_dest!='') $warn = true;
					?>
					<td><input name="image_cache_dest" type="text" id="image_cache_dest" value="<?php echo get_option('jpgrss_image_cache_dest'); ?>" size="50" /> <?php if(!$warn){ ?><em>e.g. /home/path/to/wp-content/jpgrss/cache/</em> <?php } else { ?> <strong style="color:red">Directory does not exist OR is not writable!</strong> <?php } ?></p></td>
				</tr>
				<tr>
					<td></td>
					<td><p><input name="use_image_cache" type="checkbox" id="use_image_cache" value="1" <?php if(get_option('jpgrss_use_image_cache') == '1') { echo 'checked="checked"'; } ?> />  <label for="use_image_cache"><strong>Use image cache</strong> (stores thumbnails on your server)</label></p></td>
				</tr>
				<?php if(!$warn){ ?>
				<tr>
					<td></td>
					<td><p><input name="clear_cache" id="clear_cache" type="checkbox" value="1" />  <label for="clear_cache"><strong>Clear image cache</strong> (removes cached thumbnails from your server)</label></p></td>
				</tr>
				<?php } ?>
			</table>
		</fieldset>
		<p><div class="submit"><input type="submit" name="save_cache_settings" value="<?php _e('Save Settings', 'save_cache_settings') ?>" style="font-weight:bold;" /></div></p>
	</form>
</div>

<?php } // end jpgrss_subpanel()

function jR_admin_menu() {
	if (function_exists('add_options_page')) {
		add_options_page('JPG RSS Options Page', 'JPG RSS', 8, basename(__FILE__), 'jpgrss_subpanel');
	}
}

add_action('admin_menu', 'jR_admin_menu'); 
add_action('plugins_loaded', 'widget_jpgrss_init');
?>