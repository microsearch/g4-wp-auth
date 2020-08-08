<?php

function register_g4_plugin_settings() {
    register_setting('g4-plugin-settings-group', 'service_endpoint', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => 'https://g4-prod.v1.mrcapi.net'
    ));
    register_setting('g4-plugin-settings-group', 'tenant_name', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => NULL
    ));
    register_setting('g4-plugin-settings-group', 'local_admin', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => NULL
    ));
    register_setting('g4-plugin-settings-group', 'g4_admins', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => NULL
    ));
    register_setting('g4-plugin-settings-group', 'create_wp_roles');
    register_setting('g4-plugin-settings-group', 'roles_scope', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => 'g4'
    ));
    register_setting('g4-plugin-settings-group', 'default_wp_role', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => 'Subscriber'
    ));
}

function create_wp_roles() {
    return get_option('create_wp_roles');
}

function get_service_endpoint() {
    return trim(get_option('service_endpoint'));
}

function default_wp_role() {
    return trim(get_option('default_wp_role'));
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
                        This should be normally set to <b>https://g4-prod.v1.mrcapi.net</b>.
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
                <th scope="row">G4 Administrators</th>
                <td>
                    <input type="text" name="g4_admins"
                        value="<?php echo esc_attr(get_option('g4_admins')); ?>"
                        class="regular-text" />
                    <p class="description"><b>Optional.</b></p>
                    <p class="description">
                    Comma-separated list of G4 users who will be given WordPress <b>Administrator</b> access.
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">WordPress Roles</th>
                <td>
                    <select name="create_wp_roles">
                        <option <?php if (create_wp_roles() == "none") echo 'selected' ?>
                            value="none">Do not create WordPress roles</option>
                        <option <?php if (create_wp_roles() == "role_scope") echo 'selected' ?>
                            value="role_scope">Map G4 roles to WordPress roles</option>
                        <option <?php if (create_wp_roles() == "profile") echo 'selected' ?>
                            value="profile">Map G4 profiles to WordPress roles</option>
                    </select>
                    <p class="description">
                    Controls how WordPress roles are created.
                    </p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">G4 Roles Scope</th>
                <td>
                    <input type="text" name="roles_scope"
                        value="<?php echo esc_attr(get_option('roles_scope')); ?>"
                        class="regular-text" />
                    <p class="description"><b>Required only if "Map G4 roles to WordPress roles" is selected above.</b></p>
                    <p class="description">
                    Scope of G4 roles to use for WordPress roles.
                    </p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Default WordPress Role</th>
                <td>
                    <input type="text" name="default_wp_role"
                            value="<?php echo esc_attr(get_option('default_wp_role')); ?>"
                            class="regular-text" />
                    <p class="description"><b>Optional.</b></p>
                    <p class="description">WordPress role to use if all else fails.</p>
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