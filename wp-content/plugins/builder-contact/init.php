<?php
/*
  Plugin Name:  Builder Contact
  Plugin URI:   https://themify.me/addons/contact
  Version:      1.3.0
  Author:       Themify
  Author URI:   https://themify.me
  Description:  Simple contact form. It requires to use with the latest version of any Themify theme or the Themify Builder plugin.
  Text Domain:  builder-contact
  Domain Path:  /languages
 */

defined( 'ABSPATH' ) or die( '-1' );

class Builder_Contact {

	public $url;
	private $dir;
	public $version;
	private $from_name;

	/**
	 * Creates or returns an instance of this class.
	 *
	 * @return    A single instance of this class.
	 */
	public static function get_instance() {
		static $instance = null;
		if ( $instance === null ) {
			$instance = new self;
		}
		return $instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'constants' ), 1 );
		add_action( 'plugins_loaded', array( $this, 'i18n' ), 5 );
		add_action( 'themify_builder_setup_modules', array( $this, 'register_module' ) );
		add_filter( 'themify_builder_addons_assets', array( $this, 'assets' ), 10, 1 );
		add_filter( 'plugin_row_meta', array( $this, 'themify_plugin_meta' ), 10, 2 );
		add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( $this, 'action_links') );

		if ( is_admin() ) {
			add_action( 'plugins_loaded', array( $this, 'admin' ), 10 );
			add_action( 'init', array( $this, 'updater' ) );
			add_action( 'themify_builder_admin_enqueue', array( $this, 'admin_enqueue' ) );
			add_action( 'admin_footer', array( $this, 'new_field_template' ) );
		} else {
			add_filter( 'themify_styles_top_frame', array( $this, 'admin_enqueue' ), 10, 1 );
			add_action( 'themify_builder_frontend_data', array( $this, 'new_field_template' ) );
		}
		add_action( 'wp_ajax_builder_contact_send', array( $this, 'contact_send' ) );
		add_action( 'wp_ajax_nopriv_builder_contact_send', array( $this, 'contact_send' ) );
		add_action( 'init', array( $this, 'create_post_type' ) );
		add_filter( 'manage_contact_messages_posts_columns', array( $this, 'set_custom_columns' ) );
		add_action( 'manage_contact_messages_posts_custom_column', array( $this, 'custom_contact_messages_columns' ), 10, 2 );

	}

	public function constants() {
		$data = get_file_data( __FILE__, array( 'Version' ) );
		$this->version = $data[ 0 ];
		$this->url = trailingslashit( plugin_dir_url( __FILE__ ) );
		$this->dir = trailingslashit( plugin_dir_path( __FILE__ ) );
	}

	public function themify_plugin_meta( $links, $file ) {
		if ( plugin_basename( __FILE__ ) == $file ) {
			$row_meta = array(
				'changelogs' => '<a href="' . esc_url( 'https://themify.me/changelogs/' ) . basename( dirname( $file ) ) . '.txt" target="_blank" aria-label="' . esc_attr__( 'Plugin Changelogs', 'builder-contact' ) . '">' . esc_html__( 'View Changelogs', 'builder-contact' ) . '</a>'
			);

			return array_merge( $links, $row_meta );
		}
		return (array)$links;
	}

	public function action_links( $links ) {
		if ( is_plugin_active( 'themify-updater/themify-updater.php' ) ) {
			$tlinks = array(
			 '<a href="' . admin_url( 'index.php?page=themify-licence' ) . '">'.__('Themify Licence', 'builder-contact') .'</a>',
			 );
		} else {
			$tlinks = array(
			 '<a href="' . esc_url('https://themify.me/docs/themify-updater-documentation') . '">'. __('Themify Updater', 'builder-contact') .'</a>',
			 );
		}
		return array_merge( $links, $tlinks );
	}

	public function i18n() {
		load_plugin_textdomain( 'builder-contact', false, '/languages' );
	}

	public function admin_enqueue( $styles = false ) {
		if ( ! function_exists( 'themify_enque' ) ) {
			return;
		}

		wp_enqueue_script( 'builder-contact-admin-scripts', themify_enque( $this->url . 'assets/admin-scripts.js' ), array( 'jquery' ), $this->version );
		wp_enqueue_style( 'builder-contact-admin-styles', themify_enque( $this->url . 'assets/admin.css' ), null, $this->version );

		return $styles;
	}

	public function register_module() {
		//temp code for compatibility  builder new version with old version of addon to avoid the fatal error, can be removed after updating(2017.07.20)
		if ( class_exists( 'Themify_Builder_Component_Module' ) ) {
			Themify_Builder_Model::register_directory( 'templates', $this->dir . 'templates' );
			Themify_Builder_Model::register_directory( 'modules', $this->dir . 'modules' );
		}
	}

	public function contact_send() {

		if ( isset( $_POST ) && !empty( $_POST ) ) {
			$result = array();
			/* reCAPTCHA validation */
			if ( isset( $_POST[ 'contact-recaptcha' ] ) ) {
				$response = wp_remote_get( "https://www.google.com/recaptcha/api/siteverify?secret=" . $this->get_option( 'recapthca_private_key' ) . "&response=" . $_POST[ 'contact-recaptcha' ] );
				if ( isset( $response[ 'body' ] ) ) {
					$response = json_decode( $response[ 'body' ], true );
					if ( !true == $response[ "success" ] ) {
						$result[ 'themify_message' ] = '<p class="ui red contact-error">' . __( "Please verify captcha.", 'builder-contact' ) . '</p>';
						$result[ 'themify_error' ] = 1;;
					}
				} else {
					$result[ 'themify_message' ] = '<p class="ui red contact-error">' . __( "Trouble verifying captcha. Please try again.", 'builder-contact' ) . '</p>';
					$result[ 'themify_error' ] = 1;
				}
			}
			if ( empty( $result ) ) {
			    
				//end of save uploaded files variables
				$_POST[ 'contact-settings' ] = base64_decode( $_POST[ 'contact-settings' ] );
				$settings = unserialize( stripslashes( $_POST[ 'contact-settings' ] ) );
				$recipients = array_map( 'trim', explode( ',', $settings[ 'sendto' ] ) );
				$name = trim( stripslashes( $_POST[ 'contact-name' ] ) );
				$email = trim( stripslashes( $_POST[ 'contact-email' ] ) );
				$subject = isset( $_POST[ 'contact-subject' ] ) ? trim( stripslashes( $_POST[ 'contact-subject' ] ) ) : '';
				if ( empty( $subject ) ) {
					$subject = $settings[ 'default_subject' ];
				}
				$message = trim( stripslashes( isset($_POST[ 'contact-message' ]) ? $_POST[ 'contact-message' ] : '' ) );
				$successMessage = $settings[ 'success_message_text' ];

				$fliped_array = array_flip( preg_grep( '/^(field_extra_name|contact-message)/', array_keys( $_POST ) ) );
				$extra_fields = array_intersect_key( $_POST, $fliped_array );
				$field = '';
                
				foreach ( $extra_fields as $key => $field_name ) {
					if ( 'contact-message' === $key ) {
						$field .= "<br/><br/>" . $message . "<br/><br/>" .
                                    '<table style="width: 100%;font: inherit;direction: ltr;text-align: left;">';
						continue;
					}
					$index = str_replace( '_name', '', $key );
					if(isset($_FILES[ $index ]) && 0 !== $_FILES[ $index ]['size'] ){ //+
                        $file_info = $_FILES[$index];
                        $upload_file = $this->upload_attachment( $file_info );
                        if( $upload_file && ! isset( $upload_file['themify_error'] ) ){
                            $uploaded_files_url[$index] = $upload_file['url'];
                            $uploaded_files_path[$index] = $upload_file['file'];
                            continue;
                        }else{
                            $result = $upload_file;
                        }                        
                    }
					if( ! isset( $_POST[ $index ] ) ){
                        continue;
                    }
					$value = $_POST[ $index ];

					if ( is_array( $value ) ) {
						$final_value = '';
						foreach ( $value as $val ) {
							$final_value .= $val . ', ';
						}
						$value = trim( stripslashes( substr( $final_value, 0, -2 ) ) );
					} else {
						$value = trim( stripslashes( $value ) );
					}
					$field_name = trim( stripslashes( $field_name ) );
					$field .= '<tr><td style="width: 50%;font-weight: bold;padding: 5px;vertical-align: top;">' . $field_name . " :</td><td style='width: 50%;padding: 5px;vertical-align: top;'>" . $value . "</td></tr>";
				}
				$message = $field . '</table> ';
				$subject = apply_filters( 'builder_contact_subject', $subject );
				if ( '' == $name || '' == $email  ) {
					$result[ 'themify_message' ] = '<p class="ui red contact-error">' . __( 'Please fill in the required data.', 'builder-contact' ) . '</p>';
					$result[ 'themify_error' ] = 1;
				} elseif( empty( $result ) ) {
					if ( !is_email( $email ) ) {
						$result[ 'themify_message' ] = '<p class="ui red contact-error">' . __( 'Invalid Email address!', 'builder-contact' ) . '</p>';
						$result[ 'themify_error' ] = 1;
					} else {
						$this->from_name = $name;
						$headers = 'Reply-To: ' . $name . ' <' . $email . '>' . "\r\n";
						add_filter( 'wp_mail_from_name', array( $this, 'set_from_name' ) );
						// add the email address to message body
						$message = __( 'From:', 'builder-contact' ) . ' ' . $name . ' &lt;' . $email . '&gt;' . "\n\n" . $message;
						if( 'enable' == $settings [ 'contact_sent_from' ] ) {
							$message .= "\n\n" . __( 'Sent from:', 'builder-contact' ) . ' ' . get_site_url();
						}
						add_filter( 'wp_mail_content_type', array( $this, 'set_content_type' ), 100, 1 );
						
						if ( isset( $_POST[ 'contact-sendcopy' ] ) && $_POST[ 'contact-sendcopy' ] == '1' ) {
							wp_mail( $email, $subject, $message, $headers, $uploaded_files_path );
						}
						if ( $settings[ 'post_type' ] == 'enable' ) {
						    $files_links = '';// for add file link to the post
                            if( $uploaded_files_url && ! empty( $uploaded_files_url ) ){
                                foreach ( $uploaded_files_url as $link ) {
                                    $files_links .= "<br><a href='". $link ."'>".$link."</a><br>";
                                }
                            }
							if ( $settings[ 'post_author' ] == 'add' ) {
								$post_author_email = $recipients[ 0 ];
								$post_author_id = $this->create_new_author( $post_author_email );
							}
							$this->send_via_post_type( $subject, $message . '<br>Attachments : ' . $files_links, $post_author_id );
						}
						$auto_respond_sent = false;
							
						$headerStr = $headers;
						$recipientsArr = $recipients;
						unset( $recipientsArr[ 0 ] );
						$recipientString = implode( ",", $recipientsArr );
						if($recipientString) { $headerStr .= 'Cc: ' . $recipientString . "\r\n"; }

						if ( wp_mail( $recipients[ 0 ], $subject, $message, $headerStr, $uploaded_files_path ) ) {
							$sent = true;

							if( ! $auto_respond_sent && ! empty( $settings['auto_respond'] ) && ! empty( $settings['auto_respond_message'] ) ) {
								$auto_respond_sent = true;
								$ar_subject = trim( stripslashes( $settings['auto_respond_subject'] ) );
								$ar_message = trim( stripslashes( $settings['auto_respond_message'] ) );
								$ar_headers = '';
								wp_mail( $email, $ar_subject, $ar_message, $ar_headers, $uploaded_files_path );
							}
						} else {
							global $ts_mail_errors, $phpmailer;
							if ( !isset( $ts_mail_errors ) )
								$ts_mail_errors = array();
							if ( isset( $phpmailer ) ) {
								$ts_mail_errors[] = $phpmailer->ErrorInfo;
							}
							$sent = false;
						}

						if ( $sent ) {
							$result[ 'themify_message' ] = '<p class="ui light-green contact-success">' . $successMessage . '</p>';
							$result[ 'themify_success' ] = 1;
							if ( !empty( $settings[ 'success_url' ] ) ) {
								$result[ 'themify_message' ] .= '<script>window.location = "' . esc_attr( $settings[ 'success_url' ] ) . '"</script>';
							}
						} else {
							ob_start();
							print_r( $ts_mail_errors );
							$mail_error = ob_get_clean();
							$result[ 'themify_message' ] = '<p class="ui red contact-error">' . __( 'There was an error. Please try again.', 'builder-contact' ) . '<!-- ' . $mail_error . ' -->' . '</p>';
							$result[ 'themify_error' ] = 1;
						}
						remove_filter( 'wp_mail_content_type', array( $this, 'set_content_type' ), 100, 1 );
						do_action( 'builder_contact_mail_sent' );

						if( $uploaded_files_url ){ // delete saved file , if no save in media library
                            if ( $settings[ 'post_type' ] != 'enable' ) {
                                foreach ($uploaded_files_url as $attachment){
                                    unlink($attachment);
                                }
                            }
                        }
					}
				}
			}
			echo wp_json_encode( $result );
		}
		wp_die();
		
	}

    public function upload_attachment( $file_info ){
        
        $upload_overrides = array( 'test_form' => false );
        if( !empty( $file_info ) ){
            if( !$file_info['error'] ){
                if( $file_info['size'] <= wp_max_upload_size() ){
                    $movefile = wp_handle_upload( $file_info, $upload_overrides );
                    if ( $movefile  && ! isset( $movefile['error'] ) ) {
                        $result = $movefile;
                    }else{
                        $result[ 'themify_message' ] = '<p class="ui red contact-error">' . __( 'WordPress doesn\'t allow this type of uploads.', 'builder-contact' ) . '</p>';
                        $result[ 'themify_error' ] = 1;
                    }
                }else{
                    $result[ 'themify_message' ] = '<p class="ui red contact-error">' . __( 'The selected file size is larger than the limit.', 'builder-contact' ) . '</p>';
                    $result[ 'themify_error' ] = 1;
                }
            }
            return $result;
        }
        return false;
    }
    
	public function set_from_name( $name ) {
		return $this->from_name;
	}

	protected function create_new_author( $email ) {

		$exists = email_exists( $email );
		if ( false !== $exists ) {
			return $exists;
		}

		$random_password = wp_generate_password( $length = 12, $include_standard_special_chars = false );
		$user_id = wp_create_user( $email, $random_password, $email );

		return $user_id;


	}

	public function send_via_post_type( $title, $message, $author = false ) {

		$post_info = array(
			'post_title' => $title,
			'post_type' => 'contact_messages',
			'post_content' => $message
		);

		if ( false !== $author ) {
			$post_info[ 'post_author' ] = $author;
		}
		remove_filter('content_save_pre',  'wp_filter_post_kses', 10);
		return wp_insert_post( $post_info );

	}

	public function create_post_type() {

		return register_post_type( 'contact_messages',
			array(
				'labels' => array(
					'name' => __( 'Builder Contact Submissions', 'builder-contact' ),
					'singular_name' => __( 'Builder Contact Submission', 'builder-contact' ),
					'all_items' => __( 'Contact Submissions', 'builder-contact' ),
					'menu_name' => __( 'Builder Contact', 'builder-contact' ),
				),
				'public' => false,
				'supports' => array( 'title', 'editor', 'author' ),
				'show_ui' => true
			)
		);

	}

	public function set_custom_columns( $columns ) {

		unset( $columns[ 'date' ] );
		unset( $columns[ 'author' ] );
		$columns[ 'sender' ] = __( 'Sender', 'builder-contact' );
		$columns[ 'subject' ] = __( 'Subject', 'builder-contact' );
		$columns[ 'date' ] = __( 'Date', 'builder-contact' );
		return $columns;

	}

	public function custom_contact_messages_columns( $column, $post_id ) {

		switch ( $column ) {

			case 'sender' :
				$content_post = get_post( $post_id );
				$content = $content_post->post_content;
				preg_match( '/<(.*?)>/', $content, $result );
				echo ( isset( $result[ 1 ] ) ) ? $result[ 1 ] : '';
				break;

			case 'subject' :
				echo get_the_title( $post_id );
				break;
		}

	}

	public function set_content_type( $content_type ) {
		return 'text/html';
	}

	public function admin() {
		require_once( $this->dir . 'includes/admin.php' );
		new Builder_Contact_Admin();
	}

	public function get_option( $name, $default = null ) {
		$options = get_option( 'builder_contact' );
		if ( isset( $options[ $name ] ) ) {
			return $options[ $name ];
		} else {
			return $default;
		}
	}

	public function assets( $assets ) {
		$assets[ 'builder_contact' ] = array(
			'js' => $this->url . 'assets/scripts.js',
			'css' => $this->url . 'assets/style.css',
			'ver' => $this->version,
			'selector' => '.module-contact',
			'external' => Themify_Builder_Model::localize_js( 'BuilderContact', array(
				'admin_url' => admin_url( 'admin-ajax.php' )
			) )
		);

		return $assets;
	}

	public function updater() {
		if ( class_exists( 'Themify_Builder_Updater' ) ) {
			if ( !function_exists( 'get_plugin_data' ) ) {
				include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			}
			$plugin_basename = plugin_basename( __FILE__ );
			$plugin_data = get_plugin_data( trailingslashit( plugin_dir_path( __FILE__ ) ) . basename( $plugin_basename ) );
			new Themify_Builder_Updater( array(
				'name' => trim( dirname( $plugin_basename ), '/' ),
				'nicename' => $plugin_data[ 'Name' ],
				'update_type' => 'addon',
			), $this->version, trim( $plugin_basename, '/' ) );
		}
	}

	public function new_field_template() { ?>
		<script type="text/html" id="tmpl-builder-contact-new-field">
			<#
			data.fieldTypes = {
				text: '<?php esc_html_e( 'Text', 'builder-contact' ); ?>',
				textarea: '<?php esc_html_e( 'Textarea', 'builder-contact' ); ?>',
                upload: '<?php esc_html_e( 'Upload File', 'builder-contact' ); ?>',
				radio: '<?php esc_html_e( 'Radio', 'builder-contact' ); ?>',
				select: '<?php esc_html_e( 'Select', 'builder-contact' ); ?>',
				checkbox: '<?php esc_html_e( 'Checkbox', 'builder-contact' ); ?>',
				static: '<?php esc_html_e( 'Static Text', 'builder-contact' ); ?>',
			};
			if ( ! data.field ) {
				data.field = {
					label: '<?php esc_html_e( 'Field Name', 'builder-contact' ); ?>',
					type: 'text',
					required: false
				}
			}
			#>
			<tr class="tb_contact_new_row">
				<td>
					<span class="ti-split-v"></span>
					<div><input type="text" class="tb_new_field_textbox" value="{{ data.field.label }}"></div>
				</td>
				<td colspan="2">
					<div class="tb_new_field_type">
						<ul>
							<# _.each( data.fieldTypes, function( type, index ) { #>
								<li>
									<label>
										<input type="radio" name="tb_new_field_type_{{ data.id }}" <# index === data.field.type && print( 'checked' ) #> value="{{ index }}">{{type }}
									</label>
								</li>
								<# }); #>
						</ul>
					</div>
					<div class="tb_new_field">
						<# if ( data.field ){
							field = data.field;
							#>
							<div class="builder-contact-field builder-contact-field-message">
								<div class="control-input">
									<# if( 'text' === field.type || 'textarea' === field.type ){ #>
										<input type="text" class="tb_new_field_value tb_field_type_text" placeholder="Placeholder" value="{{ field.value }}">
									<# } else if( 'upload' === field.type ){ #>
                                        <input type="file" class="tb_new_field_value tb_field_type_text" placeholder="Placeholder" value="{{ field.value }}">
                                    <# } else if( 'static' === field.type ){ #>
										<textarea class="tb_new_field_value tb_field_type_text" placeholder="Enter text or HTML here">{{{ field.value }}}</textarea>
									<# } else if( 'radio' === field.type || 'select' === field.type || 'checkbox' === field.type ){ #>
										<ul>
											<# _.each( field.value, function( value, index ){ #>
												<li>
													<input type="text" value="{{ value }}" class="tb_multi_option" data-control-binding="live" data-control-event="keyup" data-control-type="change">
													<a href="#" class="tb_contact_value_remove"><i class="ti ti-close"></i></a>
												</li>
											<# }) #>
										</ul>
										<a href="#" class="tb_add_field_option"><?php esc_html_e( 'Add Option', 'builder-contact' ); ?></a>
									<# } #>
								</div>
								<label <# if( 'static' === field.type ){ #> style="display:none" <# }#>><input type="checkbox" class="tb_new_field_required" <# true === data.field.required && print( 'checked' ) #> value="required">Required</label>
							</div>
						<# } #>
					</div>
					<a href="#" class="tb_contact_field_remove"><i class="ti ti-close"></i></a>
				</td>
				<td></td>
			</tr>
		</script>
		<script type="text/html" id="tmpl-builder-contact-new-field-options">
			<ul>
				<# _.each( data.fields, function( field, index ) { #>
					<li>
						<input type="text" value="{{ field }}" class="tb_multi_option" data-control-binding="live" data-control-event="keyup" data-control-type="change"><a href="#" class="tb_contact_value_remove"><i class="ti ti-close"></i></a>
					</li>
					<# } ); #>
			</ul>
			<a href="#" class="tb_add_field_option"><?php esc_html_e( 'Add Option', 'builder-contact' ); ?></a>
		</script>
	<?php }
}

Builder_Contact::get_instance();
