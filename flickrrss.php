<?php
/*
Plugin Name: flickrRSS
Plugin URI: http://eightface.com/wordpress/flickrrss/
Description: Allows you to integrate the photos from a flickr rss feed into your site.
Version: 3.2
License: GPL
Author: Dave Kellam
Author URI: http://eightface.com
*/

function get_flickrRSS() {

	// the function can accept up to seven parameters, otherwise it uses option panel defaults 	
  	for($i = 0 ; $i < func_num_args(); $i++) {
    	$args[] = func_get_arg($i);
    	}
  	if (!isset($args[0])) $num_items = get_option('flickrRSS_display_numitems'); else $num_items = $args[0];
  	if (!isset($args[1])) $type = get_option('flickrRSS_display_type'); else $type = $args[1];
  	if (!isset($args[2])) $tags = trim(get_option('flickrRSS_tags')); else $tags = trim($args[2]);
  	if (!isset($args[3])) $imagesize = get_option('flickrRSS_display_imagesize'); else $imagesize = $args[3];
  	if (!isset($args[4])) $before_image = stripslashes(get_option('flickrRSS_before')); else $before_image = $args[4];
  	if (!isset($args[5])) $after_image = stripslashes(get_option('flickrRSS_after')); else $after_image = $args[5];
  	if (!isset($args[6])) $userid = stripslashes(get_option('flickrRSS_flickrid')); else $userid = $args[6];
        
	# use image cache & set location
	$useImageCache = get_option('flickrRSS_use_image_cache');
	$cachePath = get_option('flickrRSS_image_cache_uri');
	$fullPath = get_option('flickrRSS_image_cache_dest'); 

	if (!function_exists('MagpieRSS')) { // Check if another plugin is using RSS, may not work
		include_once (ABSPATH . WPINC . '/rss.php');
		error_reporting(E_ERROR);
	}


	// get the feeds
	if ($type == "public") { $rss_url = 'http://api.flickr.com/services/feeds/photos_public.gne?tags=' . $tags . '&format=rss_200'; }
	elseif ($type == "user") { $rss_url = 'http://api.flickr.com/services/feeds/photos_public.gne?id=' . $userid . '&tags=' . $tags . '&format=rss_200'; }
	elseif ($type == "group") { $rss_url = 'http://api.flickr.com/services/feeds/groups_pool.gne?id=' . $userid . '&format=rss_200'; }
	else { print "flickrRSS probably needs to be setup"; }

	# get rss file
	$rss = @ fetch_rss($rss_url);

	if ($rss) {
    	$imgurl = "";
    	# specifies number of pictures
		$items = array_slice($rss->items, 0, $num_items);

	    # builds html from array
    	foreach ( $items as $item ) {
       	 if(preg_match('<img src="([^"]*)" [^/]*/>', $item['description'],$imgUrlMatches)) {
            	$imgurl = $imgUrlMatches[1];
 
            #change image size         
           	if ($imagesize == "square") {
             	$imgurl = str_replace("m.jpg", "s.jpg", $imgurl);
           	} elseif ($imagesize == "thumbnail") {
             $imgurl = str_replace("m.jpg", "t.jpg", $imgurl);
           	} elseif ($imagesize == "medium") {
             $imgurl = str_replace("_m.jpg", ".jpg", $imgurl);
           	}
           
           $title = htmlspecialchars(stripslashes($item['title']));
           $url = $item['link'];
	
	       preg_match('<http://farm[0-9]{0,3}\.static.flickr\.com/\d+?\/([^.]*)\.jpg>', $imgurl, $flickrSlugMatches);
	       $flickrSlug = $flickrSlugMatches[1];
	       
	       # cache images 
	       if ($useImageCache) {
                      
               # check if file already exists in cache
               # if not, grab a copy of it
               if (!file_exists("$fullPath$flickrSlug.jpg")) {   
                 if ( function_exists('curl_init') ) { // check for CURL, if not use fopen
                    $curl = curl_init();
                    $localimage = fopen("$fullPath$flickrSlug.jpg", "wb");
                    curl_setopt($curl, CURLOPT_URL, $imgurl);
                    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 1);
                    curl_setopt($curl, CURLOPT_FILE, $localimage);
                    curl_exec($curl);
                    curl_close($curl);
                   } else {
                 	$filedata = "";
                    $remoteimage = fopen($imgurl, 'rb');
                  	if ($remoteimage) {
                    	 while(!feof($remoteimage)) {
                         	$filedata.= fread($remoteimage,1024*8);
                       	 }
                  	}
                	fclose($remoteimage);
                	$localimage = fopen("$fullPath$flickrSlug.jpg", 'wb');
                	fwrite($localimage,$filedata);
                	fclose($localimage);
                 } // end CURL check
                } // end file check
                # use cached image
                print $before_image . "<a href=\"$url\" title=\"$title\"><img src=\"$cachePath$flickrSlug.jpg\" alt=\"$title\" /></a>" . $after_image;
            } else {
                # grab image direct from flickr
                print $before_image . "<a href=\"$url\" title=\"$title\"><img src=\"$imgurl\" alt=\"$title\" /></a>" . $after_image;      
            } // end use imageCache
       } // end pregmatch
     } // end foreach
  } else {
    #print "Flickr is having a massage (Flickr Blog)";
  }
} # end get_flickrRSS() function

function widget_flickrrss_init() {
	if (!function_exists('register_sidebar_widget')) return;

	function widget_flickrrss($args) {
		
		extract($args);

		$options = get_option('widget_flickrrss');
		$title = $options['title'];
		
		echo $before_widget . $before_title . $title . $after_title;
		get_flickrRSS();
		echo $after_widget;
	}

	function widget_flickrrss_control() {
		$options = get_option('widget_flickrrss');
		if ( !is_array($options) )
			$options = array('title'=>'');
		if ( $_POST['flickrrss-submit'] ) {
			$options['title'] = strip_tags(stripslashes($_POST['flickrrss-title']));
			update_option('widget_flickrrss', $options);
		}

		$title = htmlspecialchars($options['title'], ENT_QUOTES);
		
		echo '<p style="text-align:right;"><label for="flickrrss-title">Title: <input style="width: 200px;" id="gsearch-title" name="flickrrss-title" type="text" value="'.$title.'" /></label></p>';
		echo '<input type="hidden" id="flickrrss-submit" name="flickrrss-submit" value="1" />';
	}		

	register_sidebar_widget('flickrRSS', 'widget_flickrrss');
	register_widget_control('flickrRSS', 'widget_flickrrss_control', 300, 100);
}

function flickrRSS_subpanel() {
     if (isset($_POST['update_flickrrss'])) {
       $option_flickrid = $_POST['flickr_nsid'];
       $option_tags = $_POST['tags'];
       $option_display_type = $_POST['display_type'];
       $option_display_numitems = $_POST['display_numitems'];
       $option_display_imagesize = $_POST['display_imagesize'];
       $option_before = $_POST['before_image'];
       $option_after = $_POST['after_image'];
       update_option('flickrRSS_flickrid', $option_flickrid);
       update_option('flickrRSS_tags', $option_tags);
       update_option('flickrRSS_display_type', $option_display_type);
       update_option('flickrRSS_display_numitems', $option_display_numitems);
       update_option('flickrRSS_display_imagesize', $option_display_imagesize);
       update_option('flickrRSS_before', $option_before);
       update_option('flickrRSS_after', $option_after);
       ?> <div class="updated"><p>Options changes saved.</p></div> <?php
     }
     if (isset($_POST['save_cache_settings'])) {
       $option_useimagecache = $_POST['use_image_cache'];
       $option_imagecacheuri = $_POST['image_cache_uri'];
       $option_imagecachedest = $_POST['image_cache_dest'];
       update_option('flickrRSS_use_image_cache', $option_useimagecache);
       update_option('flickrRSS_image_cache_uri', $option_imagecacheuri);
       update_option('flickrRSS_image_cache_dest', $option_imagecachedest);
       ?> <div class="updated"><p>Cache settings saved.</p></div> <?php
     }

	?>

	<div class="wrap">
		<h2>flickrRSS Options</h2>
		<form method="post">
		
		<fieldset class="options">
		<table>
		 <tr>
		  <td><p><strong><label for="flickr_nsid">User ID</label>:</strong></p></td>
	      <td><input name="flickr_nsid" type="text" id="flickr_nsid" value="<?php echo get_option('flickrRSS_flickrid'); ?>" size="20" />
        		Use the <a href="http://idgettr.com">idGettr</a> to find your id.</p></td>
         </tr>
         <tr>
          <td><p><strong>Display:</strong></p></td>
          <td>
        	<select name="display_type" id="display_type">
        	  <option <?php if(get_option('flickrRSS_display_type') == 'user') { echo 'selected'; } ?> value="user">user</option>
		      <option <?php if(get_option('flickrRSS_display_type') == 'public') { echo 'selected'; } ?> value="public">public</option>
		      <option <?php if(get_option('flickrRSS_display_type') == 'group') { echo 'selected'; } ?> value="group">group</option>
		    </select>
		 	photos using 
        	<select name="display_numitems" id="display_numitems">
		      <option <?php if(get_option('flickrRSS_display_numitems') == '1') { echo 'selected'; } ?> value="1">1</option>
		      <option <?php if(get_option('flickrRSS_display_numitems') == '2') { echo 'selected'; } ?> value="2">2</option>
		      <option <?php if(get_option('flickrRSS_display_numitems') == '3') { echo 'selected'; } ?> value="3">3</option>
		      <option <?php if(get_option('flickrRSS_display_numitems') == '4') { echo 'selected'; } ?> value="4">4</option>
		      <option <?php if(get_option('flickrRSS_display_numitems') == '5') { echo 'selected'; } ?> value="5">5</option>
		      <option <?php if(get_option('flickrRSS_display_numitems') == '6') { echo 'selected'; } ?> value="6">6</option>
		      <option <?php if(get_option('flickrRSS_display_numitems') == '7') { echo 'selected'; } ?> value="7">7</option>
		      <option <?php if(get_option('flickrRSS_display_numitems') == '8') { echo 'selected'; } ?> value="8">8</option>
		      <option <?php if(get_option('flickrRSS_display_numitems') == '9') { echo 'selected'; } ?> value="9">9</option>
		      <option <?php if(get_option('flickrRSS_display_numitems') == '10') { echo 'selected'; } ?> value="10">10</option>
		      <option <?php if(get_option('flickrRSS_display_numitems') == '11') { echo 'selected'; } ?> value="11">11</option>
		      <option <?php if(get_option('flickrRSS_display_numitems') == '12') { echo 'selected'; } ?> value="12">12</option>
		      <option <?php if(get_option('flickrRSS_display_numitems') == '13') { echo 'selected'; } ?> value="13">13</option>
		      <option <?php if(get_option('flickrRSS_display_numitems') == '14') { echo 'selected'; } ?> value="14">14</option>
		      <option <?php if(get_option('flickrRSS_display_numitems') == '15') { echo 'selected'; } ?> value="15">15</option>
		      <option <?php if(get_option('flickrRSS_display_numitems') == '16') { echo 'selected'; } ?> value="16">16</option>
		      <option <?php if(get_option('flickrRSS_display_numitems') == '17') { echo 'selected'; } ?> value="17">17</option>
		      <option <?php if(get_option('flickrRSS_display_numitems') == '18') { echo 'selected'; } ?> value="18">18</option>
		      <option <?php if(get_option('flickrRSS_display_numitems') == '19') { echo 'selected'; } ?> value="19">19</option>
		      <option <?php if(get_option('flickrRSS_display_numitems') == '20') { echo 'selected'; } ?> value="20">20</option>
		      </select>
            <select name="display_imagesize" id="display_imagesize">
		      <option <?php if(get_option('flickrRSS_display_imagesize') == 'square') { echo 'selected'; } ?> value="square">square</option>
		      <option <?php if(get_option('flickrRSS_display_imagesize') == 'thumbnail') { echo 'selected'; } ?> value="thumbnail">thumbnail</option>
		      <option <?php if(get_option('flickrRSS_display_imagesize') == 'small') { echo 'selected'; } ?> value="small">small</option>
		      <option <?php if(get_option('flickrRSS_display_imagesize') == 'medium') { echo 'selected'; } ?> value="medium">medium</option>
		    </select>
            <label for="mediumImages">images</label></p>
           </td> 
         </tr>
         <tr>
		  <td><p><strong><label for="tag">Tags:</strong></label></p></td>
          <td><input name="tags" type="text" id="tags" value="<?php echo get_option('flickrRSS_tags'); ?>" size="40" /> Comma separated, no spaces</p>
         </tr>
         <tr>
          <td><p><strong><label for="before_image">Before</label>/<label for="after_image">After</label>:</strong></p></td>
          <td><input name="before_image" type="text" id="before_image" value="<?php echo htmlspecialchars(stripslashes(get_option('flickrRSS_before'))); ?>" size="10" /> / 
        	  <input name="after_image" type="text" id="after_image" value="<?php echo htmlspecialchars(stripslashes(get_option('flickrRSS_after'))); ?>" size="10" /> <em> e.g. &lt;li&gt;&lt;/li&gt;, &lt;p&gt;&lt;/p&gt;</em></p>
          </td>
         </tr>
         </table>
        </fieldset>

		<p><div class="submit"><input type="submit" name="update_flickrrss" value="<?php _e('Update flickrRSS', 'update_flickrrss') ?>"  style="font-weight:bold;" /></div></p>
        </form>       
    </div>
    
    <div class="wrap">   
        <h2>Cache Settings</h2>

		<form method="post">
        <fieldset class="options">
		<table>
         <tr>
          <td><p><strong><label for="image_cache_uri">URL:</label></strong></td>
          <td><input name="image_cache_uri" type="text" id="image_cache_uri" value="<?php echo get_option('flickrRSS_image_cache_uri'); ?>" size="50" /> <em>e.g. http://url.com/cache/</em></p>
         </tr>
         <tr>
          <td><p><strong><label for="image_cache_dest">Full Path:</label></strong></td>
          <td><input name="image_cache_dest" type="text" id="image_cache_dest" value="<?php echo get_option('flickrRSS_image_cache_dest'); ?>" size="50" /> <em>e.g. /home/path/to/wp-content/flickrrss/cache/</em></p></td>
         </tr>
		 <tr>
		  <td></td>
		  <td><p><input name="use_image_cache" type="checkbox" id="use_image_cache" value="true" <?php if(get_option('flickrRSS_use_image_cache') == 'true') { echo 'checked="checked"'; } ?> />  <label for="use_image_cache"><strong>Use image cache</strong> (stores thumbnails on your server)</label></p></td>
		 </tr>
        </table>
        </fieldset>
        <p><div class="submit">
           <input type="submit" name="save_cache_settings" value="<?php _e('Save Settings', 'save_cache_settings') ?>" style="font-weight:bold;" /></div>
        </p>
        </form>
    </div>

<?php } // end flickrRSS_subpanel()

function fR_admin_menu() {
   if (function_exists('add_options_page')) {
        add_options_page('flickrRSS Options Page', 'flickrRSS', 8, basename(__FILE__), 'flickrRSS_subpanel');
        }
}

add_action('admin_menu', 'fR_admin_menu'); 
add_action('plugins_loaded', 'widget_flickrrss_init');
?>