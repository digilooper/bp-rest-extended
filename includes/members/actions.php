<?php 

/**
 * This is to set member type if it is present in the sign up meta during activation.
 *
 * @param String $user_id
 * @param String $key
 * @param Array $user
 * @return void
 */
function appp_set_member_type_during_activation( $user_id, $key, $user ) {
    if( isset( $user['meta'] ) && isset( $user['meta']['member_type'] ) ) {
       $membertype = bp_set_member_type( $user_id, $user['meta']['member_type'] );
    }
}
add_action( 'bp_core_activated_user', 'appp_set_member_type_during_activation', 10, 3  );