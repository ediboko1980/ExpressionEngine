<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2003 - 2015, EllisLab, Inc.
 * @license		https://ellislab.com/expressionengine/user-guide/license.html
 * @link		http://ellislab.com
 * @since		Version 2.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * ExpressionEngine CP Class
 *
 * @package		ExpressionEngine
 * @subpackage	Core
 * @category	Core
 * @author		EllisLab Dev Team
 * @link		http://ellislab.com
 */
class Cp {

	private $view;

	protected $its_all_in_your_head = array();
	protected $footer_item          = array();

	public $cp_theme             = '';
	public $cp_theme_url         = '';	// base URL to the CP theme folder
	public $installed_modules    = FALSE;
	public $requests             = array();
	public $loaded               = array();

	public $js_files = array(
		'ui'        => array(),
		'plugin'    => array(),
		'file'      => array(),
		'package'   => array(),
		'fp_module' => array()
	);

	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
		if (ee()->router->fetch_class() == 'ee')
		{
			show_error("The CP library is only available on Control Panel requests.");
		}

		// Cannot set these in the installer
		if ( ! defined('EE_APPPATH'))
		{
			$this->cp_theme	= ( ! ee()->session->userdata('cp_theme'))
				? ee()->config->item('cp_theme')
				: ee()->session->userdata('cp_theme');
			$this->cp_theme_url = ($this->cp_theme == 'default')
				? URL_THEMES.'cp_themes/default/'
				: URL_ADDONS_THEMES.'cp_themes/'.$this->cp_theme.'/';

			ee()->load->vars(array(
				'cp_theme_url' => $this->cp_theme_url
			));
		}

		// Make sure all requests to iframe the CP are denied
		ee()->output->set_header('X-Frame-Options: SameOrigin');
	}

	// --------------------------------------------------------------------

	/**
	 * Set Certain Default Control Panel View Variables
	 *
	 * @return	void
	 */
	public function set_default_view_variables()
	{
		$js_folder = (ee()->config->item('use_compressed_js') == 'n') ? 'src' : 'compressed';
		$langfile  = substr(ee()->router->class, 0, strcspn(ee()->router->class, '_'));

		// Javascript Path Constants

		define('PATH_JQUERY', PATH_THEMES.'javascript/'.$js_folder.'/jquery/');
		define('PATH_JAVASCRIPT', PATH_THEMES.'javascript/'.$js_folder.'/');
		define('JS_FOLDER', $js_folder);


		ee()->load->library('javascript', array('autoload' => FALSE));

		ee()->load->model('member_model'); // for screen_name, quicklinks

		ee()->lang->loadfile($langfile, '', FALSE);

		// Meta-refresh tag
		if ($refresh = ee()->session->flashdata('meta-refresh'))
		{
			ee()->view->set_refresh($refresh['url'], $refresh['rate']);
		}

		$cp_table_template = array(
			'table_open' => '<table class="mainTable" border="0" cellspacing="0" cellpadding="0">'
		);

		$cp_pad_table_template = array(
			'table_open' => '<table class="mainTable padTable" border="0" cellspacing="0" cellpadding="0">'
		);

		$user_q = ee()->member_model->get_member_data(
			ee()->session->userdata('member_id'),
			array(
				'screen_name', 'notepad', 'quick_links',
				'avatar_filename', 'avatar_width', 'avatar_height'
			)
		);

		$notepad_content = ($user_q->row('notepad')) ? $user_q->row('notepad') : '';

		// Global view variables

		$vars =	array(
			'cp_page_onload'		=> '',
			'cp_page_title'			=> '',
			'cp_breadcrumbs'		=> array(),
			'cp_right_nav'			=> array(),
			'cp_messages'			=> array(),
			'cp_notepad_content'	=> $notepad_content,
			'cp_table_template'		=> $cp_table_template,
			'cp_pad_table_template'	=> $cp_pad_table_template,
			'cp_theme_url'			=> $this->cp_theme_url,
			'cp_current_site_label'	=> ee()->config->item('site_name'),
			'cp_screen_name'		=> $user_q->row('screen_name'),
			'cp_avatar_path'		=> $user_q->row('avatar_filename') ? ee()->config->slash_item('avatar_url').$user_q->row('avatar_filename') : '',
			'cp_avatar_width'		=> $user_q->row('avatar_filename') ? $user_q->row('avatar_width') : '',
			'cp_avatar_height'		=> $user_q->row('avatar_filename') ? $user_q->row('avatar_height') : '',
			'cp_quicklinks'			=> $this->_get_quicklinks($user_q->row('quick_links')),

			'EE_view_disable'		=> FALSE,
			'is_super_admin'		=> (ee()->session->userdata['group_id'] == 1) ? TRUE : FALSE,	// for conditional use in view files
		);


		// global table data
		ee()->session->set_cache('table', 'cp_template', $cp_table_template);
		ee()->session->set_cache('table', 'cp_pad_template', $cp_pad_table_template);

		// we need these paths again in my account, so we'll keep track of them
		// kind of hacky, but before it was accessing _ci_cache_vars, which is worse

		ee()->session->set_cache('cp_sidebar', 'cp_avatar_path', $vars['cp_avatar_path'])
			->set_cache('cp_sidebar', 'cp_avatar_width', $vars['cp_avatar_width'])
			->set_cache('cp_sidebar', 'cp_avatar_height', $vars['cp_avatar_height']);

		// The base javascript variables that will be available globally through EE.varname
		// this really could be made easier - ideally it would show up right below the main
		// jQuery script tag - before the plugins, so that it has access to jQuery.

		// If you use it in your js, please uniquely identify your variables - or create
		// another object literal:
		// Bad: EE.test = "foo";
		// Good: EE.unique_foo = "bar"; EE.unique = { foo : "bar"};

		$js_lang_keys = array(
			'logout'				=> lang('logout'),
			'search'				=> lang('search'),
			'session_idle'			=> lang('session_idle'),
			'btn_fix_errors'		=> lang('btn_fix_errors'),
		);

		require_once(APPPATH.'libraries/El_pings.php');
		$pings = new El_pings();

		ee()->javascript->set_global(array(
			'BASE'             => str_replace(AMP, '&', BASE),
			'XID'              => CSRF_TOKEN,
			'CSRF_TOKEN'       => CSRF_TOKEN,
			'PATH_CP_GBL_IMG'  => PATH_CP_GBL_IMG,
			'CP_SIDEBAR_STATE' => ee()->session->userdata('show_sidebar'),
			'username'         => ee()->session->userdata('username'),
			'registered'       => $pings->is_registered(),
			'router_class'     => ee()->router->class, // advanced css
			'lang'             => $js_lang_keys,
			'THEME_URL'        => $this->cp_theme_url,
			'hasRememberMe'    => (bool) ee()->remember->exists()
		));

		// Combo-load the javascript files we need for every request

		$js_scripts = array(
			'ui'		=> array('core', 'widget', 'mouse', 'position', 'sortable', 'dialog', 'button'),
			'plugin'	=> array('ee_interact.event', 'ee_broadcast.event', 'ee_notice', 'ee_txtarea', 'tablesorter', 'ee_toggle_all'),
			'file'		=> array('json2', 'underscore', 'cp/global_start', 'cp/v3/form_validation')
		);

		if ($this->cp_theme != 'mobile')
		{
			$js_scripts['plugin'][] = 'ee_navigation';
		}

		$this->add_js_script($js_scripts);
		$this->_seal_combo_loader();

		ee()->load->vars($vars);
	}

	// --------------------------------------------------------------------

	/**
	 * Render output (html)
	 *
	 * @access public
	 * @return void
	 */
	public function render($view, $data = array(), $return = FALSE)
	{
		$this->_menu();

		$date_format = ee()->session->userdata('date_format', ee()->config->item('date_format'));

		if (ee()->config->item('new_version_check') == 'y' &&
			$new_version = $this->_version_check())
		{
			$new_version['version'] = $this->formatted_version($new_version['version']);
			$new_version['build'] = ee()->localize->format_date($date_format, $this->_parse_build_date($new_version['build']), TRUE);
			ee()->view->new_version = $new_version;
		}

		$this->_notices();

		ee()->view->formatted_version = $this->formatted_version(APP_VER);

		// add global end file
		$this->_seal_combo_loader();
		$this->add_js_script('file', 'cp/global_end');

		ee()->view->ee_build_date = ee()->localize->format_date($date_format, $this->_parse_build_date(), TRUE);

		return ee()->view->render($view, $data, $return);
	}

	/**
	 * Converts our build date constant into a timestamp so we can format it
	 * for display
	 *
	 * @param  string Build date in the format of yyyymmdd, uses APP_BUILD by default
	 * @return int Timestamp representing the build date
	 */
	protected function _parse_build_date($build = NULL)
	{
		if (empty($build))
		{
			$build = APP_BUILD;
		}

		$year = substr($build, 0, 4);
		$month = substr($build, 4, 2);
		$day = substr($build, 6, 2);

		$string = $year . '-' . $month . '-' . $day;

		return ee()->localize->string_to_timestamp($string, TRUE, '%Y-%m-%d');
	}

	// --------------------------------------------------------------------

	/**
	 * Takes an app version string and formats it for the CP, which entails
	 * putting bold tags around the first number and dropping the third
	 * digit if it is a zero
	 *
	 * @param	string	$version	App version string, like 3.0.0
	 * @return	string	Formatted app version string, like <b>3</b>.0
	 */
	public function formatted_version($version)
	{
		$version = explode('.', $version);

		// Drop the last zero if the version number is 3 digits (there might
		// be regex to do this as well)
		if (count($version == 3) && $version[2] == '0')
		{
			unset($version[2]);
		}

		return preg_replace('/^(\d)\./', '<b>$1</b>.', implode('.', $version));
	}

	// --------------------------------------------------------------------

	/**
	 * Load up the menu for our view
	 *
	 * @access public
	 * @return void
	 */
	protected function _menu()
	{
		if (ee()->view->disabled('ee_menu'))
		{
			return;
		}

		ee()->view->cp_main_menu = ee()->menu->generate_menu();
	}

	// --------------------------------------------------------------------

	/**
	 * Run a number of checks/tests and display any notices
	 *
	 * @return void
	 */
	protected function _notices()
	{
		$alert = $this->_checksum_bootstrap_files();

		// These are only displayed to Super Admins
		if (ee()->session->userdata['group_id'] != 1)
		{
			return;
		}

		$notices = array();

		// Show a notice if the cache folder is not writeable
		if ( ! ee()->cache->file->is_supported())
		{
			$notices[] = lang('unwritable_cache_folder');
		}

		// Show a notice if the config file is not writeable
		if ( ! is_really_writable(ee()->config->config_path))
		{
			$notices[] = lang('unwritable_config_file');
		}

		// Check to see if the config file matches the Core version constant
		if (APP_VER !== ee()->config->item('app_version'))
		{
			$notices[] = sprintf(lang('version_mismatch'), ee()->config->item('app_version'), APP_VER);
		}

		if ( ! empty($notices))
		{
			if ( ! $alert)
			{
				$alert = ee('Alert')->makeBanner('notices')
					->asWarning()
					->withTitle(lang('cp_message_warn'))
					->now();
			}
			else
			{
				$alert->addSeparator();
			}

			$last = end($notices);
			reset($notices);
			foreach ($notices as $notice)
			{
				$alert->addToBody($notice);

				if ($notice != $last)
				{
					$alert->addSeparator();
				}
			}
		}
	}

	/**
	 * Bootstrap Checksum Validation
	 *
	 * Creates a checksum for our bootstrap files and checks their
	 * validity with the database
	 *
	 * @return Alert|null NULL if everything is alright, otherwise an Alert object
	 */
	protected function _checksum_bootstrap_files()
	{
		// Checksum Validation
		ee()->load->library('file_integrity');
		$changed = ee()->file_integrity->check_bootstrap_files();
		$checksum_alert = NULL;

		if ($changed)
		{
			// Email the webmaster - if he isn't already looking at the message
			if (ee()->session->userdata('email') != ee()->config->item('webmaster_email'))
			{
				ee()->file_integrity->send_site_admin_warning($changed);
			}

			if (ee()->session->userdata('group_id') == 1)
			{
				$alert = ee('Alert')->makeStandard('notices')
					->asWarning()
					->withTitle(lang('cp_message_warn'))
					->addToBody(lang('checksum_changed_warning'))
					->addToBody($changed);

				$button = form_open(ee('CP/URL', 'homepage/accept_checksums'), '', array('return' => base64_encode(ee()->cp->get_safe_refresh())));
				$button .= '<input class="btn submit" type="submit" value="' . lang('checksum_changed_accept') . '">';
				$button .= form_close();

				$alert->addToBody($button);

				return $alert->now();
			}
		}

		return NULL;
	}


	/**
	 * EE Version Check function
	 *
	 * Requests a file from ExpressionEngine.com that informs us what the current available version
	 * of ExpressionEngine.
	 *
	 * @return	bool|string
	 */
	protected function _version_check()
	{
		ee()->load->library('el_pings');
		$version_file = ee()->el_pings->get_version_info();

		if ( ! $version_file)
		{
			ee('Alert')->makeBanner('notices')
				->asWarning()
				->withTitle(lang('cp_message_warn'))
				->addToBody(sprintf(
					lang('new_version_error'),
					ee()->cp->masked_url('https://store.ellislab.com/manage')
				))
				->now();
			return FALSE;
		}

		$version_info = array(
			'version' => $version_file[0][0],
			'build' => $version_file[0][1],
			'security' => $version_file[0][2] == 'high',
		);

		if (version_compare($version_info['version'], APP_VER) < 1)
		{
			return FALSE;
		}

		return $version_info;
	}

	// --------------------------------------------------------------------

	/**
	 * Mask URL.
	 *
	 * To be used to create url's that "mask" the real location of the
	 * users control panel.  Eg:  http://example.com/index.php?URL=http://example2.com
	 *
	 * @param string	URL
	 * @return string	Masked URL
	 */
	public function masked_url($url)
	{
		return ee()->functions->fetch_site_index(0,0).QUERY_MARKER.'URL='.urlencode($url);
	}

	// --------------------------------------------------------------------

	/**
	 * Add JS Script
	 *
	 * Adds a javascript file to the javascript combo loader
	 *
	 * @param array - associative array of
	 */
	public function add_js_script($script = array(), $in_footer = TRUE)
	{
		if ( ! is_array($script))
		{
			if (is_bool($in_footer))
			{
				return FALSE;
			}

			$script = array($script => $in_footer);
			$in_footer = TRUE;
		}

		if ( ! $in_footer)
		{
			return $this->its_all_in_your_head = array_merge($this->its_all_in_your_head, $script);
		}

		foreach ($script as $type => $file)
		{
			if ( ! is_array($file))
			{
				$file = array($file);
			}

			if (array_key_exists($type, $this->js_files))
			{
				$this->js_files[$type] = array_merge($this->js_files[$type], $file);
			}
			else
			{
				$this->js_files[$type] = $file;
			}
		}

		return $this->js_files;
	}

	// --------------------------------------------------------------------

	/**
	 * Render Footer Javascript
	 *
	 * @return string
	 */
	public function render_footer_js()
	{
		$str = '';
		$requests = $this->_seal_combo_loader();

		foreach($requests as $req)
		{
			$str .= '<script type="text/javascript" charset="utf-8" src="'.BASE.AMP.'C=javascript'.AMP.'M=combo_load'.$req.'"></script>';
		}

		if (ee()->extensions->active_hook('cp_js_end') === TRUE)
		{
			$str .= '<script type="text/javascript" src="'.BASE.AMP.'C=javascript'.AMP.'M=load'.AMP.'file=ext_scripts"></script>';
		}

		return $str;
	}

	// --------------------------------------------------------------------

	/**
	 * Seal the current combo loader and reopen a new one.
	 *
	 * @access	private
	 * @return	array
	 */
	function _seal_combo_loader()
	{
		$str = '';
		$mtimes = array();

		$this->js_files = array_map('array_unique', $this->js_files);

		foreach ($this->js_files as $type => $files)
		{
			if (isset($this->loaded[$type]))
			{
				$files = array_diff($files, $this->loaded[$type]);
			}

			if (count($files))
			{
				$mtimes[] = $this->_get_js_mtime($type, $files);
				$str .= AMP.$type.'='.implode(',', $files);
			}
		}

		if ($str)
		{
			$this->loaded = array_merge_recursive($this->loaded, $this->js_files);

			$this->js_files = array(
					'ui'				=> array(),
					'plugin'			=> array(),
					'file'				=> array(),
					'package'			=> array(),
					'fp_module'			=> array()
			);

			$this->requests[] = $str.AMP.'v='.max($mtimes);
		}

		return $this->requests;
	}

	// --------------------------------------------------------------------

	/**
	 * Get last modification time of a js file.
	 * Returns highest if passed an array.
	 *
	 * @param	string
	 * @param	mixed
	 * @return	int
	 */
	public function _get_js_mtime($type, $name)
	{
		if (is_array($name))
		{
			$mtimes = array();

			foreach($name as $file)
			{
				$mtimes[] = $this->_get_js_mtime($type, $file);
			}

			return max($mtimes);
		}

		$folder = ee()->config->item('use_compressed_js') == 'n' ? 'src' : 'compressed';

		switch($type)
		{
			case 'ui':			$file = PATH_THEMES.'javascript/'.$folder.'/jquery/ui/jquery.ui.'.$name.'.js';
				break;
			case 'plugin':		$file = PATH_THEMES.'javascript/'.$folder.'/jquery/plugins/'.$name.'.js';
				break;
			case 'file':		$file = PATH_THEMES.'javascript/'.$folder.'/'.$name.'.js';
				break;
			case 'package':		$file = PATH_THIRD.$name.'/javascript/'.$name.'.js';
				break;
			case 'fp_module':	$file = PATH_ADDONS.$name.'/javascript/'.$name.'.js';
				break;
			default:
				return 0;
		}

		return file_exists($file) ? filemtime($file) : 0;
	}

	// --------------------------------------------------------------------

	/**
	 * Set the right navigation
	 *
	 * @param	array
	 * @param	string
	 * @return	int
	 */
	public function set_right_nav($nav = array())
	{
		ee()->view->cp_right_nav = array_reverse($nav);
	}

	// --------------------------------------------------------------------

	/**
	 * Set the in-header navigation
	 *
	 * @param	array
	 * @param	string
	 * @return	int
	 */
	public function set_action_nav($nav = array())
	{
		ee()->view->cp_action_nav = array_reverse($nav);
	}

	// --------------------------------------------------------------------

	/**
	 * URL to the current page unless POST data exists - in which case it
	 * goes to the root controller.  To use the result, prefix it with BASE.AMP
	 *
	 * @return	string
	 */
	public function get_safe_refresh()
	{
		static $url = '';

		if ( ! $url)
		{
			// We have 2 types of URLs:
			//   1. index.php?/cp/path/to/controller/with/arugments
			//   2. index.php?D=cp&C=cp&M=homepage
			//
			// In the case of #1 we likely built it with ee('CP/URL', ) thus
			// we will store the needed parts to rebuild it.
			//
			// In the case of #2 we will build out a string to return

			$uri = ee()->uri->uri_string();
			if ($uri)
			{
				$args = array();
				foreach($_GET as $key => $val)
				{
					if ($key == 'S' OR $key == 'D' OR $key == 'C' OR $key == 'M')
					{
						continue;
					}

					// If a GET argument was POSTed, use that instead
					$args[$key] = (isset($_POST[$key])) ? $_POST[$key] : $val;
				}

				$url = json_encode(array('path' => $uri, 'arguments' => $args));
			}
			else
			{
				$go_to_c = (count($_POST) > 0);
				$page = '';

				foreach($_GET as $key => $val)
				{
					if ($key == 'S' OR $key == 'D' OR ($go_to_c && $key != 'C'))
					{
						continue;
					}

					$page .= $key.'='.$val.AMP;
				}

				if (strlen($page) > 4 && substr($page, -5) == AMP)
				{
					$page = substr($page, 0, -5);
				}

				$url = $page;
			}
		}

		return $url;
	}

	// --------------------------------------------------------------------

	/**
	 * Returns GET variables from the current CP URL. Useful for replicating
	 * the state of a view if you need to go away from it, POST requests for
	 * example.
	 *
	 * @return	array	GET array filtered of proprietary CP keys
	 */
	public function get_url_state()
	{
		$filtered_get = array_filter(array_keys($_GET), function ($key) {
			return ! in_array($key, array('D', 'C', 'S', 'M'));
		});

		return array_intersect_key($_GET, array_flip($filtered_get));
	}

	// --------------------------------------------------------------------

	/**
	 * 	Get Quicklinks
	 *
	 * 	Does a lookup for quick links.  Based on the URL we determine if it is external or not
	 *
	 * 	@return array
	 */
	private function _get_quicklinks($quick_links)
	{
		$i = 1;

		$quicklinks = array();

		if (count($quick_links) != 0 && $quick_links != '')
		{
			foreach (explode("\n", $quick_links) as $row)
			{
				$x = explode('|', $row);

				$quicklinks[$i]['title'] = (isset($x[0])) ? $x[0] : '';
				$quicklinks[$i]['link'] = (isset($x[1])) ? $x[1] : '';
				$quicklinks[$i]['order'] = (isset($x[2])) ? $x[2] : '';

				$i++;
			}
		}

		$quick_links = $quicklinks;

		$len = strlen(ee()->config->item('cp_url'));

		$link = array();

		$count = 0;

		foreach ($quick_links as $ql)
		{
			if (strncmp($ql['link'], ee()->config->item('cp_url'), $len) == 0)
			{
				$l = str_replace(ee()->config->item('cp_url'), '', $ql['link']);
				$l = preg_replace('/\?S=[a-zA-Z0-9]+&D=cp&/', '', $l);

				$link[$count] = array(
					'link'		=> BASE.AMP.$l,
					'title'		=> $ql['title'],
					'external'	=> FALSE
				);
			}
			else
			{
				$link[$count] = array(
					'link'		=> $ql['link'],
					'title'		=> $ql['title'],
					'external'	=> TRUE
				);
			}

			$count++;
		}

		return $link;
	}

	// --------------------------------------------------------------------

	/**
	 * Abstracted Way to Add a Breadcrumb Links
	 *
	 * @return	void
	 */
	public function set_breadcrumb($link, $title)
	{
		static $_crumbs = array();

		if (is_object($link))
		{
			$link = $link->compile();
		}

		$_crumbs[$link] = $title;
		ee()->view->cp_breadcrumbs = $_crumbs;
	}

	// --------------------------------------------------------------------

	/**
	 * Load Package JS
	 *
	 * Load a javascript file from a package
	 *
	 * @param	string
	 * @return	void
	 */
	public function load_package_js($file)
	{
		$current_top_path = ee()->load->first_package_path();
		$package = trim(str_replace(array(PATH_THIRD, 'views'), '', $current_top_path), '/');
		ee()->jquery->plugin(BASE.AMP.'C=javascript'.AMP.'M=load'.AMP.'package='.$package.AMP.'file='.$file, TRUE);
	}

	// --------------------------------------------------------------------

	/**
	 * Load Package CSS
	 *
	 * Load a stylesheet from a package
	 *
	 * @param	string
	 * @return	void
	 */
	public function load_package_css($file)
	{
		$current_top_path = ee()->load->first_package_path();
		$package = trim(str_replace(array(PATH_THIRD, 'views'), '', $current_top_path), '/');
		$url = BASE.AMP.'C=css'.AMP.'M=third_party'.AMP.'package='.$package.AMP.'theme='.$this->cp_theme.AMP.'file='.$file;

		$this->add_to_head('<link type="text/css" rel="stylesheet" href="'.$url.'" />');
	}

	// --------------------------------------------------------------------

	/**
	 * Add Header Data
	 *
	 * Add any string to the <head> tag
	 *
	 * @param	string
	 * @return	string
	 */
	public function add_to_head($data)
	{
		// Deprecated for scripts. Let's encourage good practices. This will
		// also let us move jquery in the future.
		if (strpos($data, '<script') !== FALSE)
		{
			ee()->load->library('logger');
			ee()->logger->deprecated('2.8', 'CP::add_to_foot() for scripts');
		}

		$this->its_all_in_your_head[] = $data;
	}

	// --------------------------------------------------------------------

	/**
	 * Returns the array of items to be added in the header
	 *
	 * @return array The array of items to be added in the header
	 */
	public function get_head()
	{
		return $this->its_all_in_your_head;
	}

	// --------------------------------------------------------------------

	/**
	 * Add Footer Data
	 *
	 * Add any string above the </body> tag
	 *
	 * @param	string
	 * @return	string
	 */
	public function add_to_foot($data)
	{
		$this->footer_item[] = $data;
	}

	// --------------------------------------------------------------------

	/**
	 * Returns the array of items to be added in the footer
	 *
	 * @return array The array of items to be added in the footer
	 */
	public function get_foot()
	{
		return $this->footer_item;
	}

	// --------------------------------------------------------------------

	/**
	 * Allowed Group
	 *
	 * Member access validation
	 *
	 * @param	string  any number of permission names
	 * @return	bool    TRUE if member has all permissions
	 */
	public function allowed_group()
	{
		$which = func_get_args();

		if ( ! count($which))
		{
			return FALSE;
		}

		// Super Admins always have access
		if (ee()->session->userdata('group_id') == 1)
		{
			return TRUE;
		}

		foreach ($which as $w)
		{
			$k = ee()->session->userdata($w);

			if ( ! $k OR $k !== 'y')
			{
				return FALSE;
			}
		}

		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Is Module Installed?
	 *
	 * Returns array of installed modules.
	 *
	 * @return array
	 */
	public function get_installed_modules()
	{
		if ( ! is_array($this->installed_modules))
		{
			$this->installed_modules = array();

			ee()->db->select('LOWER(module_name) AS name');
			ee()->db->order_by('module_name');
			$query = ee()->db->get('modules');

			if ($query->num_rows())
			{
				foreach($query->result_array() as $row)
				{
					$this->installed_modules[$row['name']] = $row['name'];
				}
			}
		}

		return $this->installed_modules;
	}

	// --------------------------------------------------------------------

	/**
	 * Invalid Custom Field Names
	 *
	 * Tracks "reserved" words to avoid variable name collision
	 *
	 * @return	array
	 */
	public function invalid_custom_field_names()
	{
		static $invalid_fields = array();

		if ( ! empty($invalid_fields))
		{
			return $invalid_fields;
		}

		$channel_vars = array(
			'aol_im', 'author', 'author_id', 'avatar_image_height',
			'avatar_image_width', 'avatar_url', 'bday_d', 'bday_m',
			'bday_y', 'bio', 'comment_auto_path',
			'comment_entry_id_auto_path',
			'comment_total', 'comment_url_title_path', 'count',
			'edit_date', 'email', 'entry_date', 'entry_id',
			'entry_id_path', 'expiration_date', 'forum_topic_id',
			'gmt_edit_date', 'gmt_entry_date', 'icq', 'interests',
			'ip_address', 'location', 'member_search_path', 'month',
			'msn_im', 'occupation', 'permalink', 'photo_image_height',
			'photo_image_width', 'photo_url', 'profile_path',
			'recent_comment_date', 'relative_date', 'relative_url',
			'screen_name', 'signature', 'signature_image_height',
			'signature_image_url', 'signature_image_width', 'status',
			'switch', 'title', 'title_permalink', 'total_results',
			'trimmed_url', 'url', 'url_as_email_as_link', 'url_or_email',
			'url_or_email_as_author', 'url_title', 'url_title_path',
			'username', 'channel', 'channel_id', 'yahoo_im', 'year'
		);

		$global_vars = array(
			'app_version', 'captcha', 'charset', 'current_time',
			'debug_mode', 'elapsed_time', 'email', 'embed', 'encode',
			'group_description', 'group_id', 'gzip_mode', 'hits',
			'homepage', 'ip_address', 'ip_hostname', 'lang', 'location',
			'member_group', 'member_id', 'member_profile_link', 'path',
			'private_messages', 'screen_name', 'site_index', 'site_name',
			'site_url', 'stylesheet', 'total_comments', 'total_entries',
			'total_forum_posts', 'total_forum_topics', 'total_queries',
			'username', 'webmaster_email', 'version'
		);

		$orderby_vars = array(
			'comment_total', 'date', 'edit_date', 'expiration_date',
			'most_recent_comment', 'random', 'screen_name', 'title',
			'url_title', 'username', 'view_count_four', 'view_count_one',
			'view_count_three', 'view_count_two'
		);

		$prefixes = array(
			'parents', 'siblings'
		);

		$control_structures = array(
			'if', 'else', 'elseif'
		);

		return array_unique(array_merge(
			$channel_vars,
			$global_vars,
			$orderby_vars,
			$prefixes,
			$control_structures
		));
	}

	// --------------------------------------------------------------------

	/**
	 * 	Fetch Action IDs
	 *
	 *	@param string
	 * 	@param string
	 *	@return mixed
	 */
	public function fetch_action_id($class, $method)
	{
		ee()->db->select('action_id');
		ee()->db->where('class', $class);
		ee()->db->where('method', $method);
		$query = ee()->db->get('actions');

		if ($query->num_rows() == 0)
		{
			return FALSE;
		}

		return $query->row('action_id');
	}

	// --------------------------------------------------------------------

	/**
	 * Site Switching Logic
	 *
	 * @param	int		$site_id	ID of site to switch to
	 * @param	string	$redirect	Optional URL to redirect to after site
	 * 								switching is successful
	 * @return	void
	 */
	public function switch_site($site_id, $redirect = '')
	{
		if (ee()->session->userdata('group_id') != 1)
		{
			ee()->db->select('can_access_cp');
			ee()->db->where('site_id', $site_id);
			ee()->db->where('group_id', ee()->session->userdata['group_id']);

			$query = ee()->db->get('member_groups');

			if ($query->num_rows() == 0 OR $query->row('can_access_cp') !== 'y')
			{
				show_error(lang('unauthorized_access'));
			}
		}

		if (empty($redirect))
		{
			$redirect = ee('CP/URL', 'homepage');
		}

		// We set the cookie before switching prefs to ensure it uses current settings
		ee()->input->set_cookie('cp_last_site_id', $site_id, 0);

		ee()->config->site_prefs('', $site_id);

		ee()->functions->redirect($redirect);
	}
}

/* End of file Cp.php */
/* Location: ./system/expressionengine/libraries/Cp.php */