<?php

/**
 * User: stephen
 * Date: 2019-07-19
 *
 * @version 1.0
 *
 *
 */

include_once(plugin_dir_path(__FILE__) . 'settings.php');

if ( ! defined( 'WP_FS__TIME_WEEK_IN_SEC' ) ) {
	define( 'WP_FS__TIME_WEEK_IN_SEC', 7 * 86400 );
}

class ucf_com_bitly {

	const shortcode                 = 'ucf_com_bitly'; // what people type into their page

	function __construct() {
		// Add the javascript to the locations page
		//add_action( 'init', array( $this, 'register_location_js_css' ) );

		add_shortcode( ucf_com_bitly::shortcode, array( $this, 'handle_shortcode'));

	}


	/**
	 *
	 * adds the js and css to the current page
	 */
//	function register_location_js_css(){
//		wp_register_script( self::google_maps_register, self::google_maps_key);
//
//		wp_register_script(
//			self::script_register,
//			plugins_url( 'js/google-map.js', __FILE__ ),
//			array( 'jquery' ),
//			filemtime( plugin_dir_path(__FILE__) . '/js/google-map.js'),
//			true
//		);
//		wp_register_style(
//			self::style_register,
//			plugins_url( 'css/style.css', __FILE__ ),
//			array(),
//			filemtime( plugin_dir_path(__FILE__) . '/css/style.css'),
//			true
//		);
//	}

	/**
	 * Outputs the location html in place of the shortcode.
	 * Also sets a flag to include js and css.
	 * @param $attributes
	 *
	 * @return string
	 */
	function handle_shortcode($atts, $content) {
		$transient_key = 'bitly_' . substr($content, -162); // use last 162 characters plus a prefix for key (max 172 chars allowed)

		// check to see if we have this url shortened already. if so, just display that.
		$cached_data = get_transient($transient_key);

		if ($cached_data == false){
			// if we don't have this url shortened, we need to call bit.ly to shorten it.
			$config = get_option('ucf_com_bitly_options');

			try {
				$bitly_link = $this->bitly_shorten_url_v3($content, $config['auth_token']); // shorten the long url
				$bitly_array = parse_url($bitly_link); // don't want 'http://'
				$bitly_link_shorter = $bitly_array['host'] . $bitly_array['path'];

				$return = $bitly_link_shorter;
				set_transient('bitly_' . substr($content, -162), $bitly_link_shorter, WP_FS__TIME_WEEK_IN_SEC); // cache for one week. should never change, but you never know.

			} catch (Exception $exception){
				$return = $exception;
			}
		} else {
			$return = $cached_data;
		}


		return $return;
	}


	/**
	 * Uses v3 api and returns the short url based on the input long url.
	 *
	 * @param      $long_url
	 * @param      $bitly_auth_token
	 *
	 * @return string|null shortened link from bit.ly
	 * @throws Exception
	 */
	function bitly_shorten_url_v3($long_url, $bitly_auth_token){
		$long_url_encoded = urlencode($long_url);

		$curl = curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_URL => "https://api-ssl.bitly.com/v3/shorten?access_token=${bitly_auth_token}&longUrl=${long_url_encoded}",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPGET => true,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
		));
		$response = json_decode(curl_exec($curl));

		$err = curl_error($curl);
		$response_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		curl_close($curl);

		$link = $response->data->url;

		if ($err) {
			echo("we have an error!!!");
			throw new Exception( $err );
		}

		if ($response_code != 200){
			echo("we have an error!!!");
			echo($response_code);
			throw new Exception( $response );
		}
		return $link;
	}

	/**
	 * Uses v4 api and returns the short url based on the input long url.
	 * Doesn't work yet. Getting 403 forbidden for this method. Use v3 api until 2020 when it is deprecated.
	 *
	 * @param      $long_url
	 * @param      $bitly_auth_token
	 * @param null $group_name
	 *
	 * @return string|null shortened link from bit.ly
	 * @throws Exception
	 */
	function bitly_shorten_url_v4($long_url, $bitly_auth_token, $group_name = null){
		$curl = curl_init();

		$postfields = json_encode(
			array(
				"long_url" => urlencode($long_url), //bit.ly api requires the url be urlencoded
				//"long_url" => "https://google.com", //bit.ly api requires the url be urlencoded
				"group_guid" => $this->bitly_get_group($bitly_auth_token, $group_name)
			)
		);
		//var_dump($postfields);
		//echo($postfields["long_url"]);
		curl_setopt_array($curl, array(
			CURLOPT_URL => "https://api-ssl.bitly.com/v4/shorten",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_HTTPHEADER => array(
				"Host: api-ssl.bitly.com",
				"Authorization: Bearer ${bitly_auth_token}",
				"Content-Type: application/json",

			),
			CURLOPT_POSTFIELDS => $postfields
		));
		$response = json_decode(curl_exec($curl));

		$err = curl_error($curl);
		$response_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		curl_close($curl);

		$link = $response->link;

		var_dump($response);

		if ($err) {
			echo("we have an error!!!");
			throw new Exception( $err );
		}

		if ($response_code != 200){
			echo("we have an error!!!");
			echo($response_code);
			throw new Exception( $response );
		}
		return $link;

	}

	/**
	 * Uses v4 api and returns the group guid
	 * @param      $bitly_auth_token Token acquired from https://bitly.com/a/oauth_apps and generating a Generic Access Token
	 * @param null $group_name Name of a specific group you want. If not defined, defaults the the first group in the list.
	 *
	 * @return mixed
	 */
	function bitly_get_group($bitly_auth_token, $group_name = null){
		$curl = curl_init();

		// first, we have to get the group guids from bit.ly

		curl_setopt_array($curl, array(
			CURLOPT_URL => "https://api-ssl.bitly.com/v4/groups",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPGET => true,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_HTTPHEADER => array(
				"Host: api-ssl.bitly.com",
				"Authorization: Bearer " . $bitly_auth_token,
				"Accept: application/json"
			),
		));
		$response = json_decode(curl_exec($curl));

		$bitly_group = $response->groups[0]->organization_guid; // default to the first guid in the array. free accounts only have one group.
		foreach ($response->groups as $group){

			if ($group->name == $group_name){
				$bitly_group = $group->organization_guid;
			}
		}

		curl_close($curl);
		return $bitly_group;
	}


}

new ucf_com_bitly();

?>
