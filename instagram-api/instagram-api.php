<?php
/*
Plugin Name: Instagram Wordpress API
Plugin URI: http://github.com/msigley/
Description: Provides functions for accessing the Instagram API. Leverages the Wordpress HTTP API.
Version: 2.0.1
Author: Matthew Sigley
License: GPL2
*/

defined( 'WPINC' ) || header( 'HTTP/1.1 403' ) & exit; // Prevent direct access

class Instagram_WP_API {
	private static $version = '1.0.0';
	private static $object = null;
	private static $root_endpoint_url = 'https://graph.instagram.com/';
	private static $contruct_args = array( 'client_id', 'client_secret' );
	
	public $rest = null;

	private $client_id = null;
	private $client_secret = null;
	private $access_token = null;
	private $access_token_expires = null;

	public $embed_js = false;
	
	private function __construct( $args ) {
		foreach( $args as $arg_name => $arg_value ) {
			if( !empty($arg_value) && in_array($arg_name, self::$contruct_args) )
				$this->$arg_name = $arg_value;
		}
	}

	public function init() {
		//Plugin path
		$this->path = plugin_dir_path( __FILE__ );

		$this->access_token = get_option( 'instagram_wp_api_access_token' );
		$this->access_token_expires = get_option( 'instagram_wp_api_access_token_expires' );

		//Plugin activation/deactivation
		register_activation_hook( __FILE__, array($this, 'activation') );
		register_deactivation_hook( __FILE__, array($this, 'deactivation') );

		//REST API
		require_once $this->path . 'rest.php';
		$this->rest = new Instagram_WP_API_REST( array( 'root_endpoint_url' => 'https://graph.instagram.com/', 'authorization_header' => 'Bearer ' . $this->access_token ) );

		//General API
		require_once 'api.php';

		//oEmbed API
		add_filter('oembed_fetch_url', array($this, 'oembed_fetch_url'), 1, 3);
		add_filter('embed_oembed_html', array($this, 'embed_oembed_html'), 1, 2);

		//WP Cron
		add_action( 'instagram_wp_api_refresh_access_token', array( $this, 'refresh_access_token' ) );

		if( is_admin() ) {
			add_action( 'admin_init', array($this, 'handle_oauth_response') );
			if( empty( $this->access_token ) )
				add_action( 'admin_notices', array($this, 'oauth_notice') );
		}
	}
	
	/*
	 * Singleton instance static method
	 */
	static function &object( $args=array() ) {
		if ( ! self::$object instanceof Instagram_WP_API ) {
			//Support for Constants in wp-config.php
			if( empty($args['client_id']) && defined('INSTAGRAM_CLIENT_ID') )
				$args['client_id'] = INSTAGRAM_CLIENT_ID;
			if( empty($args['client_secret']) && defined('INSTAGRAM_CLIENT_SECRET') )
				$args['client_secret'] = INSTAGRAM_CLIENT_SECRET;
			if( empty($args) ){
				$rv = FALSE;
				return $rv;
			} 
			self::$object = new Instagram_WP_API( $args );
		}
		return self::$object;
	}

	public function activation() {
		if( !wp_next_scheduled( 'instagram_wp_api_refresh_access_token' ) ) {
			$start_of_day = time();
			$start_of_day -= $start_of_day % DAY_IN_SECONDS;
			wp_schedule_event( $start_of_day, 'twicedaily', 'instagram_wp_api_refresh_access_token' );
		}
	}

	public function deactivation() {
		delete_option( 'instagram_wp_api_access_token' );
		delete_option( 'instagram_wp_api_access_token_expires' );
		wp_clear_scheduled_hook( 'instagram_wp_api_refresh_access_token' );
		wp_cache_flush();
	}

	public function oauth_notice() {
		$oauth_url = 'https://api.instagram.com/oauth/authorize/?client_id='.$this->client_id.
			'&redirect_uri='.urlencode( admin_url( '', 'https' ) ).'&scope=user_profile,user_media&response_type=code&state=instagram_oauth_nonce_1';
		?>
		<div class="notice notice-error error">
      <p><strong>Instagram Wordpress API:</strong> Bad or missing user access token. <a href="<?php echo $oauth_url; ?>">Please reauthenicate with Instagram to pull and display content from Instagram.</a></p>
			<p>If you are having issues reauthenicating, please be sure this url is in your client's valid redirect URIs:<br />
			<code><?php echo admin_url( '', 'https' ); ?></code></p>
    </div>
		<?php
	}

	public function handle_oauth_response() {
		if( empty( $_REQUEST['code'] ) || empty( $_REQUEST['state'] ) || substr( $_REQUEST['state'], 0, 22 ) !== 'instagram_oauth_nonce_' )
			return;

		$nonce = substr( $_REQUEST['state'], 22 );
		if( empty( $nonce ) )
			return;

		$post_array = array(
			'client_id' => $this->client_id,
			'client_secret' => $this->client_secret,
			'grant_type' => 'authorization_code',
			'redirect_uri' => admin_url( '', 'https' ),
			'code' => $_REQUEST['code']
		);
		
		$headers = array();
		$args = array( 'headers' => $headers, 'sslverify' => false, 'body' => $post_array );
		
		$access_token = false;
		$expires = 0;

		// Request a short lived access token
		$response = wp_remote_post( 'https://api.instagram.com/oauth/access_token', $args) ;
		if( !is_wp_error( $response ) && 200 == wp_remote_retrieve_response_code( $response ) ) {
			$response_body = wp_remote_retrieve_body( $response );
			$response_body = json_decode( $response_body );
			if( !empty( $response_body->access_token ) ) {
				// Exchange the short lived access token for a long lived on
				$query_string = build_query( 
					array(
						'access_token' => $response_body->access_token,
						'client_secret' => $this->client_secret,
						'grant_type' => 'ig_exchange_token'
					)
				);
				
				$headers = array();
				$args = array( 'headers' => $headers, 'sslverify' => false, 'body' => array() );
				$response = wp_remote_get( "https://graph.instagram.com/access_token?$query_string", $args) ;
				if( !is_wp_error( $response ) && 200 == wp_remote_retrieve_response_code( $response ) ) {
					$response_body = wp_remote_retrieve_body( $response );
					$response_body = json_decode( $response_body );
	
					if( !empty( $response_body->access_token ) ) {
						$access_token = $response_body->access_token;
						$expires = time() + $response_body->expires_in - 30;
					}
				}
			}
		}

		update_option( 'instagram_wp_api_access_token', $access_token, true );
		update_option( 'instagram_wp_api_access_token_expires', $expires, true );

		wp_redirect( admin_url( '', 'https'), 307 );
		die();
	}

	public function refresh_access_token() {
		if( empty( $this->access_token ) || empty( $this->access_token_expires ) || $this->access_token_expires - time() > WEEK_IN_SECONDS )
			return;

		// Exchange the short lived access token for a long lived on
		$query_string = build_query( 
			array(
				'access_token' => $this->access_token,
				'grant_type' => 'ig_refresh_token'
			)
		);
		
		$headers = array();
		$args = array( 'headers' => $headers, 'sslverify' => false, 'body' => array() );
		$response = wp_remote_get( "https://graph.instagram.com/refresh_access_token?$query_string", $args) ;
		if( !is_wp_error( $response ) && 200 == wp_remote_retrieve_response_code( $response ) ) {
			$response_body = wp_remote_retrieve_body( $response );
			$response_body = json_decode( $response_body );

			if( !empty( $response_body->access_token ) ) {
				$this->access_token = $response_body->access_token;
				$this->access_token_expires = time() + $response_body->expires_in - 30;
			}
		}

		update_option( 'instagram_wp_api_access_token', $this->access_token, true );
		update_option( 'instagram_wp_api_access_token_expires', $this->access_token_expires, true );
	}

	public function oembed_fetch_url($provider, $url, $args) {
		// Omit the embed.js script for instagram oembeds
		if( preg_match( '#https?://(www\.)?instagr(\.am|am\.com)/p/.*#i', $url ) ) {
			$args['omitscript'] = 'true';
			$args['maxwidth'] = 960;
		}
		
		$accepted_args = array('callback', 'omitscript', 'hidecaption', 'maxwidth');
		foreach( $accepted_args as $accepted_arg ) {
			if( isset($args[$accepted_arg]) )
				$provider = add_query_arg($accepted_arg, $args[$accepted_arg], $provider);
		}
		
		return $provider;
	}

	public function embed_oembed_html($html, $url) {
		if( $this->embed_js )
			return $html;
		
		if( preg_match( '#https?://(www\.)?instagr(\.am|am\.com)/p/.*#i', $url ) ) {
			$this->embed_js = true;
			wp_enqueue_script('instagram-embed', 'https://platform.instagram.com/en_US/embeds.js', false, self::$version, true);
		}
		return $html;
	}

	public function self_uri(){
		$url = 'http';
		$script_name = '';
		if (isset($_SERVER['REQUEST_URI'])):
				$script_name = $_SERVER['REQUEST_URI'];
		else:
				$script_name = $_SERVER['PHP_SELF'];
				if ($_SERVER['QUERY_STRING'] > ' '):
						$script_name .= '?' . $_SERVER['QUERY_STRING'];
				endif;
		endif;
		
		if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on')
				$url .= 's';

		$url .= '://';
		if ($_SERVER['SERVER_PORT'] != '80'):
				$url .= $_SERVER['HTTP_HOST'] . ':' . $_SERVER['SERVER_PORT'] . $script_name;
		else:
				$url .= $_SERVER['HTTP_HOST'] . $script_name;
		endif;

		return $url;
	}
}

$Instagram_WP_API = Instagram_WP_API::object();
$Instagram_WP_API->init();