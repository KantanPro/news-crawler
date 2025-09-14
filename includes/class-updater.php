<?php
/**
 * GitHubリリース連携の標準更新通知＋自動再有効化＋安全リロード＋GitHub資産ZIP優先＋展開後リネーム
 * 
 * @package NewsCrawler
 * @since 2.3.93
 */

if (!defined('ABSPATH')) { exit; }

if (!class_exists('NewsCrawlerUpdater')) {
	class NewsCrawlerUpdater {
		private $plugin_file;
		private $plugin_basename;
		private $plugin_slug;
		private $repo_owner;
		private $repo_name;
		private $requires_wp;
		private $requires_php;
		private $tested_wp;

		public function __construct() {
			$this->plugin_file   = NEWS_CRAWLER_PLUGIN_DIR . 'news-crawler.php';
			$this->plugin_basename = plugin_basename($this->plugin_file);
			$this->plugin_slug   = 'news-crawler';
			$this->repo_owner    = 'KantanPro';
			$this->repo_name     = 'news-crawler';
			$this->requires_wp   = '5.0';
			$this->requires_php  = '7.4';
			$this->tested_wp     = get_bloginfo('version');

			add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'));
			add_filter('site_transient_update_plugins', array($this, 'check_for_updates'));
			add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
			add_filter('upgrader_pre_install', array($this, 'before_update'), 10, 3);
			add_filter('upgrader_post_install', array($this, 'rename_github_source'), 9, 3);
			add_filter('upgrader_post_install', array($this, 'after_update'), 10, 3);
			add_filter('upgrader_pre_download', array($this, 'upgrader_pre_download'), 10, 3);
			add_action('upgrader_process_complete', array($this, 'handle_auto_activation'), 10, 2);
			add_action('admin_init', array($this, 'maybe_reload_admin_after_activation'));
		}

		public function check_for_updates($transient) {
			if (!is_admin() && !(defined('DOING_CRON') && DOING_CRON)) {
				return $transient;
			}
			if ($transient === null) {
				$transient = new stdClass();
			}
			if (!isset($transient->checked)) {
				$transient->checked = array();
			}

			if (!function_exists('get_plugin_data')) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$plugin_data = get_plugin_data($this->plugin_file, false, false);
			$current_version = isset($plugin_data['Version']) ? $plugin_data['Version'] : NEWS_CRAWLER_VERSION;

			$transient->checked[$this->plugin_basename] = $current_version;

			$latest = $this->get_latest_version();
			if (!$latest || empty($latest['version'])) {
				return $transient;
			}

			if (version_compare($current_version, $latest['version'], '<')) {
				if (!isset($transient->response)) {
					$transient->response = array();
				}
				$transient->response[$this->plugin_basename] = (object) array(
					'id'            => $this->plugin_slug,
					'slug'          => $this->plugin_slug,
					'plugin'        => $this->plugin_basename,
					'new_version'   => $latest['version'],
					'url'           => 'https://github.com/' . $this->repo_owner . '/' . $this->repo_name,
					'package'       => $latest['download_url'],
					'requires'      => $this->requires_wp,
					'requires_php'  => $this->requires_php,
					'tested'        => $this->tested_wp,
					'last_updated'  => $latest['published_at'],
					'sections'      => array(
						'description' => $latest['description'],
						'changelog'   => $latest['changelog'],
					),
				);
				if (isset($transient->no_update[$this->plugin_basename])) {
					unset($transient->no_update[$this->plugin_basename]);
				}
			} else {
				if (!isset($transient->no_update)) {
					$transient->no_update = array();
				}
				$transient->no_update[$this->plugin_basename] = (object) array(
					'id'          => $this->plugin_slug,
					'slug'        => $this->plugin_slug,
					'plugin'      => $this->plugin_basename,
					'new_version' => $current_version,
					'url'         => 'https://github.com/' . $this->repo_owner . '/' . $this->repo_name,
					'package'     => '',
				);
				if (isset($transient->response[$this->plugin_basename])) {
					unset($transient->response[$this->plugin_basename]);
				}
			}

			return $transient;
		}

		public function plugin_info($result, $action, $args) {
			if ($action !== 'plugin_information') { return $result; }
			if (!isset($args->slug) || $args->slug !== $this->plugin_slug) { return $result; }

			$latest = $this->get_latest_version();
			if (!$latest) { return $result; }

			$info = new stdClass();
			$info->name         = $this->plugin_slug;
			$info->slug         = $this->plugin_slug;
			$info->version      = $latest['version'];
			$info->last_updated = $latest['published_at'];
			$info->requires     = $this->requires_wp;
			$info->requires_php = $this->requires_php;
			$info->tested       = $this->tested_wp;
			$info->download_link = $latest['download_url'];
			$info->sections = array(
				'description' => $latest['description'],
				'changelog'   => $latest['changelog'],
			);
			return $info;
		}

		public function upgrader_pre_download($reply, $package, $upgrader) {
			if (strpos($package, 'github.com') !== false) {
				add_filter('http_request_args', array($this, 'github_download_args'), 10, 2);
			}
			return $reply;
		}

		public function github_download_args($args, $url) {
			if (strpos($url, 'github.com') !== false) {
				$args['timeout'] = 60;
				$args['headers']['User-Agent'] = 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url');
				if (defined('NEWS_CRAWLER_GITHUB_TOKEN') && NEWS_CRAWLER_GITHUB_TOKEN) {
					$args['headers']['Authorization'] = 'Bearer ' . NEWS_CRAWLER_GITHUB_TOKEN;
				}
			}
			return $args;
		}

		public function before_update($response, $hook_extra, $result = null) {
			if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === $this->plugin_basename) {
				$was_network_active = is_multisite() && is_plugin_active_for_network($this->plugin_basename);
				$was_active = is_plugin_active($this->plugin_basename) || $was_network_active;

				set_site_transient($this->key('pre_update_state'), array(
					'was_active'     => $was_active,
					'network_active' => $was_network_active,
				), 30 * MINUTE_IN_SECONDS);

				if ($was_active) {
					deactivate_plugins($this->plugin_basename, true, $was_network_active);
				}
			}
			return $response;
		}

		public function rename_github_source($response, $hook_extra, $result) {
			if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_basename) {
				return $response;
			}
			if (empty($result) || empty($result['destination']) || empty($result['source'])) {
				return $response;
			}
			$destination = trailingslashit($result['destination']);
			$source      = trailingslashit($result['source']);
			$expected_dir = trailingslashit(WP_PLUGIN_DIR) . $this->plugin_slug . '/';

			if (untrailingslashit($destination) === untrailingslashit($expected_dir)) {
				return $response;
			}
			if (strpos(basename($source), $this->plugin_slug) === 0) {
				if (is_dir($expected_dir)) {
					$this->rmdir_recursive($expected_dir);
				}
				@rename($source, $expected_dir);
				$result['destination'] = $expected_dir;
				$response = $result;
			}
			return $response;
		}

		public function after_update($response, $hook_extra, $result) {
			if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === $this->plugin_basename) {
				delete_transient($this->key('latest_version'));
				delete_transient($this->key('latest_version_backup'));
				delete_site_transient('update_plugins');
				delete_site_transient('update_plugins_checked');
				wp_clean_plugins_cache();
				if (function_exists('wp_cache_flush')) {
					wp_cache_flush();
				}
			}
			return $response;
		}

		public function handle_auto_activation($upgrader_object, $options) {
			if ($options['action'] === 'update' && $options['type'] === 'plugin') {
				if (isset($options['plugins']) && in_array($this->plugin_basename, $options['plugins'])) {
					$was = get_site_transient($this->key('pre_update_state'));

					if ($was && !empty($was['was_active'])) {
						if (!is_plugin_active($this->plugin_basename)) {
							if (!empty($was['network_active'])) {
								activate_plugin($this->plugin_basename, '', true);
							} else {
								activate_plugin($this->plugin_basename);
							}
						}
					}
					set_transient($this->key('admin_reload'), 1, 5 * MINUTE_IN_SECONDS);
					delete_site_transient($this->key('pre_update_state'));
				}
			}
		}

		public function maybe_reload_admin_after_activation() {
			if (!is_admin()) { return; }
			if (!current_user_can('activate_plugins')) { return; }
			$needs = get_transient($this->key('admin_reload'));
			if (!$needs) { return; }

			if (!isset($_GET['kp_reloaded'])) {
				$url = add_query_arg('kp_reloaded', '1');
				if ($url) {
					wp_safe_redirect($url);
					exit;
				}
			} else {
				delete_transient($this->key('admin_reload'));
			}
		}

		private function get_latest_version() {
			$force_refresh = (is_admin() && isset($_GET['force-check']) && $_GET['force-check'] == '1');

			if (!$force_refresh) {
				$cached = get_transient($this->key('latest_version'));
				if ($cached !== false) {
					return $cached;
				}
			}

			$headers = array(
				'User-Agent'    => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
				'Accept'        => 'application/vnd.github.v3+json',
				'Cache-Control' => 'no-cache',
			);
			if (defined('NEWS_CRAWLER_GITHUB_TOKEN') && NEWS_CRAWLER_GITHUB_TOKEN) {
				$headers['Authorization'] = 'Bearer ' . NEWS_CRAWLER_GITHUB_TOKEN;
			}

			$latest_url = 'https://api.github.com/repos/' . $this->repo_owner . '/' . $this->repo_name . '/releases/latest';
			$response = wp_remote_get($latest_url, array('timeout' => 15, 'headers' => $headers));

			$data = null;
			if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
				$data = json_decode(wp_remote_retrieve_body($response), true);
			}

			if (!$data || !isset($data['tag_name']) || !empty($data['draft']) || !empty($data['prerelease'])) {
				$list_url = 'https://api.github.com/repos/' . $this->repo_owner . '/' . $this->repo_name . '/releases';
				$resp2 = wp_remote_get($list_url, array('timeout' => 15, 'headers' => $headers));
				if (!is_wp_error($resp2) && wp_remote_retrieve_response_code($resp2) === 200) {
					$list = json_decode(wp_remote_retrieve_body($resp2), true);
					if (is_array($list)) {
						foreach ($list as $rel) {
							if (!empty($rel['draft']) || !empty($rel['prerelease'])) { continue; }
							if (isset($rel['tag_name'])) { $data = $rel; break; }
						}
					}
				}
			}

			if (!$data || !isset($data['tag_name'])) {
				$old_cached = get_transient($this->key('latest_version_backup'));
				if ($old_cached !== false) { return $old_cached; }
				return false;
			}

			$normalized_version = ltrim($data['tag_name'], 'v');

			$download_url = isset($data['zipball_url']) ? $data['zipball_url'] : '';
			if (isset($data['assets']) && is_array($data['assets'])) {
				foreach ($data['assets'] as $asset) {
					if (!empty($asset['browser_download_url']) && preg_match('/\\.zip$/i', $asset['browser_download_url'])) {
						if (!empty($asset['name']) && stripos($asset['name'], $this->plugin_slug) !== false) {
							$download_url = $asset['browser_download_url'];
							break;
						}
						$download_url = $asset['browser_download_url'];
					}
				}
			}

			$version_info = array(
				'version'      => $normalized_version,
				'download_url' => $download_url,
				'published_at' => isset($data['published_at']) ? $data['published_at'] : '',
				'description'  => !empty($data['body']) ? $data['body'] : '',
				'changelog'    => $this->get_changelog_for_version($normalized_version),
				'prerelease'   => isset($data['prerelease']) ? $data['prerelease'] : false,
				'draft'        => isset($data['draft']) ? $data['draft'] : false,
			);

			set_transient($this->key('latest_version'), $version_info, 15 * MINUTE_IN_SECONDS);
			set_transient($this->key('latest_version_backup'), $version_info, DAY_IN_SECONDS);

			return $version_info;
		}

		private function get_changelog_for_version($version) {
			$changelog_file = dirname($this->plugin_file) . '/CHANGELOG.md';
			if (!file_exists($changelog_file)) { return ''; }
			$content = file_get_contents($changelog_file);
			if (!$content) { return ''; }
			$pattern = '/## \[' . preg_quote($version, '/') . '\](.*?)(?=## \[|$)/s';
			if (preg_match($pattern, $content, $m)) {
				return trim($m[1]);
			}
			return '';
		}

		private function rmdir_recursive($dir) {
			if (!is_dir($dir)) return;
			$items = scandir($dir);
			foreach ($items as $item) {
				if ($item === '.' || $item === '..') continue;
				$path = $dir . DIRECTORY_SEPARATOR . $item;
				if (is_dir($path)) {
					$this->rmdir_recursive($path);
				} else {
					@unlink($path);
				}
			}
			@rmdir($dir);
		}

		private function key($suffix) {
			return 'news_crawler_upd_' . md5($this->plugin_basename) . '_' . $suffix;
		}
	}
}