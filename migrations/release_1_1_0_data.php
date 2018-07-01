<?php
/**
*
* Linked Accounts extension for phpBB 3.2
*
* @copyright (c) 2018 Flerex
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace flerex\linkedaccounts\migrations;

class release_1_1_0_data extends \phpbb\db\migration\migration
{

	static public function depends_on()
	{
		return array('\flerex\linkedaccounts\migrations\release_0_2_0_data');
	}

	/**
	 * Populate phpBB's tables with some needed
	 * data for Linked Accounts to work with
	 * features added on version 0.2.0
	 */
	public function update_data()
	{
		return array(

			// Create a new “can post as another accounts” permission
			array('permission.add', array('u_post_as_account')),
			// set it to yes in the Stantard User role and Registered user group
			array('permission.permission_set', array('REGISTERED', 'u_post_as_account', 'group')),
			array('permission.permission_set', array('ROLE_USER_STANDARD', 'u_post_as_account')),

		);
	}
}
