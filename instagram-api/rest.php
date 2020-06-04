<?php
class Instagram_WP_API_REST {
	private static $contruct_args = array( 'slug', 'root_endpoint_url', 'cache_time_in_seconds', 'authorization_header' );
	private static $flipped_construct_args = false;

	private $slug = '';
	private $root_endpoint_url = 'https://example.com/v1/';
	private $cache_time_in_seconds = DAY_IN_SECONDS / 2;
	private $authorization_header = false;

	public function __construct( $args = array() ) {
		if( ! self::$flipped_construct_args ) {
			self::$contruct_args = array_flip( self::$contruct_args );
			self::$flipped_construct_args = true;
		}

		foreach( $args as $arg_name => $arg_value ) {
			if( empty($arg_value) || !isset( self::$contruct_args[$arg_name] ) )
				continue;

			if( !empty( self::$contruct_args[$arg_name] ) && defined( self::$contruct_args[$arg_name] ) )
				$this->$arg_name = constant( self::$contruct_args[$arg_name] );
			else
				$this->$arg_name = $arg_value;
		}
	}

	public function request( $endpoint, $method, $body = array(), $cache = true, &$response_code = false ) {
		if( !is_array( $body ) )
			return false;
		$endpoint = ltrim( $endpoint, '/' );
		$method = strtoupper( $method );
		$request_url = trailingslashit( $this->root_endpoint_url . $endpoint );
		
		if( $cache && $cached = $this->get_cached_request( $endpoint, $method, $body ) )
			return json_decode( $cached );
		
		$headers = array();
		if( 'GET' != $method )
			$headers['X-HTTP-Method-Override'] = $method;
		if( !empty( $this->authorization_header ) )
			$headers['Authorization'] = $this->authorization_header;

		$request_body = $body;
		$cache_bust = time();
		if( !empty( $body ) ) {
			if( 'GET' == $method ) {
				$request_url .= '?' . build_query( $request_body );
				$request_body = array();
			} else {
				$headers['content-type'] = 'application/json';
				$request_body = json_encode( $request_body );
			}
		}

		$args = array( 'headers' => $headers, 'body' => $request_body );

		if( 'GET' == $method ) 
			$response = wp_remote_get( $request_url, $args );
		else
			$response = wp_remote_post( $request_url, $args );

		if( is_wp_error( $response ) )
			return false;
		$response_code = wp_remote_retrieve_response_code( $response );
		if( 200 != $response_code && 400 != $response_code )
			return false;
		
		$response_body = wp_remote_retrieve_body( $response );
		$this->cache_request( $response_body, $endpoint, $method, $body );
		return json_decode( $response_body );
	}

	private function get_cached_request( $endpoint, $method, $body=array() ) {
		$endpoint = ltrim( $endpoint, '/' );
		$method = strtoupper( $method );
		$cache_key = 'request:'.md5( 'endpoint:'.$endpoint.';method:'.$method.';body:'.serialize( $body ) );
		return wp_cache_get( $cache_key, $this->slug );
	}

	private function cache_request( $data, $endpoint, $method, $body=array() ) {
		$endpoint = ltrim( $endpoint, '/' );
		$method = strtoupper( $method );
		$cache_key = 'request:'.md5( 'endpoint:'.$endpoint.';method:'.$method.';body:'.serialize( $body ) );
		wp_cache_set( $cache_key, $data, $this->slug, $this->cache_time_in_seconds );
	}

	public function delete_cached_request( $endpoint, $method, $body=array() ) {
		$endpoint = ltrim( $endpoint, '/' );
		$method = strtoupper( $method );
		$cache_key = 'request:'.md5( 'endpoint:'.$endpoint.';method:'.$method.';body:'.serialize( $body ) );
		return wp_cache_delete( $cache_key, $this->slug );
	}
}