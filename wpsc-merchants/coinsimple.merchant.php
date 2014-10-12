<?php

/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2011-2014 BitPay, CoinSimple
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

$nzshpcrt_gateways[$num] = array(
		'name'                                    => __( 'Bitcoin Payments by CoinSimple', 'wpsc' ),
		'api_version'                             => 1.0,
		'image'                                   => WPSC_URL . '/images/coinsimple.png',
		'has_recurring_billing'                   => false,
		'wp_admin_cannot_cancel'                  => true,
		'display_name'                            => __( 'Bitcoin', 'wpsc' ),
		'user_defined_name[wpsc_merchant_coinsimple]' => 'Bitcoin',
		'requirements'                            => array('php_version' => 5.3),
		'internalname'                            => 'wpsc_merchant_coinsimple',
		'form'                                    => 'form_coinsimple',
		'submit_function'                         => 'submit_coinsimple',
		'function'                                => 'gateway_coinsimple',
		);

function debuglog2($contents)
{
	if (isset($contents)) {
		if (is_resource($contents)) {
			error_log(serialize($contents));
		} else {
			error_log(var_dump($contents, true));
		}
	}
}

function form_coinsimple()
{
	$rows = array();
	// API Key
	$rows[] = array(
			'Business ID',
			'<input name="coinsimple_shopid" type="text" value="' . get_option('coinsimple_shopid') . '" />',
			'<p class="description">You can get this in the API tab on the businesses page.</p>'
			);

	// API Key
	$rows[] = array(
			'Business API Key',
			'<input name="coinsimple_apikey" type="text" value="' . get_option('coinsimple_apikey') . '" />',
			'<p class="description">You can get this in the API tab on the businesses page.</p>'
			);

	//Allows the merchant to specify a URL to redirect to upon the customer completing payment on the bitpay.com
	//invoice page. This is typcially the "Transaction Results" page.
	$rows[] = array(
			'Redirect URL',
			'<input name="coinsimple_redirect" type="text" value="' . get_option('coinsimple_redirect') . '" />',
			'<p class="description"><strong>Important!</strong> Put the URL that you want the buyer to be redirected to after payment.</p>'
			);

	$output .= '
	<tr>
		<td colspan="2">
			<p class="description">
				<h1>CoinSimple</h1><br /><strong>To create an account at coinsimple you can go <a href="https://coinsimple.com" alt="CoinSimple">here</a></strong>
			</p>
		</td>
	</tr>' . "\n";

	foreach ($rows as $r) {
		$output .= '<tr> <td>' . $r[0] . '</td> <td>' . $r[1];

		if (isset($r[2])) {
			$output .= $r[2];
		}

		$output .= '</td></tr>';
	}

	return $output;
}

function submit_coinsimple()
{
	if (isset($_POST['submit']) && stristr($_POST['submit'], 'Update') !== false) {
		$params = array(
				'coinsimple_apikey',
				'coinsimple_shopid',
				'coinsimple_redirect'
		);

		foreach ($params as $p) {
			if ($_POST[$p] != null) {
				update_option($p, $_POST[$p]);
			} else {
				add_settings_error($p, 'error', __('The setting ' . $p . ' cannot be blank! Please enter a value for this field', 'wpse'), 'error');
			}
		}
	}

	return true;
}

function gateway_coinsimple($seperator, $sessionid)
{
	require('wp-content/plugins/wp-e-commerce/wpsc-merchants/coinsimple/cs_lib.php');

	//$wpdb is the database handle,
	//$wpsc_cart is the shopping cart object
	global $wpdb, $wpsc_cart;

	//This grabs the purchase log id from the database
	//that refers to the $sessionid
	$purchase_log = $wpdb->get_row(
		"SELECT * FROM `" . WPSC_TABLE_PURCHASE_LOGS .
		"` WHERE `sessionid`= " . $sessionid . " LIMIT 1",
		ARRAY_A);

	//This grabs the users info using the $purchase_log
	// from the previous SQL query
	$usersql = "SELECT `" . WPSC_TABLE_SUBMITED_FORM_DATA . "`.value,
		`" . WPSC_TABLE_CHECKOUT_FORMS . "`.`name`,
		`" . WPSC_TABLE_CHECKOUT_FORMS . "`.`unique_name` FROM
		`" . WPSC_TABLE_CHECKOUT_FORMS . "` LEFT JOIN
		`" . WPSC_TABLE_SUBMITED_FORM_DATA . "` ON
		`" . WPSC_TABLE_CHECKOUT_FORMS . "`.id =
		`" . WPSC_TABLE_SUBMITED_FORM_DATA . "`.`form_id` WHERE
		`" . WPSC_TABLE_SUBMITED_FORM_DATA . "`.`log_id`=" . $purchase_log['id'];

	$userinfo = $wpdb->get_results($usersql, ARRAY_A);

	// convert from awkward format
	foreach ((array)$userinfo as $value) {
		if (strlen($value['value'])) {
			$ui[$value['unique_name']] = $value['value'];
		}
	}

	$userinfo = $ui;

	// name
	if (isset($userinfo['billingfirstname'])) {
		$options['name'] = $userinfo['billingfirstname'];

		if (isset($userinfo['billinglastname'])) {
			$options['name'] .= ' ' . $userinfo['billinglastname'];
		}
	}

	if ($userinfo["billingemail"]) {
		$options['email'] = $userinfo["billingemail"];
	}

	$options['items'] = [];

	foreach ($wpsc_cart->cart_items as $item) {
		$options['items'][] = [
			"price" => $item->unit_price,
			"quantity" => $item->quantity,
			"description" => $item->product_name
		];
	}

	if (get_option('permalink_structure') != '') {
		$separator = "?";
	} else {
		$separator = "&";
	}

	//currency
	$currencyId = get_option('currency_type');
	$options['currency']          = strtolower($wpdb->get_var($wpdb->prepare("SELECT `code` FROM `" . WPSC_TABLE_CURRENCY_LIST . "` WHERE `id` = %d LIMIT 1", $currencyId)));
	$options['callback_url']   = get_option('siteurl') . '/?coinsimple_callback=true';

	//pass sessionid along so that it can be used to populate the transaction results page
	$options['redirect_url']       = get_option('coinsimple_redirect') . $separator . 'sessionid=' . $sessionid;

	$options['custom']           	 = $sessionid;
	$options['shop_key'] = get_option('coinsimple_shopid');
	$invoice = csCreateInvoice($sessionid, $options, get_option('coinsimple_apikey'));

	if (isset($invoice['error'])) {
		debuglog2($invoice);

		// close order
		$sql = "UPDATE `" . WPSC_TABLE_PURCHASE_LOGS . "` SET `processed`= '5' WHERE `sessionid`=" . $sessionid;
		$wpdb->query($sql);

		//redirect back to checkout page with errors
		$_SESSION['WpscGatewayErrorMessage'] = __('Sorry your transaction did not go through successfully, please try again.');
		//header('Location: ' . get_option('checkout_url'));
		exit();
	} else {
		$wpsc_cart->empty_cart();

		unset($_SESSION['WpscGatewayErrorMessage']);
		header('Location: ' . $invoice['url']);

		exit();
	}

}

function coinsimple_callback()
{
	if (isset($_GET['coinsimple_callback'])) {
		global $wpdb;

		require('wp-content/plugins/wp-e-commerce/wpsc-merchants/coinsimple/cs_lib.php');

		$response = csVerifyNotification(get_option('coinsimple_apikey'));

		if (isset($response['error'])) {
			debuglog($response);
		} else if ($response['status'] == "paid"){
			$sessionid = $response['custom'];
			$sql = "UPDATE `" . WPSC_TABLE_PURCHASE_LOGS . "` SET `processed`= '3' WHERE `sessionid`=" . $sessionid;

			if (is_numeric($sessionid)) {
				$wpdb->query($sql);
			}
		}
	}
}

add_action('init', 'coinsimple_callback');
