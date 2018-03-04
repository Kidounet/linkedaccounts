<?php

namespace flerex\linkedaccounts\ucp;

class main_info
{
	public function module()
	{
		return array(
			'filename'	=> '\flerex\linkedaccounts\ucp\main_module',
			'title'		=> 'LINKED_ACCOUNTS',
			'modes'		=> array(
				'management' => array(
					'title'	=> 'LINKED_ACCOUNTS_MANAGEMENT',
					'auth'	=> 'ext_flerex/linkedaccounts && acl_u_link_accounts',
					'cat'	=> array('LINKED_ACCOUNTS'),
				),
				'link' => array(
					'title'	=> 'LINKING_ACCOUNT',
					'auth'	=> 'ext_flerex/linkedaccounts && acl_u_link_accounts',
					'cat'	=> array('LINKED_ACCOUNTS'),
				),
			),
		);
	}
}
