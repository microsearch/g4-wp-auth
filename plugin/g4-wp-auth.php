<?php
/**
* Plugin Name: G4 Authentication Plugin
* Description: Authenticate users using G4 credentials.
* Version: 1.0
* Author: Ferruccio Barletta
**/

error_log('hello errors!');

function demo_auth($user, $username, $password) {
	remove_action('authenticate', 'wp_authenticate_username_password', 20);
	return $user;
}

add_filter('authenticate', 'demo_auth', 10, 3);

?>