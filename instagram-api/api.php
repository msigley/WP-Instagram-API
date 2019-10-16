<?php
/* Endpoint API */
function instagram_get_user_items($user_id = 'self', $query_args=array()) {
	$Instagram_WP_API = Instagram_WP_API::object();
	
	if( !empty($query_args['max_id']) )
		settype($query_args['max_id'], 'string');
	if( !empty($query_args['min_id']) )
		settype($query_args['min_id'], 'string');
	if( empty($query_args['min_timestamp']) )
		$query_args['min_timestamp'] = 0;
	if( !empty($query_args['max_timestamp']) && $query_args['max_timestamp'] <= $query_args['max_timestamp'] )
		unset($query_args['max_timestamp']);
	if( !empty($query_args['count']) && 0 > $query_args['count'] )
		unset($query_args['count']);
	
	$response = $Instagram_WP_API->get_request( "users/$user_id/media/recent", $query_args );
	if( empty($response) )
		return false;

	//Handle instagram pagination
	//Only concatonate max 10 pages for performance and sanity
	$response_data = $response->data;
	$i = 0;
	while( !empty($response->pagination->next_url) && $i < 10 ) {
		if( !empty( $query_args['count'] && count( $response_data ) >= $query_args['count'] ) )
			break;
		
		$next_page_args = array( 'max_id' => $response->pagination->next_max_id );
		if( !empty($query_args['count']) )
			$next_page_args['count'] = $query_args['count'];
		$page_response = $Instagram_WP_API->get_request( "users/$user_id/media/recent", $next_page_args );
		if( !empty($page_response->data) )
			$response_data = array_merge( $response_data, $page_response->data );
		$i++;
	}
	if( !empty($query_args['count']) && count( $response_data ) > $query_args['count'] )
		$response_data = array_slice( $response_data, 0, $query_args['count'], true );
	$response->data = $response_data;

	return $response;
}