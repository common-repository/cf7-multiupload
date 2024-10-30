<?php
/*
Plugin Name: CF7-MultiUpload
Plugin URI: 
Description: Add Multiple Upload field type to the popular Contact Form 7 plugin.
Author: Webgensis
Author URI: http://webgensis.com
Version: 1.1.0
*/

define('WPCF7_MULT_UPLOAD_VERSION',"1.0.0");

// this plugin needs to be initialized AFTER the Contact Form 7 plugin.
add_action('plugins_loaded', 'contact_form_7_multiupload_fields', 10);
 
function contact_form_7_multiupload_fields() {
	global $pagenow;
	if(!function_exists('wpcf7_add_shortcode')) {
		if($pagenow != 'plugins.php') { return; }

		add_action('admin_notices', 'cfmultuploadfieldserror');

		function cfmultuploadfieldserror() {
			$out = '<div class="error" id="messages"><p>';
			if(file_exists(WP_PLUGIN_DIR.'/contact-form-7/wp-contact-form-7.php')) {
				$out .= 'The Contact Form 7 plugin is installed, but <strong>you must activate Contact Form 7</strong> below for the Multiple Upload Field plugin to work.';
			} else {
				$out .= 'The Contact Form 7 plugin must be installed for the Tag-it Field plugin to work. <a href="'.admin_url('plugin-install.php?tab=plugin-information&plugin=contact-form-7&from=plugins&TB_iframe=true&width=600&height=550').'" class="thickbox" title="Contact Form 7">Install Now.</a>';
			}
			$out .= '</p></div>';
			echo $out;
		}
	}
}

load_plugin_textdomain('wpcf7-multiupload', false, basename( dirname( __FILE__ ) ) . '/languages' );

add_action( 'wpcf7_init', 'wpcf7_add_shortcode_multiupload' );
function wpcf7_add_shortcode_multiupload() {
	wpcf7_add_shortcode(
		array( 'multiupload', 'multiupload*' ),
		'wpcf7_multiupload_shortcode_handler', true );
}

function wpcf7_multiupload_shortcode_handler( $tag ) {

	$tag = new WPCF7_Shortcode( $tag );

	if ( empty( $tag->name ) )
		return '';

	$validation_error = wpcf7_get_validation_error( $tag->name );
	$class = wpcf7_form_controls_class( $tag->type, 'wpcf7-multiupload' );

	if ( $validation_error )
		$class .= ' wpcf7-not-valid';

	$atts = array();
	$atts['size'] = $tag->get_size_option( '40' );
	$atts['class'] = $tag->get_class_option( $class );
	$atts['id'] = $tag->get_id_option();
	$atts['tabindex'] = $tag->get_option( 'tabindex', 'int', true );

	if ( $tag->is_required() ) {
		$atts['aria-required'] = 'true';
	}

	$atts['aria-invalid'] = $validation_error ? 'true' : 'false';
	$atts['type'] = 'file';
	$atts['multiple'] = 'multiple';
	$multiple = true;
	$atts['name'] = $tag->name. ( $multiple ? '[]' : '' );
	$atts = wpcf7_format_atts( $atts );

	$html = sprintf(
		'<span class="wpcf7-form-control-wrap %1$s"><input %2$s />%3$s</span>',
		sanitize_html_class( $tag->name ), $atts, $validation_error );

	return $html;
}


/* Encode type filter */
add_filter( 'wpcf7_form_enctype', 'wpcf7_files_form_enctype_filter' );

function wpcf7_files_form_enctype_filter( $enctype ) {
	$multipart = (bool) wpcf7_scan_shortcode( array( 'type' => array( 'multiupload', 'multiupload*' ) ) );

	if ( $multipart ) {
		$enctype = 'multipart/form-data';
	}
	return $enctype;
}


add_filter( 'wpcf7_messages', 'wpcf7_multiupload_messages' );

function wpcf7_multiupload_messages( $messages ) {   
	return array_merge( $messages, array(
		'upload_failed' => array(
			'description' => __( "Uploading a file fails for any reason", 'contact-form-7' ),
			'default' => __( 'Failed to upload file.', 'contact-form-7' )
		),

		'upload_file_type_invalid' => array(
			'description' => __( "Uploaded file is not allowed file type", 'contact-form-7' ),
			'default' => __( 'This file type is not allowed.', 'contact-form-7' )
		),

		'upload_file_too_large' => array(
			'description' => __( "Uploaded file is too large", 'contact-form-7' ),
			'default' => __( 'This file is too large.', 'contact-form-7' )
		),

		'upload_failed_php_error' => array(
			'description' => __( "Uploading a file fails for PHP error", 'contact-form-7' ),
			'default' => __( 'Failed to upload file. Error occurred.', 'contact-form-7' )
		)
	) );
}

/* Validation filter */
add_filter( 'wpcf7_validate_multiupload', 'wpcf7_multiupload_validation_filter', 10, 2 );
add_filter( 'wpcf7_validate_multiupload*', 'wpcf7_multiupload_validation_filter', 10, 2 );

function wpcf7_multiupload_validation_filter( $result, $tag ) {
	
	$tag = new WPCF7_Shortcode( $tag );
	$name = $tag->name;
	$id = $tag->get_id_option();
	$file = isset( $_FILES[$name] ) ? $_FILES[$name] : null;

	if ( empty( $file ) && !$tag->is_required() ) {
		return $result;
	}
			  
	if ( empty( $file ) && $tag->is_required() ) {
		$result->invalidate( $tag, wpcf7_get_message( 'invalid_required' ) );
		return $result;
	}
	
	$allowed_file_types = array();
	if ( $file_types_a = $tag->get_option( 'filetypes' ) ) {
		foreach ( $file_types_a as $file_types ) {
			$file_types = explode( '|', $file_types );

			foreach ( $file_types as $file_type ) {
				$file_type = trim( $file_type, '.' );
				$file_type = str_replace( array( '.', '+', '*', '?' ),
					array( '\.', '\+', '\*', '\?' ), $file_type );
				$allowed_file_types[] = $file_type;
			}
		}
	}	

	$allowed_file_types = array_unique( $allowed_file_types );
	$file_type_pattern = implode( '|', $allowed_file_types );
	$allowed_size = 1048576*10; // default upload size 10 MB

	// Default file-type restriction
	if ( '' == $file_type_pattern )
	$file_type_pattern = 'jpg|jpeg|png|gif|pdf|doc|docx|ppt|pptx|odt|avi|ogg|m4a|mov|mp3|mp4|mpg|wav|wmv';

	$file_type_pattern = trim( $file_type_pattern, '|' );
	$file_type_pattern = '(' . $file_type_pattern . ')';
	$file_type_pattern = '/\.' . $file_type_pattern . '$/i';
	
	foreach($file as $key => $ff){				
		if($key == 'name'){
			foreach($ff as $fname){
				if ( ! preg_match( $file_type_pattern, $fname ) ) {
					$result->invalidate( $tag, wpcf7_get_message( 'upload_file_type_invalid' ) );
					return $result;
				}			
			}
		}		
	}
	
	foreach($file as $key => $ff){		
		if($key == 'size'){
			foreach($ff as $fsize){				
				if ( $fsize > $allowed_size ) {
					$result->invalidate( $tag, wpcf7_get_message( 'upload_file_too_large' ) );
					return $result;
				}			
			}
		}		
	}
	
	 $upload_d = wp_upload_dir(); 
	 $directory_path = $upload_d['basedir'].'/multiupload/';
	
	 if (!file_exists($directory_path)) {
		wp_mkdir_p($directory_path);
	 }
	 $uploads_dir = $directory_path;
		
	 foreach( $file as $key => $all ){
        foreach( $all as $i => $val ){
            $rearranged_array[$i][$key] = $val;    
        }    
     }
  
	foreach($rearranged_array as $single_file){
		foreach($single_file as $file_key => $file_val){		
			if($file_key == 'name'){
				$filename = $file_val;
				$filename = wpcf7_canonicalize( $filename );
				$filename = sanitize_file_name( $filename );
				$filename = wpcf7_antiscript_file_name( $filename );
				$filename = wp_unique_filename( $uploads_dir, $filename );
				$new_file = trailingslashit( $uploads_dir ) . $filename;			
			}
			
			if($file_key == 'tmp_name'){
				if ( false === @move_uploaded_file( $file_val, $new_file ) ) {
					$result->invalidate( $tag, wpcf7_get_message( 'upload_failed' ) );
					return $result;
				}
				$uploaded_files[] = $new_file;					
			}				
		}
	}
		
	if($uploaded_files){
		$addtional_msg = implode(',,',$uploaded_files);
		update_option('uplodedfiles',$addtional_msg);	
	}
	else update_option('uplodedfiles','');
	
	return $result;
}


add_action('wpcf7_before_send_mail', 'wpcf7_update_email_body');

function wpcf7_update_email_body($contact_form) {

  $uplodedfiles = get_option('uplodedfiles');	
  
  if($uplodedfiles != ''){
	$submission = WPCF7_Submission::get_instance();
	
    if ( $submission ) {  
		$mail = $contact_form->prop('mail');
		$str = '';
		$uploaded = explode(',,',$uplodedfiles);
		$counter = 1;
		foreach ($uploaded as $ff){
			if($counter > 1) $con = ',  '; else $con = '';
			$str .= $con.basename ($ff);
			$counter ++;
		}
						
		$slice1 = explode('[multiupload-',$mail['body']);
		$slice2 = explode(']',$slice1[1]);
		$shortcode = '[multiupload-'.$slice2[0].']';
		$str = str_replace($shortcode,$str,$mail['body']);
		$mail['body'] = $str;
		$contact_form->set_properties(array('mail' => $mail));
	}    
  }
}


/* Tag generator */

add_action( 'admin_init', 'wpcf7_add_tag_generator_multiupload', 60 );

function wpcf7_add_tag_generator_multiupload() {

	if (class_exists('WPCF7_TagGenerator')) {
		$tag_generator = WPCF7_TagGenerator::get_instance();
		$tag_generator->add( 'multiupload', __( 'multiupload', 'contact-form-7' ),'wpcf7_tag_generator_multiupload' );
	} else if (function_exists('wpcf7_add_tag_generator')) {
		wpcf7_add_tag_generator( 'multiupload', __( 'multiupload', 'wpcf7' ), 'wpcf7-tg-pane-multiupload', 'wpcf7_tag_generator_multiupload' );
	}
	
}

function wpcf7_tag_generator_multiupload( $contact_form, $args = '' ) {

	if (class_exists('WPCF7_TagGenerator')) {
		$args = wp_parse_args( $args, array() );
		$type = 'multiupload';

		$description = __( "Generate a form-tag for a multiple upload field.", 'contact-form-7' );
		?>
		<div class="control-box">
		<fieldset>
		<legend><?php echo sprintf( esc_html( $description ) ); ?></legend>
		<table class="form-table">
		<tbody>
			<tr>
			<th scope="row"><?php echo esc_html( __( 'Field type', 'contact-form-7' ) ); ?></th>
			<td>
				<fieldset>
				<legend class="screen-reader-text"><?php echo esc_html( __( 'Field type', 'contact-form-7' ) ); ?></legend>
				<label><input type="checkbox" name="required" /> <?php echo esc_html( __( 'Required field', 'contact-form-7' ) ); ?></label>
				</fieldset>
			</td>
			</tr>

			<tr>
			<th scope="row"><label for="<?php echo esc_attr( $args['content'] . '-name' ); ?>"><?php echo esc_html( __( 'Name', 'contact-form-7' ) ); ?></label></th>
			<td><input type="text" name="name" class="tg-name oneline" id="<?php echo esc_attr( $args['content'] . '-name' ); ?>" /></td>
			</tr>

			<tr>
			<th scope="row"><label for="<?php echo esc_attr( $args['content'] . '-id' ); ?>"><?php echo esc_html( __( 'Id attribute', 'contact-form-7' ) ); ?></label></th>
			<td><input type="text" name="id" class="idvalue oneline option" id="<?php echo esc_attr( $args['content'] . '-id' ); ?>" /></td>
			</tr>

			<tr>
			<th scope="row"><label for="<?php echo esc_attr( $args['content'] . '-class' ); ?>"><?php echo esc_html( __( 'Class attribute', 'contact-form-7' ) ); ?></label></th>
			<td><input type="text" name="class" class="classvalue oneline option" id="<?php echo esc_attr( $args['content'] . '-class' ); ?>" /></td>
			</tr>

			<tr>
            <th scope="row"><label for="<?php echo esc_attr( $args['content'] . '-filetypes' ); ?>"><?php echo esc_html( __( 'Acceptable file types', 'contact-form-7' ) ); ?></label></th>
            <td><input type="text" name="filetypes" class="filetype oneline option" id="<?php echo esc_attr( $args['content'] . '-filetypes' ); ?>" placeholder="jpg|png|gif" /></td>
            </tr>
			

		</tbody>
		</table>
		</fieldset>
		</div>

		<div class="insert-box">
			<input type="text" name="<?php echo $type; ?>" class="tag code" readonly="readonly" onfocus="this.select()" />

			<div class="submitbox">
			<input type="button" class="button button-primary insert-tag" value="<?php echo esc_attr( __( 'Insert Tag', 'contact-form-7' ) ); ?>" />
			</div>

			<br class="clear" />

			<p class="description mail-tag"><label for="<?php echo esc_attr( $args['content'] . '-mailtag' ); ?>"><?php echo sprintf( esc_html( __( "To use the value input through this field in a mail field, you need to insert the corresponding mail-tag (%s) into the field on the Mail tab.", 'contact-form-7' ) ), '<strong><span class="mail-tag"></span></strong>' ); ?><input type="text" class="mail-tag code hidden" readonly="readonly" id="<?php echo esc_attr( $args['content'] . '-mailtag' ); ?>" /></label></p>
		</div>
		<?php
	}else{

		// For older CF7 versions
		?>

		<div id="wpcf7-tg-pane-multiupload" class="hidden">
			<form action="">
			<table>
				<tr><td><input type="checkbox" name="required" />&nbsp;<?php echo esc_html( __( 'Required field?', 'contact-form-7' ) ); ?></td></tr>
				<tr><td><?php echo esc_html( __( 'Name', 'contact-form-7' ) ); ?><br /><input type="text" name="name" class="tg-name oneline" /></td><td></td></tr>
			</table>

			<table>
				<tr>
					<td><code>id</code> (<?php echo esc_html( __( 'optional', 'contact-form-7' ) ); ?>)<br />
					<input type="text" name="id" class="idvalue oneline option" /></td>

					<td><code>class</code> (<?php echo esc_html( __( 'optional', 'contact-form-7' ) ); ?>)<br />
					<input type="text" name="class" class="classvalue oneline option" /></td>
				</tr>

				<tr>
					<td><code>width</code> (<?php echo esc_html( __( 'optional', 'contact-form-7' ) ); ?>)<br />
					<input type="number" name="cols" class="numeric oneline option" min="1" /></td>

					<td><code>height</code> (<?php echo esc_html( __( 'optional', 'contact-form-7' ) ); ?>)<br />
					<input type="number" name="rows" class="numeric oneline option" min="1" /></td>
				</tr>

			</table>

			<div class="tg-tag"><?php echo esc_html( __( "Copy this code and paste it into the form left.", 'contact-form-7' ) ); ?><br /><input type="text" name="multiupload" class="tag wp-ui-text-highlight code" readonly="readonly" onfocus="this.select()" /></div>

			<div class="tg-mail-tag"><?php echo esc_html( __( "And, put this code into the Mail fields below.", 'contact-form-7' ) ); ?><br /><input type="text" class="mail-tag wp-ui-text-highlight code" readonly="readonly" onfocus="this.select()" /></div>
			</form>
		</div>

		<?php
	}
}
?>