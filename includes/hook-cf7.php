<?php

/**
 * Shortcode format: [sendy list_id "PzdYpyrRQByF8FZPKJbdSw"]
 **/

// Hook into the CF7 submission routine
add_action('sendy_hooks_add_hooks', 'sendy_hook_cf7_init');
function sendy_hook_cf7_init() {
  if (function_exists('wpcf7_add_form_tag')) {
    add_action('wpcf7_before_send_mail', 'subscribe_from_cf7', 10, 1);
    wpcf7_add_form_tag('sendy', 'get_list_id', array('name-attr' => true));
  }
}

// Add settings field
add_action('sendy_hooks_add_fields', 'sendy_hook_cf7_admin_init');
function sendy_hook_cf7_admin_init() {
  add_settings_field('sendy_hooks_list_contact', 'List of contact form leads (CF7)', 'plugin_sendy_hooks_list_contact', 'sendy_hooks', 'sendy_hooks_api_section');

  add_settings_section('sendy_hooks_cf7_section', 'Contact Form 7 settings', 'plugin_cf7_settings_section_text', 'sendy_hooks');
  add_settings_field('sendy_hooks_cf7_name', 'String used as "name" in CF7', 'plugin_sendy_hooks_cf7_name', 'sendy_hooks', 'sendy_hooks_cf7_section');
  add_settings_field('sendy_hooks_cf7_email', 'String used as "email" in CF7', 'plugin_sendy_hooks_cf7_email', 'sendy_hooks', 'sendy_hooks_cf7_section');
  add_settings_field('sendy_hooks_cf7_forms', 'CF7 forms to be synced with Sendy', 'plugin_sendy_hooks_cf7_forms', 'sendy_hooks', 'sendy_hooks_cf7_section');
}
function plugin_sendy_hooks_list_contact() {
  $options  = get_option('sendy_hooks');
  $list_contact = $options && $options['list_contact'] ? $options['list_contact'] : '';
  echo "<input id='sendy_hooks_list_contact' name='sendy_hooks[list_contact]' type='text' size='50' value='{$list_contact}' />";
}
function plugin_cf7_settings_section_text() {
  echo '<p>Please fill in the Contact Form 7 settings to be used.';
}
function plugin_sendy_hooks_cf7_name() {
  $options  = get_option('sendy_hooks');
  $cf7_name = $options && $options['cf7_name'] ? $options['cf7_name'] : '';
  echo "<input id='sendy_hooks_cf7_name' name='sendy_hooks[cf7_name]' type='text' size='50' value='{$cf7_name}' />";
}
function plugin_sendy_hooks_cf7_email() {
  $options  = get_option('sendy_hooks');
  $cf7_email = $options && $options['cf7_email'] ? $options['cf7_email'] : '';
  echo "<input id='sendy_hooks_cf7_email' name='sendy_hooks[cf7_email]' type='text' size='50' value='{$cf7_email}' />";
}
function plugin_sendy_hooks_cf7_forms() {
  $options  = get_option('sendy_hooks');
  $posts    = get_posts(array(
    'post_type'   => 'wpcf7_contact_form',
    'numberposts' => -1
  ));
  echo '<select name="sendy_hooks[cf7_forms][]" id="sendy_hooks_cf7_email" multiple="multiple">';
  foreach ($posts as $post) {
    echo '<option value="' . $post->ID . '"' .
      (is_array($options['cf7_forms']) && in_array($post->ID, $options['cf7_forms']) ? ' selected="selected" ' : '') .
      '>' . $post->post_title . ' (' . $post->ID . ')</option>';
  }
  echo '</select>';
}

// Add field validation
add_filter('sendy_hooks_sanitize_fields', 'sendy_hook_cf7_validate', 10, 2);
function sendy_hook_cf7_validate($options, $input) {
  $options['list_contact']  = trim($input['list_contact']);
  $options['cf7_name']      = trim($input['cf7_name']);
  $options['cf7_email']     = trim($input['cf7_email']);
  $options['cf7_forms']     = $input['cf7_forms'];

  return $options;
}

/**
 * Creates a hidden input with the sendy list id
 *
 * @param mixed $args Array or string
 * @return string Hidden input with Sendy list id
 */
function get_list_id($tag) {

  $atts = array(
    'type'  => 'hidden',
    'name'  => $tag->name,
    'value' => implode(',', $tag->values),
  );

  if (count($tag->values) == 0) {
    return false;
  }
  $input = sprintf(
    '<input %s />',
    wpcf7_format_atts( $atts )
  );

  return $input;
}

/**
 * Subscribe from WPCF Forms after the email has been sent
 *
 * @param array $args
 * @return WP_error or NULL
 */
function subscribe_from_cf7($contact_form) {

  $options         = get_option('sendy_hooks');
  $form_error      = new WP_Error;
  $form_id         = $_POST['_wpcf7'];
  $cf7_name_tag    = ($options['cf7_name'] ? $options['cf7_name'] : 'your-name');
  $cf7_email_tag   = ($options['cf7_email'] ? $options['cf7_email'] : 'your-email');
  $cf7_forms       = ($options['cf7_forms'] ? $options['cf7_forms'] : array());

  // check if the contact form needs to send data to Sendy
  if (!isset($_POST['list_id']) && !in_array($form_id, $cf7_forms)) { return; }

  try {

    if ($options['url']) { $url = $options['url']; }
    else { throw new Exception(__('Missing url of Sendy instance', 'sendy-hooks')); }

    if (isset($_POST['list_id'])) { $list = $_POST['list_id']; }
    elseif ($options['list_contact']) { $list = $options['list_contact']; }
    else { throw new Exception(__('Missing ID of Sendy mailing list', 'sendy-hooks')); }

    if (isset($_POST[$cf7_name_tag])) { $name = $_POST[$cf7_name_tag]; }
    else { throw new Exception(__('Missing person\'s name', 'sendy-hooks')); }

    if (isset($_POST[$cf7_email_tag])) { $email = $_POST[$cf7_email_tag]; }
    else { throw new Exception(__('Missing person\'s email address', 'sendy-hooks')); }

    return push_to_api($list, $email, $name);

  } catch (Exception $e) {

    $form_error->add('sendy_params_incorrect', $e->getMessage());

    return $form_error;
  }
}
