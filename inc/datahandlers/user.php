<?php
/**
 * MyBB 1.2
 * Copyright � 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

/**
 * User handling class, provides common structure to handle user data.
 *
 */
class UserDataHandler extends DataHandler
{
	/**
	* The language file used in the data handler.
	*
	* @var string
	*/
	var $language_file = 'datahandler_user';

	/**
	* The prefix for the language variables used in the data handler.
	*
	* @var string
	*/
	var $language_prefix = 'userdata';
	
	/**
	 * Array of data inserted in to a user.
	 *
	 * @var array
	 */
	var $user_insert_data = array();

	/**
	 * Array of data used to update a user.
	 *
	 * @var array
	 */
	var $user_update_data = array();
	
	/**
	 * User ID currently being manipulated by the datahandlers.
	 *
	 * @var int
	 */
	var $uid = 0;	

	/**
	 * Verifies if a username is valid or invalid.
	 *
	 * @param boolean True when valid, false when invalid.
	 */
	function verify_username()
	{
		global $mybb;
		
		$username = &$this->data['username'];
		require_once MYBB_ROOT.'inc/functions_user.php';

		// Fix bad characters
		$username = str_replace(array(chr(160), chr(173)), array(" ", "-"), $username);

		// Remove multiple spaces from the username
		$username = preg_replace("#\s{2,}#", " ", $username);

		// Check if the username is not empty.
		if(trim($username) == '')
		{
			$this->set_error('missing_username');
			return false;
		}

		// Check if the username belongs to the list of banned usernames.
		$bannedusernames = get_banned_usernames();
		if(in_array($username, $bannedusernames))
		{
			$this->set_error('banned_username');
			return false;
		}

		// Check for certain characters in username (<, >, &, and slashes)
		if(eregi("<", $username) || eregi(">", $username) || eregi("&", $username) || strpos($username, "\\") !== false || eregi(";", $username))
		{
			$this->set_error("bad_characters_username");
			return false;
		}

		// Check if the username is of the correct length.
		if(($mybb->settings['maxnamelength'] != 0 && my_strlen($username) > $mybb->settings['maxnamelength']) || ($mybb->settings['minnamelength'] != 0 && my_strlen($username) < $mybb->settings['minnamelength']) && !$bannedusername && !$missingname)
		{
			$this->set_error('invalid_username_length', array($mybb->settings['minnamelength'], $mybb->settings['maxnamelength']));
			return false;
		}

		return true;
	}

	/**
	 * Verifies if a username is already in use or not.
	 *
	 * @return boolean False when the username is not in use, true when it is.
	 */
	function verify_username_exists()
	{
		global $db;

		$username = &$this->data['username'];

		$query = $db->simple_select(TABLE_PREFIX."users", "COUNT(uid) AS count", "LOWER(username)='".$db->escape_string(strtolower($username))."'");
		$user_count = $db->fetch_field($query, "count");
		if($user_count > 0)
		{
			$this->set_error("username_exists", array($username));
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	* Verifies if a new password is valid or not.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_password()
	{
		global $mybb;

		$user = &$this->data;

		// Always check for the length of the password.
		if(my_strlen($user['password']) < $mybb->settings['minpasswordlength'])
		{
			$this->set_error('invalid_password_length', array($mybb->settings['minpasswordlength'], $mybb->settings['maxpasswordlength']));
			return false;
		}

		// See if the board has "require complex passwords" enabled.
		if($mybb->settings['requirecomplexpasswords'] == "yes")
		{
			// Complex passwords required, do some extra checks.
			// First, see if there is one or more complex character(s) in the password.
			if(!preg_match('#[\W]+#', $user['password']))
			{
				$this->set_error('no_complex_characters');
				return false;
			}
		}

		// If we have a "password2" check if they both match
		if(isset($user['password2']) && $user['password'] != $user['password2'])
		{
			$this->set_error("passwords_dont_match");
			return false;
		}

		// MD5 the password
		$user['md5password'] = md5($user['password']);

		// Generate our salt
		if(!$user['salt'])
		{
			$user['salt'] = generate_salt();
		}

		// Combine the password and salt
		$user['saltedpw'] = salt_password($user['md5password'], $user['salt']);

		// Generate the user login key
		$user['loginkey'] = generate_loginkey();

		return true;
	}

	/**
	* Verifies usergroup selections and other group details.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_usergroup()
	{
		$user = &$this->data;
		return true;
	}
	/**
	* Verifies if an email address is valid or not.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_email()
	{
		global $mybb;

		$user = &$this->data;

		// Check if an email address has actually been entered.
		if(trim($user['email']) == '')
		{
			$this->set_error('missing_email');
			return false;
		}

		// Check if this is a proper email address.
		if(validate_email_format($user['email']) === false)
		{
			$this->set_error('invalid_email_format');
			return false;
		}

		// Check banned emails
		$bannedemails = explode(" ", $mybb->settings['bannedemails']);
		if(is_array($bannedemails))
		{
			foreach($bannedemails as $bannedemail)
			{
				$bannedemail = strtolower(trim($bannedemail));
				if($bannedemail != '')
				{
					if(strstr($user['email'], $bannedemail) != '')
					{
						$this->set_error('banned_email');
						return false;
					}
				}
			}
		}

		// If we have an "email2", verify it matches the existing email
		if(isset($user['email2']) && $user['email'] != $user['email2'])
		{
			$this->set_error("emails_dont_match");
			return false;
		}
	}

	/**
	* Verifies if a website is valid or not.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_website()
	{
		$website = &$this->data['website'];

		if($website == '' || $website == 'http://')
		{
			$website = '';
			return true;
		}

		// Does the website start with http://?
		if(substr_count($website, 'http://') == 0)
		{
			// Website does not start with http://, let's see if the user forgot.
			$website = 'http://'.$website;
			if(substr_count($website, 'http://') == 0)
			{
				$this->set_error('invalid_website');
				return false;
			}
		}

		return true;
	}

	/**
	 * Verifies if an ICQ number is valid or not.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_icq()
	{
		$icq = &$this->data['icq'];

		if($icq != '' && !is_numeric($icq))
		{
			$this->set_error("invalid_icq_number");
			return false;
		}
		$icq = intval($icq);
		return true;
	}

	/**
	 * Verifies if an MSN Messenger address is valid or not.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_msn()
	{
		$msn = &$this->data['msn'];

		if($msn != '' && validate_email_format($msn) == false)
		{
			$this->set_error("invalid_msn_address");
			return false;
		}
		return true;
	}

	/**
	* Verifies if a birthday is valid or not.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_birthday()
	{
		$user = &$this->data;
		$birthday = &$user['birthday'];

		if(!is_array($birthday))
		{
			return true;
		}

		// Sanitize any input we have
		$birthday['day'] = intval($birthday['day']);
		$birthday['month'] = intval($birthday['month']);
		$birthday['year'] = intval($birthday['year']);

		// Error if a day and month exists, and the birthday day and range is not in range
		if($birthday['day'] && $birthday['month'])
		{ 
			if($birthday['day'] < 1 || $birthday['day'] > 31 || $birthday['month'] < 1 || $birthday['month'] > 12 || ($birthday['month'] == 2 && $birthday['day'] > 29))
			{
				$this->set_error("invalid_birthday");
				return false;
			}

			// Check if the day actually exists.
			$months = get_bdays($birthday['year']);
			if($birthday['day'] > $months[$birthday['month']-1])
			{
				$this->set_error("invalid_birthday");
				return false;
			}
		}
				
		// Error if a year exists and the year is out of range
		if($birthday['year'] != 0 && ($birthday['year'] < (date("Y")-100)) || $birthday['year'] > date("Y"))
		{ 
			$this->set_error("invalid_birthday");
			return false;
		}
		
		// Make the user's birthday field
		if($birthday['year'] != 0)
		{
			// If the year is specified, put together a d-m-y string
			$user['bday'] = $birthday['day']."-".$birthday['month']."-".$birthday['year'];
		}
		elseif($birthday['day'] && $birthday['month'])
		{
			// If only a day and month are specified, put together a d-m string
			$user['bday'] = $birthday['day']."-".$birthday['month']."-";
		}
		else
		{
			// No field is specified, so return an empty string for an unknown birthday
			$user['bday'] = '';
		}
		return true;
	}

	/**
	* Verifies if a profile fields are filled in correctly.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_profile_fields()
	{
		global $db;

		$user = &$this->data;
		$profile_fields = &$this->data['profile_fields'];

		// Loop through profile fields checking if they exist or not and are filled in.
		$userfields = array();
		$comma = '';
		$editable = '';
		
		if(!$this->data['profile_fields_editable'])
		{
			$editable = "editable='yes'";
		}

		// Fetch all profile fields first.
		$options = array(
			'order_by' => 'disporder'
		);
		$query = $db->simple_select(TABLE_PREFIX.'profilefields', 'name, type, fid, required', $editable, $options);

		// Then loop through the profile fields.
		while($profilefield = $db->fetch_array($query))
		{
			$profilefield['type'] = htmlspecialchars_uni($profilefield['type']);
			$thing = explode("\n", $profilefield['type'], "2");
			$type = trim($thing[0]);
			$field = "fid{$profilefield['fid']}";

			// If the profile field is required, but not filled in, present error.
			if(!$profile_fields[$field] && $profilefield['required'] == "yes" && !$proferror)
			{
				$this->set_error('missing_required_profile_field', array($profilefield['name']));
			}

			// Sort out multiselect/checkbox profile fields.
			$options = '';
			if(($type == "multiselect" || $type == "checkbox") && is_array($profile_fields[$field]))
			{
				$expoptions = explode("\n", $thing[1]);
				$expoptions = array_map('trim', $expoptions);
				foreach($profile_fields[$field] as $value)
				{
					if(!in_array($value, $expoptions))
					{
						$this->set_error('bad_profile_field_values', array($profilefield['name']));
					}
					if($options)
					{
						$options .= "\n";
					}
					$options .= $db->escape_string($value);
				}
			}
			else if($type == "select" || $type == "radio")
			{
				$expoptions = explode("\n", $thing[1]);
				$expoptions = array_map('trim', $expoptions);
				if(!in_array(htmlspecialchars_uni($profile_fields[$field]), $expoptions) && $profile_fields[$field] != "")
				{
					$this->set_error('bad_profile_field_values', array($profilefield['name']));
				}
				$options = $db->escape_string($profile_fields[$field]);
			}
			else
			{
				$options = $db->escape_string($profile_fields[$field]);
			}
			$user['user_fields'][$field] = $options;
		}

		return true;
	}

	/**
	* Verifies if an optionally entered referrer exists or not.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_referrer()
	{
		global $db;

		$user = &$this->data;

		// Does the referrer exist or not?
		if($mybb->settings['usereferrals'] == "yes" && $user['referrer'] != '')
		{
			$options = array(
				'limit' => 1
			);
			$query = $db->simple_select(TABLE_PREFIX.'users', 'uid', "username='".$db->escape_string($user['referrer'])."'", $options);
			$referrer = $db->fetch_array($query);
			if(!$referrer['uid'])
			{
				$this->set_error('invalid_referrer', array($user['referrer']));
				return false;
			}
		}
		$user['referrer_uid'] = $referrer['uid'];

		return true;
	}

	/**
	* Verifies user options.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_options()
	{
		$options = &$this->data['options'];

		// Verify yes/no options.
		$this->verify_yesno_option($options, 'allownotices', 'yes');
		$this->verify_yesno_option($options, 'hideemail', 'no');
		$this->verify_yesno_option($options, 'emailnotify', 'no');
		$this->verify_yesno_option($options, 'receivepms', 'yes');
		$this->verify_yesno_option($options, 'pmpopup', 'yes');
		$this->verify_yesno_option($options, 'pmnotify', 'yes');
		$this->verify_yesno_option($options, 'invisible', 'no');
		$this->verify_yesno_option($options, 'remember', 'yes');
		$this->verify_yesno_option($options, 'dst', 'no');
		$this->verify_yesno_option($options, 'showsigs', 'yes');
		$this->verify_yesno_option($options, 'showavatars', 'yes');
		$this->verify_yesno_option($options, 'showquickreply', 'yes');
		$this->verify_yesno_option($options, 'showredirect', 'yes');

		if(isset($options['showcodebuttons']))
        {
            $options['showcodebuttons'] = intval($options['showcodebuttons']);
            if($options['showcodebuttons'] != 0)
            {
                $options['showcodebuttons'] = 1;
            }
        }
        else if($this->method == "insert")
        {
            $options['showcodebuttons'] = 1;
        }
		
		if($this->method == "insert" || (isset($options['threadmode']) && $options['threadmode'] != "threaded"))
		{
			$options['threadmode'] = 'linear';
		}

		// Verify the "threads per page" option.
		if($this->method == "insert" || (array_key_exists('tpp', $options) && $mybb->settings['usetppoptions']))
		{
			$explodedtpp = explode(",", $mybb->settings['usertppoptions']);
			if(is_array($explodedtpp))
			{
				@asort($explodedtpp);
				$biggest = $explodedtpp[count($explodedtpp)-1];
				// Is the selected option greater than the allowed options?
				if($options['tpp'] > $biggest)
				{
					$options['tpp'] = $biggest;
				}
			}
			$options['tpp'] = intval($options['tpp']);
		}
		// Verify the "posts per page" option.
		if($this->method == "insert" || (array_key_exists('ppp', $options) && $mybb->settings['userpppoptions']))
		{
			$explodedppp = explode(",", $mybb->settings['userpppoptions']);
			if(is_array($explodedppp))
			{
				@asort($explodedppp);
				$biggest = $explodedppp[count($explodedppp)-1];
				// Is the selected option greater than the allowed options?
				if($options['ppp'] > $biggest)
				{
					$options['ppp'] = $biggest;
				}
			}
			$options['ppp'] = intval($options['ppp']);
		}
		// Is our selected "days prune" option valid or not?
		if($this->method == "insert" || array_key_exists('daysprune', $options))
		{
			$options['daysprune'] = intval($options['daysprune']);
			if($options['daysprune'] < 0)
			{
				$options['daysprune'] = 0;
			}
		}
		$this->data['options'] = $options;
	}

	/**
	 * Verifies if a registration date is valid or not.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_regdate()
	{
		$regdate = &$this->data['regdate'];

		$regdate = intval($regdate);
		// If the timestamp is below 0, set it to the current time.
		if($regdate <= 0)
		{
			$regdate = time();
		}
		return true;

	}

	/**
	 * Verifies if a last visit date is valid or not.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_lastvisit()
	{
		$lastvisit = &$this->data['lastvisit'];

		$lastvisit = intval($lastvisit);
		// If the timestamp is below 0, set it to the current time.
		if($lastvisit <= 0)
		{
			$lastvisit = time();
		}
		return true;

	}

	/**
	 * Verifies if a last active date is valid or not.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_lastactive()
	{
		$lastactive = &$this->data['lastactive'];

		$lastactive = intval($lastactive);
		// If the timestamp is below 0, set it to the current time.
		if($lastactive <= 0)
		{
			$lastactive = time();
		}
		return true;

	}

	/**
	 * Verifies if an away mode status is valid or not.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_away()
	{
		global $mybb;

		$user = &$this->data;
		// If the board does not allow "away mode" or the user is marking as not away, set defaults.
		if($mybb->settings['allowaway'] == "no" || $user['away']['away'] != 'yes')
		{
			$user['away']['away'] = "no";
			$user['away']['date'] = 0;
			$user['away']['returndate'] = 0;
			$user['away']['reason'] = '';
			return true;
		}
		else if($user['away']['returndate'])
		{
			list($returnday, $returnmonth, $returnyear) = explode('-', $user['away']['returndate']);
			if(!$returnday || !$returnmonth || !$returnyear)
			{
				$this->set_error("missing_returndate");
			}
		}
	}

	/**
	 * Verifies if a langage is valid for this user or not.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_language()
	{
		global $lang;

		$language = &$this->data['language'];

		// An invalid language has been specified?
		if($language != '' && !$lang->language_exists($language))
		{
			$this->set_error("invalid_language");
			return false;
		}
		return true;
	}

	/**
	* Validate all user assets.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function validate_user()
	{
		global $mybb, $plugins;

		$user = &$this->data;

		// First, grab the old user details if this user exists
		if($user['uid'])
		{
			$old_user = get_user($user['uid']);
		}

		if($this->method == "insert" || array_key_exists('username', $user))
		{
			// If the username is the same - no need to verify
			if(!$old_user['username'] || $user['username'] != $old_user['username'])
			{
				$this->verify_username();
				$this->verify_username_exists();
			}
			else
			{
				unset($user['username']);
			}
		}
		if($this->method == "insert" || array_key_exists('password', $user))
		{
			$this->verify_password();
		}
		if($this->method == "insert" || array_key_exists('usergroup', $user))
		{
			$this->verify_usergroup();
		}
		if($this->method == "insert" || array_key_exists('email', $user))
		{
			$this->verify_email();
		}
		if($this->method == "insert" || array_key_exists('website', $user))
		{
			$this->verify_website();
		}
		if($this->method == "insert" || array_key_exists('icq', $user))
		{
			$this->verify_icq();
		}
		if($this->method == "insert" || array_key_exists('msn', $user))
		{
			$this->verify_msn();
		}
		if($this->method == "insert" || is_array($user['birthday']))
		{
			$this->verify_birthday();
		}
		if($this->method == "insert" || array_key_exists('profile_fields', $user))
		{
			$this->verify_profile_fields();
		}
		if($this->method == "insert" || array_key_exists('referrer', $user))
		{
			$this->verify_referrer();
		}
		if($this->method == "insert" || array_key_exists('options', $user))
		{
			$this->verify_options();
		}
		if($this->method == "insert" || array_key_exists('regdate', $user))
		{
			$this->verify_regdate();
		}
		if($this->method == "insert" || array_key_exists('lastvisit', $user))
		{
			$this->verify_lastvisit();
		}
		if($this->method == "insert" || array_key_exists('lastactive', $user))
		{
			$this->verify_lastactive();
		}
		if($this->method == "insert" || array_key_exists('away', $user))
		{
			$this->verify_away();
		}
		if($this->method == "insert" || array_key_exists('language', $user))
		{
			$this->verify_language();
		}
		
		$plugins->run_hooks_by_ref("datahandler_user_validate", $this);
		
		// We are done validating, return.
		$this->set_validated(true);
		if(count($this->get_errors()) > 0)
		{
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	* Inserts a user into the database.
	*/
	function insert_user()
	{
		global $db, $cache, $plugins;

		// Yes, validating is required.
		if(!$this->get_validated())
		{
			die("The user needs to be validated before inserting it into the DB.");
		}
		if(count($this->get_errors()) > 0)
		{
			die("The user is not valid.");
		}

		$user = &$this->data;

		$this->user_insert_data = array(
			"username" => $db->escape_string($user['username']),
			"password" => $user['saltedpw'],
			"salt" => $user['salt'],
			"loginkey" => $user['loginkey'],
			"email" => $db->escape_string($user['email']),
			"postnum" => intval($user['postnum']),
			"avatar" => $db->escape_string($user['avatar']),
			"avatartype" => $db->escape_string($user['avatartype']),
			"usergroup" => intval($user['usergroup']),
			"additionalgroups" => $db->escape_string($user['additionalgroups']),
			"displaygroup" => intval($user['displaygroup']),
			"usertitle" => $db->escape_string(htmlspecialchars_uni($user['usertitle'])),
			"regdate" => intval($user['regdate']),
			"lastactive" => intval($user['lastactive']),
			"lastvisit" => intval($user['lastvisit']),
			"website" => $db->escape_string(htmlspecialchars($user['website'])),
			"icq" => intval($user['icq']),
			"aim" => $db->escape_string(htmlspecialchars($user['aim'])),
			"yahoo" => $db->escape_string(htmlspecialchars($user['yahoo'])),
			"msn" => $db->escape_string(htmlspecialchars($user['msn'])),
			"birthday" => $user['bday'],
			"signature" => $db->escape_string($user['signature']),
			"allownotices" => $user['options']['allownotices'],
			"hideemail" => $user['options']['hideemail'],
			"emailnotify" => $user['options']['emailnotify'],
			"receivepms" => $user['options']['receivepms'],
			"pmpopup" => $user['options']['pmpopup'],
			"pmnotify" => $user['options']['emailpmnotify'],
			"remember" => $user['options']['remember'],
			"showsigs" => $user['options']['showsigs'],
			"showavatars" => $user['options']['showavatars'],
			"showquickreply" => $user['options']['showquickreply'],
			"showredirect" => $user['options']['showredirect'],
			"tpp" => intval($user['options']['tpp']),
			"ppp" => intval($user['options']['ppp']),
			"invisible" => $user['options']['invisible'],
			"style" => intval($user['style']),
			"timezone" => $db->escape_string($user['timezone']),
			"dst" => $user['options']['dst'],
			"threadmode" => $user['options']['threadmode'],
			"daysprune" => intval($user['options']['daysprune']),
			"dateformat" => $db->escape_string($user['dateformat']),
			"timeformat" => $db->escape_string($user['timeformat']),
			"regip" => $user['regip'],
			"language" => $db->escape_string($user['language']),
			"showcodebuttons" => $user['options']['showcodebuttons'],
			"away" => $user['away']['away'],
			"awaydate" => $user['away']['date'],
			"returndate" => $user['away']['returndate'],
			"awayreason" => $db->escape_string($user['away']['awayreason']),
			"notepad" => $db->escape_string($user['notepad']),
			"referrer" => intval($user['referrer_uid']),
			"buddylist" => '',
			"ignorelist" => '',
			"pmfolders" => '',
			"notepad" => ''
		);
		
		$plugins->run_hooks_by_ref("datahandler_user_insert", $this);
		
		$db->insert_query(TABLE_PREFIX."users", $this->user_insert_data);
		$this->uid = $db->insert_id();

		$user['user_fields'] = array(
			'ufid' => $this->uid
		);

		$query = $db->query("SHOW FIELDS FROM ".TABLE_PREFIX."userfields");
		while($field = $db->fetch_array($query))
		{
			if($field['Field'] == 'ufid')
			{
				continue;
			}
			$user['user_fields'][$field['Field']] = '';
		}

		$db->insert_query(TABLE_PREFIX."userfields", $user['user_fields']);

		// Update forum stats
		$cache->updatestats();

		return array(
			"uid" => $this->uid,
			"username" => $user['username'],
			"loginkey" => $user['loginkey'],
			"email" => $user['email'],
			"password" => $user['password'],
			"usergroup" => $user['usergroup']
		);
	}

	/**
	* Updates a user in the database.
	*/
	function update_user()
	{
		global $db, $plugins;


		// Yes, validating is required.
		if(!$this->get_validated())
		{
			die("The user needs to be validated before inserting it into the DB.");
		}
		if(count($this->get_errors()) > 0)
		{
			die("The user is not valid.");
		}

		$user = &$this->data;
		$user['uid'] = intval($user['uid']);
		$this->uid = $user['uid'];

		// Set up the update data.
		if(isset($user['username']))
		{
			$this->user_update_data['username'] = $db->escape_string($user['username']);
		}
		if(isset($user['saltedpw']))
		{
			$this->user_update_data['password'] = $user['saltedpw'];
			$this->user_update_data['salt'] = $user['salt'];
			$this->user_update_data['loginkey'] = $user['loginkey'];
		}
		if(isset($user['email']))
		{
			$this->user_update_data['email'] = $user['email'];
		}
		if(isset($user['postnum']))
		{
			$this->user_update_data['postnum'] = intval($user['postnum']);
		}
		if(isset($user['avatar']))
		{
			$this->user_update_data['avatar'] = $db->escape_string($user['avatar']);
			$this->user_update_data['avatartype'] = $db->escape_string($user['avatartype']);
		}
		if(isset($user['usergroup']))
		{
			$this->user_update_data['usergroup'] = intval($user['usergroup']);
		}
		if(isset($user['additionalgroups']))
		{
			$this->user_update_data['additionalgroups'] = $db->escape_string($user['additionalgroups']);
		}
		if(isset($user['displaygroup']))
		{
			$this->user_update_data['displaygroup'] = intval($user['displaygroup']);
		}
		if(isset($user['usertitle']))
		{
			$this->user_update_data['usertitle'] = $db->escape_string(htmlspecialchars_uni($user['usertitle']));
		}
		if(isset($user['regdate']))
		{
			$this->user_update_data['regdate'] = intval($user['regdate']);
		}
		if(isset($user['lastactive']))
		{
			$this->user_update_data['lastactive'] = intval($user['lastactive']);
		}
		if(isset($user['lastvisit']))
		{
			$this->user_update_data['lastvisit'] = intval($user['lastvisit']);
		}
		if(isset($user['signature']))
		{
			$this->user_update_data['signature'] = $db->escape_string($user['signature']);
		}
		if(isset($user['website']))
		{
			$this->user_update_data['website'] = $db->escape_string(htmlspecialchars($user['website']));
		}
		if(isset($user['icq']))
		{
			$this->user_update_data['icq'] = intval($user['icq']);
		}
		if(isset($user['aim']))
		{
			$this->user_update_data['aim'] = $db->escape_string(htmlspecialchars($user['aim']));
		}
		if(isset($user['yahoo']))
		{
			$this->user_update_data['yahoo'] = $db->escape_string(htmlspecialchars($user['yahoo']));
		}
		if(isset($user['msn']))
		{
			$this->user_update_data['msn'] = $db->escape_string(htmlspecialchars($user['msn']));
		}
		if(isset($user['bday']))
		{
			$this->user_update_data['birthday'] = $user['bday'];
		}
		if(isset($user['style']))
		{
			$this->user_update_data['style'] = intval($user['style']);
		}
		if(isset($user['timezone']))
		{
			$this->user_update_data['timezone'] = $db->escape_string($user['timezone']);
		}
		if(isset($user['dateformat']))
		{
			$this->user_update_data['dateformat'] = $db->escape_string($user['dateformat']);
		}
		if(isset($user['timeformat']))
		{
			$this->user_update_data['timeformat'] = $db->escape_string($user['timeformat']);
		}
		if(isset($user['regip']))
		{
			$this->user_update_data['regip'] = $db->escape_string($user['regip']);
		}
		if(isset($user['language']))
		{
			$this->user_update_data['language'] = $user['language'];
		}
		if(isset($user['away']))
		{
			$this->user_update_data['away'] = $user['away']['away'];
			$this->user_update_data['awaydate'] = $db->escape_string($user['away']['date']);
			$this->user_update_data['returndate'] = $db->escape_string($user['away']['returndate']);
			$this->user_update_data['awayreason'] = $db->escape_string($user['away']['awayreason']);
		}
		if(isset($user['notepad']))
		{
			$this->user_update_data['notepad'] = $db->escape_string($user['notepad']);
		}
		if(is_array($user['options']))
		{
			foreach($user['options'] as $option => $value)
			{
				$this->user_update_data[$option] = $value;
			}
		}

		// First, grab the old user details for later use.
		$old_user = get_user($user['uid']);

		$plugins->run_hooks_by_ref("datahandler_user_update", $this);

		if(count($this->user_update_data) < 1)
		{
			return false;
		}

		// Actual updating happens here.
		$db->update_query(TABLE_PREFIX."users", $this->user_update_data, "uid='{$user['uid']}'");

		// Maybe some userfields need to be updated?
		if(is_array($user['user_fields']))
		{
			$query = $db->simple_select(TABLE_PREFIX."userfields", "*", "ufid='{$user['uid']}'");
			$fields = $db->fetch_array($query);
			if(!$fields['ufid'])
			{
				$user_fields = array(
					'ufid' => $user['uid']
				);

				$query = $db->query("SHOW FIELDS FROM ".TABLE_PREFIX."userfields");
				while($field = $db->fetch_array($query))
				{
					if($field['Field'] == 'ufid')
					{
						continue;
					}
					$user_fields[$field['Field']] = '';
				}
				$db->insert_query(TABLE_PREFIX."userfields", $user_fields);
			}
			$db->update_query(TABLE_PREFIX."userfields", $user['user_fields'], "ufid='{$user['uid']}'");
		}

		// Let's make sure the user's name gets changed everywhere in the db if it changed.
		if($this->user_update_data['username'] != $old_user['username'] && $this->user_update_data['username'] != '')
		{
			$username_update = array(
				"username" => $this->user_update_data['username']
			);
			$lastposter_update = array(
				"lastposter" => $this->user_update_data['username']
			);

			$db->update_query(TABLE_PREFIX."posts", $username_update, "uid='{$user['uid']}'");
			$db->update_query(TABLE_PREFIX."threads", $username_update, "uid='{$user['uid']}'");
			$db->update_query(TABLE_PREFIX."threads", $lastposter_update, "lastposteruid='{$user['uid']}'");
			$db->update_query(TABLE_PREFIX."forums", $lastposter_update, "lastposteruid='{$user['uid']}'");
		}

	}
}
?>