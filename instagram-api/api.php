<?php
/* Endpoint API */
function instagram_get_user_items( $query_args=array() ) {
	$Instagram_WP_API = Instagram_WP_API::object();
	
	if( empty( $query_args['limit'] ) || 0 > $query_args['limit'] )
		$query_args['limit'] = 25;

	if( $query_args['limit'] > 1000 )
		$query_args['limit'] = 1000;

	if( empty( $query_args['fields'] ) )
		$query_args['fields'] = 'id,caption,media_type,media_url,permalink,thumbnail_url,timestamp,username';
	
	$response = $Instagram_WP_API->rest->request( "me/media", "get", $query_args );
	if( empty($response) )
		return false;

	//Handle instagram pagination
	//Only concatonate max 10 pages for performance and sanity
	$response_data = $response->data;
	$response->count = count( $response_data );
	$i = 0;
	while( $response->count < $query_args['limit'] && $i < 10 ) {
		$next_page_args = array( 'after' => $response->paging->after );
		$next_page_args['limit'] = 100; // 100 is the max limit in one request
		$page_response = $Instagram_WP_API->rest->request( "me/media", "get", $next_page_args );
		if( !empty($page_response->data) ) {
			$response_data = array_merge( $response_data, $page_response->data );
			$response->count += count( $page_response->data );
		}
		$i++;
	}

	if( $response->count > $query_args['limit'] )
		$response_data = array_slice( $response_data, 0, $query_args['limit'], true );
	$response->data = $response_data;

	return $response;
}