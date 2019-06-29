<?php

/**
 * CashBaba Class
 */
class WC_cashbaba {
	
	
		
	 //$reValue = $_POST ;
	 //print_r($reValue);
	 
	
    private $table = 'wc_cashbaba';
	
    function __construct() {
        add_action( 'wp_ajax_wc-cashbaba-confirm-trx', array($this, 'process_form') );

        add_action( 'woocommerce_order_details_after_order_table', array($this, 'transaction_form_order_view') );
    }

    function transaction_form_order_view( $order ) {
	
		
		
        if ( $order->has_status( 'on-hold' ) && $order->payment_method == 'cashbaba' && ( is_view_order_page() || is_order_received_page() ) ) {
			
		 
         $items =  $order->get_items();
		 $quantities = "";
		 foreach($items as $value){
			 $quantities += $value['qty'];
		 }
		
		$option = get_option( 'woocommerce_cashbaba_settings', array() );
		
		 $merchantId   = isset( $option['merchant_Id']) ? $option['merchant_Id'] : '' ;
		 $merchantKey  = isset( $option['merchant_Key']) ? $option['merchant_Key'] : '' ;
		 $returnUrl    = isset( $option['return_Url']) ? $option['return_Url'] : '' ;
		 
		 $orders         = wc_get_order( $order );
		 $OrderToralPay  = $orders->get_total();
		 
		 $OrderId   =  $orders ->id;
		 $totalPay  = $OrderToralPay;
		 $quantities;
			 
		
		 //$userId = get_current_user_id();	

		 //$wp_session['user_id'] =  $userId;
		
		//$returnUrl = plugins_url('woocommerce-cashbaba/includes/test.php'); 
		
		$today	  = date("m-d-Y");
		$orderNo  = time(); 
		$data = array(
		    "MerchantId" 			 => $merchantId,
			"MerchantKey" 			 => $merchantKey,
			"NoOfItems" 			 => $quantities,
			"OrderId" 				 => $OrderId,
			"OrderAmount" 			 => $totalPay,
			"ExpectedSettlementDate" => $today,
			"ReturnUrl" 			 => $returnUrl
		 );
		 
		 
		/* $data = array(
		    "MerchantId" 			 => 990040112,
			"MerchantKey" 			 => "W!n067nXbc",
			"NoOfItems" 			 => 1,
			"OrderId" 				 => $orderNo,
			"OrderAmount" 			 => 100,
			"ExpectedSettlementDate" => $today,
			"ReturnUrl" 			 => $returnUrl
		 );*/
		 
		 
		 
		 
		/*echo "<pre>";
		 print_r($data);
		echo "</pre>";
	
		 exit;*/
		 
		$jdata = wp_json_encode($data);
		
		//$url = 'https://www.cashbaba.co:6081/api/Payment/Sale';
        //$url = 'https://sanapi.cashbaba.com.bd/api/Payment/Sale';
        
		$url = 'https://api.cashbaba.com.bd/api/Payment/Sale';
		
			//open connection
				$ch = curl_init();

				//set the url, number of POST vars, POST data
				curl_setopt($ch,CURLOPT_URL, $url);
				curl_setopt($ch,CURLOPT_POST, count($data));
				curl_setopt($ch,CURLOPT_POSTFIELDS, $jdata);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
					'Content-Type: application/json')                                                                       
				);       

				//execute post
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				 $result = curl_exec($ch);
				 $receivedData = json_decode($result,true);				
				 
				/*echo "<pre>";
					print_r($receivedData);
				echo "</pre>";
				echo "<br/>";
				exit;*/
				
				// Check for errors and display the error message
				if($errno = curl_errno($ch)) {
					$error_message = curl_strerror($errno);
					echo "cURL error ({$errno}):\n {$error_message}";
				}
				
		// Check for errors and display the error message
				if($errno = curl_errno($ch)) {
					$error_message = curl_strerror($errno);
					echo "cURL error ({$errno}):\n {$error_message}";
				}
				
			//close connection
			 curl_close($ch);   
			
			 $RedirtUrl = $receivedData['RedirectUrl'];
			 $msg = $receivedData['Message'];
			 
			
			 
			if($msg == 'Ok'){
				header('Location:'.$RedirtUrl);
				exit;
			}else{
				
				/* echo "<pre>";
					print_r($receivedData);
				echo "</pre>";
				echo "<br/>"; */
				
				echo "Something Error";
			}
        }
    }

    /**
     * Show the payment field in checkout
     *
     * @return void
     */
    public static function tranasaction_form( $order_id ) {
		
        $option = get_option( 'woocommerce_cashbaba_settings', array() );
       
    }

    public function process_form() {
		
		 $response = file_get_contents( $returnUrl );
		 
		 echo "<pre>";
			print_r($_POST);
		 echo "</pre>";
		 exit;
		 
        /*if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'wc-cashbaba-confirm-trx' ) ) {
            wp_send_json_error( __( 'Are you cheating?', 'wc-cashbaba' ) );
        }*/

       // $order_id       = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
        //$transaction_id = sanitize_key( $_POST['cashbaba_trxid'] );

        //$order          = wc_get_order( $order_id );
        $response       = $this->do_request();

        if ( ! $response ) {
            wp_send_json_error( __( 'Something went wrong submitting the request', 'wc-cashbaba' ) );
            return;
        }

        if ( $this->transaction_exists( $response->trxId ) ) {
            wp_send_json_error( __('This transaction has already been used!', 'wc-cashbaba' ) );
            return;
        }

        switch ($response->trxStatus) {

            case '0010':
            case '0011':
                wp_send_json_error( __( 'Transaction is pending, please try again later', 'wc-cashbaba' ) );
                return;

            case '0100':
                wp_send_json_error( __( 'Transaction ID is valid but transaction has been reversed. ', 'wc-cashbaba' ) );
                return;

            case '0111':
                wp_send_json_error( __( 'Transaction is failed.', 'wc-cashbaba' ) );
                return;

            case '1001':
                wp_send_json_error( __( 'Invalid MSISDN input. Try with correct mobile no.', 'wc-cashbaba' ) );
                break;

            case '1002':
                wp_send_json_error( __( 'Invalid transaction ID', 'wc-cashbaba' ) );
                return;

            case '1003':
                wp_send_json_error( __( 'Authorization Error, please contact site admin.', 'wc-cashbaba' ) );
                return;

            case '1004':
                wp_send_json_error( __( 'Transaction ID not found.', 'wc-cashbaba' ) );
                return;

            case '9999':
                wp_send_json_error( __( 'System error, could not process request. Please contact site admin.', 'wc-cashbaba' ) );
                return;

            case '0000':
                $price = (float) $order->get_total();

                // check for BDT if exists
                $bdt_price = get_post_meta( $order->id, '_bdt', true );
                if ( $bdt_price != '' ) {
                    $price = $bdt_price;
                }

                if ( $price > (float) $response->amount ) {
                    wp_send_json_error( __( 'Transaction amount didn\'t match, are you cheating?', 'wc-cashbaba' ) );
                    return;
                }

                $this->insert_transaction( $response );

                $order->add_order_note( sprintf( __( 'cashbaba payment completed with TrxID#%s! cashbaba amount: %s', 'wc-cashbaba' ), $response->trxId, $response->amount ) );
                $order->payment_complete();

                wp_send_json_success( $order->get_view_order_url() );

                break;
        }

        wp_send_json_error();
    }

    /**
     * Do the remote request
     *
     * For some reason, WP_HTTP doesn't work here. May be
     * some implementation related problem in their side.
     *
     * @param  string  $transaction_id
     *
     * @return object
     */
    function do_request() {

        //$option = get_option( 'woocommerce_cashbaba_settings', array() );
       /* $query = array(
            'user'   => isset( $option['username'] ) ? $option['username'] : '',
            'pass'   => isset( $option['pass'] ) ? $option['pass'] : '',
            'msisdn' => isset( $option['mobile'] ) ? $option['mobile'] : '',
            'trxid'  => $transaction_id
        );*/
		$today	  = date("m-d-Y");
		$orderNo  = time(); 
		$query = array(
		    "MerchantId" 			 => 990040112,
			"MerchantKey" 			 => "W!n067nXbc",
			"NoOfItems" 			 => 1,
			"OrderId" 				 => $orderNo,
			"OrderAmount" 			 => 100,
			"ExpectedSettlementDate" => $today,
			"ReturnUrl" 			 => "http://localhost/dhakafashion/wp-admin/"
		 );

        //$url      = self::base_url . '?' . http_build_query( $query, '', '&' );
        //$response = file_get_contents( $url );
		
		
		$url = 'https://www.cashbaba.co:6081/api/Payment/Sale';
		
		$jdata = json_encode($data);
			
			//print_r($jdata);
			
			
			//open connection
				$ch = curl_init();

				//set the url, number of POST vars, POST data
				curl_setopt($ch,CURLOPT_URL, $url);
				curl_setopt($ch,CURLOPT_POST, count($data));
				curl_setopt($ch,CURLOPT_POSTFIELDS, $jdata);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
					'Content-Type: application/json')                                                                       
				);       

				//execute post
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				 $result = curl_exec($ch);
				 $receivedData = json_decode($result,true);
				 
				 
				echo "<pre>";
					print_r($receivedData);
				echo "</pre>";
				echo "<br/>";
				exit;
				
				// Check for errors and display the error message
				if($errno = curl_errno($ch)) {
					$error_message = curl_strerror($errno);
					echo "cURL error ({$errno}):\n {$error_message}";
				}

				//close connection
				curl_close($ch); 
		
		
		
		
		
		
		

        /*if ( false !== $response ) {
            $response = json_decode( $response );

            return $response->transaction;
        }*/

        return false;
    }

    /**
     * Insert transaction info in the db table
     *
     * @param  object  $response
     *
     * @return void
     */
    function insert_transaction( $response ) {
        global $wpdb;

        $wpdb->insert( $wpdb->prefix . $this->table, array(
            'trxId'  => $response->trxId,
            'sender' => $response->sender,
            'ref'    => $response->reference,
            'amount' => $response->amount
        ), array(
            '%d',
            '%s',
            '%s',
            '%s'
        ) );
    }

    /**
     * Check if a transaction exists
     *
     * @param  string  $transaction_id
     *
     * @return bool
     */
    function transaction_exists( $transaction_id ) {
        global $wpdb;

        $query  = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}{$this->table} WHERE trxId = %d", $transaction_id );
        $result = $wpdb->get_row( $query );

        if ( $result ) {
            return true;
        }

        return false;
    }
}

new WC_cashbaba();
