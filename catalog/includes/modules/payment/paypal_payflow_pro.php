<?php
/*
  Developed by Brian Burton - Dynamo Effects
  
  Copyright (c) 2008 Dynamo Effects

  Released under the GNU General Public License
*/

  class paypal_payflow_pro {
    var $code, $title, $description, $enabled;

    function paypal_payflow_pro() {
      global $order;

      $this->code = 'paypal_payflow_pro';
      $this->title = MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_TEXT_TITLE;
      $this->public_title = MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_TEXT_PUBLIC_TITLE;
      $this->description = MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_TEXT_DESCRIPTION;
      $this->sort_order = MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_SORT_ORDER;
      $this->enabled = ((MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_STATUS == 'True') ? true : false);
      
      if ((int)MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_ORDER_STATUS_ID > 0) {
        $this->order_status = MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_ORDER_STATUS_ID;
      }

      if (is_object($order)) $this->update_status();
    }

    function update_status() {
      global $order;

      if ( ($this->enabled == true) && ((int)MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_ZONE > 0) ) {
        $check_flag = false;
        $check_query = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id");
        while ($check = tep_db_fetch_array($check_query)) {
          if ($check['zone_id'] < 1) {
            $check_flag = true;
            break;
          } elseif ($check['zone_id'] == $order->billing['zone_id']) {
            $check_flag = true;
            break;
          }
        }

        if ($check_flag == false) {
          $this->enabled = false;
        }
      }
    }

    function javascript_validation() {
      return false;
    }

    function selection() {
      for ($i=1; $i<13; $i++) {
        $expires_month[] = array('id' => sprintf('%02d', $i), 'text' => strftime('%B',mktime(0,0,0,$i,1,2000)));
      }

      $today = getdate(); 
      for ($i=$today['year']; $i < $today['year']+10; $i++) {
        $expires_year[] = array('id' => strftime('%y',mktime(0,0,0,1,1,$i)), 'text' => strftime('%Y',mktime(0,0,0,1,1,$i)));
      }

      $selection = array('id' => $this->code,
                         'module' => $this->public_title,
                         'fields' => array(array('title' => MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_TEXT_CREDIT_CARD_NUMBER,
                                                 'field' => tep_draw_input_field('paypal_payflow_pro_number')),
                                           array('title' => MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_TEXT_CREDIT_CARD_EXPIRES,
                                                 'field' => tep_draw_pull_down_menu('paypal_payflow_pro_expires_month', $expires_month) . '&nbsp;' . tep_draw_pull_down_menu('paypal_payflow_pro_expires_year', $expires_year)),
                                           array('title' => MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_TEXT_CREDIT_CARD_CVV2,
                                                 'field' => tep_draw_input_field('paypal_payflow_pro_cvv2', '', 'size="4"'))));

      return $selection;
    }

    function pre_confirmation_check() {
      return false;
    }

    function confirmation() {
      $confirmation = array('title' => $this->title . ': ' . $this->cc_type,
                            'fields' => array(array('title' => MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_TEXT_CREDIT_CARD_NUMBER,
                                                    'field' => substr($_POST['paypal_payflow_pro_number'], 0, 4) . str_repeat('X', (strlen($_POST['paypal_payflow_pro_number']) - 8)) . substr($_POST['paypal_payflow_pro_number'], -4)),
                                              array('title' => MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_TEXT_CREDIT_CARD_EXPIRES,
                                                    'field' => strftime('%B, %Y', mktime(0,0,0,$_POST['paypal_payflow_pro_expires_month'], 1, '20' . $_POST['paypal_payflow_pro_expires_year'])))));

      return $confirmation;
    }

    function process_button() {
      $process_button_string = tep_draw_hidden_field('paypal_payflow_pro_number', $_POST['paypal_payflow_pro_number']) .
                               tep_draw_hidden_field('paypal_payflow_pro_expires_month', $_POST['paypal_payflow_pro_expires_month']) .
                               tep_draw_hidden_field('paypal_payflow_pro_expires_year', $_POST['paypal_payflow_pro_expires_year']) .
                               tep_draw_hidden_field('paypal_payflow_pro_cvv2', $_POST['paypal_payflow_pro_cvv2']);

      return $process_button_string;
    }

    function before_process() {
      global $order;
      
      $cc_number = preg_replace('/[^0-9]/', '', $_POST['paypal_payflow_pro_number']);
      $cc_expires_month = preg_replace('/[^0-9]/', '', $_POST['paypal_payflow_pro_expires_month']);
      $cc_expires_year = preg_replace('/[^0-9]/', '', $_POST['paypal_payflow_pro_expires_year']);
      $cc_cvv2 = preg_replace('/[^0-9]/', '', $_POST['paypal_payflow_pro_cvv2']);
      
      include(DIR_WS_CLASSES . 'cc_validation.php');

      $cc_validation = new cc_validation();
      $result = $cc_validation->validate($cc_number, $cc_expires_month, $cc_expires_year);

      $error = '';
      switch ($result) {
        case -1:
          $error = sprintf(TEXT_CCVAL_ERROR_UNKNOWN_CARD, substr($cc_validation->cc_number, 0, 4));
          break;
        case -2:
        case -3:
        case -4:
          $error = TEXT_CCVAL_ERROR_INVALID_DATE;
          break;
        case false:
          $error = TEXT_CCVAL_ERROR_INVALID_NUMBER;
          break;
      }

      if ( ($result == false) || ($result < 1) ) {
        $payment_error_return = 'payment_error=' . $this->code . '&error=' . urlencode(stripslashes($error));

        tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, $payment_error_return, 'SSL', true, false));
      }

      $this->cc_type = $cc_validation->cc_type;
      $this->cc_number = $cc_validation->cc_number;
      $this->cc_expires_month = $cc_expires_month;
      $this->cc_expires_year = $cc_expires_year;
      $this->cc_cvv2 = $cc_cvv2;
      
      $billing_state = '';
      $delivery_state = '';
      
      if ($order->billing['zone_id'] > 0) {
        $zone_query = tep_db_query("SELECT zone_code 
                                    FROM " . TABLE_ZONES . " 
                                    WHERE zone_id = " . (int)$order->billing['zone_id'] . " 
                                    LIMIT 1");
        $zone = tep_db_fetch_array($zone_query);
        
        $billing_state = $zone['zone_code'];
      } elseif (!is_null($order->billing['state'])) {
        $zone_query = tep_db_query("SELECT zone_code 
                                    FROM " . TABLE_ZONES . " 
                                    WHERE zone_name = '" . $order->billing['state'] . "' 
                                      AND zone_country_id = " . (int)$order->billing['country']['id'] . " 
                                    LIMIT 1");
        if (tep_db_num_rows($zone_query) > 0) {
          $zone = tep_db_fetch_array($zone_query);
          
          $billing_state = $zone['zone_code'];
        }
      }
      
      if ($order->delivery['zone_id'] > 0) {
        $zone_query = tep_db_query("SELECT zone_code 
                                    FROM " . TABLE_ZONES . " 
                                    WHERE zone_id = " . (int)$order->delivery['zone_id'] . " 
                                    LIMIT 1");
        $zone = tep_db_fetch_array($zone_query);
        
        $delivery_state = $zone['zone_code'];
      } elseif (!is_null($order->delivery['state'])) {
        $zone_query = tep_db_query("SELECT zone_code 
                                    FROM " . TABLE_ZONES . " 
                                    WHERE zone_name = '" . $order->delivery['state'] . "' 
                                      AND zone_country_id = " . (int)$order->delivery['country']['id'] . " 
                                    LIMIT 1");
        if (tep_db_num_rows($zone_query) > 0) {
          $zone = tep_db_fetch_array($zone_query);
          
          $delivery_state = $zone['zone_code'];
        }
      }
      
      $paypal_query_array = array(
        'USER'             => MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_USER,
        'VENDOR'           => MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_VENDOR,
        'PARTNER'          => MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_PARTNER,
        'PWD'              => MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_PASSWORD,
        'TENDER'           => 'C',  
        'TRXTYPE'          => 'S',
        'ACCT'             => $this->cc_number,
        'CVV2'             => $this->cc_cvv2,
        'EXPDATE'          => $this->cc_expires_month . $this->cc_expires_year,
        'FREIGHTAMT'       => round($order->info['shipping_cost'], 2),
        'TAXAMT'           => round($order->info['tax'], 2),
        'AMT'              => round($order->info['total'], 2),
        'CURRENCY'         => $_SESSION['currency'],
        'FIRSTNAME'        => $order->billing['firstname'],
        'LASTNAME'         => $order->billing['lastname'],
        'STREET'           => $order->billing['street_address'],
        'CITY'             => $order->billing['city'],
        'STATE'            => $billing_state,
        'ZIP'              => $order->billing['postcode'],
        'COUNTRY'          => $order->billing['country']['iso_code_3'],
        'SHIPTOFIRSTNAME'  => $order->delivery['firstname'],
        'SHIPTOLASTNAME'   => $order->delivery['lastname'],
        'SHIPTOSTREET'     => $order->delivery['street_address'],
        'SHIPTOCITY'       => $order->delivery['city'],
        'SHIPTOSTATE'      => $delivery_state,
        'SHIPTOZIP'        => $order->delivery['postcode'],
        'COUNTRY'          => $order->delivery['country']['iso_code_3'],
        'EMAIL'            => $order->customer['email_address'],
        'CUSTIP'           => $_SERVER['REMOTE_ADDR'],
        'COMMENT1'         => '',
        'INVNUM'           => '',
        'ORDERDESC'        => '',
        'VERBOSITY'        => 'MEDIUM'
      );

      foreach ($paypal_query_array as $key => $value) {
        $paypal_query[] = $key . '[' . strlen($value) . ']=' . $value;
      }
      
      $paypal_query = implode('&', $paypal_query);

      $user_agent = $_SERVER['HTTP_USER_AGENT'];

      $headers[] = "Content-Type: text/namevalue"; 
      $headers[] = "Content-Length : " . strlen ($paypal_query);
      $headers[] = "X-VPS-Timeout: 45";
      $headers[] = "X-VPS-Request-ID:" . $unique_id;
      
      if (MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_SERVER == 'Live') {
        $submit_url = "https://payflowpro.paypal.com";
      } else {
        $submit_url = "https://pilot-payflowpro.paypal.com";
      }

      $ch = curl_init();
      
      if (trim(MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_PROXY) != '') {
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
        curl_setopt($ch, CURLOPT_PROXY, MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_PROXY);
      }
      
      curl_setopt($ch, CURLOPT_URL, $submit_url);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
      curl_setopt($ch, CURLOPT_HEADER, 1);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_TIMEOUT, 90);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); 
      curl_setopt($ch, CURLOPT_POSTFIELDS, $paypal_query);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,  2);
      curl_setopt($ch, CURLOPT_FORBID_REUSE, TRUE);
      curl_setopt($ch, CURLOPT_POST, 1);

      $i=1;
      while ($i++ <= 3) {
        $result = curl_exec($ch);
        $headers = curl_getinfo($ch);
        if ($headers['http_code'] != 200) {
          sleep(5);
        } else if ($headers['http_code'] == 200) {
          break;
        }
      }

      if ($headers['http_code'] != 200) {
        curl_close($ch);
        
        $payment_error_return = 'error_message=' . $this->code . '&error=' . urlencode(stripslashes(MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_TEXT_ERROR_BAD_RESPONSE));

        tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, $payment_error_return, 'SSL', true, false));
        
        exit;
      }
      
      curl_close($ch);
      $result = strstr($result, "RESULT");

      $proArray = array();
      while(strlen($result)){

        $keypos= strpos($result,'=');
        $keyval = substr($result,0,$keypos);

        $valuepos = strpos($result,'&') ? strpos($result,'&'): strlen($result);
        $valval = substr($result,$keypos+1,$valuepos-$keypos-1);

        $proArray[$keyval] = $valval;
        $result = substr($result,$valuepos+1,strlen($result));
      }

      $result_code = $proArray['RESULT'];
      
      $RespMsg = '';

      if ($result_code == 1 || $result_code == 26) {
        $RespMsg = MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_TEXT_ERROR_CREDENTIALS;
      /*
      } else if ($result_code == 0) {

        if (isset($proArray['AVSADDR'])) {
          if ($proArray['AVSADDR'] != "Y") {
            $RespMsg = MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_TEXT_ERROR_BILLING_STREET; 
          }
        }
        if (isset($proArray['AVSZIP'])) {
          if ($proArray['AVSZIP'] != "Y") {
            $RespMsg = MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_TEXT_ERROR_BILLING_ZIP;
          }
        }
        if (isset($proArray['CVV2MATCH'])) {
          if ($proArray['CVV2MATCH'] != "Y") {
            $RespMsg = MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_TEXT_ERROR_CVV2;
          }
        }
        */
      } else if ($result_code == 12) {
        $RespMsg = MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_TEXT_ERROR_DECLINED;
      } else if ($result_code == 13) {
        $RespMsg = MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_TEXT_ERROR_PENDING;
      } else if ($result_code == 23 || $result_code == 24) {
        $RespMsg = MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_TEXT_ERROR_CC_NUMBER;
      }

      if ($fraud == 'YES') {
        if ($result_code == 125) {
          $RespMsg = MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_TEXT_ERROR_DECLINED;
        } else if ($result_code == 126 || $result_code == 127) {
          $RespMsg = MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_TEXT_ERROR_REVIEW;
        }
      }
      
      if ($RespMsg != '') {
        $payment_error_return = 'payment_error=' . $this->code . '&error=' . urldecode($RespMsg);

        tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, $payment_error_return, 'SSL', true, false));
        
        exit;
      }
      
      $order->info['cc_type'] = $this->cc_type;
      $order->info['cc_owner'] = $order->billing['firstname'] . ' ' . $order->billing['lastname'];
      $order->info['cc_number'] = $this->cc_number;
      $order->info['cc_expires'] = $this->cc_expires_month . substr($this->cc_expires_year, 2, 2);

    }

    function after_process() {
      return false;
    }

    function get_error() {
      $error = array('title' => MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_TEXT_ERROR,
                     'error' => stripslashes(urldecode($_GET['error'])));

      return $error;
    }

    function check() {
      if (!isset($this->_check)) {
        $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_STATUS'");
        $this->_check = tep_db_num_rows($check_query);
      }
      
      return $this->_check;
    }

    function install() {
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable PayPal PayFlow Pro', 'MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_STATUS', 'True', 'Do you want to enable this module?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Live or Test Transactions', 'MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_SERVER', 'Live', 'Do you want to do live or test transactions?', '6', '0', 'tep_cfg_select_option(array(\'Live\', \'Test\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Credentials: Partner', 'MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_PARTNER', 'PayPal', 'The partner ID of the company you purchased this service from.', '6', '0', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Credentials: Vendor/Merchant', 'MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_VENDOR', '', 'The vendor for this account (case sensitive).', '6', '0', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Credentials: User', 'MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_USER', '', 'The username for this account, which might be the same as the vendor (case sensitive).', '6', '0', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Credentials: Password', 'MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_PASSWORD', '', 'The password for this account (case sensitive).', '6', '0', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Proxy Address', 'MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_PROXY', '', 'If your host requires all cURL transactions to go through a proxy, enter that address here.', '6', '0', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0' , now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status', 'MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
    }

    function remove() {
      tep_db_query("DELETE from " . TABLE_CONFIGURATION . " WHERE configuration_key IN ('" . implode("', '", $this->keys()) . "')");
    }

    function keys() {
      return array('MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_STATUS', 'MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_SERVER', 'MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_PARTNER', 'MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_VENDOR', 'MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_USER', 'MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_PASSWORD', 'MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_PROXY', 'MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_SORT_ORDER', 'MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_ZONE', 'MODULE_PAYMENT_PAYPAL_PAYFLOW_PRO_ORDER_STATUS_ID');      
    }
  }
?>