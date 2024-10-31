<?php



/*
Plugin Name: Nomads Connected
Plugin URI: http://www.nomadsconnected.com/integration/wordpress.html
Description: Interface to the <a href="http://www.nomadsconnected.com">Nomads Connected</a> Travel Network. Allows you to include Google Maps of your route (or parts of it) in your articles and pages. You need a free Nomads Connected user account to use it.
Version: 2.0.1
Author: Frank H&ouml;fer
Minimum WordPress Version Required: 3.5
*/


/**
 * The Nomads Connected class
 */
class WPNomadsConnected
{
	/**
	 * Properties
	 */
	 
	var $version = '2.0.1';

	/**
	 * Constructor
	 */
	function WPNomadsConnected()
	{
	}
	
	/**
	 * Read Options
	 */
	function read_options() {

		$defaults = array(
			'google_api_key' => '', 
			'nc_api_key' => '',
			'google_map_type' => 0,  // 0 = Map, 1 = Satallite, 2 = Hybrid
			'google_map_height' => 350,  // height in pixels
			'marker_color' => 'red',
			'line_past_color' => 'AA0000',  // line colors in RGB
			'line_future_color' => '000000',
			'line_size' => '2',          // thickness of lines
			'days_between' => '3',       // connect only points with x days between
		);
		$wp_nc_options = get_option('wp_nc_options');
		foreach ($defaults as $key => $val)
		{
			if (!isset($wp_nc_options[$key]))
			{
				$wp_nc_options[$key] = $defaults[$key];
			}
		}
		return $wp_nc_options;
	}

	/**
	 * Register Activation
	 */
	function register_activation()
	{
		
		global $wpnc;
		
		$options = array(
			'google_api_key' => '', 
			'nc_api_key' => '',
			'google_map_type' => 0,  // 0 = Map, 1 = Satallite, 2 = Hybrid
			'google_map_height' => 350,  // height in pixels
			'line_future_color' => 'FF0000',  // line colors in RGB
			'line_past_color' => '0000FF',
			'line_size' => '2',          // thickness of lines
			'days_between' => '3',       // connect only points with x days between
		);
		add_option('wp_nc_options', $options);
		$wp_nc_options = $wpnc->read_options();
		update_option('wp_nc_options', $wp_nc_options);
	}


	/**
	 * Hook: wp_head
	 */
	function wp_head()
	{
		global $wpnc, $posts;
		$wp_nc_options = $wpnc->read_options();

		echo '
<!-- BEGIN Nomads Connected -->
';
		
		// first find out if there are any maps
		$have_maps = false;
		for ($i = 0; $i < count($posts); $i++)
		{
			$post = $posts[$i];
			
			$show_map = get_post_meta($post->ID, '_wpnc_show_map', true);
			$date_from = get_post_meta($post->ID, '_wpnc_from', true);
			$date_through = get_post_meta($post->ID, '_wpnc_through', true);
			
			if($show_map == 'Y' && !empty($date_from) && !empty($date_through)) {
				$have_maps = true;
			}
		}
		
		if($have_maps) {

			// Get all waypoints
			$url = 'http://www.nomadsconnected.com/api/wp.html?apiKey='.$wp_nc_options['nc_api_key'].'&mode=2';

		  // get data from Nomads Connected
		  $ch = curl_init();
		  curl_setopt($ch, CURLOPT_URL, $url);
		  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		  curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		  $json = curl_exec($ch);
		  curl_close($ch);
		  if($json == null || $json == "") {
		  	$json = '{"result":-1}';
		  }
			$json = str_replace("\\'","\u0027",$json); // replace nasty escaped single quotes that json_decode can't handle
		
			// process data
			$arr = json_decode($json,true);
			if($arr['result'] == 0) {
			
				echo '
<script type="text/javascript">
//<![CDATA[

var marker_image = {
	url: "' . WP_CONTENT_URL . '/plugins/nomads-connected/img/marker/'.$wp_nc_options['marker_color'].'-pushpin.png",
	size: new google.maps.Size(32.0, 32.0),
	origin: new google.maps.Point(0,0),
	anchor: new google.maps.Point(10.0, 32.0)
}
var marker_shadow = {
	url: "' . WP_CONTENT_URL . '/plugins/nomads-connected/img/marker/pushpin_shadow.png",
	size: new google.maps.Size(59.0, 32.0),
	origin: new google.maps.Point(0,0),
	anchor: new google.maps.Point(10.0, 32.0)
}
		
		
function ncLoad() {
';
				
				for ($i = 0; $i < count($posts); $i++)
				{
					$post = $posts[$i];
					
					$show_map = get_post_meta($post->ID, '_wpnc_show_map', true);
					$date_from = get_post_meta($post->ID, '_wpnc_from', true);
					$date_through = get_post_meta($post->ID, '_wpnc_through', true);
					
					if($show_map == 'Y') {
						$wpnc->render_googlemap_js($post->ID, $arr['waypoints'], $date_from, $date_through);
					}
				}

				echo '
}
				
function ncAddLoadEvent(func)
{	
	var oldonload = window.onload;
	if (typeof window.onload != "function"){
    	window.onload = func;
	} else {
		window.onload = function(){
			oldonload();
			func();
		}
	}
}
function ncAddUnloadEvent(func)
{	
	var oldonunload = window.onunload;
	if (typeof window.onunload != "function"){
    	window.onunload = func;
	} else {
		window.onunload = function(){
			oldonunload();
			func();
		}
	}
}
ncAddLoadEvent(ncLoad);
// ncAddUnloadEvent(GUnload);
				
//]]>
</script>

<!-- Fix broken Google map display in Twenty Eleven theme -->						
<style type="text/css">div.wpnc_map img { max-width: none; }</style>						
';
				
			}
			else {
				echo '
					<!-- NC error -->';
			} 

		}
		
		echo '
<!-- END Nomads Connected -->
';
	}


	/**
	 * Render the JavaScript for a Google Map
	*/
	function render_googlemap_js($post_id, $wp_array, $date_from, $date_through) {
	
		global $wpnc;
		$wp_nc_options = $wpnc->read_options();
	
		echo '

	var nc_map_options = {
				panControl: false,
				zoomControl: true,
				scaleControl: false
	}
	var mapdiv = document.getElementById("wpnc_map_'.$post_id.'");
	var nc_map = new google.maps.Map(mapdiv, nc_map_options);
	var nc_bounds = new google.maps.LatLngBounds();
	var plp=[];
	var plpp=[];';

		$is_past = true;
		$plwp = array();
		
		
		$min_zoom = 21;
		for($i=0;$i<count($wp_array);$i++) {
			$wp = $wp_array[$i];
			if($this->is_in_range($wp,$date_from,$date_through)) {
				$min_zoom = min($min_zoom, $wp['pr']);
				// extend bounds
				echo '
	nc_bounds.extend(new google.maps.LatLng('.$wp['lat'].','.$wp['lng'].'));';
			}
		}
	
		if($min_zoom < 21) {
			echo '
	nc_map.fitBounds(nc_bounds);';
		}
		else {
			echo '
	nc_map.setZoom(1); nc_map.setCenter(new google.maps.LatLng(30.0,0.0));';
		}
		
		for($i=0;$i<count($wp_array);$i++) {
			$wp = $wp_array[$i];
			if($this->is_in_range($wp,$date_from,$date_through)) {

				// render this marker
				$plwp[] = $wp;
				$bygone_toggle = (isset($bygone) ? $bygone : NULL);
				$bygone = (time() > $wp['vfSec']); // true if the point is past


				if( ($i > 0) &&  ($bygone != $bygone_toggle) && (count($plwp) > 1) ) {	// bygone changed (except first pass of for-loop); put last past point to the first element of future points
					echo 'plp.push(point);';
				}
                             
				echo '
	point=new google.maps.LatLng('.$wp['lat'].','.$wp['lng'].');';

				echo 'var marker=new google.maps.Marker({position:point,map:nc_map,shadow:marker_shadow,icon:marker_image});';
				
				echo (($bygone) ? 'plpp.push(point);' : 'plp.push(point);');

				// need to flush polyline?
				if(count($plwp)>0) {					// polyline is not empty
					if($i==count($wp_array)-1			// last entry
							 || $wp_array[$i+1]['vfSec'] > $wp['vtSec'] + 60*60*24*$wp_nc_options['days_between']		// next entry more than x days away
							 || !($this->is_in_range($wp_array[$i+1],$date_from,$date_through)) 						// next entry out of date range
					){
						if(count($plwp)>1) {				// only need to draw a polyline if there are at least 2 points+
                                                        echo '
    var nc_past = new google.maps.Polyline({map:nc_map,path:plpp,strokeColor:"#'.$wp_nc_options['line_past_color'].'",strokeWeight:'.$wp_nc_options['line_size'].',strokeOpacity:0.7,geodesic:true});plpp=[];
	var nc_future = new google.maps.Polyline({map:nc_map,path:plp,strokeColor:"#'.$wp_nc_options['line_future_color'].'",strokeWeight:'.$wp_nc_options['line_size'].',strokeOpacity:0.4,geodesic:true});plp=[];';
						}
						else {
							echo('plpp=[];plp=[];');	// even if the polylines don't need to be drawn, they need to be discarded.
						}
						$plwp = array();
					}
				}
			}
		}
		
		echo '
	nc_map.setMapTypeId(';
		if($wp_nc_options['google_map_type'] == 2)
			echo 'google.maps.MapTypeId.HYBRID';
		elseif ($wp_nc_options['google_map_type'] == 1)
			echo 'google.maps.MapTypeId.SATELLITE';
		else
			echo 'google.maps.MapTypeId.ROADMAP';
		echo');';
	}
	
	
	/**
	 * Return true if a waypoint is within the given date range
	 */
	function is_in_range($wp,$date_from,$date_through) {
	
		if((strcmp($wp['vf'],$date_from) >= 0) && (strcmp($wp['vt'],$date_through) <= 0)) return true; // completely within
		if((strcmp($wp['vf'],$date_from) < 0) && (strcmp($wp['vt'],$date_through) > 0)) return true; // true overlap at start
		if((strcmp($wp['vt'],$date_through) < 0) && (strcmp($wp['vt'],$date_through) > 0)) return true; // true overlap at end
		
		return false;
	}
	
	/**
	 * Get Google Maps Locale - by Alain Messin, tweaked by Ben :)
	 * See http://code.google.com/apis/maps/faq.html#languagesupport
	 * for link to updated languages codes
	 */
	function get_googlemaps_locale( $before = '', $after = '' ) {
		
		$l = get_locale();
		
		if ( !empty($l) ) {

			// WordPress locale is xx_XX, some codes are known by google with - in place of _ , so replace
			$l = str_replace('_', '-', $l);
			
			// Known Google codes
			$codes = array(
				'en-AU',
				'en-GB',
				'pt-BR',
				'pt-PT',
				'zh-CN',
				'zh-TW'
			);
			
			// Other codes known by googlemaps are 2 characters codes
			if ( !in_array($l, $codes) ) {
				$l = substr($l, 0, 2);
			}
		
		}
		
		if ( !empty($l) ) {
			$l = $before . $l . $after;
		}
		
		return $l;
		
	}
	
	
	

	
	/**
	 * Hook: the_content
	 */
	function the_content($content = '')
	{
	
		global $wpnc, $post;
		$wp_nc_options = $wpnc->read_options();
		
		// Get values
		$show_map = get_post_meta($post->ID, '_wpnc_show_map', true);
		$date_from = get_post_meta($post->ID, '_wpnc_from', true);
		$date_through = get_post_meta($post->ID, '_wpnc_through', true);

		// do we need a map?			
		if($show_map == "Y"  && !empty($date_from) && !empty($date_through)) {

			// Show at bottom of post
			return $content . '<div class="wpnc_map" id="wpnc_map_' . $post->ID . '" style="height:'.$wp_nc_options['google_map_height'].'px;"></div>';

		}
		
		return $content;
		
	}

	
	
	/**
	 * Hook: Init
	 */
	function init()
	{
		global $wpnc;
	
		// Only show if NC API Key valid
		if ($this->checkNomadsConnectedAPIKey())
		{
		
			// Use the admin_menu action to define the custom boxes
			add_action('admin_menu', array($this, 'add_custom_boxes'));
			
			// Use the save_post action to do something with the data entered
			add_action('save_post', array($this, 'wpnc_save_postdata'));
			
			
			// Register Scripts
			$locale = $wpnc->get_googlemaps_locale();
			
//			wp_register_script('googlemaps', 'http://maps.google.com/maps?file=api&amp;v=2&amp;hl=' . $locale . '&amp;key=' . $wpnc->get_google_api_key() . '&amp;sensor=false', false, '2');
			wp_register_script('googlemaps', 'http://maps.googleapis.com/maps/api/js?sensor=false', false, '3');


			wp_enqueue_script('googlemaps');
			
		}
		
	}
	
	

	/**
	 * Hook: admin_init
	 */
	function admin_init()
	{
		
		// Register Settings
		if (function_exists('register_setting'))
		{
			register_setting('wp-nc-options', 'wp_nc_options', '');
		}

		// Register admin Scripts
		$locale = $this->get_googlemaps_locale();
		wp_register_script('jquery-ui-i18n', WP_CONTENT_URL . '/plugins/nomads-connected/js/jquery-ui-i18n.js', array('jquery','jquery-ui-datepicker'), '1.7.2');

		wp_enqueue_script('jquery-ui-datepicker');
		wp_enqueue_script('jquery-ui-i18n');

	}



	/**
	 * Hook: admin_head
	 */
	function admin_head()
	{
	
		global $wpnc, $post_ID;
		$wp_nc_options = $wpnc->read_options();
		$locale = $this->get_googlemaps_locale();
		
		// Only load if on a post or page
		if ($wpnc->show_route())
		{
			echo '
<link rel="stylesheet" href="' . WP_CONTENT_URL . '/plugins/nomads-connected/css/jquery-ui-1.7.2.custom.css" type="text/css" />';

			echo '
			
<script type="text/javascript">
//<![CDATA[

	var nc_route = ';
	
	// Get the route
	$url = 'http://www.nomadsconnected.com/api/wp.html?apiKey='.$wp_nc_options['nc_api_key'].'&mode=3';
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  $data = curl_exec($ch);
  curl_close($ch);     

  if($data != null && $data != "") {
  	echo $data;
  }
  else echo '{"result":-1}';

	echo ';
	
	function wpnc_set_dates(from,through) {
		jQuery("#wpnc_from").val(from);
		jQuery("#wpnc_through").val(through);
	}
	
	jQuery(function() {';
	
	echo '
		jQuery.datepicker.setDefaults(jQuery.datepicker.regional["'.$locale.'"]);
		jQuery("#wpnc_from").datepicker({dateFormat:"yy-mm-dd",changeMonth:true,changeYear:true,showAnim:"fadeIn"})
		jQuery("#wpnc_through").datepicker({dateFormat:"yy-mm-dd",changeMonth:true,changeYear:true,showAnim:"fadeIn"});
	
		var a = [];

		if(nc_route.result ==0) {
			if(nc_route.waypoints.length > 0) {
				a.push("<h4>'.__('Past Waypoints', 'nomads-connected').'</h4>");
				a.push("<table>");
				for(i=nc_route.waypoints.length-1; i>=0; i--) {
					nc_current_waypoint = nc_route.waypoints[i];
					a.push("<tr><td></td><td style=\"font-size:11px\">\
						<b><a href=\"javascript:void(0);\" onclick=\"wpnc_set_dates(\'"+nc_current_waypoint.vf+"\',\'"+nc_current_waypoint.vt+"\')\">"+nc_current_waypoint.lb+"</a></b><br/>\
						"+nc_current_waypoint.vf+" &#151; "+nc_current_waypoint.vt+"</td></tr>\
					");
				}
				a.push("</table>");
				jQuery("#wpnc_widget_route").html(a.join(""));
			}
			else {
				jQuery("#wpnc_widget_route").html("<p>'.__('You have no past waypoints.', 'nomads-connected').'</p>");
			}
		}
		else {
			jQuery("#wpnc_widget_route").html("<p>'.__('Could not read waypoints. Please check your settings!', 'nomads-connected').'</p>");
		}
		
	});

//]]>
</script>

			
			';
		
		
		}
		
	}
	
	
	
	/**
	 * Hook: admin_menu
	 */
	function admin_menu()
	{
		
		global $wpnc;
		
		if (function_exists('add_options_page'))
		{
			add_options_page('Nomads Connected Options', 'Nomads Connected', 8, __FILE__, array($wpnc, 'options_page'));
		}
		
	}

	

	/**
	 * Show Route
	 * Returns true if we are editing an article or a page
	 */
	function show_route()
	{
	
		global $post_ID, $pagenow;
		
		if (is_admin())
		{
			// If editing a post or page...
			if (is_numeric($post_ID) && $post_ID > 0)
			{
				return true;
			}
			// If writing a new post or page...
			if ($pagenow == 'post-new.php' || $pagenow == 'page-new.php')
			{
				return true;
			}
		}
		
		return false;
		
	}
	
	

	/**
	 * Options Page
	 */
	function options_page()
	{
		
		global $wpnc;
		
		$wp_nc_options = $wpnc->read_options();
		
		// Process option updates
		if (isset($_POST['action']) && $_POST['action'] == 'update')
		{
		
//			$wp_nc_options['google_api_key'] = $_POST['google_api_key'];
			$wp_nc_options['nc_api_key'] = $_POST['nc_api_key'];
			$wp_nc_options['google_map_type'] = $_POST['google_map_type'];
			$wp_nc_options['google_map_height'] = $_POST['google_map_height'];		
			$wp_nc_options['marker_color'] = $_POST['marker_color'];
			$wp_nc_options['days_between'] = $_POST['days_between'];
			$wp_nc_options['line_size'] = $_POST['line_size'];
			$wp_nc_options['line_past_color'] = $_POST['line_past_color'];
			$wp_nc_options['line_future_color'] = $_POST['line_future_color'];
			
			$regexRGB = '/^([0-9]|[A-F]|[a-f]){6}$/';
			if(preg_match($regexRGB,$_POST['line_past_color']))
			  $wp_nc_options['line_past_color'] = $_POST['line_past_color'];
			else
			  echo '<div class="error"><p>' . __('The past color you chose is not valid. Please choose a color in RGB format like "FF0000" for red or "FFFFFF" for black.', 'nomads-connected') . '</p></div>';

			
			if(preg_match($regexRGB,$_POST['line_future_color']))
			  $wp_nc_options['line_future_color'] = $_POST['line_future_color'];
			else
			  echo '<div class="error"><p>' . __('The future color you chose is not valid. Please choose a color in RGB format like "FF0000" for red or "FFFFFF" for black.', 'nomads-connected') . '</p></div>';
			
	
			update_option('wp_nc_options', $wp_nc_options);
			echo '<div class="updated"><p>' . __('Nomads Connected settings updated', 'nomads-connected') . '</p></div>';
			
		}

		// Write the form
		echo '
		<div class="wrap">
			<h2>' . __('Nomads Connected Settings', 'nomads-connected') . '</h2>
			<form method="post">';
		echo '<h3>' . __('General Settings', 'nomads-connected') . '</h3>';

		if(!function_exists('curl_init')) {
			echo '<div class="error"><p>' . __('Your web server is not configured properly to use this plugin. This plugin uses the cURL Library to communicate with the Nomads Connected Server, however the cURL support for PHP is currently disabled. Please contact your web server administrator/provider.', 'nomads-connected') . '</p></div>';
		}
		else {
			if (!$this->checkNomadsConnectedAPIKey())
			{
				echo '<div class="error"><p>' . __('Before you can use the plugin you must activate the <a href="http://www.nomadsconnected.com/settings/systemSettings.html">API Key</a> for your Nomads Connected account - the plugin will not function without it!', 'nomads-connected') . '</p>
					<p>' . __('If you are not registered with Nomads Connected yet you must <a href="http://www.nomadsconnected.com/access/register.html">create a free account</a> first.', 'nomads-connected') . '</p></div>';
			}


			echo '<table class="form-table">
						<tr valign="top">
							<th scope="row">' . __('Nomads Connected API Key', 'nomads-connected') . '</th>
							<td><input name="nc_api_key" type="text" id="nc_api_key" value="' . $wp_nc_options['nc_api_key'] . '" size="50" /></td>
						</tr>
					</table>

					<h3>' . __('Map Display Settings', 'nomads-connected') . '</h3>
					<table class="form-table">
						<tr valign="top">
							<th scope="row">' . __('Default Map Type', 'nomads-connected') . '</th>
							<td><select name="google_map_type" id="google_map_type"> 
								<option value="0" '.(($wp_nc_options['google_map_type'] == 0) ? 'selected' : '' ).'>'.__('Map', 'nomads-connected').'</option>
								<option value="1" '.(($wp_nc_options['google_map_type'] == 1) ? 'selected' : '' ).'>'.__('Satellite', 'nomads-connected').'</option>
								<option value="2" '.(($wp_nc_options['google_map_type'] == 2) ? 'selected' : '' ).'>'.__('Hybrid', 'nomads-connected').'</option>
							</select></td>
						</tr>
						<tr valign="top">
							<th scope="row">' . __('Map Height', 'nomads-connected') . '</th>
							<td><input name="google_map_height" type="text" id="google_map_height" value="' . $wp_nc_options['google_map_height'] . '" size="4" /> '.__('Pixels', 'nomads-connected').'</td>
						</tr>
					</table>
					
					<h3>' . __('Route Display Settings', 'nomads-connected') . '</h3>
					<table class="form-table">
						<tr valign="top">
							<th scope="row">' . __('Marker Color', 'nomads-connected') . '</th>
							<td><select name="marker_color" id="google_map_type"> 
								<option value="blue" '.(($wp_nc_options['marker_color'] == 'blue') ? 'selected' : '' ).'>'.__('Blue', 'nomads-connected').'</option>
								<option value="grn" '.(($wp_nc_options['marker_color'] == 'grn') ? 'selected' : '' ).'>'.__('Green', 'nomads-connected').'</option>
								<option value="ltblu" '.(($wp_nc_options['marker_color'] == 'ltblu') ? 'selected' : '' ).'>'.__('Light Blue', 'nomads-connected').'</option>
								<option value="pink" '.(($wp_nc_options['marker_color'] == 'pink') ? 'selected' : '' ).'>'.__('Pink', 'nomads-connected').'</option>
								<option value="purple" '.(($wp_nc_options['marker_color'] == 'purple') ? 'selected' : '' ).'>'.__('Purple', 'nomads-connected').'</option>
								<option value="red" '.(($wp_nc_options['marker_color'] == 'red') ? 'selected' : '' ).'>'.__('Red', 'nomads-connected').'</option>
								<option value="ylw" '.(($wp_nc_options['marker_color'] == 'ylw') ? 'selected' : '' ).'>'.__('Yellow', 'nomads-connected').'</option>
							</select></td>
						</tr>
						<tr valign="top">
							<th scope="row">' . __('Connect Markers', 'nomads-connected') . '</th>
							<td><select name="days_between" id="google_map_type"> 
								<option value="-1" '.(($wp_nc_options['days_between'] == '-1') ? 'selected' : '' ).'>'.__('never', 'nomads-connected').'</option>
								<option value="1" '.(($wp_nc_options['days_between'] == 1) ? 'selected' : '' ).'>'.__('if they are not more than 1 day apart', 'nomads-connected').'</option>
								<option value="2" '.(($wp_nc_options['days_between'] == 2) ? 'selected' : '' ).'>'.__('if they are not more than 2 days apart', 'nomads-connected').'</option>
								<option value="3" '.(($wp_nc_options['days_between'] == 3) ? 'selected' : '' ).'>'.__('if they are not more than 3 days apart', 'nomads-connected').'</option>
								<option value="7" '.(($wp_nc_options['days_between'] == 7) ? 'selected' : '' ).'>'.__('if they are not more than 1 week apart', 'nomads-connected').'</option>
							</select></td>
						</tr>
						<tr valign="top">
							<th scope="row">' . __('Line Color for Past Routes', 'nomads-connected') . '</th>
							<td><select name="line_past_color" id="google_map_type"> 
								<option value="000000" '.(($wp_nc_options['line_past_color'] == "000000") ? 'selected' : '' ).'>'.__('Black', 'nomads-connected').'</option>
								<option value="FF0000" '.(($wp_nc_options['line_past_color'] == "FF0000") ? 'selected' : '' ).'>'.__('Red', 'nomads-connected').'</option>
								<option value="00FF00" '.(($wp_nc_options['line_past_color'] == "00FF00") ? 'selected' : '' ).'>'.__('Green', 'nomads-connected').'</option>
								<option value="0000FF" '.(($wp_nc_options['line_past_color'] == "0000FF") ? 'selected' : '' ).'>'.__('Blue', 'nomads-connected').'</option>
								<option value="FFFF00" '.(($wp_nc_options['line_past_color'] == "FFFF00") ? 'selected' : '' ).'>'.__('Yellow', 'nomads-connected').'</option>
								<option value="AA0000" '.(($wp_nc_options['line_past_color'] == "AA0000") ? 'selected' : '' ).'>'.__('Dark Red', 'nomads-connected').'</option>
								<option value="FFFFFF" '.(($wp_nc_options['line_past_color'] == "FFFFFF") ? 'selected' : '' ).'>'.__('White', 'nomads-connected').'</option>
							</select></td>
						</tr>
						<tr valign="top">
							<th scope="row">' . __('Line Color for Future Routes', 'nomads-connected') . '</th>
							<td><select name="line_future_color" id="google_map_type"> 
								<option value="000000" '.(($wp_nc_options['line_future_color'] == "000000") ? 'selected' : '' ).'>'.__('Black', 'nomads-connected').'</option>
								<option value="FF0000" '.(($wp_nc_options['line_future_color'] == "FF0000") ? 'selected' : '' ).'>'.__('Red', 'nomads-connected').'</option>
								<option value="00FF00" '.(($wp_nc_options['line_future_color'] == "00FF00") ? 'selected' : '' ).'>'.__('Green', 'nomads-connected').'</option>
								<option value="0000FF" '.(($wp_nc_options['line_future_color'] == "0000FF") ? 'selected' : '' ).'>'.__('Blue', 'nomads-connected').'</option>
								<option value="FFFF00" '.(($wp_nc_options['line_future_color'] == "FFFF00") ? 'selected' : '' ).'>'.__('Yellow', 'nomads-connected').'</option>
								<option value="AA0000" '.(($wp_nc_options['line_future_color'] == "AA0000") ? 'selected' : '' ).'>'.__('Dark Red', 'nomads-connected').'</option>
								<option value="FFFFFF" '.(($wp_nc_options['line_future_color'] == "FFFFFF") ? 'selected' : '' ).'>'.__('White', 'nomads-connected').'</option>
							</select></td>
						</tr>
						<tr valign="top">
							<th scope="row">' . __('Line Width', 'nomads-connected') . '</th>
							<td><input name="line_size" type="text" id="line_size" value="' . $wp_nc_options['line_size'] . '" size="1" maxlength="1"/> '.__('Pixels', 'nomads-connected').'</td>
						</tr>
					</table>


					<p class="submit">
						<input type="submit" name="Submit" value="' . __('Save Changes', 'nomads-connected') . '" />
						<input type="hidden" name="action" value="update" />
						<input type="hidden" name="option_fields" value="google_map_type,google_api_key,nc_api_key" />
					</p>';
	
			if (function_exists('register_setting') && function_exists('settings_fields'))
			{
				settings_fields('wp-nc-options'); 
			}
		}
			
		echo '</form>
			</div>';
		
	}
	
	

	
	/**
	 * Check Google API Key
	 */
	function checkGoogleAPIKey()
	{
		
		global $wpnc;
		
		$wp_nc_options = $wpnc->read_options();
		$api_key = $wpnc->get_google_api_key();
		
		if (empty($api_key ) || !isset($api_key))
		{
			return false;
		}
		return true;
		
	}
	
	
	
	/**
	 * Get Google API Key
	 */
	function get_google_api_key()
	{
		global $wpnc;
		
		$wp_nc_options = $wpnc->read_options();
		return apply_filters( 'wpnc_google_api_key', $wp_nc_options['google_api_key'] );
		
	}
	


	/**
	 * Check Nomads Connected API Key
	 */
	function checkNomadsConnectedAPIKey()
	{
		
		global $wpnc;
		
		$wp_nc_options = $wpnc->read_options();
		$api_key = $wpnc->get_nc_api_key();
		
		if (empty($api_key ) || !isset($api_key))
		{
			return false;
		}
		return true;
		
	}
	
	
	/**
	 * Get Nomads Connected API Key
	 */
	function get_nc_api_key()
	{
		global $wpnc;
		
		$wp_nc_options = $wpnc->read_options();
		return apply_filters( 'wpnc_nc_api_key', $wp_nc_options['nc_api_key'] );
		
	}
	
	/**
	 * Get Google Map Type
	 */
	function get_google_map_type()
	{
		global $wpnc;
		
		$wp_nc_options = $wpnc->read_options();
		return apply_filters( 'google_map_type', $wp_nc_options['google_map_type'] );
	}



	
	/* =============== Admin Edit Pages =============== */
	
	

	/**
	 * ---------- Add Custom Boxes ----------
	 * Adds a custom section to the "advanced" Post and Page edit screens
	 * using the admin_menu hook
	 */
	function add_custom_boxes()
	{
	
		if (function_exists( 'add_meta_box'))
		{
			add_meta_box('wpnc_location', __('Nomads Connected', 'nomads-connected'), array($this, 'wpnc_inner_custom_box'), 'post', 'advanced');
			add_meta_box('wpnc_location', __('Nomads Connected', 'nomads-connected'), array($this, 'wpnc_inner_custom_box'), 'page', 'advanced');
		}
		else
		{
			add_action('dbx_post_advanced', array($this, 'wpnc_old_custom_box'));
			add_action('dbx_page_advanced', array($this, 'wpnc_old_custom_box'));
		}
		
	}
	
	
	
	/**
	 * ---------- Inner Custom Box ----------
	 * Prints the inner fields for the custom post/page section.
	 */
	function wpnc_inner_custom_box()
	{
		
		global $post;
		
		$show_map = get_post_meta($post->ID, '_wpnc_show_map', true);
		if ( isset($show_map) && $show_map=='Y' ) {
			$show_map_checked = ' checked="checked"';
		}
		
		$date_from = get_post_meta($post->ID, '_wpnc_from', true);
		$date_through = get_post_meta($post->ID, '_wpnc_through', true);

		// Use nonce for verification
		echo '<input type="hidden" name="wpnc_noncename" id="wpnc_noncename" value="' . wp_create_nonce(plugin_basename(__FILE__)) . '" />';

		echo '<div>
			<label for="wpnc_show_map"><input name="wpnc_show_map" type="checkbox" id="wpnc_show_map" value="Y"'.$show_map_checked.'/>
			'.__('Display Your Route', 'nomads-connected').'
			</div>';

		echo '<table style="margin-top:5px">
			<tr>
				<td style="font-size: 11px;">' . __('From Date', 'nomads-connected') . '<br/><input style="font-size: 11px;" name="wpnc_from" type="text" size="12" id="wpnc_from" value="' . $date_from . '" readonly="readonly" /></td>
				<td style="font-size: 11px;">' . __('Through Date', 'nomads-connected') . '<br/><input style="font-size: 11px;" name="wpnc_through" type="text" size="12" id="wpnc_through" value="' . $date_through . '" readonly="readonly" /></td>
			</tr>
		</table>';
			
		echo '<div id="wpnc_widget_route" style="border-top:1px solid silver; max-height: 150px; overflow:auto;">' . __('Loading waypoints...', 'nomads-connected') . '</div>';
	}
	
	
	
	/**
	 * ---------- Old Custom Box ----------
	 * Prints the edit form for pre-WordPress 2.5 post/page.
	 */
	function wpnc_old_custom_box()
	{
	
		echo '<div class="dbx-b-ox-wrapper">' . "\n";
		echo '<fieldset id="wpnc_fieldsetid" class="dbx-box">' . "\n";
		echo '<div class="dbx-h-andle-wrapper"><h3 class="dbx-handle">' . __('Nomads Connected', 'wpnc') . "</h3></div>";   
		echo '<div class="dbx-c-ontent-wrapper"><div class="dbx-content">';
		
		// output editing form
		wpnc_inner_custom_box();
		
		echo "</div></div></fieldset></div>\n";
		
	}
	
	
	
	/**
	 * ---------- Nomads Connected: Save post data ----------
	 * When the post is saved, saves our custom data.
	 */
	function wpnc_save_postdata($post_id)
	{
	
		// Verify this came from the our screen and with proper authorization,
		// because save_post can be triggered at other times
		if (!wp_verify_nonce($_POST['wpnc_noncename'], plugin_basename(__FILE__)))
		{
			return $post_id;
		}
		
		// Authenticate user
		if ('page' == $_POST['post_type'])
		{
			if (!current_user_can('edit_page', $post_id))
				return $post_id;
		}
		else
		{
			if (!current_user_can('edit_post', $post_id))
				return $post_id;
		}
		
		$mydata = array();
		
		// Find and save the switch
		
		delete_post_meta($post_id, '_wpnc_show_map');
		
		if ( isset($_POST['wpnc_show_map']) && $_POST['wpnc_show_map']=='Y' ) {
			add_post_meta($post_id, '_wpnc_show_map', 'Y');
			$mydata['_wpnc_show_map']  = 'Y';
			
		}
		else {
			add_post_meta($post_id, '_wpnc_show_map', 'N');
			$mydata['_wpnc_show_map']  = 'N';
		}

		// Find and save the date range
		if (isset($_POST['wpnc_from']) && isset($_POST['wpnc_through']))
		{
			
			// Only delete post meta if isset (to avoid deletion in bulk/quick edit mode)
			delete_post_meta($post_id, '_wpnc_from');
			delete_post_meta($post_id, '_wpnc_through');
			
			add_post_meta($post_id, '_wpnc_from', $_POST['wpnc_from']);
			add_post_meta($post_id, '_wpnc_through', $_POST['wpnc_through']);
			
			$mydata['_wpnc_from']  = $_POST['wpnc_from'];
			$mydata['_wpnc_through'] = $_POST['wpnc_through'];
				
		}
		
		return $mydata;
	
	}
	
	
	
}


// Language
load_plugin_textdomain('nomads-connected', PLUGINDIR . '/nomads-connected/languages');




// Init.
global $wpnc;
$wpnc = new WPNomadsConnected();

// Hooks
register_activation_hook(__FILE__, array($wpnc, 'register_activation'));

// Frontend Hooks
add_action('wp_head', array($wpnc, 'wp_head'));
add_filter('the_content', array($wpnc, 'the_content'));

// Admin Hooks
add_action('init', array($wpnc, 'init'));
add_action('admin_init', array($wpnc, 'admin_init'));
add_action('admin_menu', array($wpnc, 'admin_menu'));
add_action('admin_head', array($wpnc, 'admin_head'));

//add_filter('post_limits', array($wpnc, 'post_limits'));
//add_filter('posts_join', array($wpnc, 'posts_join'));
//add_filter('posts_where', array($wpnc, 'posts_where'));


?>
