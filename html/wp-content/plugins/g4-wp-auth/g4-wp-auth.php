<?php
/**
* Plugin Name: G4 Authentication Plugin
* Description: Authenticate users using G4 credentials.
* Version: 0.2.0
* Author: Ferruccio Barletta
**/

function g4_auth($user, $username, $password) {
	$admin = strtolower(trim(get_option('local_admin')));
	if ($username == '' || $password == '' || strtolower($username) == $admin) return;
	$admin = trim(get_option('local_admin'));
	$tenant = trim(get_option('tenant_name'));
	$endpoint = get_service_endpoint();
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
		$userinfo = get_userinfo($auth);
		$role = add_user_role($userinfo);
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
			'last_name' => $name[1],
			'role' => $role
		];
		if ($user != null && $user->ID != 0)
			$userdata['ID'] = $user->ID;
		$new_user_id = wp_insert_user($userdata);
		$user = new WP_User($new_user_id);
	}

	remove_action('authenticate', 'wp_authenticate_username_password', 20);
	return $user;
}

add_filter('authenticate', 'g4_auth', 10, 3);

function request_auth($username, $password) {
	$tenant = trim(get_option('tenant_name'));
	$request = [
		'method' => 'POST',
		'blocking' => true,
		'headers' => [
			'content-type' => 'application/json',
			'x-g4-tenant' => $tenant,
		],
		'body' => json_encode([
			'username' => $username,
			'password' => $password
		]),
		'data_format' => 'body'
	];
	return wp_remote_post(get_service_endpoint().'/auth', $request);
}

function get_userinfo($auth) {
	$tenant = trim(get_option('tenant_name'));
	$g4_userid = $auth['userId'];
	$request = [
		'method' => 'GET',
		'blocking' => true,
		'headers' => [
			'content-type' => 'application/json',
			'x-g4-tenant' => $tenant,
			'authorization' => 'Bearer '.$auth['bearer']
		]
	];
	$response = wp_remote_get(get_service_endpoint().'/user/'.$g4_userid, $request);
	return json_decode($response['body'], true);
}

function add_user_role($userinfo) {
	$rolenames = $userinfo['roleNames'];
	$role = 'Member';
	foreach ($rolenames as $name) {
		if (substr($name, 0, 9) === 'position:') {
			$role = substr($name, 9);
			break;
		} else if (substr($name, 0, 8) === 'g4admin:') {
			$role = 'MicroSearch Administrator';
			break;
		}
	}
	add_role($role, $role);
	return $role;
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

function register_g4_plugin_settings() {
	register_setting('g4-plugin-settings-group', 'service_endpoint');
	register_setting('g4-plugin-settings-group', 'tenant_name');
	register_setting('g4-plugin-settings-group', 'local_admin');
}

function get_service_endpoint() {
	$endpoint = trim(get_option('service_endpoint'));
	return $endpoint ==  '' ? 'https://g4-dev.v1.mrcapi.net' : $endpoint;
}

function g4_plugin_settings_page() {
?>
	<div class="wrap">
	<h1>G4 Authentication Settings</h1>

	<form method="post" action="options.php">
		<?php settings_fields('g4-plugin-settings-group'); ?>
		<?php do_settings_sections('g4-plugin-settings-group'); ?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row">WordPress Admin Username</th>
				<td>
					<input type="text" name="local_admin"
						value="<?php echo esc_attr(get_option('local_admin')); ?>"
						class="regular-text" />
					<p class="description">
						<b>Optional</b>, but highly recommended.
					</p>
					<p class="description">
						When the G4 Authentican plugin is activated, users with
						WordPress accounts (including the administrator) will no
						longer be able to login.
					</p>
					<p class="description">
						Entering the username of the WordPress administrator here
						will allow that user to authenticate locally,
						bypassing G4 authentication.
					</p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">G4 Service URL</th>
				<td>
					<input type="url" name="service_endpoint"
						value="<?php echo esc_attr(get_service_endpoint()); ?>"
						class="regular-text code" />
					<p class="description"><b>Required.</b>
					G4 Authentication will fail without this.
					</p>
					<p class="description">
						The URL of the G4 API.
						This should be set to <b>https://g4-dev.v1.mrcapi.net</b>
						while G4 is still in development.
					</p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">Tenant Name</th>
				<td>
					<input type="text" name="tenant_name"
						value="<?php echo esc_attr(get_option('tenant_name')); ?>"
						class="regular-text" />
					<p class="description"><b>Required.</b>
					G4 Authentication will fail without this.
					</p>
					<p class="description">
					The G4 tenant whose users should have access to this site.
					</p>
				</td>
			</tr>
			<tr valign="top">
			<th scope="row">Notes</th>
			<td>
				<p class="description">
					The data flow is strictly one way.
				</p>
				<p class="description">
					When a user is authenticated, the local user record is updated
					with that user's information or	a new user record is created if necessary.
				</p>
				<p class="description">
					We do this because a lot of WordPress functionality depends on having
					user records in its database.
				</p>
				<p class="description">
					Also, if there is a problem with G4 Authentication,
					you can deactivate the plugin and users will still be able to
					authenticate using up-to-date credentials.
				</p>
				<p class="description">
					However, if a user changes their password on the WordPress site,
					that change will not get pushed back to G4 and
					the user will still have to use their old password.
				</p>
				<p class="description">
					Password changes have to be made in G4. This is the case even when the
					plugin is active.
				</p>
			</td>
			</tr>
		</table>
		<?php submit_button(); ?>

		<?php if (isset($_GET['settings-updated'])) { ?>
			<div id="message" class="notice notice-success is-dismissible">
				<p><strong><?php _e('Settings saved.') ?></strong></p>
			</div>
		<?php } ?>


	</form>
	</div>
<?php
}

?>