<?php

class EM_Gateway_Virement extends EM_Gateway {

	var $gateway = 'virement';
	var $title = 'Virement';
	var $status = 5;
    var $status_txt = 'Awaiting Virement Payment';
	var $button_enabled = true;
	var $payment_return = true;
	var $supports_multiple_bookings = true;

	public function __construct() {
		parent::__construct();
        
        if( get_option('em_'. $this->gateway . "_payment_txt_color" ) ) { $txtColor = get_option('em_'. $this->gateway . "_payment_txt_color" ); } else { $txtColor = '#333333'; }
        if( get_option('em_'. $this->gateway . "_payment_txt_bgcolor" ) ) { $txtBgColor = get_option('em_'. $this->gateway . "_payment_txt_bgcolor" ); } else { $txtBgColor = 'none'; }
        
        $this->status_txt = '<span style="color:'.$txtColor.';background-color:'.$txtBgColor.'">'.get_option('em_'. $this->gateway . "_payment_txt_status" ).'</span>';

        add_action('em_gateway_js', array(&$this,'em_gateway_js'));
        //add_action('em_template_my_bookings_header',array(&$this,'say_thanks')); //say thanks on my_bookings page
        add_filter('em_booking_validate', array(&$this, 'em_booking_validate'),10,2); // Hook into booking validation
	}

	/*
	 * --------------------------------------------------
	 * Booking UI - modifications to booking pages and tables containing Virement bookings
	 * --------------------------------------------------
	 */

	/**
	 * Outputs some JavaScript during the em_gateway_js action, which is run inside a script html tag, located in gateways/gateway.virement.js
	 */
	function em_gateway_js(){
		include(dirname(__FILE__).'/gateway.virement.js');
	}


	/*
	 * --------------------------------------------------
	 * Booking Interception - functions that modify booking object behaviour
	 * --------------------------------------------------
	 */


	/**
	 * Intercepts return data after a booking has been made
	 * Add payment method choices if setting is enabled via gateway settings
	 * @param array $return
	 * @param EM_Booking $EM_Booking
	 * @return array
	 */

	/**
	 * Hook into booking validation and check validate payment type if present
	 * @param boolean $result
	 * @param EM_Booking $EM_Booking
	 */
	function em_booking_validate($result, $EM_Booking) {
        
		if( isset( $_POST['paymentType'] ) && empty( $_POST['paymentType'] ) ) {
			$EM_Booking->add_error('Please specify payment choose');
			$result = false;
		}
        
		return $result;
	}

	/**
	 * Intercepts return data after a booking has been made and adds Virement vars, modifies feedback message.
	 * @param array $return
	 * @param EM_Booking $EM_Booking
	 * @return array
	 */
	function booking_form_feedback( $return, $EM_Booking = false ){

		//Double check $EM_Booking is an EM_Booking object and that we have a booking awaiting payment.
		if( is_object($EM_Booking) && $this->uses_gateway($EM_Booking) ){
			if( !empty($return['result']) && get_option('em_'. $this->gateway . "_redirect" ) > 0 && $EM_Booking->get_price() > 0 && $EM_Booking->booking_status == $this->status ){
                
				$return['message'] = get_option('em_virement_booking_feedback');
				$virement_url = $this->get_virement_url();
				$virement_vars = $this->get_virement_vars($EM_Booking);
				$virement_return = array('virement_url'=>$virement_url, 'virement_vars'=>$virement_vars);
				$return = array_merge($return, $virement_return);
			}else{
				//returning a free message
				$return['message'] = get_option('em_virement_booking_feedback');
			}
		}
        //print_r($_REQUEST);
        
		return $return;
	}

	/*
	 * ------------------------------------------------------------
	 * Virement Functions - functions specific to Virement payments
	 * ------------------------------------------------------------
	 */

	/**
	 * Retreive the Virement vars needed to send to the gatway to proceed with payment
	 * @param EM_Booking $EM_Booking
	 */
	function get_virement_vars( $EM_Booking ) {
		global $wp_rewrite, $EM_Notices;

		$currency = get_option('dbem_bookings_currency', 'USD');
		$currency = apply_filters('em_gateway_virement_get_currency', $currency, $EM_Booking );

		$amount = $EM_Booking->get_price();
		$amount = apply_filters('em_gateway_virement_get_amount', $amount, $EM_Booking, $_REQUEST );
        

		$virement_vars = array(
			//'instId' => get_option('em_'. $this->gateway . "_instId" ),
			'cartId' => $EM_Booking->booking_id,
			'currency' => $currency,
			'amount' => number_format( $amount, 2),
            'invoice' => 'EM-BOOKING#'. $EM_Booking->booking_id, //added to enable searching in event of failed IPNs
			'desc' => $EM_Booking->get_event()->event_name
		);
            
		return apply_filters('em_gateway_virement_get_virement_vars', $virement_vars, $EM_Booking, $this);
	}

	/**
	 * gets virement gateway url
	 * @returns string
	 */
	function get_virement_url(){

        if( get_option('em_'. $this->gateway . "_redirect" ) ) {
            $url = get_permalink( get_option('em_'.$this->gateway.'_redirect') );
            return $url;
        } else {
            return;
        }

	}

    /**
	 * Outputs extra custom information, e.g. payment details or procedure, which is displayed when this gateway is selected when booking (not when using Quick Pay Buttons)
	 */
	function booking_form(){
		echo get_option('em_'.$this->gateway.'_form');
	}

	/*
	 * --------------------------------------------------
	 * Gateway Settings Functions
	 * --------------------------------------------------
	 */

	function mysettings() {
		global $EM_options;
        
        $textFeedback = get_option('em_'. $this->gateway . "_booking_feedback" );
        $textStatus = get_option('em_'. $this->gateway . "_payment_txt_status" );

		?>
	<table class="form-table">
		<tbody>
			<tr valign="top">
				<th scope="row"><?php _e('Redirecting Message','events-manager-pro-virement') ?></th>
				<td>
					<input type="text" name="virement_booking_feedback" value="<?php if( empty($textFeedback) ) { 
            echo esc_attr_e(__('Please wait, you will be redirected ...', 'events-manager-pro-virement')); 
        } else {
            echo esc_attr_e(get_option('em_'. $this->gateway . "_booking_feedback" ));
        } ?>" style='width: 40em;' /><br />
					<em><?php _e('The message that is shown to a user when a booking is successful whilst being redirected to Virement for payment.','events-manager-pro-virement'); ?></em>
				</td>
			</tr>
            <tr valign="top">
				<th scope="row"><?php _e('Status text','events-manager-pro-virement') ?></th>
				<td>
					<input type="text" name="virement_payment_txt_status" value="<?php if( empty($textStatus) ) { 
            echo esc_attr_e(__('Awaiting Virement Payment', 'events-manager-pro-virement')); 
        } else {
            echo esc_attr_e(get_option('em_'. $this->gateway . "_payment_txt_status" ));
        } ?>" style='width: 40em;' /><br />
					<em><?php _e('By default: <i>Awaiting Virement Payment</i>', 'events-manager-pro-virement'); ?></em>
                    <br />
                    <input type="text" value="<?php if( get_option('em_'. $this->gateway . "_payment_txt_color" ) ) { echo get_option('em_'. $this->gateway . "_payment_txt_color" ); } else { echo '#333333'; } ?>" name="virement_payment_txt_color" class="wpempvir-color-field" data-default-color="#000000" /><br />
					<em><?php _e('Select a color for the text. By default: <i>#333333</i>', 'events-manager-pro-virement'); ?></em><br />
                    <input type="text" value="<?php if( get_option('em_'. $this->gateway . "_payment_txt_bgcolor" ) ) { echo get_option('em_'. $this->gateway . "_payment_txt_bgcolor" ); } ?>" name="virement_payment_txt_bgcolor" class="wpempvir-color-field" /><br />
					<em><?php _e('Select a color for the background text. By default: <i>none</i>', 'events-manager-pro-virement'); ?></em><br />
				</td>
			</tr>
            
            <tr valign="top">
				<th scope="row"><?php _e('Redirection Page','events-manager-pro-virement') ?></th>
				<td>
                    <?php
                        if( get_option('em_'. $this->gateway . "_redirect" ) ) { 
                            $idSelectPage = get_option('em_'. $this->gateway . "_redirect" );
                        } else {
                            $idSelectPage = 0;
                        }
                        $args = array('name' => 'virement_redirect', 'selected' => $idSelectPage, 'show_option_none' => __('Please select a page','events-manager-pro-virement') ); 
                        wp_dropdown_pages($args);
                    ?>
				</td>
            </tr>

		</tbody>
	</table>

		<?php
	}

	function update() {
        
         $gateway_options = array(
            $this->gateway . '_booking_feedback' => wp_kses_data($_REQUEST[ $this->gateway.'_booking_feedback' ]),
            //$this->gateway . "_invoice_option" => $_REQUEST[ $this->gateway.'_invoice_option' ],
            $this->gateway . "_payment_txt_status" => wp_kses_data($_REQUEST[ $this->gateway.'_payment_txt_status' ]),
            $this->gateway . "_payment_txt_color" => wp_kses_data($_REQUEST[ $this->gateway.'_payment_txt_color' ]),
            $this->gateway . "_payment_txt_bgcolor" => wp_kses_data($_REQUEST[ $this->gateway.'_payment_txt_bgcolor' ]),
            $this->gateway . "_redirect" => $_REQUEST[ $this->gateway.'_redirect' ],
        );
        foreach($gateway_options as $key=>$option){
			update_option('em_'.$key, stripslashes($option));
		}
        
        //add wp_kses filters for relevant options and merge in
		$options_wpkses[] = 'em_'. $this->gateway . '_booking_feedback';
		foreach( $options_wpkses as $option_wpkses ) add_filter('gateway_update_'.$option_wpkses,'wp_kses_post');

		//pass options to parent which handles saving
		return parent::update($gateway_options);
	}
}

EM_Gateways::register_gateway('virement', 'EM_Gateway_Virement');
