<?php
/*
	FusionPBX API
	Version: MPL 1.1

	The contents of this file are subject to the Mozilla Public License Version
	1.1 (the "License"); you may not use this file except in compliance with
	the License. You may obtain a copy of the License at
	http://www.mozilla.org/MPL/

	Software distributed under the License is distributed on an "AS IS" basis,
	WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	for the specific language governing rights and limitations under the
	License.

	The Original Code is FusionPBX

	The Initial Developer of the Original Code is
	Mark J Crane <markjcrane@fusionpbx.com>
	Portions created by the Initial Developer are Copyright (C) 2008-2025
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

//includes files
	require_once dirname(__DIR__) . "/resources/require.php";

//set content type to JSON
	header('Content-Type: application/json');

//get the rewrite URI from the query string
	$rewrite_uri = $_GET['rewrite_uri'] ?? '';

//parse the URI to get endpoint and parameters
	$uri_parts = explode('/', trim($rewrite_uri, '/'));
	$endpoint = $uri_parts[0] ?? '';
	$resource_id = $uri_parts[1] ?? '';

//get HTTP method
	$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

//route to the appropriate endpoint
	switch ($endpoint) {
		case 'users':
			require_once __DIR__ . '/users.php';
			break;
		case '':
			// API root - return available endpoints
			echo json_encode([
				'status' => 'success',
				'message' => 'FusionPBX API',
				'endpoints' => [
					'/api/users' => 'User management (POST to create)'
				]
			]);
			break;
		default:
			http_response_code(404);
			echo json_encode([
				'status' => 'error',
				'message' => 'Endpoint not found',
				'endpoint' => $endpoint
			]);
			break;
	}

?>
