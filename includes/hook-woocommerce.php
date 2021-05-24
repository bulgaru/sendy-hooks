<?php

// Hook into the Woocommerce payment routine
add_action('sendy_hooks_add_hooks', 'sendy_hook_woocommerce_init');
function sendy_hook_woocommerce_init() {
  if (class_exists('WooCommerce')) {
    add_action('woocommerce_thankyou', 'subscribe_from_woocommerce', 10, 1);
  }
}

// Add settings field
add_action('sendy_hooks_add_fields', 'sendy_hook_woocommerce_admin_init');
function sendy_hook_woocommerce_admin_init() {
  add_settings_field('sendy_hooks_list_purchase', 'List of purchasing clients (WC)', 'plugin_sendy_hooks_list_purchase', 'sendy_hooks', 'sendy_hooks_api_section');
}
function plugin_sendy_hooks_list_purchase() {
  $options  = get_option('sendy_hooks');
  $list_purchase = $options && $options['list_purchase'] ? $options['list_purchase'] : '';
  echo "<input id='sendy_hooks_list_purchase' name='sendy_hooks[list_purchase]' type='text' size='50' value='{$list_purchase}' />";
}

// Add field validation
add_filter('sendy_hooks_sanitize_fields', 'sendy_hook_woocommerce_validate', 10, 2);
function sendy_hook_woocommerce_validate($options, $input) {
  $options['list_purchase'] = trim($input['list_purchase']);
  return $options;
}

/**
 * Subscribe after payment
 *
 * @param int $user_id
 * @return WP_error or NULL
 */
function subscribe_from_woocommerce($order_id) {

  $options     = get_option('sendy_hooks');
  $form_error  = new WP_Error;

  $order       = new WC_Order($order_id);
  $user_id     = (int) $order->user_id;
  $user        = get_userdata($user_id);

  try {

    if ($options['url']) { $url = $options['url']; }
    else { throw new Exception(__('Missing url of Sendy instance', 'sendy-hooks')); }

    if ($options['list_purchase']) { $list = $options['list_purchase']; }
    else { throw new Exception(__('Missing ID of Sendy mailing list', 'sendy-hooks')); }

    if ($user->user_email) { $email = $user->user_email; }
    else { throw new Exception(__('Missing person\'s email address', 'sendy-hooks')); }

    if ($user->first_name && $user->last_name) {
      $name = $user->first_name . ' ' . $user->last_name;
    }
    else $name = '';

    return push_to_api($list, $email, $name);

  } catch (Exception $e) {

    $form_error->add('sendy_params_incorrect', $e->getMessage());

    return $form_error;
  }
}
