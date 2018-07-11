<?php
/**
*
* Linked Accounts extension for phpBB 3.2
*
* @copyright (c) 2018 Flerex
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace flerex\linkedaccounts\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class main_listener implements EventSubscriberInterface
{

	const DEFAULT_POSTING_AS_VALUE = -1;

	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \flerex\linkedaccounts\service\utils */
	protected $utils;

	/** @var string */
	protected $root_path;

	/** @var string */
	protected $php_ext;

	/**
	 * Assign functions defined in this class to event listeners in the core
	 *
	 * @return array
	 */
	static public function getSubscribedEvents()
	{
		return array(
			'core.user_setup'							=> 'load_language_on_setup',
			'core.permissions'							=> 'add_permissions',
			'core.page_header'							=> 'add_switchable_accounts',
			'core.delete_user_after'					=> 'cleanup_table',
			'core.posting_modify_template_vars'			=> 'posting_as_template',
			'core.posting_modify_submit_post_after'		=> 'posting_as_logic',
			'core.posting_modify_submit_post_before'	=> 'posting_as_logic_before',
		);
	}

	/**
	 * Constructor
	 *
	 * @param \phpbb\auth\auth						$auth
	 * @param \phpbb\user							$user
	 * @param \phpbb\request\request				$request
	 * @param \phpbb\template\template				$template
	 * @param \phpbb\controller\helper				$helper
	 * @param \flerex\linkedaccounts\service\utils	$utils
	 */
	public function __construct(\phpbb\auth\auth $auth, \phpbb\user $user, \phpbb\request\request $request, \phpbb\template\template $template, \phpbb\controller\helper $helper, \flerex\linkedaccounts\service\utils $utils, $root_path, $php_ext)
	{
		$this->auth			= $auth;
		$this->user			= $user;
		$this->request		= $request;
		$this->template		= $template;
		$this->helper		= $helper;
		$this->utils		= $utils;
		$this->root_path	= $root_path;
		$this->php_ext		= $php_ext;
	}

	/**
	 * Load the Linked Accounts language file
	 *	 flerex/linkedaccounts/language/en/common.php
	 *
	 * @param \phpbb\event\data $event The event object
	 */
	public function load_language_on_setup($event)
	{

		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = array(
			'ext_name' => 'flerex/linkedaccounts',
			'lang_set' => 'common',
		);
		$event['lang_set_ext'] = $lang_set_ext;

	}

	/**
	 * Make phpBB aware of Linked Accounts' permissions
	 *
	 * @param \phpbb\event\data $event The event object
	 */
	public function add_permissions($event)
	{
		$permissions = $event['permissions'];
		$permissions['u_link_accounts']	= array('lang' => 'ACL_U_LINK_ACCOUNTS', 'cat' => 'profile');
		$permissions['u_post_as_account'] = array('lang' => 'ACL_U_POST_AS_ACCOUNT', 'cat' => 'post');
		$permissions['a_link_accounts'] = array('lang' => 'ACL_A_LINK_ACCOUNTS', 'cat' => 'user_group');
		$event['permissions'] = $permissions;
	}

	/**
	 * Create global variables with the switched accounts
	 * to be used on the template event
	 *
	 * @param \phpbb\event\data $event The event object
	 */
	public function add_switchable_accounts($event)
	{

		$this->template->assign_var('U_CAN_LINK_ACCOUNT', $this->auth->acl_get('u_link_accounts'));
		foreach($this->utils->get_linked_accounts() as $linked_account)
		{
			$this->template->assign_block_vars('switchable_account', array(
				'SWITCH_LINK'	=> $this->helper->route('flerex_linkedaccounts_switch', array('account_id' => $linked_account['user_id'])),
				'AVATAR'		=> phpbb_get_user_avatar($linked_account),
				'NAME'			=> get_username_string('no_profile', $linked_account['user_id'], $linked_account['username'], $linked_account['user_colour']),
			));
		}

	}

	/**
	 * Add the template variables necessary for
	 *  the posting as menu.
	 *
	 * @param \phpbb\event\data $event The event object
	 */
	public function posting_as_template($event)
	{

		// The user must have permission
		$post_as_permission = $this->auth->acl_get('u_post_as_account');
		$this->template->assign_var('U_CAN_POST_AS_ACCOUNT', $post_as_permission);
		if(!$post_as_permission) {
			return;
		}

		$linked_accounts = $this->utils->get_linked_accounts();

		// The user must have ownership over the post (if it's editing another account's post, the user should be linked to it)
		$can_change_author = $this->utils->can_change_author_of_post($event['post_id'], $linked_accounts);
		$this->template->assign_var('U_CAN_POST_AS_ACCOUNT', $can_change_author);
		if(!$can_change_author) {
			return;
		}

		$default_value = $event['mode'] == 'edit' ? (int) $event['post_data']['poster_id'] : $this->user->data['user_id'];
		$poster_id = $this->request->variable('posting_as', $default_value);

		$this->template->assign_block_vars('available_accounts', array(
			'ID'	=> $this->user->data['user_id'],
			'NAME'	=> get_username_string('username', $this->user->data['user_id'], $this->user->data['username'], $this->user->data['user_colour']),
			'ATTR'	=> $poster_id == $default_value ? ' selected' : '',
		));

		$is_first_post = $event['mode'] =='post' || ($event['mode'] == 'edit' && $event['post_data']['topic_first_post_id'] == $event['post_id']);

		$available_accounts = array_filter($linked_accounts, function($user) use (&$event, $is_first_post) {
			return $this->utils->can_switch_to($user['user_id']) && $this->utils->user_can_post_on_forum($user['user_id'], $event['post_data']['forum_id'], $event['mode'], $is_first_post);
		});

		// Don't show the “posting as” menu if you don't have any account link
		$this->template->assign_var('U_CAN_POST_AS_ACCOUNT', count($available_accounts) > 0);

		foreach($available_accounts as $account)
		{
			$this->template->assign_block_vars('available_accounts', array(
				'ID'	=> $account['user_id'],
				'NAME'	=> get_username_string('username', $account['user_id'], $account['username'], $account['user_colour']),
				'ATTR'	=> $poster_id == $account['user_id'] ? ' selected' : '',
			));
		}
	}


	/**
	 * Inject in the posting procedure whether the post
	 * should be set to be reapproved.
	 *
	 * For implementation reasons we are separating
	 * posting_as_logic in two events (before & after
	 * the posting procedure) to take advantage of
	 * the posting procedure's ability to change the post
	 * status (i.e. to set it back to be approved) when 
	 * editing or replying.
	 * 
	 * @param \phpbb\event\data $event The event object
	 */
	public function posting_as_logic_before($event)
	{

		$default_value = $event['mode'] == 'edit' ? (int) $event['data']['poster_id'] : self::DEFAULT_POSTING_AS_VALUE;
		$poster_id = $this->request->variable('posting_as', $default_value);

		$is_first_post = $event['data']['topic_first_post_id'] == 0 || $event['data']['topic_first_post_id'] == $event['data']['post_id'];

		$needs_approval = false;
		if(!$this->auth->acl_get('u_post_as_account') // user must have permissions
			|| $poster_id == $default_value // “poster as” should be changed
			|| !$this->utils->can_change_author_of_post_by_user($poster_id) // the new account of the post must be linked to the user
			|| !$this->utils->can_switch_to($poster_id) // the new account should be loggin-able (not banned, inactive, etc.)
			|| !$this->utils->user_can_post_on_forum($poster_id, $event['data']['forum_id'], $event['mode'], $is_first_post, $needs_approval) // check whether the other user can post or reply in the forum depending if we're editing, replying or posting.
		)
		{

			/*
			
				Most of these checks are very costly (they run several SQL queries under the hood) so
				in order to avoid running them all again in the continuation of this method, we will
				use the $data variable that is shared between both events in order to tell the other event
				whether the checks failed or not.

			 */

			$data = $event['data'];
			$data['flerex_linkedaccounts_cannot_continue'] = true;
			$event['data'] = $data;

 			return;
		}

		if($needs_approval)
		{
			$data = $event['data'];
			$data['post_visibility'] = $event['mode'] == 'edit' ? ITEM_REAPPROVE : ITEM_UNAPPROVED;
			$event['data'] = $data;
		}
	}

	/**
	 * Implement the logic behind the “posting
	 * as” menu.
	 *
	 * We need to implement all of the logic
	 * regarding the switching posting as phpBB's
	 * native posting procedure has the current
	 * user hardcoded in the method. This implies
	 * that we have to change the author AFTER
	 * posting, which also means that we have to
	 * make all the corresponding checks (whether
	 * the user has permissions, etc.).
	 *
	 * @param \phpbb\event\data $event The event object
	 */
	public function posting_as_logic($event)
	{

		if(isset($event['data']['flerex_linkedaccounts_cannot_continue']))
		{
			return;
		}

		/* We know that if we can continue, “posting_as” will
			already be filtered and we don't need to worry
			about having to compute the correct default value,
			so we set it to DEFAULT_POSTING_AS_VALUE (could be
			anything).
		*/
		$poster_id = $this->request->variable('posting_as', self::DEFAULT_POSTING_AS_VALUE);

		if (!function_exists('change_poster'))
		{
			// needed for phpbb_get_post_data() (which is needed for change_poster)
			include($this->root_path . 'includes/functions_mcp.' . $this->php_ext);
			// needed for sync() (called inside change_poster)
			include($this->root_path . 'includes/functions_admin.' . $this->php_ext);
			// needed for change_poster()
			include($this->root_path . 'includes/mcp/mcp_post.' . $this->php_ext);
		}

		$created_post = $event['data']['post_id'];
		$post_info = phpbb_get_post_data(array($created_post), false, true)[$created_post];
		$user_info = $this->utils->get_user($poster_id);

		change_poster($post_info, $user_info);

	}

	/**
	 * Remove all links of a user when it is being deleted
	 *
	 * @param \phpbb\event\data $event The event object
	 */
	public function cleanup_table($event)
	{
		$this->utils->remove_links($event['user_ids']);
	}
}