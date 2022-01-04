<?php

function appp_upload_attachment_from_file( $files, $activity_id ) {
	global $appp_set_activity_id_global;
	$appp_set_activity_id_global = $activity_id;

	if ( ! function_exists( 'wp_handle_upload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	$overrides = array( 'test_form' => false );

	// Register our path override.
	add_filter( 'upload_dir', 'appp_activity_attachment_upload_dir' );

	// Do our thing. WordPress will move the file to 'uploads/attachments'.
	foreach ( $files['name'] as $key => $value ) {

		if ( $files['name'][ $key ] ) {

			$uploadedfile = array(
				'name'     => $files['name'][ $key ],
				'type'     => $files['type'][ $key ],
				'tmp_name' => $files['tmp_name'][ $key ],
				'error'    => $files['error'][ $key ],
				'size'     => $files['size'][ $key ],
			);

			$movefile = wp_handle_upload( $uploadedfile, $overrides );

			appp_shrink_image( $movefile['file'] );

		}
	}

	// Set everything back to normal.
	remove_filter( 'upload_dir', 'appp_activity_attachment_upload_dir' );

	$appp_set_activity_id_global = null;

}


/**
 * Override the default upload path.
 *
 * @param   array $dir
 * @return  array
 */
function appp_activity_attachment_upload_dir( $dir ) {

	global $appp_set_activity_id_global;

	return array(
		'path'   => $dir['basedir'] . '/attachments/activity/' . $appp_set_activity_id_global,
		'url'    => $dir['baseurl'] . '/attachments/activity/' . $appp_set_activity_id_global,
		'subdir' => '/attachments',
	) + $dir;
}

function appp_get_activity_attachments_path() {

	$upload_dir = wp_upload_dir();
	return $upload_dir['basedir'] . '/attachments/activity';

}

function appp_get_activity_attachments_url() {

	$upload_dir = wp_upload_dir();
	return $upload_dir['baseurl'] . '/attachments/activity';

}

function appp_get_activity_attachments( $activity_id ) {

	$dir = appp_get_activity_attachments_path() . '/' . $activity_id;
	$url = appp_get_activity_attachments_url() . '/' . $activity_id;

	$files = array();

	if ( is_dir( $dir ) ) {
		if ( $dh = opendir( $dir ) ) {
			while ( ( $file = readdir( $dh ) ) !== false ) {
				if ( $file == '.' || $file == '..' ) {
					continue;
				}
				$files[] = $url . '/' . $file;
			}
			closedir( $dh );
		}
	}

	return $files;
}

/**
 * Shrink upload image attachments
 *
 * @return string
 */
function appp_shrink_image( $file ) {

	// Get the image editor.
	$editor = wp_get_image_editor( $file );

	if ( is_wp_error( $editor ) ) {

		return $editor;
	}

	// Image and target size.
	$target   = 1200;
	$sizeORIG = $editor->get_size();

	// chekc if the image is larger than the target crop dimensions.
	if ( ( isset( $sizeORIG['width'] ) && $sizeORIG['width'] > $target ) || ( isset( $sizeORIG['height'] ) && $sizeORIG['height'] > $target ) ) {

		$width  = $sizeORIG['width'];
		$height = $sizeORIG['height'];

		if ( $width > $height ) {
			$percentage = ( $target / $width );
		} else {
			$percentage = ( $target / $height );
		}

		// gets the new value and applies the percentage, then rounds the value.
		$width  = round( $width * $percentage );
		$height = round( $height * $percentage );

		$resized = $editor->resize( $width, $height, false );

		// Stop in case of error.
		if ( is_wp_error( $resized ) ) {
			return $resized;
		}
	}

	$editor->set_quality( 90 );

	// Use the editor save method to get a path to the edited image.
	return $editor->save( $file );

}


function appp_get_image_data( $file ) {
	// Try to get image basic data.
	list( $width, $height, $sourceImageType ) = @getimagesize( $file );

	// No need to carry on if we couldn't get image's basic data.
	if ( is_null( $width ) || is_null( $height ) || is_null( $sourceImageType ) ) {
		return false;
	}

	// Initialize the image data.
	$image_data = array(
		'width'  => $width,
		'height' => $height,
	);

	/**
	 * Make sure the wp_read_image_metadata function is reachable for the old Avatar UI
	 * or if WordPress < 3.9 (New Avatar UI is not available in this case)
	 */
	if ( ! function_exists( 'wp_read_image_metadata' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	// Now try to get image's meta data.
	$meta = wp_read_image_metadata( $file );
	if ( ! empty( $meta ) ) {
		$image_data['meta'] = $meta;
	}

	/**
	 * Filter here to add/remove/edit data to the image full data
	 *
	 * @since 2.4.0
	 *
	 * @param array $image_data An associate array containing the width, height and metadatas.
	 */
	return $image_data;
}

/**
 * Filter notification response returned from the API. Creates attachments
 *
 * @since 1.0.0
 *
 * @param WP_REST_Response              $response     The response data.
 * @param WP_REST_Request               $request      Request used to generate the response.
 * @param BP_Notifications_Notification $notification Notification object.
 *
 * @return WP_REST_Response
 */
function appp_add_attachments_to_activity_items( $activity, $response, $request ) {
	$files = $request->get_file_params();

	if ( isset( $files['files'] ) ) {
		appp_upload_attachment_from_file( $files['files'], $activity->id );
	}

	return $response;

}
add_action( 'bp_rest_activity_create_item', 'appp_add_attachments_to_activity_items', 10, 3 );