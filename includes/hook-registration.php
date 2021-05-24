<?php

// Hook into the user registration routine
add_action('sendy_hooks_add_hooks', 'sendy_hook_registration_init');
function sendy_hook_registration_init() {
  add_action('user_register', 'subscribe_from_registration');
}

// Add settings field
add_action('sendy_hooks_add_fields', 'sendy_hook_registration_admin_init');
function sendy_hook_registration_admin_init() {
  add_settings_field('sendy_hooks_list_registered', 'List of registered users (WP)', 'plugin_sendy_hooks_list_registered', 'sendy_hooks', 'sendy_hooks_api_section');
}
function plugin_sendy_hooks_list_registered() {
  $options  = get_option('sendy_hooks');
  $list_registered = $options && $options['list_registered'] ? $options['list_registered'] : '';
  echo "<input id='sendy_hooks_list_registered' name='sendy_hooks[list_registered]' type='text' size='50' value='{$list_registered}' />";
}

// Add field validation
add_filter('sendy_hooks_sanitize_fields', 'sendy_hook_registration_validate', 10, 2);
function sendy_hook_registration_validate($options, $input) {
  $options['list_registered'] = trim($input['list_registered']);
  return $options;
}

/**
 * Subscribe during registration
 *
 * @param int $user_id
 * @return WP_error or NULL
 */
function subscribe_from_registration($user_id) {

  $options       = get_option('sendy_hooks');
  $form_error    = new WP_Error;

  try {

    if ($options['url']) { $url = $options['url']; }
    else { throw new Exception(__('Missing url of Sendy instance', 'sendy-hooks')); }

    if ($options['list_registered']) { $list = $options['list_registered']; }
    else { throw new Exception(__('Missing ID of Sendy mailing list', 'sendy-hooks')); }

    if (isset($_POST['email'])) { $email = $_POST['email']; }
    else { throw new Exception(__('Missing person\'s email address', 'sendy-hooks')); }

    if (isset($_POST['billing_first_name']) && isset($_POST['billing_last_name'])) {
      $name = $_POST['billing_first_name'] . ' ' . $_POST['billing_last_name'];
    }
    else $name = '';

    return push_to_api($list, $email, $name);

  } catch (Exception $e) {

    $form_error->add('sendy_params_incorrect', $e->getMessage());

    return $form_error;
  }
}
