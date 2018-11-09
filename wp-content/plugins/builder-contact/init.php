<?php

/*
  Plugin Name:  Builder Contact
  Plugin URI:   http://themify.me/addons/contact
  Version:      1.2.0
  Author:       Themify
  Description:  Simple contact form. It requires to use with the latest version of any Themify theme or the Themify Builder plugin.
  Text Domain:  builder-contact
  Domain Path:  /languages
 */

defined('ABSPATH') or die('-1');

class Builder_Contact {

    public $url;
    private $dir;
    public $version;

    /**
     * Creates or returns an instance of this class.
     *
     * @return	A single instance of this class.
     */
    public static function get_instance() {
        static $instance = null;
        if ($instance === null) {
            $instance = new self;
        }
        return $instance;
    }

    private function __construct() {
        add_action('plugins_loaded', array($this, 'constants'), 1);
        add_action('plugins_loaded', array($this, 'i18n'), 5);
        add_action('themify_builder_setup_modules', array($this, 'register_module'));
		add_filter( 'plugin_row_meta', array( $this, 'themify_plugin_meta'), 10, 2 );

        if (is_admin()) {
            add_action('plugins_loaded', array($this, 'admin'), 10);
            add_action('init', array($this, 'updater'));
            add_action('themify_builder_admin_enqueue', array($this, 'admin_enqueue'));
			add_action( 'admin_footer', array( $this, 'new_field_template' ) );
        } else {
            add_filter('themify_styles_top_frame', array($this, 'admin_enqueue'), 10, 1);
            add_action( 'themify_builder_frontend_data', array( $this, 'new_field_template' ) );
        }
        add_action('wp_ajax_builder_contact_send', array($this, 'contact_send'));
        add_action('wp_ajax_nopriv_builder_contact_send', array($this, 'contact_send'));
    }

    public function constants() {
        $data = get_file_data(__FILE__, array('Version'));
        $this->version = $data[0];
        $this->url = trailingslashit(plugin_dir_url(__FILE__));
        $this->dir = trailingslashit(plugin_dir_path(__FILE__));
    }

	public function themify_plugin_meta( $links, $file ) {
		if ( plugin_basename( __FILE__ ) == $file ) {
			$row_meta = array(
			  'changelogs'    => '<a href="' . esc_url( 'https://themify.me/changelogs/' ) . basename( dirname( $file ) ) .'.txt" target="_blank" aria-label="' . esc_attr__( 'Plugin Changelogs', 'builder-contact' ) . '">' . esc_html__( 'View Changelogs', 'builder-contact' ) . '</a>'
			);
	 
			return array_merge( $links, $row_meta );
		}
		return (array) $links;
	}
    public function i18n() {
        load_plugin_textdomain('builder-contact', false, '/languages');
    }

    public function admin_enqueue($styles=false) {

		wp_enqueue_script( 'builder-contact-admin-scripts', themify_enque( $this->url . 'assets/admin-scripts.js' ), array( 'jquery' ), $this->version );
		wp_enqueue_style( 'builder-contact', themify_enque( $this->url . 'assets/admin.css' ), null, $this->version );

		return $styles;
    }

    public function register_module() {
        //temp code for compatibility  builder new version with old version of addon to avoid the fatal error, can be removed after updating(2017.07.20)
        if (class_exists('Themify_Builder_Component_Module')) {
            Themify_Builder_Model::register_directory('templates', $this->dir . 'templates');
            Themify_Builder_Model::register_directory('modules', $this->dir . 'modules');
        }
    }

    public function contact_send() {
        if (isset($_POST) && !empty($_POST)) {
            $result = array();
            /* reCAPTCHA validation */
            if (isset($_POST['contact-recaptcha'])) {
                $response = wp_remote_get("https://www.google.com/recaptcha/api/siteverify?secret=" . $this->get_option('recapthca_private_key') . "&response=" . $_POST['contact-recaptcha']);
                if (isset($response['body'])) {
                    $response = json_decode($response['body'], true);
                    if (!true == $response["success"]) {
                        $result['themify_message'] = '<p class="ui red contact-error">' . __("Bots are not allowed to submit.", 'builder-contact') . '</p>';
                        $result['themify_error'] = 1;
                        ;
                    }
                } else {
                    $result['themify_message'] = '<p class="ui red contact-error">' . __("Trouble verifying captcha. Please try again.", 'builder-contact') . '</p>';
                    $result['themify_error'] = 1;
                }
            }
            if (empty($result)) {
                $settings = unserialize(stripslashes($_POST['contact-settings']));
                $recipients = array_map('trim', explode(',', $settings['sendto']));
                $name = trim(stripslashes($_POST['contact-name']));
                $email = trim(stripslashes($_POST['contact-email']));
                $subject = isset($_POST['contact-subject']) ? trim(stripslashes($_POST['contact-subject'])) : '';
                if (empty($subject)) {
                    $subject = $settings['default_subject'];
                }
                $message = trim(stripslashes($_POST['contact-message']));

				$fliped_array = array_flip( preg_grep( '/^(field_extra_name|contact-message)/', array_keys( $_POST ) ) );
				$extra_fields = array_intersect_key( $_POST, $fliped_array);
				$field = '';
				foreach( $extra_fields as $key => $field_name ) {
					if( 'contact-message' === $key ) {
						$field .= "\n\n" . $message . "\n\n";
						continue;
					}
					$index = str_replace( '_name', '',$key );
					$value =  $_POST[ $index ];

					if( is_array( $value ) ){
						$final_value = '';
						foreach( $value as $val ){
							$final_value .= $val . ', ';
						}
						$value = trim( stripslashes( substr( $final_value, 0, -2 ) ) );
					}else{
						$value = trim( stripslashes( $value ) );
					}
					$field_name = trim( stripslashes( $field_name ) );
					$field .= $field_name . ":\n" . $value . "\n\n";

				}
				$message = $field;

                $subject = apply_filters('builder_contact_subject', $subject);
                if ('' == $name || '' == $email || '' == $message) {
                    $result['themify_message'] = '<p class="ui red contact-error">' . __('Please fill in the required data.', 'builder-contact') . '</p>';
                    $result['themify_error'] = 1;
                } else {
                    if (!is_email($email)) {
                        $result['themify_message'] = '<p class="ui red contact-error">' . __('Invalid Email address!', 'builder-contact') . '</p>';
                        $result['themify_error'] = 1;
                    } else {
						$headers = 'Reply-To: "' . $name . '" <' . $email . ">\n";
						$headers .= 'From: "' . $name . '" <' . $email . ">";
                        // add the email address to message body
                        $message = __('From:', 'builder-contact') . ' ' . $name . ' <' . $email . '>' . "\n\n" . $message;
                        add_filter('wp_mail_content_type', array($this, 'set_content_type'), 100, 1);
                        if (isset($_POST['contact-sendcopy']) && $_POST['contact-sendcopy'] == '1') {
                            wp_mail($email, $subject, $message, $headers);
                        }
                        foreach ($recipients as $recipient) {
                            $recipient = $this->str_rot47($recipient);
                            if (wp_mail($recipient, $subject, $message, $headers)) {
                                $result['themify_message'] = '<p class="ui light-green contact-success">' . __('Message sent. Thank you.', 'builder-contact') . '</p>';
                                $result['themify_success'] = 1;
                                if( !empty( $settings['success_url'] ) ){
                                    $result['themify_message'] .= '<script>window.location = "' . esc_attr( $settings['success_url'] ) . '"</script>';
                                }
                            } else {
                                global $ts_mail_errors, $phpmailer;
                                if (!isset($ts_mail_errors))
                                    $ts_mail_errors = array();
                                if (isset($phpmailer)) {
                                    $ts_mail_errors[] = $phpmailer->ErrorInfo;
                                }
                                $result['themify_message'] = '<p class="ui red contact-error">' . __('There was an error. Please try again.', 'builder-contact') . '<!-- ' . implode(', ', $ts_mail_errors) . ' -->' . '</p>';
                                $result['themify_error'] = 1;
                            }
                        }
                        remove_filter('wp_mail_content_type', array($this, 'set_content_type'), 100, 1);
                        do_action('builder_contact_mail_sent');
                    }
                }
            }
            echo wp_json_encode($result);
        }
        wp_die();
    }

    public function set_content_type($content_type) {
        return 'text/plain';
    }

    public function admin() {
        require_once( $this->dir . 'includes/admin.php' );
        new Builder_Contact_Admin();
    }

    public function get_option($name, $default = null) {
        $options = get_option('builder_contact');
        if (isset($options[$name])) {
            return $options[$name];
        } else {
            return $default;
        }
    }

    public function updater() {
        if (class_exists('Themify_Builder_Updater')) {
            if (!function_exists('get_plugin_data')){
                include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
            }
            $plugin_basename = plugin_basename(__FILE__);
            $plugin_data = get_plugin_data(trailingslashit(plugin_dir_path(__FILE__)) . basename($plugin_basename));
            new Themify_Builder_Updater(array(
                'name' => trim(dirname($plugin_basename), '/'),
                'nicename' => $plugin_data['Name'],
                'update_type' => 'addon',
                    ), $this->version, trim($plugin_basename, '/'));
        }
    }

    public static function str_rot47($str) {
        return strtr($str, '!"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}~', 'PQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}~!"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNO');
    }

	public function new_field_template() {?>
		<script type="text/html" id="tmpl-builder-contact-new-field">
			<#
					data.fieldTypes = {
					text: '<?php esc_html_e( 'Text', 'builder-contact' ); ?>',
					textarea: '<?php esc_html_e( 'Textarea', 'builder-contact' ); ?>',
					radio: '<?php esc_html_e( 'Radio', 'builder-contact' ); ?>',
					select: '<?php esc_html_e( 'Select', 'builder-contact' ); ?>',
					checkbox: '<?php esc_html_e( 'Checkbox', 'builder-contact' ); ?>'
				};
				if ( ! data.field ) {
					data.field = {
						label: '<?php esc_html_e( 'Field Name', 'builder-contact' ); ?>',
						type: 'text',
						required: false
					}
				}
			#>
			<tr class="tb-contact-new-row">
				<td>
                                    <span class="ti-split-v"></span>
                                    <div><input type="text" class="tb-new-field-textbox" value="{{ data.field.label }}"></div>
				</td>
				<td colspan="2">
					<div class="tb-new-field-type">
						<ul>
							<# _.each( data.fieldTypes, function( type, index ) { #>
								<li>
                                                                    <label>
                                                                        <input type="radio" name="tb-new-field-type-{{ data.id }}" <# index === data.field.type && print( 'checked' ) #> value="{{ index }}">{{ type }}
                                                                    </label>
								</li>
							<# }); #>
						</ul>
					</div>
					<div class="tb-new-field">
						<#
							if ( data.field ){
								field = data.field;
								#>
								<div class="builder-contact-field builder-contact-field-message">
									<div class="control-input">
										<# if( 'text' === field.type || 'textarea' === field.type ){ #>
											<input type="text" class="tb-new-field-value tb-field-type-text" placeholder="Placeholder" value="{{ field.value }}">
										<# } else if( 'radio' === field.type || 'select' === field.type || 'checkbox' === field.type ){ #>
											<ul>
												<# _.each( field.value, function( value, index ){ #>
													<li>
														<input type="text" value="{{ value }}" class="tb-multi-option" data-control-binding="live" data-control-event="keyup" data-control-type="change">
														<a href="#" class="tb-contact-value-remove"><i class="ti ti-close"></i></a>
													</li>
												<# }) #>
											</ul>
											<a href="#" class="tb-add-field-option"><?php esc_html_e( 'Add Option', 'builder-contact' ); ?></a>
										<# } #>
									</div><input type="checkbox" class="tb-new-field-required" <# true === data.field.required && print( 'checked' ) #> value="required">Required
								</div>
							<# } #>
					</div>
					<a href="#" class="tb-contact-field-remove"><i class="ti ti-close"></i></a>
				</td>
				<td></td>
			</tr>
		</script>
		<script type="text/html" id="tmpl-builder-contact-new-field-options">
			<ul>
                            <# _.each( data.fields, function( field, index ) { #>
                                <li>
                                    <input type="text" value="{{ field }}" class="tb-multi-option"  data-control-binding="live" data-control-event="keyup" data-control-type="change"><a href="#" class="tb-contact-value-remove"><i class="ti ti-close"></i></a>
                                </li>
                            <# } ); #>
			</ul>
			<a href="#" class="tb-add-field-option"><?php esc_html_e( 'Add Option', 'builder-contact' ); ?></a>
		</script>
	<?php }
}

Builder_Contact::get_instance();
