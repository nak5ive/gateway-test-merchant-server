<?php

/*
 * Copyright (c) 2016 Mastercard
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

error_reporting('all');

// pull environment vars
$merchantId = getenv('GATEWAY_MERCHANT_ID');
$password = getenv('GATEWAY_API_PASSWORD');
$baseUrl = getenv('GATEWAY_BASE_URL');
$apiVersion = getenv('GATEWAY_API_VERSION');

// merchant id must be TEST
//$merchantIdPrefix = substr($merchantId, 0, 4);
//if (strcasecmp($merchantIdPrefix, "test") != 0) {
//    error(500, 'Only TEST merchant IDs should be used with this software');
//}

// parse baseUrl and only keep original hostname
$baseUrlHost = parse_url($baseUrl, PHP_URL_HOST);
if (empty($baseUrlHost)) {
    error(500, 'Invalid gateway base url');
}

// build api endpoint url
$gatewayUrl = "https://$baseUrlHost/api/rest/version/$apiVersion/merchant/$merchantId";

// parae query string
$query = array();
parse_str($_SERVER['QUERY_STRING'], $query);

// build auth headers
$headers = array(
    'Content-type: application/json',
    'Authorization: Basic ' . base64_encode("merchant.$merchantId:$password")
);

// construct page url
$pageUrl = "http".(!empty($_SERVER['HTTPS'])?"s":"")."://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];

function intercept($method) {
    return strcasecmp($_SERVER['REQUEST_METHOD'], $method) == 0;
}

function doRequest($url, $method, $data = null, $headers = null) {
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    if (!empty($data)) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    }
    if (!empty($headers)) {
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    }
    $response = curl_exec($curl);
    curl_close($curl);

    return $response;
}

function error($code, $message) {
    http_response_code($code);
    print_r($message);
    exit;
}

function requiredQueryParam($param) {
    global $query;

    if (!array_key_exists($param, $query) || empty($query[$param])) {
        error(400, 'Missing required query param: ' . $param);
    }

    return $query[$param];
}

function getJsonPayload() {
    $input = file_get_contents('php://input');
    if (!empty($input)) {
        json_decode($input);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error(400, 'Could not parse json payload');
        }
    }

    return $input;
}

function outputJsonResponse($response) {
    header('Content-Type: application/json');
    print_r($response);
    exit;
}

function proxyCall($path) {
    global $headers, $gatewayUrl;

    // get json payload from request
    $payload = getJsonPayload();

    // proxy authenticated request
    $response = doRequest($gatewayUrl . $path, $_SERVER['REQUEST_METHOD'], $payload, $headers);

    // output response
    outputJsonResponse($response);
}
