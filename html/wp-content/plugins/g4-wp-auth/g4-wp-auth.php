<?php
/**
* Plugin Name: G4 Authentication Plugin
* Description: Authenticate users using G4 credentials.
* Version: 1.2.1
* Author: Ferruccio Barletta
**/

include 'g4-plugin-settings.php';

function g4_auth($user, $username, $password) {
	$admin = normalize(get_option('local_admin'));
	if ($username == '' || $password == '' || normalize($username) == $admin) return;
	$admin = normalize(get_option('local_admin'));
	$tenant = normalize(get_option('tenant_name'));
	$endpoint = get_service_endpoint();
	$g4admins = array_map('normalize', explode(",", get_option('g4_admins')));
	if ($tenant == '' || get_service_endpoint() == '') {
		remove_action('authenticate', 'wp_authenticate_username_password', 20);
		return new WP_Error('denied', __("ERROR: G4 Authentication plugin is not properly configured"));
	}

	$result = request_auth($username, $password);
	if (is_wp_error($result)) {
		remove_action('authenticate', 'wp_authenticate_username_password', 20);
		return new WP_Error('denied', __("ERROR: Failed to connect to G4 Authentication Service"));
	}

	$auth = json_decode($result['body'], true);
	if (isset($auth['error']) && $auth['error'] != null) {
		remove_action('authenticate', 'wp_authenticate_username_password', 20);
		return new WP_Error('denied', __("G4 ERROR: ").$auth['error']);
	}

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
		$role = (in_array($auth['username'], $g4admins)) ? 'administrator' : 'subscriber';
		set_user_roles($role, $user, $auth);
	}

	remove_action('authenticate', 'wp_authenticate_username_password', 20);
	return $user;
}

add_filter('authenticate', 'g4_auth', 10, 3);

function normalize($name) {
	return strtolower(trim($name));
}

function request_auth($username, $password) {
	$tenant = normalize(get_option('tenant_name'));
	$request = [
		'method' => 'POST',
		'blocking' => true,
		'headers' => [
			'content-type' => 'application/json',
			'x-g4-tenant' => $tenant,
			'x-g4-application' => 'wp-auth'
		],
		'body' => json_encode([
			'username' => $username,
			'password' => $password,
			'detail'=> [
				'remote-addr' => $_SERVER['REMOTE_ADDR']
			]
		]),
		'data_format' => 'body'
	];
	return wp_remote_post(get_service_endpoint().'/auth', $request);
}

function new_role($displayname) {
	$name = str_replace(' ', '-', normalize($displayname));
	add_role($name, $displayname);
	return $name;
}

function set_user_roles($role, $user, $auth) {
	$rolenames = $auth['roles'];
	foreach ($rolenames as $name) {
		if (substr($name, 0, 8) === 'g4admin:') {
			$user->set_role('administrator');
			$user->add_role(new_role('MicroSearch Administrator'));
			return;
		}
	}

	$user->set_role($role);
	switch (create_wp_roles()) {
		default:
		case 'none':
			return;
		case 'role_scope':
			$roles_scope = normalize(get_option('roles_scope'));
			$role_count = 0;
			foreach ($rolenames as $scoped_name) {
				$len = strlen($roles_scope);
				if ($len > 0) {
					if (substr($scoped_name, 0, $len + 1) === $roles_scope . ":") {
						$name = substr($scoped_name, $len + 1);
						$user->add_role(new_role($name));
						++$role_count;
					}
				}
			}
			if ($role_count === 0 && default_wp_role() !== '') {
				$user->add_role(default_wp_role());
			}
			return;
		case 'profile':
			$profilenames = $auth['profiles'];
			$role_count = 0;
			foreach ($profilenames as $name) {
				$user->add_role(new_role($name));
				++$role_count;
			}
			if ($role_count === 0 && default_wp_role() !== '') {
				$user->add_role(default_wp_role());
			}
			return;
	}

}

function split_name($name) {
    $name = trim($name);
    $last_name = (strpos($name, ' ') === false) ? '' : preg_replace('#.*\s([\w-]*)$#', '$1', $name);
    $first_name = trim(preg_replace('#'.$last_name.'#', '', $name));
    return array($first_name, $last_name);
}

add_action('admin_menu', 'g4_plugin_create_menu');

function g4_plugin_create_menu() {
	add_menu_page('G4 Authentication', 'Authentication', 'administrator',
		__FILE__, 'g4_plugin_settings_page');
	add_action('admin_init', 'register_g4_plugin_settings');
}

?>