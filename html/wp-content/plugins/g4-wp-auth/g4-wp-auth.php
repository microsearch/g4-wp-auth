<?php
/**
* Plugin Name: G4 Authentication Plugin
* Description: Authenticate users using G4 credentials.
* Version: 1.2.6
* Author: Ferruccio Barletta
**/

// uncomment to enable debugging output
// define('WP_DEBUG', true);
// define('WP_DEBUG_LOG', true);
// define('SCRIPT_DEBUG', true);
// define('SAVEQUERIES', true);

// if (!function_exists('write_log')) {
//     function write_log($log)  {
//         if (is_array($log) || is_object($log)) {
//             error_log(print_r($log, true));
//         } else {
//             error_log($log);
//         }
//     }
// }

include 'g4-plugin-settings.php';
include 'g4-no-email-login.php';

function g4_auth($user, $username, $password) {
    $wpdb = $GLOBALS['wpdb'];
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
        $auth_username = $auth['username'];
        $user = WP_User::get_data_by('login', $auth_username);
        $name = split_name($auth['fullname']);
        $userdata = [
            'user_pass' => $password,
            'user_login' => $auth_username,
            'user_nicename' => $auth_username,
            'user_email' => $auth['email'],
            'display_name' => $auth['fullname'],
            'first_name' => $name[0],
            'last_name' => $name[1]
        ];
        if ($user != null && $user->ID != 0)
            $userdata['ID'] = $user->ID;
        $existing_user = get_user_by('email', $auth['email']);
        if ($existing_user && $existing_user->user_login != $auth_username) {
            $result = $wpdb->update($wpdb->users,
                array('user_login' => $auth_username, 'user_nicename' => $auth_username),
                array('ID' => $existing_user->ID));
            $user =  new WP_Error('denied', __(
                "Your WordPress credentials have been updated to match your G4 credentials." .
                "<hr>Please enter your credentials once more to complete the login process."));
        } else {
            $new_user_id = wp_insert_user($userdata);
            if ($new_user_id instanceof WP_Error)
                return $new_user_id;
            $user = new WP_User($new_user_id);
            $role = (in_array($auth_username, $g4admins)) ? 'administrator' : 'subscriber';
            set_user_roles($role, $user, $auth);
        }
    }

    remove_action('authenticate', 'wp_authenticate_username_password', 20);
    return $user;
}

function g4_login($message) {
    wp_remote_post(get_service_endpoint().'/sync');
    return "<center><p>Authentication provided by MicroSearch G4</p></center><br/>";
}

add_filter('authenticate', 'g4_auth', 10, 3);
add_filter('login_message', 'g4_login', 10, 1);

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

function role_name($displayname) {
    return str_replace(' ', '-', normalize($displayname));
}

function new_role($displayname) {
    $rolename = role_name($displayname);
    add_role($rolename, $displayname);
    return $rolename;
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
    $user->remove_role('subscriber');
    switch (create_wp_roles()) {
        default:
        case 'none':
            return;
        case 'role_scope':
            $g4_role_scopes = array_map('normalize',
                explode(",", get_option('g4_role_scopes')));
            foreach ($g4_role_scopes as $role_scope) {
                foreach ($rolenames as $scoped_name) {
                    $len = strlen($role_scope);
                    if ($len > 0) {
                        if (substr($scoped_name, 0, $len + 1) === $role_scope . ":") {
                            $name = substr($scoped_name, $len + 1);
                            $user->add_role(new_role($name));
                            return;
                        }
                    }
                }
            }
            if (default_wp_role() !== '') {
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
                $user->add_role(role_name(default_wp_role()));
            }
            return;
    }

}

function split_name($name) {
    $name = trim($name);
    $last_name = (strpos($name, ' ') === false)
        ? ''
        : preg_replace('#.*\s([\w-]*)$#', '$1', $name);
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