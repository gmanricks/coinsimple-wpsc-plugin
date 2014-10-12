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

function bpCurl($url, $post = false)
{

	$curl = curl_init($url);

	$length = 0;

	if ($post) {
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $post);

		$length = strlen($post);
	}

	$header = array(
			'Content-Type: application/json',
			'Content-Length: ' . $length
			);

	curl_setopt($curl, CURLOPT_PORT, 443);
	curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
	curl_setopt($curl, CURLOPT_TIMEOUT, 10);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1); // verify certificate
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2); // check existence of CN and verify that it matches hostname
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);
	curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1);

	$responseString = curl_exec($curl);

	if($responseString == false) {
		$response = curl_error($curl);
	} else {
		$response = json_decode($responseString, true);
	}

	curl_close($curl);

	return $response;
}

function csCreateInvoice($orderId, $options = array(), $api_key)
{

	$time = time();
	$options['timestamp'] = $time;

	$options['hash'] = hash_hmac("sha256", $api_key, $time);

	$post = json_encode($options);

	$response = bpCurl('https://app.coinsimple.com/api/v1/invoice', $post);

	if (is_string($response)) {
		return array('error' => $response);
	}

	return $response;
}

// Call from your notification handler to convert $_POST data to an object containing invoice data
function csVerifyNotification($api_key = false)
{
	if (!$api_key) {
			return array('error' => 'No API key');
	}

	$post = file_get_contents("php://input");

	if (!$post) {
		return array('error' => 'No post data');
	}

	$json = json_decode($post, true);

	if (is_string($json)) {
		return array('error' => $json);
	}

	if (!array_key_exists('custom', $json) || !array_key_exists('timestamp', $json) || !array_key_exists('hash', $json)) {
		return array('error' => 'no identifier');
	}

	$hash = hash_hmac("sha256", $api_key, $json['timestamp']);

	if ($hash != $json['hash']) {
		return array('error' => 'authentication failed (bad hash)');
	}

	return $json;
}
