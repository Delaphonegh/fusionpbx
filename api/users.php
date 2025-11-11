<?php
/*
	FusionPBX API - Users Endpoint
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

//check authentication
	if (empty($_SESSION['authorized']) || !$_SESSION['authorized']) {
		http_response_code(401);
		echo json_encode([
			'status' => 'error',
			'message' => 'Unauthorized - Authentication required'
		]);
		exit;
	}

//check permissions
	if (!permission_exists('user_add')) {
		http_response_code(403);
		echo json_encode([
			'status' => 'error',
			'message' => 'Forbidden - Insufficient permissions'
		]);
		exit;
	}

//get HTTP method
	$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

//handle different HTTP methods
	switch ($method) {
		case 'POST':
			// Create user
			create_user();
			break;
		case 'GET':
			http_response_code(405);
			echo json_encode([
				'status' => 'error',
				'message' => 'Method not allowed. Use POST to create a user.'
			]);
			break;
		default:
			http_response_code(405);
			echo json_encode([
				'status' => 'error',
				'message' => 'Method not allowed'
			]);
			break;
	}

/**
 * Create a new user
 */
function create_user() {
	global $database, $settings;

	//get JSON input
	$input = file_get_contents('php://input');
	$data = json_decode($input, true);

	//if JSON decode failed, try POST data
	if (json_last_error() !== JSON_ERROR_NONE) {
		$data = $_POST;
	}

	//validate required fields
	$required_fields = ['username', 'password', 'user_email', 'group_uuid'];
	$missing_fields = [];
	foreach ($required_fields as $field) {
		// Check both 'group_uuid' and 'group_uuid_name' for group
		if ($field === 'group_uuid') {
			if (empty($data['group_uuid']) && empty($data['group_uuid_name'])) {
				$missing_fields[] = $field;
			}
		} else {
			if (empty($data[$field])) {
				$missing_fields[] = $field;
			}
		}
	}

	if (!empty($missing_fields)) {
		http_response_code(400);
		echo json_encode([
			'status' => 'error',
			'message' => 'Missing required fields',
			'missing_fields' => $missing_fields
		]);
		exit;
	}

	//extract data
	$username = $data['username'];
	$password = $data['password'];
	$user_email = $data['user_email'];
	// Handle group_uuid - can be just UUID or "uuid|name" format
	$group_uuid_input = $data['group_uuid'] ?? $data['group_uuid_name'] ?? '';
	$domain_uuid = $data['domain_uuid'] ?? $_SESSION['domain_uuid'];
	$user_language = $data['user_language'] ?? '';
	$user_time_zone = $data['user_time_zone'] ?? '';
	$user_type = $data['user_type'] ?? 'user';
	$user_enabled = $data['user_enabled'] ?? 'true';
	$user_status = $data['user_status'] ?? '';
	$contact_organization = $data['contact_organization'] ?? '';
	$contact_name_given = $data['contact_name_given'] ?? '';
	$contact_name_family = $data['contact_name_family'] ?? '';

	//validate email format
	if (!valid_email($user_email)) {
		http_response_code(400);
		echo json_encode([
			'status' => 'error',
			'message' => 'Invalid email address format'
		]);
		exit;
	}

	//validate username format if required
	if (!empty($settings->get('users', 'username_format')) && $settings->get('users', 'username_format') != 'any') {
		if (
			($settings->get('users', 'username_format') == 'email' && !valid_email($username)) ||
			($settings->get('users', 'username_format') == 'no_email' && valid_email($username))
		) {
			http_response_code(400);
			echo json_encode([
				'status' => 'error',
				'message' => 'Username format is invalid. Expected format: ' . $settings->get('users', 'username_format')
			]);
			exit;
		}
	}

	//check if username already exists
	$sql = "select count(*) from v_users ";
	if (!empty($settings->get('users', 'unique')) && $settings->get('users', 'unique') == "global") {
		$sql .= "where username = :username ";
		$parameters['username'] = $username;
	} else {
		$sql .= "where username = :username ";
		$sql .= "and domain_uuid = :domain_uuid ";
		$parameters['username'] = $username;
		$parameters['domain_uuid'] = $domain_uuid;
	}
	$num_rows = $database->select($sql, $parameters, 'column');
	if ($num_rows > 0) {
		http_response_code(409);
		echo json_encode([
			'status' => 'error',
			'message' => 'Username already exists'
		]);
		exit;
	}
	unset($sql, $parameters);

	//check user limit if defined
	if (!empty($settings->get('limit', 'users'))) {
		$sql = "select count(*) ";
		$sql .= "from v_users ";
		$sql .= "where domain_uuid = :domain_uuid ";
		$parameters['domain_uuid'] = $domain_uuid;
		$user_count = $database->select($sql, $parameters, 'column');
		unset($sql, $parameters);

		if ($user_count >= $settings->get('limit', 'users')) {
			http_response_code(403);
			echo json_encode([
				'status' => 'error',
				'message' => 'Maximum user limit reached: ' . $settings->get('limit', 'users')
			]);
			exit;
		}
	}

	//validate password requirements
	$required = [];
	$required['length'] = $settings->get('users', 'password_length', 12);
	$required['number'] = $settings->get('users', 'password_number', false);
	$required['lowercase'] = $settings->get('users', 'password_lowercase', false);
	$required['uppercase'] = $settings->get('users', 'password_uppercase', false);
	$required['special'] = $settings->get('users', 'password_special', false);

	$password_errors = [];
	if (!empty($required['length']) && is_numeric($required['length']) && $required['length'] != 0) {
		if (strlen($password) < $required['length']) {
			$password_errors[] = 'Password must be at least ' . $required['length'] . ' characters';
		}
	}
	if ($required['number']) {
		if (!preg_match('/(?=.*[\d])/', $password)) {
			$password_errors[] = 'Password must contain at least one number';
		}
	}
	if ($required['lowercase']) {
		if (!preg_match('/(?=.*[a-z])/', $password)) {
			$password_errors[] = 'Password must contain at least one lowercase letter';
		}
	}
	if ($required['uppercase']) {
		if (!preg_match('/(?=.*[A-Z])/', $password)) {
			$password_errors[] = 'Password must contain at least one uppercase letter';
		}
	}
	if ($required['special']) {
		if (!preg_match('/(?=.*[\W])/', $password)) {
			$password_errors[] = 'Password must contain at least one special character';
		}
	}

	if (!empty($password_errors)) {
		http_response_code(400);
		echo json_encode([
			'status' => 'error',
			'message' => 'Password does not meet requirements',
			'password_errors' => $password_errors
		]);
		exit;
	}

	//validate user status
	switch ($user_status) {
		case "Available":
		case "Available (On Demand)":
		case "On Break":
		case "Do Not Disturb":
		case "Logged Out":
			break;
		default:
			$user_status = '';
	}

	//generate user UUID
	$user_uuid = uuid();

	//prepare data array
	$i = $n = $x = $c = 0;
	$array = [];

	//add user language setting if provided
	if (!empty($user_language)) {
		$array['user_settings'][$i]['user_setting_uuid'] = uuid();
		$array['user_settings'][$i]['user_uuid'] = $user_uuid;
		$array['user_settings'][$i]['domain_uuid'] = $domain_uuid;
		$array['user_settings'][$i]['user_setting_category'] = 'domain';
		$array['user_settings'][$i]['user_setting_subcategory'] = 'language';
		$array['user_settings'][$i]['user_setting_name'] = 'code';
		$array['user_settings'][$i]['user_setting_value'] = $user_language;
		$array['user_settings'][$i]['user_setting_enabled'] = 'true';
		$i++;
	}

	//add user time zone setting if provided
	if (!empty($user_time_zone)) {
		$array['user_settings'][$i]['user_setting_uuid'] = uuid();
		$array['user_settings'][$i]['user_uuid'] = $user_uuid;
		$array['user_settings'][$i]['domain_uuid'] = $domain_uuid;
		$array['user_settings'][$i]['user_setting_category'] = 'domain';
		$array['user_settings'][$i]['user_setting_subcategory'] = 'time_zone';
		$array['user_settings'][$i]['user_setting_name'] = 'name';
		$array['user_settings'][$i]['user_setting_value'] = $user_time_zone;
		$array['user_settings'][$i]['user_setting_enabled'] = 'true';
		$i++;
	}

	//assign user to group
	if (!empty($group_uuid_input)) {
		// Handle both "uuid" and "uuid|name" formats
		if (strpos($group_uuid_input, '|') !== false) {
			$group_data = explode('|', $group_uuid_input);
			$group_uuid = $group_data[0];
			$group_name = $group_data[1] ?? '';
		} else {
			$group_uuid = $group_uuid_input;
			$group_name = '';
		}

		//verify group exists and user has permission to assign it
		$sql = "select * from v_groups ";
		$sql .= "where (domain_uuid = :domain_uuid or domain_uuid is null) ";
		$sql .= "and group_uuid = :group_uuid ";
		$parameters['domain_uuid'] = $domain_uuid;
		$parameters['group_uuid'] = $group_uuid;
		$group_row = $database->select($sql, $parameters, 'row');
		unset($sql, $parameters);

		if (empty($group_row)) {
			http_response_code(400);
			echo json_encode([
				'status' => 'error',
				'message' => 'Group not found'
			]);
			exit;
		}

		if ($group_row['group_level'] > ($_SESSION['user']['group_level'] ?? 0)) {
			http_response_code(403);
			echo json_encode([
				'status' => 'error',
				'message' => 'Insufficient permissions to assign user to this group'
			]);
			exit;
		}

		$array['user_groups'][$n]['user_group_uuid'] = uuid();
		$array['user_groups'][$n]['domain_uuid'] = $domain_uuid;
		$array['user_groups'][$n]['group_name'] = $group_name ?: $group_row['group_name'];
		$array['user_groups'][$n]['group_uuid'] = $group_uuid;
		$array['user_groups'][$n]['user_uuid'] = $user_uuid;
		$n++;
	}

	//add contact if contact fields provided
	if (permission_exists('contact_add') && (!empty($contact_organization) || !empty($contact_name_given) || !empty($contact_name_family))) {
		$contact_uuid = uuid();
		$array['contacts'][$c]['domain_uuid'] = $domain_uuid;
		$array['contacts'][$c]['contact_uuid'] = $contact_uuid;
		$array['contacts'][$c]['contact_type'] = 'user';
		$array['contacts'][$c]['contact_organization'] = $contact_organization;
		$array['contacts'][$c]['contact_name_given'] = $contact_name_given;
		$array['contacts'][$c]['contact_name_family'] = $contact_name_family;
		$array['contacts'][$c]['contact_nickname'] = $username;
		$c++;

		if (permission_exists('contact_email_add')) {
			$contact_email_uuid = uuid();
			$array['contact_emails'][$c]['contact_email_uuid'] = $contact_email_uuid;
			$array['contact_emails'][$c]['domain_uuid'] = $domain_uuid;
			$array['contact_emails'][$c]['contact_uuid'] = $contact_uuid;
			$array['contact_emails'][$c]['email_address'] = $user_email;
			$array['contact_emails'][$c]['email_primary'] = '1';
			$c++;
		}
	}

	//set password hash
	$options = array('cost' => 10);

	//add user to array
	$array['users'][$x]['user_uuid'] = $user_uuid;
	$array['users'][$x]['domain_uuid'] = $domain_uuid;
	$array['users'][$x]['username'] = $username;
	$array['users'][$x]['password'] = password_hash($password, PASSWORD_DEFAULT, $options);
	$array['users'][$x]['salt'] = null;
	$array['users'][$x]['user_email'] = $user_email;
	$array['users'][$x]['user_status'] = $user_status;
	$array['users'][$x]['user_type'] = $user_type;
	$array['users'][$x]['user_enabled'] = $user_enabled;
	if (!empty($contact_uuid)) {
		$array['users'][$x]['contact_uuid'] = $contact_uuid;
	}
	$array['users'][$x]['add_user'] = $_SESSION["user"]["username"] ?? 'api';
	$array['users'][$x]['add_date'] = date("Y-m-d H:i:s.uO");
	$x++;

	//add temporary permissions
	$p = permissions::new();
	$p->add("user_setting_add", "temp");
	$p->add("user_edit", "temp");
	$p->add('user_group_add', 'temp');
	if (permission_exists('contact_add')) {
		$p->add('contact_add', 'temp');
	}
	if (permission_exists('contact_email_add')) {
		$p->add('contact_email_add', 'temp');
	}

	//save the data
	try {
		$database->save($array);
		$message = $database->message ?? [];

		//remove temporary permissions
		$p->delete("user_setting_add", "temp");
		$p->delete("user_edit", "temp");
		$p->delete('user_group_add', 'temp');
		if (permission_exists('contact_add')) {
			$p->delete('contact_add', 'temp');
		}
		if (permission_exists('contact_email_add')) {
			$p->delete('contact_email_add', 'temp');
		}

		//clear settings cache
		settings::clear_cache();

		//return success response
		http_response_code(201);
		echo json_encode([
			'status' => 'success',
			'message' => 'User created successfully',
			'user_uuid' => $user_uuid,
			'username' => $username,
			'user_email' => $user_email
		]);
	} catch (Exception $e) {
		//remove temporary permissions on error
		$p->delete("user_setting_add", "temp");
		$p->delete("user_edit", "temp");
		$p->delete('user_group_add', 'temp');
		if (permission_exists('contact_add')) {
			$p->delete('contact_add', 'temp');
		}
		if (permission_exists('contact_email_add')) {
			$p->delete('contact_email_add', 'temp');
		}

		http_response_code(500);
		echo json_encode([
			'status' => 'error',
			'message' => 'Failed to create user',
			'error' => $e->getMessage()
		]);
	}
}

?>
