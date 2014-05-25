<?php
/*
Plugin Name: Justclick Reg
Plugin URI: http://poltavcev.name/go/justclickreg/
Description: Enable automatic subsription to <a href="http://justclick.ru">Justclick.ru</a> mailing group for new registered user, also automatic remove subsription when user deleted. Translated to Russian, Ukrainian.
Version: 1.0
Author: Serhiy Derepa
*/

if(!class_exists('Justclick_Reg'))
{
	class Justclick_Reg
	{
        private $settings, $justclick_response;

		/* Construct the plugin object */
		public function __construct()
		{
			// register actions
			add_action('admin_menu', array($this, 'add_menu'));
			add_action('admin_init', array($this, 'admin_init'));
			add_action('init', array($this, 'load_plugin_textdomain'));
			add_action('user_register', array($this, 'Justclick_AddLeadToGroup'));
			add_action('delete_user', array($this, 'Justclick_DeleteSubscribe'));
	        add_filter('plugin_action_links_justclick-reg/justclick-reg.php', array($this, 'plugin_settings_link'));
 		}

		/* add a menu */
		public function add_menu()
		{
			add_options_page( __('Justclick Reg Settings', 'justclick-reg'), __('Justclick Reg', 'justclick-reg'), 'manage_options', 'justclick-reg', array( $this, 'justclick_reg_settings_page' ) );
		}

		/* Menu Callback */
		public function justclick_reg_settings_page()
		{
			if(!current_user_can('manage_options'))
			{
				wp_die( __( 'You do not have sufficient permissions to access this page.' ), 'justclick-reg' );
			}
        ?>
        <div class="wrap justclick-reg">
            <?php screen_icon(); ?>
            <h2><?php _e('Justclick Reg Settings','justclick-reg') ?></h2>
            <form action="options.php" method="POST">
                <?php settings_fields( 'justclick_reg_options_group' ); ?>
                <?php do_settings_sections( 'justclick-reg' ); ?>
                <?php submit_button(__('Save settings', 'justclick-reg')); ?>
            </form>
        </div>
        <?php
        }

    	/* hook into WP's admin_init action hook */
		public function admin_init()
		{
			// Set up the settings for this plugin
			$this->init_settings();

			// Additional admin_init tasks
			// Register and enqueue style sheet.
			wp_register_style( 'justclick-reg', plugins_url( 'justclick-reg/css/justclick-reg.css' ) );
			wp_enqueue_style( 'justclick-reg' );
		}

		/* Initialize plugin settings */
		public function init_settings()
		{
            $this->settings = get_option( 'justclick_reg_options' );

			// register the settings for this plugin
			register_setting( 'justclick_reg_options_group', 'justclick_reg_options', array( $this, 'justclick_reg_options_sanitize' ) );
		    add_settings_section( 'justclick_reg_options_section', '', array( $this, 'justclick_reg_options_section_callback' ), 'justclick-reg' );

            add_settings_field( 'justclick_reg_login', __('Justclick user login', 'justclick-reg'), array( $this, 'justclick_text_render' ), 'justclick-reg', 'justclick_reg_options_section', array(
              'id' => 'justclick_reg_login',
              'name' => 'justclick_reg_options[justclick_reg_login]',
              'value' => $this->settings['justclick_reg_login']
            ) );
            add_settings_field( 'justclick_reg_secret', __('Justclick secret key for signature', 'justclick-reg'), array( $this, 'justclick_text_render' ), 'justclick-reg', 'justclick_reg_options_section', array(
              'id' => 'justclick_reg_secret',
              'name' => 'justclick_reg_options[justclick_reg_secret]',
              'value' => $this->settings['justclick_reg_secret']
            ) );
            add_settings_field( 'justclick_reg_group', __('Justclick mailing group', 'justclick-reg'), array( $this, 'justclick_text_render' ), 'justclick-reg', 'justclick_reg_options_section', array(
              'id' => 'justclick_reg_group',
              'name' => 'justclick_reg_options[justclick_reg_group]',
              'value' => $this->settings['justclick_reg_group']
            ) );
            add_settings_field( 'justclick_reg_register', __('Add subscribtion for user when register', 'justclick-reg'), array( $this, 'justclick_checkbox_render' ), 'justclick-reg', 'justclick_reg_options_section', array(
              'id' => 'justclick_reg_register',
              'name' => 'justclick_reg_options[justclick_reg_register]',
              'value' => '1',
              'checked' => checked( $this->settings['justclick_reg_register'], 1, false )
             ) );
            add_settings_field( 'justclick_reg_deleted', __('Remove subscribtion for user when deleted', 'justclick-reg'), array( $this, 'justclick_checkbox_render' ), 'justclick-reg', 'justclick_reg_options_section', array(
              'id' => 'justclick_reg_deleted',
              'name' => 'justclick_reg_options[justclick_reg_deleted]',
              'value' => '1',
              'checked' => checked( $this->settings['justclick_reg_deleted'], 1, false )
             ) );
    	}

        /* section callback (some help) */
        public function justclick_reg_options_section_callback()
        {
        }

        /* Sanitize input options */
        function justclick_reg_options_sanitize( $input ) {

          if( !empty( $input['justclick_reg_login'] ) )
              $input['justclick_reg_login'] = sanitize_text_field( $input['justclick_reg_login'] );

          return $input;
        }

		/*
        * Render form fields for option
        */
		public function justclick_text_render($args)
		{
          $id = esc_attr( $args['id'] );
          $name = esc_attr( $args['name'] );
          $value = esc_attr( $args['value'] );
          echo "<input type='text' id='$id' name='$name' value='$value' />";
    	}

		public function justclick_checkbox_render($args)
		{
          $id = esc_attr( $args['id'] );
          $name = esc_attr( $args['name'] );
          $value = esc_attr( $args['value'] );
          $checked = esc_attr( $args['checked'] );
          echo "<input type='checkbox' $checked id='$id' name='$name' value='$value' />";
    	}

        /*
        * ====================================================
        *
        * Main Justclick funtionals
        *
        * ====================================================
        */

        // Add just registered user to justclick mailing group
        function Justclick_AddLeadToGroup($user_id)
		{
            $this->settings = get_option( 'justclick_reg_options' );

			if($this->settings['justclick_reg_register'] && $this->settings['justclick_reg_login']&& $this->settings['justclick_reg_secret'])
            {
                $subscriber = get_userdata($user_id);
                $send_data = array(
                  'rid[0]' => $this->settings['justclick_reg_group'], // mantadory
                  'lead_name' => $subscriber->display_name,
                  'lead_email' => $subscriber->user_email, // mantadory
                  'lead_phone' => '',
                  'lead_city' => '',
                  'aff' => '',
                  'tag' => '',
                  'ad' => '',
                  'doneurl2' => '',
                );
				$send_data['hash'] = $this->GetHash($send_data, $this->settings['justclick_reg_login'], $this->settings['justclick_reg_secret']);
				$this->justclick_response = json_decode($this->Send('http://'.$this->settings['justclick_reg_login'].'.justclick.ru/api/AddLeadToGroup', $send_data));
            }
        }

        // Remove sunscribtion from justclick mailing group whe user deleted
        function Justclick_DeleteSubscribe($user_id)
		{
            $this->settings = get_option( 'justclick_reg_options' );

			if($this->settings['justclick_reg_deleted'] && $this->settings['justclick_reg_login']&& $this->settings['justclick_reg_secret'])
            {
            $subscriber = get_userdata($user_id);
            $send_data = array(
              'lead_email' => $subscriber->user_email, // mantadory
              'rass_name' => $this->settings['justclick_reg_group'] // mantadory
            );
            $send_data['hash'] = $this->GetHash($send_data, $this->settings['justclick_reg_login'], $this->settings['justclick_reg_secret']);
			$this->justclick_response = json_decode($this->Send('http://'.$this->settings['justclick_reg_login'].'.justclick.ru/api/DeleteSubscribe', $send_data));
            }
        }
        /*
        * Coommon functions for all Justclick API calls
        */
        // Send request to API service
		function Send($url, $data)
		{
			 $ch = curl_init();
			 curl_setopt($ch, CURLOPT_URL, $url);
			 curl_setopt($ch, CURLOPT_POST, true);
			 curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
			 curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			 curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
			 curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			 $response = curl_exec($ch);
			 curl_close($ch);
			 return $response;
		}
        // Make signature for send data
		function GetHash($params, $login, $secret)
		{
			 $params = http_build_query($params);
			 return md5("{$params}::{$login}::{$secret}");
		}
        // Check signature in response
        function CheckHash($response, $user_justclick)
		{
			 $secret = $user_justclick['user_rps_key'];
			 $code = $response->error_code;
			 $text = $response->error_text;
			 $hash = md5("$code::$text::$secret");
			 return ($hash == $response->hash)? true : false;
		}
        /*
        * ====================================================
        * END Main Justclick funtionals
        * ====================================================
        */

    	// Add the settings link to the plugins page
    	function plugin_settings_link($links)
    	{
    		$settings_link = '<a href="'. get_admin_url(null, 'options-general.php?page=justclick-reg') .'">'.__('Settings','justclick-reg').'</a>';
    		array_unshift($links, $settings_link);
    		return $links;
    	}

		/* Translations */
		public function load_plugin_textdomain() {
			load_plugin_textdomain('justclick-reg', false, dirname(plugin_basename(__FILE__)) . '/languages/');
		}

		/* Activate the plugin */
		public static function activate()
		{ /* Do nothing */ }
        /* Deactivate the plugin */
		public static function deactivate()
		{/* Do nothing */ }

    } // END class Justclick_Reg

	// Installation and uninstallation hooks
	register_activation_hook(__FILE__, array('Justclick_Reg', 'activate'));
	register_deactivation_hook(__FILE__, array('Justclick_Reg', 'deactivate'));

	// instantiate the plugin class
	$justclick_reg = new Justclick_Reg();

} // END if(!class_exists('Justclick_Reg'))

?>