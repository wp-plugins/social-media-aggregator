<?php

/*
Plugin Name: Social Media Aggregator
Plugin URI: http://www.invokemedia.com
Description: Aggregates social feeds from Facebook, Twitter, Instagram, YouTube, Vimeo, and RSS.
Version: 1.2
Author: Invoke Media
Author URI: http://www.invokemedia.com
*/

require_once ('vendor/autoload.php');
require_once ('classes/FacebookFeed.php');
require_once ('classes/TwitterFeed.php');
require_once ('classes/InstagramFeed.php');
require_once ('classes/YouTubeFeed.php');
require_once ('classes/VimeoFeed.php');
require_once ('classes/RssFeed.php');

// require_once ('helpers/save_external_img.php'); // not using yet..

class IM_Aggregator {

	protected $settings;
	protected $sources;
	protected $shortname;
	protected $prefix;
	protected $settings_slug;
	protected $sections;
	protected $taxonomy_id;

	public function __construct() {

		$this->sources = array('facebook', 'twitter', 'instagram', 'rss-0', 'rss-1', 'rss-2');

		$this->post_type = 'sa-socialfeed';
		$this->taxonomy_id = 'sa_tax_sources';
		$this->shortname = 'sa';
		$this->prefix = 'sa_';
		$this->settings_slug = 'sa-settings';
		$this->options_slug = 'sa-options';
		$this->sections = $this->get_sections();

		add_action('add_meta_boxes', array($this, 'load_custom_fields'));
		add_action('admin_menu', array($this, 'plugin_settings'));
		add_action('admin_init', array($this, 'register_settings'));
		add_action('init', array($this, '_init'));

		if (is_admin()) {
			// both front- and back-end ajax calls..
			add_action('wp_ajax_nopriv_get_feeds', array($this, 'get_feeds'));
			add_action('wp_ajax_get_feeds', array($this, 'get_feeds'));

			// ajax actions for admin only..
			add_action('wp_ajax_fetch_social_feeds', array($this, 'fetch_social_feeds'));
			add_action('wp_ajax_reset_since_times', array($this, 'reset_since_times'));
		}

		add_filter('cron_schedules', array($this, 'cron_add_minute'));
		add_action('run_cron', array($this, 'start_aggregation'));

		// helpers
		add_filter('sa/helpers/get_plugin_url', array($this, 'get_plugin_url'), 1, 1);

		$this->settings = array(
			'plugin_url' => apply_filters('sa/helpers/get_plugin_url', plugins_url('',__FILE__)),
			'version' => '1.2'
		);

		if (!defined('IMSA_LOAD_SCRIPTS')) define('IMSA_LOAD_SCRIPTS', true);

		// this action now gets added after checking to see if posts use the shortcode, no use loading
		// the css and js if this plugin isn't being used..
		if (IMSA_LOAD_SCRIPTS) {
			add_action('wp_enqueue_scripts', array($this, 'load_scripts'), 22); // 22 sets the priority order, default is 10, lower number = earlier..
		}

		// register plugin activation and deactivation hooks..
		register_activation_hook(__FILE__, array($this, 'activate'));
		register_deactivation_hook(__FILE__, array($this, 'deactivate'));

		add_shortcode('imsa', array($this, 'handle_shortcode'));

		add_action('admin_enqueue_scripts', array($this, 'load_admin_scripts'));

	}

	public function _init () {

		// $this->log('reset bcl timestamp..');
		// $rss = new RSSFeed('https://www.youtube.com/rss/user/BCLiberals', 'bclyt');
		// $rss->reset_timestamp();

		add_theme_support('post-thumbnails', array($this->post_type));

		// create taxonomy..
		$labels = array(
			'name'              => 'Source Types',
			'singular_name'     => 'Source Type',
			'search_items'      => 'Search Source Types',
			'all_items'         => 'All Source Types',
			'parent_item'       => 'Parent Source Type',
			'parent_item_colon' => 'Parent Source Type:',
			'edit_item'         => 'Edit Source Type',
			'update_item'       => 'Update Source Type',
			'add_new_item'      => 'Add New Source Type',
			'new_item_name'     => 'New Source Type Name',
			'menu_name'         => 'Source Type',
		);

		$args = array(
			'public'			=> true,
			'hierarchical'		=> false,
			'labels'			=> $labels,
			'show_ui'			=> true,
			'show_admin_column'	=> true,
			'query_var'			=> true,
		);

		register_taxonomy ($this->taxonomy_id, $this->post_type, $args);

		register_post_type($this->post_type,
			array(
				'labels' => array(
					'name' => 'Social Content',
					'singular_name' => 'Social Content',
					'menu_name' => 'Social Content'
				),
				'public' => true,
				'taxonomies' => array($this->taxonomy_id),
				'supports' => array('title', 'editor', 'thumbnail')
			)
		);

		register_taxonomy_for_object_type($this->taxonomy_id, $this->post_type);

		// add taxonomy terms..
		foreach ($this->sections as $section) {
			wp_insert_term($section['title'], $this->taxonomy_id, array('slug' => $section['id']));
		}
	}

	public function plugin_settings () {
		add_submenu_page('edit.php?post_type=' . $this->post_type, 'Settings', 'Settings', 'read', $this->settings_slug, array($this, 'render_settings_page'));
		add_submenu_page('edit.php?post_type=' . $this->post_type, 'Options', 'Options', 'read', $this->options_slug, array($this, 'render_options_page'));
		// add_plugins_page('Settings', 'Settings', 'manage_options', $this->settings_slug, array($this, 'render_settings_page')); // doesnt work..
	}

	public function register_settings () {

		// ** SECTIONS..

		// may not need to do this?
		// if (false == get_option('facebook_page')) {
		// 	add_option('facebook_page', array('enabled' => '1'));
		// }

		foreach ($this->sections as $section) {
			add_settings_section($section['id'], $section['title'], $section['callback'], $section['page']);
		}

		// ** FIELDS..
		$fields = $this->get_fields();
		foreach ($fields as $field) {
			$this->create_field ($field);
		}

		// ** REGISTERING..
		foreach ($this->sections as $section) {
			register_setting ($section['page'], $section['page'], array($this, 'validate_options'));
		}
	}

	public function render_section ($desc) {
		echo "<p></p>";
	}

	public function get_sections () {
		$sections = array();

		$sections[$this->prefix . 'facebook'] = array(
			'id' => $this->prefix . 'facebook',				// ID used to identify this section and with which to register options
			'title' => 'Facebook',							// Title to be displayed on the administration page
			'callback' => array($this, 'render_section'),	// Callback used to render the description of the section
			'page' => 'facebook_page'						// Page on which to add this section of options
		);

		$sections[$this->prefix . 'twitter'] = array(
			'id' => $this->prefix . 'twitter',
			'title' => 'Twitter',
			'callback' => array($this, 'render_section'),
			'page' => 'twitter_page'
		);

		$sections[$this->prefix . 'instagram'] = array(
			'id' => $this->prefix . 'instagram',
			'title' => 'Instagram',
			'callback' => array($this, 'render_section'),
			'page' => 'instagram_page'
		);

		$sections[$this->prefix . 'youtube'] = array(
			'id' => $this->prefix . 'youtube',
			'title' => 'YouTube',
			'callback' => array($this, 'render_section'),
			'page' => 'youtube_page'
		);

		$sections[$this->prefix . 'vimeo'] = array(
			'id' => $this->prefix . 'vimeo',
			'title' => 'Vimeo',
			'callback' => array($this, 'render_section'),
			'page' => 'vimeo_page'
		);

		$sections[$this->prefix . 'rss'] = array(
			'id' => $this->prefix . 'rss',
			'title' => 'RSS Feed',
			'callback' => array($this, 'render_section'),
			'page' => 'rss_page'
		);

		return $sections;
	}

	public function get_fields() {
		$fields = array();

		// *** FACEBOOK ************************************************************************************
		$fields[] = array(
			'id' => $this->prefix . 'enabled',
			'title' => 'Enable Facebook',
			'callback' => array($this, 'render_field'),
			'page' => 'facebook_page',
			'section' => $this->prefix . 'facebook',
			'desc' => 'Enable Facebook',
			'type' => 'checkbox',
			'default_value' => '',
			'class' => ''
		);

		$fields[] = array(
			'id' => $this->prefix . 'fb_app_id',
			'title' => 'App ID',
			'callback' => array($this, 'render_field'),
			'page' => 'facebook_page',
			'section' => $this->prefix . 'facebook',
			'desc' => 'Provide your app ID',
			'type' => 'text',
			'default_value' => '',
			'class' => ''
		);

		$fields[] = array(
			'id' => $this->prefix . 'fb_app_secret',
			'title' => 'App Secret',
			'callback' => array($this, 'render_field'),
			'page' => 'facebook_page',
			'section' => $this->prefix . 'facebook',
			'desc' => 'Provide your app secret',
			'type' => 'text',
			'default_value' => '',
			'class' => ''
		);

		$fields[] = array(
			'id' => $this->prefix . 'fb_page_id',
			'title' => 'Page ID',
			'callback' => array($this, 'render_field'),
			'page' => 'facebook_page',
			'section' => $this->prefix . 'facebook',
			'desc' => 'Provide a page ID',
			'type' => 'text',
			'default_value' => '',
			'class' => ''
		);

		// *** TWITTER ************************************************************************************
		$fields[] = array(
			'id' => $this->prefix . 'enabled',
			'title' => 'Enable Twitter',
			'callback' => array($this, 'render_field'),
			'page' => 'twitter_page',
			'section' => $this->prefix . 'twitter',
			'desc' => 'Enable Twitter',
			'type' => 'checkbox',
			'default_value' => '',
			'class' => ''
		);

		$fields[] = array(
			'id' => $this->prefix . 'tw_key',
			'title' => 'Key',
			'callback' => array($this, 'render_field'),
			'page' => 'twitter_page',
			'section' => $this->prefix . 'twitter',
			'desc' => 'Provide your key',
			'type' => 'text',
			'default_value' => '',
			'class' => ''
		);

		$fields[] = array(
			'id' => $this->prefix . 'tw_secret',
			'title' => 'Secret',
			'callback' => array($this, 'render_field'),
			'page' => 'twitter_page',
			'section' => $this->prefix . 'twitter',
			'desc' => 'Provide your secret',
			'type' => 'text',
			'default_value' => '',
			'class' => ''
		);

		$fields[] = array(
			'id' => $this->prefix . 'tw_token',
			'title' => 'Token',
			'callback' => array($this, 'render_field'),
			'page' => 'twitter_page',
			'section' => $this->prefix . 'twitter',
			'desc' => 'Provide your token',
			'type' => 'text',
			'default_value' => '',
			'class' => ''
		);

		$fields[] = array(
			'id' => $this->prefix . 'tw_token_secret',
			'title' => 'Token Secret',
			'callback' => array($this, 'render_field'),
			'page' => 'twitter_page',
			'section' => $this->prefix . 'twitter',
			'desc' => 'Provide your token secret',
			'type' => 'text',
			'default_value' => '',
			'class' => ''
		);

		$fields[] = array(
			'id' => $this->prefix . 'tw_screenname',
			'title' => 'Screen Name',
			'callback' => array($this, 'render_field'),
			'page' => 'twitter_page',
			'section' => $this->prefix . 'twitter',
			'desc' => 'Provide a screen name',
			'type' => 'text',
			'default_value' => '',
			'class' => 'url'
		);

		// *** INSTAGRAM ************************************************************************************
		$fields[] = array(
			'id' => $this->prefix . 'enabled',
			'title' => 'Enable Instagram',
			'callback' => array($this, 'render_field'),
			'page' => 'instagram_page',
			'section' => $this->prefix . 'instagram',
			'desc' => 'Enable Instagram',
			'type' => 'checkbox',
			'default_value' => '',
			'class' => ''
		);

		$fields[] = array(
			'id' => $this->prefix . 'insta_access_token',
			'title' => 'Access Token',
			'callback' => array($this, 'render_field'),
			'page' => 'instagram_page',
			'section' => $this->prefix . 'instagram',
			'desc' => 'Provide access token. <a href="http://jelled.com/instagram/access-token" target="_blank">Here\'s how</a>',
			'type' => 'text',
			'default_value' => '',
			'class' => ''
		);

		$fields[] = array(
			'id' => $this->prefix . 'insta_screenname',
			'title' => 'Screen Name',
			'callback' => array($this, 'render_field'),
			'page' => 'instagram_page',
			'section' => $this->prefix . 'instagram',
			'desc' => 'Provide a screen name',
			'type' => 'text',
			'default_value' => '',
			'class' => ''
		);

		// *** YouTube ************************************************************************************
		$fields[] = array(
			'id' => $this->prefix . 'enabled',
			'title' => 'Enable YouTube',
			'callback' => array($this, 'render_field'),
			'page' => 'youtube_page',
			'section' => $this->prefix . 'youtube',
			'desc' => 'Enable YouTube',
			'type' => 'checkbox',
			'default_value' => '',
			'class' => ''
		);

		$fields[] = array(
			'id' => $this->prefix . 'yt_username',
			'title' => 'User Name',
			'callback' => array($this, 'render_field'),
			'page' => 'youtube_page',
			'section' => $this->prefix . 'youtube',
			'desc' => 'Provide a user name',
			'type' => 'text',
			'default_value' => '',
			'class' => ''
		);

		// *** Vimeo ************************************************************************************
		$fields[] = array(
			'id' => $this->prefix . 'enabled',
			'title' => 'Enable Vimeo',
			'callback' => array($this, 'render_field'),
			'page' => 'vimeo_page',
			'section' => $this->prefix . 'vimeo',
			'desc' => 'Enable Vimeo',
			'type' => 'checkbox',
			'default_value' => '',
			'class' => ''
		);

		$fields[] = array(
			'id' => $this->prefix . 'vim_username',
			'title' => 'User Name',
			'callback' => array($this, 'render_field'),
			'page' => 'vimeo_page',
			'section' => $this->prefix . 'vimeo',
			'desc' => 'Provide a user name',
			'type' => 'text',
			'default_value' => '',
			'class' => ''
		);

		// *** RSS Feed ************************************************************************************
		$fields[] = array(
			'id' => $this->prefix . 'enabled',
			'title' => 'Enable RSS Feed',
			'callback' => array($this, 'render_field'),
			'page' => 'rss_page',
			'section' => $this->prefix . 'rss',
			'desc' => 'Enable RSS Feed',
			'type' => 'checkbox',
			'default_value' => '',
			'class' => ''
		);

		$fields[] = array(
			'id' => $this->prefix . 'rss_url',
			'title' => 'URL',
			'callback' => array($this, 'render_field'),
			'page' => 'rss_page',
			'section' => $this->prefix . 'rss',
			'desc' => 'Provide RSS URL',
			'type' => 'text',
			'default_value' => '',
			'class' => 'url'
		);

		return $fields;
	}

	public function create_field ($field) {

		extract ($field);

		$field_args = array(
			'id' => $id,
			'page' => $page,
			'type' => $type,
			'desc' => $desc,
			'default_value' => $default_value,
			'class' => $class
		);

		add_settings_field ($id, $title, array($this, 'render_field'), $page, $section, $field_args);

	}

	public function render_field ($field_args = array()) {

		// $this->log('in render_field..');
		extract ($field_args);

		$options = get_option($page);

		$html = '<div class="' . $class . '">';

		switch ($type) {
			case 'text':
				$value = isset($options[$id]) ? $options[$id] : '';
				$html .= '<input type="text" id="' . $id . '" name="' . $page . '[' . $id . ']" value="' . $value . '" />';
				$html .= '<br/><span class="field-desc">' . $desc . '</span>';
				break;
			case 'checkbox':
				$html .= '<input type="checkbox" id="' . $id . '" name="' . $page . '[' . $id . ']" value="1" ' . checked (1, isset ($options[$id]) ? $options[$id] : 0, false) . '/>';
				$html .= '<label for="' . $id . '">&nbsp;'  . $desc . '</label>';
				break;
			case 'button':
				$html .= 'test';
				break;
			default:
				# code...
				break;
		}

		$html .= '</div>';

		echo $html;
	}

	public function validate_options ($input) {
		$output = array();

		foreach ($input as $key => $value) {
			if (isset($input[$key])) {
				$output[$key] = strip_tags(stripslashes($input[$key]));
			}
		}
		return apply_filters('validate_options', $output, $input);
	}

	public function render_settings_page () {
		?>

		<div id="<?php echo $this->settings_slug; ?>" class="wrap">

			<h2>Social Aggregator Settings</h2>
			<?php settings_errors(); ?>

			<?php
				$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'facebook_page';
			?>

			<h2 class="nav-tab-wrapper">
				<?php

					foreach ($this->sections as $section) {
						$tab = $active_tab == $section['page'] ? 'nav-tab-active' : '';
						$options = get_option($section['page']);
						$feed_enabled = empty($options['sa_enabled']) ? '' : 'feed-enabled';
						echo '<a href="?post_type=' . $this->post_type . '&amp;page=' . $this->settings_slug . '&amp;tab=' . $section['page'] . '" class="nav-tab ' . $tab . ' ' . $feed_enabled . '">' . $section['title'] . '</a>';
					}
				?>
			</h2>

			<form method="post" action="options.php">
				<?php

				settings_fields ($active_tab);
				do_settings_sections ($active_tab);

				submit_button();

				?>
			</form>

		</div><!-- /.wrap -->

		<?php
	}

	public function render_options_page () {
		?>
		<div class="wrap">
			<h2>Social Aggregator Options</h2>
			<div id="sa-options">
				<div class="sa-section">
					<h3>Manually Fetch Social Feeds</h3>
					<p>To manually fetch all enabled social feeds without having to wait for the daily (wp) cron job to run, click the button below.</p>
					<button id="sa-btn-fetch" class="button button-primary button-large">Manually Fetch Social Feeds</button>
					<span class="spinner"></span>
					<div class="message"></div>
				</div>
				<div class="sa-section">
					<h3>Reset</h3>
					<p>The plugin will only fetch feeds that have not yet been added to avoid duplicates. If you would like to start fresh and load all latest feeds you'll need to reset.</p>
					<button id="sa-btn-reset" class="button button-primary button-large">Reset</button>
					<span class="spinner"></span>
				</div>
				<hr />
			</div>
		</div>
		<?php
	}

	public function cron_add_minute ($schedules) {
		// Adds once weekly to the existing schedules.
		$schedules['minute'] = array(
			'interval' => 60,
			'display' => 'Once every minute'
		);

		return $schedules;
	}

	// when plugin is activated..
	public function activate () {
		$this->log('** IM_Aggregator activated..');
		if (!wp_next_scheduled('run_cron')) {
			wp_schedule_event(time(), 'daily', 'run_cron'); // TODO: change this to 'daily' for production..
		}
	}

	// when plugin is deactivated..
	public function deactivate () {
		$this->log('** IM_Aggregator deactivated..');
		wp_clear_scheduled_hook('run_cron');
	}

	public function start_aggregation () {
		$this->log('<<<<<<<<<<<<<<<<<<<<<<<<<< start aggregation..');

		// increasing maximum execution time to 3 min for this part, it can take more time
		// than the default 30 secs for the images to be downloaded and processed by PHP/WP..
		// set_time_limit(180);

		$feed_enabled = false;

		// fetch from Facebook..
		$options = get_option('facebook_page');
		if (isset($options[$this->prefix . 'enabled'])) {
			$feed_enabled = true;
			$fb = new FacebookFeed ();
			$result = $fb->getFeed ($options);
			if ($this->feed_error($result)) {
				return $result['message'];
			}
			else {
				$this->save_feed_items($result, $this->sections[$this->prefix . 'facebook']);
			}
		}

		// fetch from Twitter..
		$options = get_option('twitter_page');
		if (isset($options[$this->prefix . 'enabled'])) {
			$feed_enabled = true;
			$tw = new TwitterFeed ();
			$result = $tw->getFeed ($options);
			if ($this->feed_error($result)) {
				return $result['message'];
			}
			else {
				$this->save_feed_items($result, $this->sections[$this->prefix . 'twitter']);
			}
		}

		// fetch from Instagram..
		$options = get_option('instagram_page');
		if (isset($options[$this->prefix . 'enabled'])) {
			$feed_enabled = true;
			$in = new InstagramFeed ();
			$result = $in->getFeed ($options);
			if ($this->feed_error($result)) {
				return $result['message'];
			}
			else {
				$this->save_feed_items($result, $this->sections[$this->prefix . 'instagram']);
			}
		}

		// fetch from YouTube..
		$options = get_option('youtube_page');
		if (isset($options[$this->prefix . 'enabled'])) {
			$feed_enabled = true;
			$yt = new YouTubeFeed ();
			$result = $yt->getFeed ($options);
			if ($this->feed_error($result)) {
				return $result['message'];
			}
			else {
				$this->save_feed_items($result, $this->sections[$this->prefix . 'youtube']);
			}
		}

		// fetch from Vimeo..
		$options = get_option('vimeo_page');
		if (isset($options[$this->prefix . 'enabled'])) {
			$feed_enabled = true;
			$vim = new VimeoFeed ();
			$result = $vim->getFeed ($options);
			if ($this->feed_error($result)) {
				return $result['message'];
			}
			else {
				$this->save_feed_items($result, $this->sections[$this->prefix . 'vimeo']);
			}
		}

		// fetch from RSS Feed..
		$options = get_option('rss_page');
		if (isset($options[$this->prefix . 'enabled'])) {
			$feed_enabled = true;
			$rss = new RssFeed ();
			$result = $rss->getFeed ($options);
			if ($this->feed_error($result)) {
				return $result['message'];
			}
			else {
				$this->save_feed_items($result, $this->sections[$this->prefix . 'rss']);
			}
		}

		$this->log('>>>>>>>>>>>>>>>>>>>>>>>>>> aggregation complete..!');
		if (!$feed_enabled) return 'No social feeds are enabled. <a href="edit.php?post_type=' . $this->post_type . '&page=' . $this->settings_slug . '">Enable some feeds.</a>';
	}

	private function feed_error ($res) {
		if (isset($res['error']) && $res['error'] >= 4) return true;
		return false;
	}

	private function save_feed_items ($res, $section) {
		if (isset($res['error'])) {
			$this->log ($res['message']);
			if ($res['error'] >= 4) return $res['message'];
		}
		else {

			// update the since time..
			$options = get_option($section['page']);
			$options[$this->prefix . 'since_time'] = $res['since_time'];
			update_option($section['page'], $options);

			foreach ($res['data'] as $post) {

				// $title = wp_trim_words($this->validateString('(' . $post['source_type'] . ') ' . $post['message']), $num_words = 12, $more = '...');

				$title = empty($post['message']) ? $section['title'] . ' Post (' . date('M j, y', $post['date_created']) . ')' : $post['message'];
				$description = empty($post['description']) ? $title : $post['description'];

				$p = array(
					'post_title' => $title,
					'post_content' => $description,
					'post_status' => 'publish',
					'post_type' => $this->post_type
				);

				$postId = wp_insert_post($p);

				// across all social feeds (global fields)..
				update_post_meta($postId, $this->prefix . 'id', $this->validateString($post['id']));
				update_post_meta($postId, $this->prefix . 'link', $this->validateUrl($post['link']));
				update_post_meta($postId, $this->prefix . 'date_added', $this->validateNumber($post['date_added']));
				update_post_meta($postId, $this->prefix . 'date_created', $this->validateString($post['date_created']));

				// some social feeds may or may not have these fields..
				// if (isset($post['pub_date'])) update_post_meta($postId, $this->prefix . 'pub_date', $this->validateString($post['pub_date']));
				if (isset($post['picture'])) update_post_meta($postId, $this->prefix . 'picture', $this->validateString($post['picture']));
				if (isset($post['author'])) update_post_meta($postId, $this->prefix . 'author', $this->validateString($post['author']));

				// if it's a video feed.. (youTube, vimeo)
				if (isset($post['video_id'])) {
					update_post_meta($postId, $this->prefix . 'video_id', $this->validateString($post['video_id']));
					// update_post_meta($postId, $this->prefix . 'video_thumb', $this->validateString($post['video_thumb']));
					// somatic_attach_external_image('http://img.youtube.com/vi/' . $post['video_id'] . '/0.jpg', $postId, true);
				}

				wp_set_post_terms ($postId, $section['id'], $this->taxonomy_id);
			}
		}
	}

	public function load_scripts () {

		// js scripts..
		$scripts = array(
			array(
				'handle' => 'imsa',
				'src' => $this->settings['plugin_url'] . 'js/im-social-aggregator.js',
				'deps' => array(),
				'in_footer' => true
			)
		);

		foreach($scripts as $script) {
			wp_register_script($script['handle'], $script['src'], $script['deps'], $this->settings['version'], $script['in_footer']);
		}

		// enqueue scripts and styles..
		wp_enqueue_script('imsa');
		// declare the URL to the file that handles the AJAX request (wp-admin/admin-ajax.php)
		wp_localize_script('imsa', 'IMSA', array('ajaxurl' => admin_url('admin-ajax.php')));
	}

	public function load_admin_scripts () {
		global $typenow;

		if ($typenow == $this->post_type) {

			// js..
			wp_register_script('sa-admin', $this->settings['plugin_url'] . 'js/admin.js', null, $this->settings['version']);
			wp_enqueue_script('sa-admin');

			// css..
			wp_register_style('im-social-aggregator-admin', $this->settings['plugin_url'] . 'css/im-social-aggregator-admin.css', null, $this->settings['version']);
			wp_enqueue_style('im-social-aggregator-admin');
		}
	}

	public function load_custom_fields () {

		$fields = array(
			array('id' => 'sa_picture', 'type' => 'text', 'title' => 'Picture', 'description' => 'The photo displayed in the social aggregator.'),
			array('id' => 'sa_link', 'type' => 'text', 'title' => 'Link', 'description' => 'The link user will be taken to when item is clicked.'),
			array('id' => 'sa_author', 'type' => 'text', 'title' => 'Author', 'description' => 'The author of the item.'),
			// array('id' => 'sa_featured', 'type' => 'checkbox', 'title' => 'Set as featured? ', 'description' => 'Check this box to display featured as featured item.'),
		);

		add_meta_box($this->settings_slug, 'Social Feed Options', array($this, 'build_custom_field'), $this->post_type, 'normal', 'default', array($fields));
	}

	public function build_custom_field ($post = null, $box = null) {
		$custom_fields = get_post_custom($post->ID);
		wp_nonce_field('sa_nonce_action', 'sa_nonce_name');
		$fieldset = $box['args'][0];

		echo '<table class="form-table"><tbody>';

		foreach ($fieldset as $value) {

			if (empty($custom_fields[$value['id']][0])) continue;

			echo '<tr>';
			echo '<th scope="row">' . $value['title'] . '</th>';
			echo '<td><input type="text" name="' . $value['id'] . '" id="' . $value['id'] . '" value="' . $custom_fields[$value['id']][0] . '" readonly/><br/><span class="field-desc">' . $value['description'] . '</span></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * returns all social feeds found in table..
	 *
	 * @return array
	 */
	protected function _get_feeds ($feeds) {

		$data = array();

		$args = array(
			'post_status' => 'publish',
			'post_type' => $this->post_type,
			'orderby' => 'date',
			'order' => 'DESC',
			'posts_per_page' => -1
		);

		if (!empty($feeds) && $feeds[0] != '') {

			// need to prepend slug with prefix..
			$prefixed_feeds = array();
			foreach ($feeds as $feed) {
				$prefixed_feeds[] = $this->prefix . $feed;
			}

			$args['tax_query'] = array(
				array(
					'taxonomy' => $this->taxonomy_id,
					'field' => 'slug',
					'terms' => $prefixed_feeds
				)
			);
		}

		$query = new WP_Query($args);

		while ($query->have_posts()) {

			$query->the_post();
			$postId = get_the_ID();

			$terms = wp_get_post_terms($postId, $this->taxonomy_id);
			$term = $terms[0]; // get the first term, there should only be one term and it is the source of where the content came from..
			$term = substr($term->slug, 3); // remove the prefix..

			$item = array(
				'id' => $postId,
				'source' => $term,
				'title' => get_the_title(),
				'message' => get_the_content(),
				'picture' => get_post_meta($postId, 'sa_picture', true),
				'link' => get_post_meta($postId, 'sa_link', true),
				'author' => get_post_meta($postId, 'sa_author', true)
			);

			$data[$term][] = $item;
		}

		wp_reset_query();

		return $data;

	}

	// options ********************************************************************************
	public function fetch_social_feeds () {
		$this->log ('fetch feeds..');
		$result = $this->start_aggregation();

		$response = json_encode(array('message' => $result));

		// // response output..
		header("Content-Type: application/json");
		echo $response;

		exit;
	}

	public function reset_since_times () {
		$this->log ('reset_since_times..');
		foreach ($this->sections as $section) {
			$page = $section['page'];
			$options = get_option($page);
			$options[$this->prefix . 'since_time'] = 1;
			update_option($page, $options);
		}
		exit;
	}

	/**
	 * get_feeds
	 *
	 * returns all feeds in json format if made from
	 * an ajax call, otherwise returns a php array..
	 *
	 */
	public function get_feeds ($feeds = array()) {

		if (empty($_GET['action'])) {
			// php call..
			return $this->_get_feeds($feeds);
		}
		else {
			// ajax call..
			if (!empty($_GET['feeds'])) $feeds = $_GET['feeds'];
			$data = $this->_get_feeds($feeds);

			// generate the response..
			$response = json_encode(array('success' => true, 'feeds' => $data));

			// // response output..
			header("Content-Type: application/json");
			echo $response;

			// IMPORTANT: don't forget to "exit"..
			exit;
		}
	}

	public function handle_shortcode ($attr, $content = '') {
		$a = shortcode_atts(array(
			'cols' => 3,
			'source_types' => '',
			'num_items_per_source' => -1
		), $attr);

		// limit the range of possible columns..
		if ($a['cols'] > 9) {
			$a['cols'] = 9;
		}
		else if ($a['cols'] < 1) {
			$a['cols'] = 1;
		}

		// clean up source types..
		$a['source_types'] = explode(',', $a['source_types']);
		$sources = array_map(function ($item) {
			return trim($item);
		}, $a['source_types']);

		$html = '<div id="imsa-1" class="gallery gallery-columns-' . $a['cols'] . ' gallery-size-thumbnail">';

		$feeds = $this->get_feeds($sources);

		// if no feeds found, output a notice..
		if (empty($feeds)) {
			return '<p style="color:#CF0000; font-style:oblique;">IMSA -- No Social Feeds Found. Check if you have social content available.</p>';
		}

		foreach ($feeds as $feed) {
			foreach ($feed as $item) {
				$html .= '<figure class="gallery-item">';
				$html .= '<div class="gallery-icon landscape">';
				$html .= '<a href="' . $item['link'] . '" target="_blank">';
				if (!empty($item['picture'])) {
					$html .= '<img src="' . $item['picture'] . '"/>';
				}
				else {
					$html .= $item['message'];
				}
				$html .= '</a>';
				$html .= '</div>';
				$html .= '</figure>';
			}
		}

		$html .= '</div>';
		return $html;
	}

	// helpers..
	public function validateString ($val) {
		return sanitize_text_field($val);
	}

	public function validateUrl ($url) {
		return esc_url($url);
	}

	public function validateNumber ($val) {
		return intval($val);
	}

	public function get_plugin_url ($url) {
		return trailingslashit($url);
	}

	public function log ($message) {
		if (WP_DEBUG_LOG === true) {
			if (is_array($message) || is_object($message)) {
				error_log(print_r($message, true));
			}
			else {
				error_log($message);
			}
		}
	}
}

$imsa = new IM_Aggregator();