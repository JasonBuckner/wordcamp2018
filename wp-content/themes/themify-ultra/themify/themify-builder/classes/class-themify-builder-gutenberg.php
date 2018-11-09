<?php
class Themify_Builder_Gutenberg {
	private $builder;

	public $block_patterns = '<!-- wp:themify-builder/canvas /-->';

	public $post_types;

	public function __construct( $builder ) {
		$this->builder = $builder;
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_scripts') );
		add_action( 'init', array( $this, 'init') );
		add_filter( 'themify_defer_js_exclude', array( $this, 'exclude_defer_script' ) );

		$this->post_types = $this->builder->builder_post_types_support();
		foreach( $this->post_types as $type ) {
			add_filter( 'rest_prepare_' . $type, array( $this, 'enable_block_existing_content'), 10, 3 );
		}
		add_filter( 'admin_body_class', array( $this, 'admin_body_class') );

		add_action( 'rest_api_init', array( $this, 'register_builder_content_field' ) );
	}

	public function init() {
		if ( function_exists( 'register_block_type' ) ) {
			wp_register_style(
				'themify-builder-style',
				themify_enque(THEMIFY_BUILDER_URI . '/css/themify-builder-style.css'),
				array( 'wp-blocks' )
			);

			register_block_type( 'themify-builder/canvas', array(
				'render_callback' => array( $this, 'render_builder_block'),
				'style' => 'themify-builder-style'
			) );
		}

		$post_type_object = get_post_type_object( 'page' );
		$post_type_object->template = array(
			array( 'themify-builder/canvas' )
		);

		$post_type_post = get_post_type_object( 'post' );
		$post_type_post->template = array(
			array( 'themify-builder/canvas' )
		);
	}

	public function enqueue_editor_scripts() {
		wp_enqueue_script( 
			'themify-builder-gutenberg-block', 
			themify_enque(THEMIFY_BUILDER_URI . '/js/themify-builder-gutenberg.js'), 
			array( 'wp-blocks', 'wp-i18n', 'wp-element', 'backbone' )
		);
	}

	public function render_builder_block( $attributes ) {

		if ( Themify_Builder_Model::is_front_builder_activate() ) {
			return '<!--themify-builder:block-->';
		} else {
			remove_filter('the_content', array($this->builder, 'builder_show_on_front'), 11);
		}

		$post_id = get_the_ID();
		$builder_data = $this->builder->get_builder_data($post_id);

		do_action('themify_builder_before_template_content_render');

		if ( !is_array($builder_data) ) {
			$builder_data = array();
		}
		Themify_Builder_Component_Base::$post_id = $post_id;
		$template = $this->builder->in_the_loop ? 'builder-output-in-the-loop.php' : 'builder-output.php';
		return Themify_Builder_Component_Base::retrieve_template($template, array('builder_output' => $builder_data, 'builder_id' => $post_id), '', '', false);
	}

	public function exclude_defer_script( $handles ) {
		return array_merge( $handles, array( 'themify-builder-gutenberg-block' ) );
	}

	/**
	 * Enable builder block on existing content data.
	 * 
	 * @param object $data 
	 * @param object $post 
	 * @param array $context 
	 * @return object
	 */
	public function enable_block_existing_content( $data, $post, $context ) {
		global $ThemifyBuilder_Data_Manager;
                if('edit' === $context['context']){
                    if ( ! empty( $data->data['content']['raw'] ) &&  ! preg_match( '/<!-- wp:themify-builder\/canvas/s', $data->data['content']['raw'] ) ) {
                            $data->data['content']['raw'] = $data->data['content']['raw'] . ' ' . $this->block_patterns;
                    }
                    // Remove static content
                    if ( ! empty( $data->data['content']['raw'] )) {
                        $data->data['content']['builder_static_content'] = $ThemifyBuilder_Data_Manager->get_static_content( $data->data['content']['raw'] );
                        $data->data['content']['raw'] = $ThemifyBuilder_Data_Manager->update_static_content_string( '', $data->data['content']['raw'] );
                            //$data->data['content']['raw'] = str_replace('<p></p>', '', $data->data['content']['raw'] );
                    }
                }
		return $data;
	}

	/**
	 * Added body class
	 * @param string $classes 
	 * @return string
	 */
	public function admin_body_class( $classes ) {
		$classes .= 'themify-gutenberg-editor';
		return $classes;
	}

	/**
	 * Register builder content meta
	 * 
	 * @access public
	 * @return type
	 */
	public function register_builder_content_field() {
		foreach( $this->post_types as $type ) {
			register_rest_field( $type, 'builder_content', array(
					'get_callback'    => array( $this, 'get_post_meta_builder'),
					'schema'          => null,
				)
			);
		}
	}
	
	/**
	 * Get builder content value.
	 * 
	 * @access public
	 * @param type $object 
	 * @return type
	 */
	public function get_post_meta_builder( $object ) {
		global $ThemifyBuilder_Data_Manager;
		$post_id = $object['id'];

		return $ThemifyBuilder_Data_Manager->_get_all_builder_text_content( $ThemifyBuilder_Data_Manager->get_data( $post_id ) );
	}
}
new Themify_Builder_Gutenberg( $this );