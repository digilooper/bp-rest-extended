<?php

/**
 * Add extra request params to members endpoint.
 *
 * @since 1.0.0
 *
 * @param  array $params
 * @return array
 */
function appp_add_extra_param_to_member_endpoint( $params ) {
	$params['user_login'] = array(
		'description'       => __( 'Username of member.', 'apppcore' ),
		'default'           => '',
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_key',
		'validate_callback' => 'rest_validate_request_arg',
	);

	$params['custom_filter'] = array(
		'description'       => __( 'Filter members loop with custom filter.', 'apppcore' ),
		'default'           => '',
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_key',
		'validate_callback' => 'rest_validate_request_arg',
	);

	return $params;

}
add_filter( 'bp_rest_members_collection_params', 'appp_add_extra_param_to_member_endpoint' );

/**
 * Sets user ids param if a user_login param is in rest request.
 * BP doesnt have a get member by login so we filter!
 *
 * @param  [type] $args
 * @param  [type] $request
 * @return void
 */
function appp_set_user_ids_param_from_username( $args, $request ) {
	$user_login_param = $request->get_param( 'user_login' );

	if ( '' !== $user_login_param ) {

		$author_obj = get_user_by( 'login', $user_login_param );

		if ( $author_obj ) {
			$args['user_ids'] = $author_obj->ID;
		}
	}

	return $args;

}
add_filter( 'bp_rest_members_get_items_query_args', 'appp_set_user_ids_param_from_username', 10, 2 );


/**
 * Filter activity response returned from the API.
 *
 * @since 1.0.0
 *
 * @param WP_REST_Response     $response The response data.
 * @param WP_REST_Request      $request  Request used to generate the response.
 * @param BP_Activity_Activity $activity Activity object.
 *
 * @return WP_REST_Response
 */
function appp_add_data_to_activity_api_items( $response, $request, $activity ) {

	global $bp;

	$logged_in = is_user_logged_in();

	$response->data['username']     = bp_core_get_username( $response->data['user_id'] );
	$response->data['display_name'] = bp_core_get_user_displayname( $response->data['user_id'] );
	$response->data['user_type']    = bp_get_member_type( $response->data['user_id'] );
	$response->data['time_since']   = bp_core_time_since( str_replace( 'T', ' ', $response->data['date'] ) );

	// Get the 'comment-reply' support for the current activity type.
	$can_comment = bp_activity_type_supports( $activity->type, 'comment-reply' );

	// Neutralize activity_comment.
	if ( 'activity_comment' === $activity->type ) {
		$can_comment = false;
	}

	$response->data['can_comment']  = $logged_in ? $can_comment : false;
	$response->data['can_favorite'] = $logged_in ? bp_activity_can_favorite() : false;

	// Assume the user cannot delete the activity item.
	$can_delete = false;

	// Only logged in users can delete activity.
	if ( $logged_in ) {

		// Community moderators can always delete activity (at least for now).
		if ( bp_current_user_can( 'bp_moderate' ) ) {
			$can_delete = true;
		}

		// Users are allowed to delete their own activity. This is actually
		// quite powerful, because doing so also deletes all comments to that
		// activity item. We should revisit this eventually.
		if ( isset( $activity->user_id ) && ( bp_loggedin_user_id() === $activity->user_id ) ) {
			$can_delete = true;
		}

		/*
		* Viewing a single item, and this user is an admin of that item.
		*
		* Group activity items are handled separately.
		* See bp_groups_filter_activity_user_can_delete().
		*/
		if ( 'groups' !== $activity->component && bp_is_single_item() && bp_is_item_admin() ) {
			$can_delete = true;
		}
	}

	$response->data['can_delete'] = $logged_in ? $can_delete : false;

	/**
 * Add custom activity actions for AppPresser
*/
	switch ( $response->data['type'] ) {

		case 'activity_comment':

			if ( 'activity' === $activity->component ) {

				$attachments = appp_get_activity_attachments( $activity->id );

				$response->data['attachments'] = $attachments;

			}

			break;

		case 'activity_update':
			if ( 'gamipress' === $activity->component ) {
				$response->data['activity_action'] = array( 'action' => 'Earned a new badge' );
			}
			if ( 'activity' === $activity->component ) {
				$response->data['activity_action'] = array( 'action' => 'Posted an update' );

				$attachments = appp_get_activity_attachments( $activity->id );

				$response->data['attachments'] = $attachments;

			}

			if ( 'groups' === $activity->component ) {

				$group = groups_get_group( array( 'group_id' => $activity->item_id ) );

				$response->data['activity_action'] = array( 'action' => 'Posted an update in <a class="router-link" href="/' . $bp->groups->slug . '/' . $group->slug . '">' . $group->name . '</a> ' );

				$attachments = appp_get_activity_attachments( $activity->id );

				$response->data['attachments'] = $attachments;

			}

			break;

		case 'created_group':
			$group = groups_get_group( $response->data['primary_item_id'] );

			$response->data['activity_action'] = array(
				'action' => 'Created a new group',
				'name'   => $group->name,
				'slug'   => $group->slug,
			);
			break;

		case 'joined_group':
			$group = groups_get_group( $response->data['primary_item_id'] );

			$response->data['activity_action'] = array(
				'action' => 'Joined the group',
				'name'   => $group->name,
				'slug'   => $group->slug,
			);
			break;

		case 'new_member':
			$response->data['activity_action'] = array(
				'action' => 'Became a registered member',
			);
			break;

		case 'new_blog_comment':
			$post = get_post( $response->data['primary_item_id'] );

			$response->data['activity_action'] = array(
				'action' => 'Commented on a post',
				'name'   => $post->post_title,
				'slug'   => $post->post_name,
			);
			break;

		case 'new_blog_post':
			$post = get_post( $response->data['primary_item_id'] );

			if ( $post ) {
				$response->data['activity_action'] = array(
					'action' => 'Published a new post',
					'name'   => $post->post_title,
					'slug'   => $post->post_name,
				);
			}

			break;

		case 'friendship_created':
			$response->data['content']['rendered'] = '<ion-router-link href="/members/' . $response->data['username'] . '">' . $response->data['display_name'] . '</ion-router-link> became friends with <ion-router-link href="/members/' . bp_core_get_username( $response->data['secondary_item_id'] ) . '">' . bp_core_get_user_displayname( $response->data['secondary_item_id'] ) . '</ion-router-link>';

			break;

	}

	return $response;
}
add_action( 'bp_rest_activity_prepare_value', 'appp_add_data_to_activity_api_items', 10, 3 );

/**
 * Save api meta object to sign up meta for processing later.
 *
 * @param [type] $meta
 * @param [type] $request
 * @return void
 */
function appp_save_signup_meta_during_registration( $meta, $request ) {

	$params = $request->get_params();

	if ( isset( $params['meta'] ) ) {
		foreach( $params['meta'] as $key => $value ) {
			$meta[ $key ] = $value;
		}
	}

	return $meta;
}
add_filter( 'bp_rest_signup_create_item_meta', 'appp_save_signup_meta_during_registration', 10, 2 );


 /**
  * Add Member Types Pro field otions to api response data.
  * This custom xprofile field plugin isnt returning options correctly so gotta filter it in
  *
  * @param [type] $field_groups
  * @param [type] $response
  * @param [type] $request
  * @return Object
  */
function appp_add_membertypes_to_fields_api_response( $field_groups, $response, $request ) {

	foreach ( $response->data as $key => $field ) {

		if ( 'membertype' === $field['type'] ) {

			$member_types = bp_get_member_types();
			$selected_types = bp_xprofile_get_meta( $field['id'], 'field', 'bpmtp_field_selected_types', true );

			// error_log(print_r($member_types,true));
			// error_log(print_r($selected_types,true));
		
			// error_log(print_r($field,true));

			// {
			// 	"id": 42,
			// 	"group_id": 1,
			// 	"parent_id": 10,
			// 	"type": "option",
			// 	"name": "Singing",
			// 	"description": {
			// 	  "rendered": ""
			// 	},
			// 	"is_required": false,
			// 	"can_delete": true,
			// 	"field_order": 0,
			// 	"option_order": 1,
			// 	"order_by": "",
			// 	"is_default_option": false
			//   }

			$options = [];

			foreach( $selected_types as $key => $value ) {

				$options[ $key ] = array(
					'id' => $key,
					'type' => 'option',
					'name' => $value,
					'description' => array( 'rendered' => $value ),
					'is_required' => $field['is_required']
				);

			}

			$response->data[ $key ]['options'] = $options;

		}
	}

	return $response;
}
add_action( 'bp_rest_xprofile_fields_get_items', 'appp_add_membertypes_to_fields_api_response', 10, 3 );