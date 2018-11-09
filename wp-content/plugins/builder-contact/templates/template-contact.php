<?php
if (!defined('ABSPATH'))
    exit; // Exit if accessed directly
/**
 * Template Contact
 *
 * Access original fields: $mod_settings
 */
if (TFCache::start_cache($mod_name, self::$post_id, array('ID' => $module_ID))):
    $fields_default = array(
        'mod_title_contact' => '',
        'layout_contact' => 'style1',
        'mail_contact' => get_option('admin_email'),
        'field_name_label' => empty($mod_settings['field_name_label']) && !empty($mod_settings['field_name_placeholder']) ? '' : __('Name', 'builder-contact'),
        'field_name_placeholder' => '',
        'field_email_label' => empty($mod_settings['field_email_label']) && !empty($mod_settings['field_email_placeholder']) ? '' : __('Email', 'builder-contact'),
        'field_email_placeholder' => '',
        'field_subject_label' => empty($mod_settings['field_subject_label']) && !empty($mod_settings['field_subject_placeholder']) ? '' : __('Subject', 'builder-contact'),
        'field_subject_placeholder' => '',
        'default_subject' => '',
        'success_url' => '',
        'field_captcha_label' => __('Captcha', 'builder-contact'),
        'field_extra' => '{ "fields": [] }',
        'field_order' => '{}',
        'field_message_label' => empty($mod_settings['field_message_label']) && !empty($mod_settings['field_message_placeholder']) ? '' : __('Message', 'builder-contact'),
        'field_message_placeholder' => '',
        'field_sendcopy_label' => __('Send Copy', 'builder-contact'),
        'field_send_label' => __('Send', 'builder-contact'),
        'field_send_align' => 'left',
        'animation_effect' => '',
        'css_class_contact' => ''
    );
    $field_subject_active = isset($mod_settings['field_subject_active']) && 'yes' === $mod_settings['field_subject_active'];
    $field_subject_require = isset($mod_settings['field_subject_require']) && 'yes' === $mod_settings['field_subject_require'];
    $field_sendcopy_active = isset($mod_settings['field_sendcopy_active']) && 'yes' === $mod_settings['field_sendcopy_active'];
    $field_captcha_active = isset($mod_settings['field_captcha_active']) && 'yes' === $mod_settings['field_captcha_active'];
    $fields_args = wp_parse_args($mod_settings, $fields_default);
    unset($mod_settings);
    $animation_effect = self::parse_animation_effect($fields_args['animation_effect'], $fields_args);
	$field_extra = json_decode( $fields_args['field_extra'], true );
	$field_order = json_decode( $fields_args['field_order'], true );

	$container_class = implode(' ', apply_filters('themify_builder_module_classes', array(
        'module', 'module-' . $mod_name, $module_ID, 'contact-' . $fields_args['layout_contact'], $animation_effect, $fields_args['css_class_contact']
                    ), $mod_name, $module_ID, $fields_args)
    );

// data that is passed from the form to server
    $form_settings = array(
        'sendto' => Builder_Contact::str_rot47($fields_args['mail_contact']),
        'default_subject' => $fields_args['default_subject'],
        'success_url' => $fields_args['success_url']
    );

    $container_props = apply_filters('themify_builder_module_container_props', array(
        'id' => $module_ID,
        'class' => $container_class
            ), $fields_args, $mod_name, $module_ID);
    ?>
    <!-- module contact -->
    <div <?php echo self::get_element_attributes($container_props); ?>>

        <?php if ($fields_args['mod_title_contact'] !== ''): ?>
            <?php echo $fields_args['before_title'] . apply_filters('themify_builder_module_title', $fields_args['mod_title_contact'], $fields_args) . $fields_args['after_title']; ?>
        <?php endif; ?>

        <?php do_action('themify_builder_before_template_content_render'); ?>

        <form action="<?php echo admin_url('admin-ajax.php'); ?>" class="builder-contact" id="<?php echo $module_ID; ?>-form" method="post">
            <div class="contact-message"></div>

            <div class="builder-contact-fields">
                <div class="builder-contact-field builder-contact-field-name builder-contact-text-field" data-order="<?php echo $field_order['field_name_label']?>">
                    <label class="control-label" for="<?php echo $module_ID; ?>-contact-name"><?php if ($fields_args['field_name_label'] !== ''): ?><?php echo $fields_args['field_name_label']; ?> <span class="required">*</span><?php endif; ?></label>
                    <div class="control-input">
                        <input type="text" name="contact-name" placeholder="<?php echo $fields_args['field_name_placeholder']; ?>" id="<?php echo $module_ID; ?>-contact-name" value="" class="form-control" required />
                    </div>
                </div>

                <div class="builder-contact-field builder-contact-field-email builder-contact-text-field" data-order="<?php echo $field_order['field_email_label']?>">
                    <label class="control-label" for="<?php echo $module_ID; ?>-contact-email"><?php if ($fields_args['field_email_label'] !== ''): ?><?php echo $fields_args['field_email_label']; ?> <span class="required">*</span><?php endif; ?></label>
                    <div class="control-input">
                        <input type="text" name="contact-email" placeholder="<?php echo $fields_args['field_email_placeholder']; ?>" id="<?php echo $module_ID; ?>-contact-email" value="" class="form-control" required />
                    </div>
                </div>

                <?php if ($field_subject_active) : ?>
                    <div class="builder-contact-field builder-contact-field-subject builder-contact-text-field" data-order="<?php echo $field_order['field_subject_label']?>">
                        <label class="control-label" for="<?php echo $module_ID; ?>-contact-subject"><?php echo $fields_args['field_subject_label']; ?> <?php if( $field_subject_require ){ ?><span class="required">*</span><?php } ?></label>
                        <div class="control-input">
                            <input type="text" name="contact-subject" placeholder="<?php echo $fields_args['field_subject_placeholder']; ?>" id="<?php echo $module_ID; ?>-contact-subject" value="" class="form-control" <?php echo $field_subject_require ?  'required' : '' ?> />
                        </div>
                    </div>
                <?php endif; ?>

                <div class="builder-contact-field builder-contact-field-message builder-contact-textarea-field" data-order="<?php echo $field_order['field_message_label']?>">
                    <label class="control-label" for="<?php echo $module_ID; ?>-contact-message"><?php if ($fields_args['field_message_label'] !== ''): ?><?php echo $fields_args['field_message_label']; ?> <span class="required">*</span><?php endif; ?></label>
                    <div class="control-input">
                        <textarea name="contact-message" placeholder="<?php echo $fields_args['field_message_placeholder']; ?>" id="<?php echo $module_ID; ?>-contact-message" rows="8" cols="45" class="form-control" required></textarea>
                    </div>
                </div>

				<?php foreach( $field_extra['fields'] as $field_index =>  $field ): ?>
					<?php $field['value'] = isset( $field['value'] ) ? $field['value'] : ''; ?>
					<div class="builder-contact-field builder-contact-field-extra builder-contact-<?php echo $field['type']; ?>-field" data-order="<?php echo $field_order[$field['label']]?>">
						<label class="control-label"><?php echo $field['label']; ?> <input type="hidden" name="field_extra_name_<?php echo $field_index; ?>" value="<?php echo $field['label']; ?>"/><?php if( isset( $field['required'] ) && true === $field['required'] ){ ?><span class="required">*</span><?php } ?></label>
						<div class="control-input">
							<?php if( 'textarea' === $field['type'] ): ?>
								<textarea name="field_extra_<?php echo $field_index; ?>" placeholder="<?php echo $field['value']; ?>" rows="8" cols="45" class="form-control" <?php echo isset( $field['required'] ) && true === $field['required']?  'required' : '' ?> ></textarea>
							<?php elseif( 'text' === $field['type'] ): ?>
								<input type="text" name="field_extra_<?php echo $field_index; ?>" placeholder="<?php echo $field['value']; ?>" class="form-control" <?php echo isset( $field['required'] ) && true === $field['required']?  'required' : '' ?> />
							<?php elseif( 'radio' === $field['type'] ): ?>
								<?php foreach( $field['value'] as $index => $value ): ?>
									<label>
										<input type="radio" name="field_extra_<?php echo $field_index; ?>" value="<?php echo $value; ?>" class="form-control" <?php echo isset( $field['required'] ) && true === $field['required']?  'required' : '' ?> /> <?php echo $value; ?>
									</label>
								<?php endforeach; ?>
							<?php elseif( 'select' === $field['type'] ): ?>
								<select name="field_extra_<?php echo $field_index; ?>" class="form-control" <?php echo isset( $field['required'] ) && true === $field['required']?  'required' : '' ?>>
									<?php foreach( $field['value'] as $index => $value ): ?>
										<option value="<?php echo $value; ?>"> <?php echo $value; ?> </option>
									<?php endforeach; ?>
								</select>
							<?php elseif( 'checkbox' === $field['type'] ): ?>
								<?php foreach( $field['value'] as $index => $value ): ?>
									<label>
										<input type="checkbox" name="field_extra_<?php echo $field_index; ?>[]" value="<?php echo $value; ?>" class="form-control" <?php echo isset( $field['required'] ) && true === $field['required']?  'required' : '' ?> /> <?php echo $value; ?>
									</label>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>

                <?php if ($field_sendcopy_active) : ?>
                    <div class="builder-contact-field builder-contact-field-sendcopy">
                        <div class="control-label">
                            <div class="control-input checkbox">
                                <label class="send-copy">
                                    <input type="checkbox" name="contact-sendcopy" id="<?php echo $module_ID; ?>-send-copy" value="1" /> <?php echo $fields_args['field_sendcopy_label']; ?>
                                </label>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($field_captcha_active && Builder_Contact::get_instance()->get_option('recapthca_public_key') != '' && Builder_Contact::get_instance()->get_option('recapthca_private_key') != '') : ?>
                    <div class="builder-contact-field builder-contact-field-captcha">
                        <label class="control-label" for="<?php echo $module_ID; ?>-contact-captcha"><?php echo $fields_args['field_captcha_label']; ?> <span class="required">*</span></label>
                        <div class="control-input">
                            <div class="g-recaptcha" data-sitekey="<?php echo esc_attr(Builder_Contact::get_instance()->get_option('recapthca_public_key')); ?>"></div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="builder-contact-field builder-contact-field-send">
                    <div class="control-input builder-contact-field-send-<?php echo $fields_args['field_send_align']; ?>">
                        <button type="submit" class="btn btn-primary"> <i class="fa fa-cog fa-spin"></i> <?php echo $fields_args['field_send_label']; ?> </button>
                    </div>
                </div>
            </div>
            <script type="text/html" class="builder-contact-form-data"><?php echo serialize($form_settings); ?></script>

        </form>

        <?php do_action('themify_builder_after_template_content_render'); ?>
    </div>
    <!-- /module contact -->
	
<?php endif; ?>
<?php TFCache::end_cache(); ?>
