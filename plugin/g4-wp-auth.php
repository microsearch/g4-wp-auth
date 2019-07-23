<?php
/**
* Plugin Name: G4 Authentication Plugin
* Description: Authenticate users using G4 credentials.
* Version: 0.0.1
* Author: Ferruccio Barletta
**/

const MRCAPI = 'https://g4-dev.v1.mrcapi.net/authentication';
const TENANT = 'nso';

function split_name($name) {
    $name = trim($name);
    $last_name = (strpos($name, ' ') === false) ? '' : preg_replace('#.*\s([\w-]*)$#', '$1', $name);
    $first_name = trim( preg_replace('#'.$last_name.'#', '', $name ) );
    return array($first_name, $last_name);
}

function demo_auth($user, $username, $password) {

	if ($username == '' || $password == '') return;

	$request = [
		'method' => 'POST',
		'blocking' => true,
		'headers' => array(
			'content-type' => 'application/json',
			'x-g4-tenant' => TENANT
		),
		'body' => json_encode(array(
			'username' => $username,
			'password' => $password
		)),
		'data_format' => 'body'
	];
	$result = wp_remote_post(MRCAPI, $request);
	if (is_wp_error($result)) return;

	$auth = json_decode($result['body'], true);
	if ($auth['accessAllowed'] == 0) {
		$user = new WP_Error('denied', __("ERROR: Invalid username or password"));
	} else {
		$userobj = new WP_User();
		$user = $userobj->get_data_by('login', $auth['username']);
		$name = split_name($auth['fullname']);
		$userdata = [
			'user_pass' => $password,
			'user_login' => $auth['username'],
			'user_nicename' => $auth['username'],
			'user_email' => $auth['email'],
			'display_name' => $auth['fullname'],
			'first_name' => $name[0],
			'last_name' => $name[1]
		];
		if ($user != null && $user->ID != 0)
			$userdata['ID'] = $user->ID;
		$new_user_id = wp_insert_user($userdata);
		$user = new WP_User($new_user_id);
	}

	//remove_action('authenticate', 'wp_authenticate_username_password', 20);
	return $user;
}

add_filter('authenticate', 'demo_auth', 10, 3);

?>