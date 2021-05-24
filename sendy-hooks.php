<?php
/*
  Plugin Name: Sendy Hooks
  Plugin URI: https://bsensus.com
  Description: Sendy integration with User registration, Contact Form 7 and WooCommerce
  Author: b-sensus
  Version: 1.2.1
  Author URI: https://bsensus.com
  WC requires at least: 3.0.0
  WC tested up to: 5.2.2
*/

define('SENDY_HOOKS_PLUGIN', __FILE__);
define('SENDY_HOOKS_PLUGIN_DIR', untrailingslashit(dirname(SENDY_HOOKS_PLUGIN)));

/**
 * Interface
 */
add_action('init', 'sendy_hooks_init');
function sendy_hooks_init() {
  do_action('sendy_hooks_add_hooks');
}

/**
 * Settings
 */
add_action('admin_menu', 'plugin_admin_add_sendy_hooks_page');
function plugin_admin_add_sendy_hooks_page() {
  add_options_page('Sendy settings', 'Sendy Hooks', 'manage_woocommerce', 'sendy_hooks', 'plugin_admin_sendy_hooks_options');
}

function plugin_admin_sendy_hooks_options() {
?>
<div>
  <h2>Sendy integration settings</h2>
  These are the settings that help you set-up the application for Sendy integration.<br>
  Please fulfill the requirements when including or changing parameters.
  <form action="options.php" method="post">
    <?php settings_fields('sendy_hooks_options'); ?>
    <?php do_settings_sections('sendy_hooks'); ?>

    <input name="Submit" class="button-primary" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />
  </form>
</div>
<?php
}

add_action('admin_init', 'plugin_admin_init');
function plugin_admin_init() {
  register_setting('sendy_hooks_options', 'sendy_hooks', 'plugin_sendy_hooks_validate');
  add_settings_section('sendy_hooks_api_section', 'API data for sendy connection', 'plugin_api_settings_section_text', 'sendy_hooks');
  add_settings_field('sendy_hooks_url', 'Sendy Installation subscribe URL', 'plugin_sendy_hooks_url', 'sendy_hooks', 'sendy_hooks_api_section');
  add_settings_field('sendy_hooks_key', 'Sendy API key (found in settings)', 'plugin_sendy_hooks_key', 'sendy_hooks', 'sendy_hooks_api_section');

  do_action('sendy_hooks_add_fields');
}
function plugin_api_settings_section_text() {
  echo '<p>Please fill in the Sendy API settings to be used.';
}
function plugin_sendy_hooks_url() {
  $options  = get_option('sendy_hooks');
  echo "<input id='sendy_hooks_url' name='sendy_hooks[url]' type='text' size='50' value='{$options['url']}' />";
}
function plugin_sendy_hooks_key() {
  $options  = get_option('sendy_hooks');
  echo "<input id='sendy_hooks_key' name='sendy_hooks[key]' type='text' size='50' value='{$options['key']}' />";
}

/**
 * Validation
 */
function validate_url($url) {
  $path           = parse_url($url, PHP_URL_PATH);
  $encoded_path   = array_map('urlencode', explode('/', $path));
  $url            = str_replace($path, implode('/', $encoded_path), $url);
  return filter_var($url, FILTER_VALIDATE_URL) ? true : false;
}

function plugin_sendy_hooks_validate($input) {
  $options = get_option('sendy_hooks');

  // sanitize the default fields
  $options['url'] = trim($input['url']);
  $options['key'] = trim($input['key']);
  if (!validate_url($options['url'])) {
    $options['url'] = '';
  }

  // use a filter to sanitize the hooks' fields
  $options = apply_filters('sendy_hooks_sanitize_fields', $options, $input);

  return $options;
}

/**
 * Push user registration through Sendy api
 * https://sendy.co/api
 *
 * @param string $name
 * @param string $email
 * @param string $list
 * @return WP_error or NULL
 */
function push_to_api($list, $email, $name = "") {

  $form_error   = new WP_Error;
  $options      = get_option('sendy_hooks');

  try {

    $postdata   = http_build_query(array(
      'api_key'  => $options['key'],
      'name'     => $name,
      'email'    => $email,
      'list'     => $list,
      'boolean'  => 'true'
    ));
    $opts       = array('http' => array(
      'method'   => 'POST',
      'header'   => 'Content-type: application/x-www-form-urlencoded',
      'content'  => $postdata
    ));

    $context    = stream_context_create($opts);
    $result     = file_get_contents($options['url'], false, $context);

    if (!$result) {
      throw new Exception(__('Something went wrong. We\'re fixing it!', 'sendy-hooks'));
    }

    return;

  } catch (Exception $e) {

    $form_error->add('sendy_rejected', $e->getMessage());

    return $form_error;
  }
}

/**
 * Adding all hooks
 */
foreach (glob(SENDY_HOOKS_PLUGIN_DIR . '/includes/*.php') as $filename) {
  require_once($filename);
}
