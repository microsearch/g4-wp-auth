<?php
/**
* Plugin Name: G4 Authentication Plugin
* Description: Authenticate users using G4 credentials.
* Version: 0.0.1
* Author: Ferruccio Barletta
**/

function g4_auth($user, $username, $password) {
	if ($username == '' || $password == '') return;
	$tenant = trim(get_option('tenant_name'));
	$endpoint = get_service_endpoint();
	if ($tenant == '' || get_service_endpoint() == '') return;

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
	$result = wp_remote_post($endpoint.'/authentication', $request);
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

add_filter('authenticate', 'g4_auth', 10, 3);

function split_name($name) {
    $name = trim($name);
    $last_name = (strpos($name, ' ') === false) ? '' : preg_replace('#.*\s([\w-]*)$#', '$1', $name);
    $first_name = trim( preg_replace('#'.$last_name.'#', '', $name ) );
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
				<th scope="row">G4 Service URL</th>
				<td>
					<input type="url" name="service_endpoint"
						value="<?php echo esc_attr(get_service_endpoint()); ?>"
						class="regular-text code" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">Tenant Name</th>
				<td>
					<input type="text" name="tenant_name"
						value="<?php echo esc_attr(get_option('tenant_name')); ?>"
						class="regular-text" />
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>

	</form>
	</div>
<?php
}

?>