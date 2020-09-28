<?php

function login_username_label_change($translated_text, $text, $domain)  {
	if ($text === 'Username or Email Address') {
		$translated_text = __('Username');
	}
	return $translated_text;
}

function login_username_label() {
	add_filter( 'gettext', 'login_username_label_change', 20, 3 );
}

add_action( 'login_head', 'login_username_label' );

function change_login_username_label( $defaults ){
    $defaults['label_username'] = __('Username');
    return $defaults;
}

remove_filter('authenticate', 'wp_authenticate_email_password', 20);