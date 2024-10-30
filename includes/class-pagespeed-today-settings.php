<?php
if (!defined('ABSPATH')) exit;

class PageSpeed_today_Settings {
	
	/**
	 * The single instance of PageSpeed_today_Settings.
	 * @var    object
	 * @access  private
	 * @since    1.0.0
	 */
	private static $_instance = null;
	/**
	 * The main plugin object.
	 * @var    object
	 * @access  public
	 * @since    1.0.0
	 */
	public $parent = null;

	/**
	 * Prefix for plugin settings.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $base = '';

	/**
	 * Available settings for plugin.
	 * @var     array
	 * @access  public
	 * @since   1.0.0
	 */
	public $settings = array();
	
	/**
	 * Constructor function.
	 * @access  public
	 * @since   1.0.0
	 */
	public function __construct($parent) {
		$this->parent = $parent;
		$this->base = 'pagespeed_today_';

		// Initialise settings
		add_action('init', array(
			$this,
			'init_settings'
		) , 11);

		// Register plugin settings
		add_action('admin_init', array(
			$this,
			'register_settings'
		));

		// Add settings page to menu
		add_action('admin_menu', array(
			$this,
			'add_menu_item'
		));

		// Add settings link to plugins page
		add_filter('plugin_action_links_' . plugin_basename($this->parent->file) , array(
			$this,
			'add_settings_link'
		));
	}

	/**
	 * Initialise settings
	 * @return void
	 */
	public function init_settings() {
		$this->settings = $this->settings_fields();
		if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST[$this->parent->_token . '_action'])) {
			switch ($_POST[$this->parent->_token . '_action']) {
				case 'process':
					$this->process();
					break;
	
				case 'scan':
					$this->scan();
					break;
	
				case 'restore_backup':
					$this->restore_backup();
					break;
	
				case 'delete_backup':
					$this->delete_backup();
					break;
					
				case 'check_license':
					$this->check_license();
					break;
			}

			if (isset($_POST[$this->parent->_token . '_ajax']) && !empty($_POST[$this->parent->_token . '_ajax'])) {
				exit();
			}
		}
	}

	/**
	 * Remove dir function
	 * @return void
	 */
	public function rrmdir($dir, $force = false) {
		if (is_dir($dir)) {
			$objects = scandir($dir);
			foreach($objects as $object) {
				if ($object != "." && $object != "..") {
					if (is_dir($dir . "/" . $object)) {
						$this->rrmdir($dir . "/" . $object, $force);
					} else {
						unlink($dir . "/" . $object);
					}
				}
			}
		}

		if ($force) {
			rmdir($dir);
		}
	}

	/**
	 * Optimization process function
	 * @return void
	 */
	public function process() {
		if (get_option($this->parent->_token . '_image') || get_option($this->parent->_token . '_css') || get_option($this->parent->_token . '_js')) {
			if (isset($_POST[$this->parent->_token . '_url']) && !empty($_POST[$this->parent->_token . '_url'])) {
				$url = urlencode(urlencode(urlencode($_POST[$this->parent->_token . '_url'])));
				$id = $_POST[$this->parent->_token . '_id'];
				$response = wp_remote_get('http://api.pagespeed.today/v1/' . $url, array(
					'timeout' => 120
				));
				if (is_array($response) && !is_wp_error($response) && !empty($response['body'])) {
					switch ($response['body']) {
						case 'Done':
							$filename = PAGESPEED_TODAY_PLUGIN_DIR . 'tmp/optimized_contents.zip';
							$tmp_folder = PAGESPEED_TODAY_PLUGIN_DIR . 'tmp/';
							$host = 'http://api.pagespeed.today/tmp/' . md5($url) . '.zip';
							$ch = curl_init();
							curl_setopt($ch, CURLOPT_URL, $host);
							curl_setopt($ch, CURLOPT_VERBOSE, 1);
							curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
							curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
							curl_setopt($ch, CURLOPT_HEADER, 0);
							$result = curl_exec($ch);
							curl_close($ch);
							if ($result) {
								$fp = fopen($filename, 'w');
								fwrite($fp, $result);
								fclose($fp);
								$zip = new PageSpeedTodayZip();
								if ($zip->unzip_file($filename) === true) {
									$zip->unzip_to($tmp_folder);
									$manifest = file_get_contents($tmp_folder . 'MANIFEST');
									if ($manifest) {
										$manifest = explode("\n", $manifest);
										if (sizeof($manifest) > 7) {
											$backup_dir = $this->make_backup($manifest, $_POST[$this->parent->_token . '_url']);
											$data = get_option($this->parent->_token . '_data');
											$data['backups'][] = ['id' => $_POST[$this->parent->_token . '_id'], 'post_title' => $_POST[$this->parent->_token . '_post_title'], 'url' => $_POST[$this->parent->_token . '_url'], 'date' => date('Y-m-d H:i:s') , 'path' => $backup_dir, ];
											if (!isset($data[$id]['count']) || empty($data[$id]['count'])) {
												$data[$id]['count'] = 0;
											}
			
											if (!isset($data[$id]['size']) || empty($data[$id]['size'])) {
												$data[$id]['size'] = 0;
											}
			
											if (!isset($data['total']['size']) || empty($data['total']['size'])) {
												$data['total']['size'] = 0;
											}
			
											if (!isset($data['total']['files']) || empty($data['total']['files'])) {
												$data['total']['files'] = 0;
											}
			
											foreach($manifest as $key => $value) {
												if ($key > 7) {
													$value = explode(': ', $value);
													if (isset($value[1]) && !empty($value[1])) {
														$folder = explode('/', $value[0]);
														$value[1] = parse_url($value[1]);
														if ($value[1]['host'] == $_SERVER['HTTP_HOST'] || file_exists(ABSPATH . urldecode($value[1]['path']))) {
															if (get_option($this->parent->_token . '_' . $folder[0]) && file_exists(ABSPATH . urldecode($value[1]['path'])) && urldecode($value[1]['path']) != '/') {
																$data[$id]['count']++;
																$data['total']['files']++;
																$data[$id]['size'] += (filesize(ABSPATH . urldecode($value[1]['path'])) - filesize($tmp_folder . $value[0]));
																$data['total']['size'] += (filesize(ABSPATH . urldecode($value[1]['path'])) - filesize($tmp_folder . $value[0]));
																@unlink(ABSPATH . urldecode($value[1]['path']));
																@copy($tmp_folder . $value[0], ABSPATH . urldecode($value[1]['path']));
															}
														}
													}
												}
											}
			
											update_option($this->parent->_token . '_data', $data);
										}
									}
			
									add_action('admin_notices', 'admin_notice__success');
								} else {
									add_action('admin_notices', 'admin_notice__error');
								}
			
								$this->rrmdir($tmp_folder);
							} else {
								add_action('admin_notices', 'admin_notice__empty');
							}
							break;
						case 'Delay':
							add_action('admin_notices', 'admin_notice__delay');
							break;
						case 'Limit':
							add_action('admin_notices', 'admin_notice__limit');
							break;
					}
					
				} else {
					add_action('admin_notices', 'admin_notice__empty');
				}
			}
		} else {
			add_action('admin_notices', 'admin_notice__settings_check');
		}
	}

	/**
	 * PageSpeed scan function
	 * @return void
	 */
	public function scan() {
		if (isset($_POST[$this->parent->_token . '_url']) && !empty($_POST[$this->parent->_token . '_url'])) {
			$url = urlencode(urlencode(urlencode($_POST[$this->parent->_token . '_url'])));
			$id = $_POST[$this->parent->_token . '_id'];
			$response = wp_remote_get('http://api.pagespeed.today/v1/scan/' . $url, array(
				'timeout' => 120
			));
			$data = get_option($this->parent->_token . '_data');
			if (is_array($response) && !is_wp_error($response) && !empty($response['body'])) {
				switch ($response['body']) {
					case 'Delay':
						add_action('admin_notices', 'admin_notice__delay');
						break;
					case 'Limit':
						add_action('admin_notices', 'admin_notice__limit');
						break;
					default:
						if (isset($data[$id]['score']) && !empty($data[$id]['score'])) {
							$data[$id]['prev_score'] = $data[$id]['score'];
						}
		
						$data[$id]['score'] = $response['body'];
						add_action('admin_notices', 'admin_notice__success');
						break;
				}
				
			} else {
				add_action('admin_notices', 'admin_notice__error');
			}

			update_option($this->parent->_token . '_data', $data);
		}
	}
	
	/**
	 * Leverage Browser Caching process function
	 * @since 1.1.0
	 * @return void
	 */
	public function cache_process() {
		$file = ABSPATH . '.htaccess';
		
		if (file_exists($file)) {
			if (is_writable($file)) {
				$file_content = file_get_contents(ABSPATH . '.htaccess');
				if (get_option($this->parent->_token . '_cache')) {
					if (!strstr($file_content, '# BEGIN PageSpeed.Today Browser Caching')) {
						$file_content .= "\n\n";
						$file_content .= '# BEGIN PageSpeed.Today Browser Caching' . "\n";
						$file_content .= '## EXPIRES CACHING ##' . "\n";
						$file_content .= '<IfModule mod_expires.c>' . "\n";
						$file_content .= 'ExpiresActive On' . "\n";
						$file_content .= 'ExpiresByType image/jpg "access 1 year"' . "\n";
						$file_content .= 'ExpiresByType image/jpeg "access 1 year"' . "\n";
						$file_content .= 'ExpiresByType image/gif "access 1 year"' . "\n";
						$file_content .= 'ExpiresByType image/png "access 1 year"' . "\n";
						$file_content .= 'ExpiresByType text/css "access 1 month"' . "\n";
						$file_content .= 'ExpiresByType text/html "access 1 month"' . "\n";
						$file_content .= 'ExpiresByType application/pdf "access 1 month"' . "\n";
						$file_content .= 'ExpiresByType text/x-javascript "access 1 month"' . "\n";
						$file_content .= 'ExpiresByType application/x-shockwave-flash "access 1 month"' . "\n";
						$file_content .= 'ExpiresByType image/x-icon "access 1 year"' . "\n";
						$file_content .= 'ExpiresDefault "access 1 month"' . "\n";
						$file_content .= '</IfModule>' . "\n";
						$file_content .= '## EXPIRES CACHING ##' . "\n";
						$file_content .= "\n";
						$file_content .= '<IfModule mod_headers.c>' . "\n";
						$file_content .= '# 1 Month for most static assets' . "\n";
						$file_content .= '<filesMatch ".(css|jpg|jpeg|png|gif|js|ico)$">' . "\n";
						$file_content .= 'Header set Cache-Control "max-age=2592000, public"' . "\n";
						$file_content .= '</filesMatch>' . "\n";
						$file_content .= '</IfModule>' . "\n";
						$file_content .= '# END PageSpeed.Today Browser Caching' . "\n";
						
						file_put_contents($file, $file_content);
					}
				} elseif(strstr($file_content, '# BEGIN PageSpeed.Today Browser Caching')) {
					$file_content = preg_replace('/^# BEGIN PageSpeed\.Today Browser Caching\n.*?\n# END PageSpeed\.Today Browser Caching\n?/mis', '', $file_content);
					file_put_contents($file, $file_content);
				}
			}
		}
	}
	
	/**
	 * Enable Compression process function
	 * @since 1.1.0
	 * @return void
	 */
	public function compression_process() {
		$file = ABSPATH . '.htaccess';
		
		if (file_exists($file)) {
			if (is_writable($file)) {
				$file_content = file_get_contents(ABSPATH . '.htaccess');
				if (get_option($this->parent->_token . '_compression')) {
					if (!strstr($file_content, '# BEGIN PageSpeed.Today Compression')) {
						$file_content .= "\n\n";
						$file_content .= '# BEGIN PageSpeed.Today Compression' . "\n";
						$file_content .= '<IfModule mod_deflate.c>' . "\n";
						$file_content .= 'AddOutputFilterByType DEFLATE text/plain' . "\n";
						$file_content .= 'AddOutputFilterByType DEFLATE text/html' . "\n";
						$file_content .= 'AddOutputFilterByType DEFLATE text/xml' . "\n";
						$file_content .= 'AddOutputFilterByType DEFLATE text/css' . "\n";
						$file_content .= 'AddOutputFilterByType DEFLATE application/xml' . "\n";
						$file_content .= 'AddOutputFilterByType DEFLATE application/xhtml+xml' . "\n";
						$file_content .= 'AddOutputFilterByType DEFLATE application/rss+xml' . "\n";
						$file_content .= 'AddOutputFilterByType DEFLATE application/javascript' . "\n";
						$file_content .= 'AddOutputFilterByType DEFLATE application/x-javascript' . "\n";
						$file_content .= '</IfModule>' . "\n";
						$file_content .= "\n";
						$file_content .= '<ifModule mod_gzip.c>' . "\n";
						$file_content .= 'mod_gzip_on Yes' . "\n";
						$file_content .= 'mod_gzip_dechunk Yes' . "\n";
						$file_content .= 'mod_gzip_item_include file .(html?|txt|css|js|php|pl)$' . "\n";
						$file_content .= 'mod_gzip_item_include handler ^cgi-script$' . "\n";
						$file_content .= 'mod_gzip_item_include mime ^text/.*' . "\n";
						$file_content .= 'mod_gzip_item_include mime ^application/x-javascript.*' . "\n";
						$file_content .= 'mod_gzip_item_exclude mime ^image/.*' . "\n";
						$file_content .= 'mod_gzip_item_exclude rspheader ^Content-Encoding:.*gzip.*' . "\n";
						$file_content .= '</IfModule>' . "\n";
						$file_content .= '# END PageSpeed.Today Compression' . "\n";
						
						file_put_contents($file, $file_content);
					}
				} elseif(strstr($file_content, '# BEGIN PageSpeed.Today Compression')) {
					$file_content = preg_replace('/^# BEGIN PageSpeed\.Today Compression\n.*?\n# END PageSpeed\.Today Compression\n?/mis', '', $file_content);
					file_put_contents($file, $file_content);
				}
			}
		}
	}
	
	/**
	 * License check function
	 * @return void
	 */
	public function check_license() {
		if (isset($_POST[$this->parent->_token . '_url']) && !empty($_POST[$this->parent->_token . '_url'])) {
			$url = urlencode(urlencode(urlencode($_POST[$this->parent->_token . '_url'])));
			$response = wp_remote_get('http://api.pagespeed.today/v1/license/' . $url, array(
				'timeout' => 120
			));
			$data = get_option($this->parent->_token . '_data');
			if (is_array($response) && !is_wp_error($response) && !empty($response['body'])) {
				switch ($response['body']) {
					case 'OK':
						$data['license'] = 1;
						add_action('admin_notices', 'admin_notice__license_success');
						break;
					default:
						$data['license'] = 0;
						add_action('admin_notices', 'admin_notice__license_error');
						break;
				}
				
			} else {
				add_action('admin_notices', 'admin_notice__error');
			}

			update_option($this->parent->_token . '_data', $data);
		}
	}

	/**
	 * Restore backup function
	 * @return void
	 */
	public function restore_backup() {
		if (isset($_POST[$this->parent->_token . '_backup_key'])) {
			$key = $_POST[$this->parent->_token . '_backup_key'];
			$data = get_option($this->parent->_token . '_data');
			if (isset($data['backups'][$key]['path']) && !empty($data['backups'][$key]['path'])) {
				$backup_dir = $data['backups'][$key]['path'];
				if (is_dir($backup_dir)) {
					$manifest = file_get_contents($backup_dir . 'MANIFEST');
					if ($manifest) {
						$manifest = explode("\n", $manifest);
						if (sizeof($manifest) > 7) {
							foreach($manifest as $key => $value) {
								if ($key > 7) {
									$value = explode(': ', $value);
									$value[1] = parse_url($value[1]);
									if ($value[1]['host'] == $_SERVER['HTTP_HOST']) {
										@unlink(ABSPATH . urldecode($value[1]['path']));
										@copy($backup_dir . $value[0], ABSPATH . urldecode($value[1]['path']));
									}
								}
							}
						}
					}

					add_action('admin_notices', 'admin_notice__success');
				}
			}
		}
	}

	/**
	 * Delete backup function
	 * @return void
	 */
	public function delete_backup() {
		if (isset($_POST[$this->parent->_token . '_backup_key']) && !empty($_POST[$this->parent->_token . '_backup_key'])) {
			$key = $_POST[$this->parent->_token . '_backup_key'];
			$data = get_option($this->parent->_token . '_data');
			if (isset($data['backups'][$key]['path']) && !empty($data['backups'][$key]['path'])) {
				$backup_dir = $data['backups'][$key]['path'];
				$this->rrmdir($backup_dir, true);
				unset($data['backups'][$key]);
				update_option($this->parent->_token . '_data', $data);
				add_action('admin_notices', 'admin_notice__success');
			}
		}
	}

	/**
	 * Create backup function
	 * @return $backup_dir
	 */
	public function make_backup($manifest, $url) {
		$backup_dir = PAGESPEED_TODAY_PLUGIN_DIR . 'backups/' . md5($url . date("Y-m-d H:i:s")) . '/';
		if (!is_dir($backup_dir)) {
			mkdir($backup_dir);
			mkdir($backup_dir . 'image');
			mkdir($backup_dir . 'css');
			mkdir($backup_dir . 'js');
		}

		foreach($manifest as $key => $value) {
			if ($key > 7) {
				$value = explode(': ', $value);
				if (isset($value[1]) && !empty($value[1])) {
					$value[1] = parse_url($value[1]);
					if ($value[1]['host'] == $_SERVER['HTTP_HOST']) {
						@copy(ABSPATH . urldecode($value[1]['path']), $backup_dir . $value[0]);
					}
				}
			}
		}

		@copy(PAGESPEED_TODAY_PLUGIN_DIR . 'tmp/MANIFEST', $backup_dir . 'MANIFEST');
		return $backup_dir;
	}

	/**
	 * Add settings page to admin menu
	 * @return void
	 */
	public function add_menu_item() {
		$page = add_options_page(__('PageSpeed.today', 'pagespeed-today') , __('PageSpeed.today', 'pagespeed-today') , 'manage_options', $this->parent->_token . '_settings', array(
			$this,
			'settings_page'
		));
		add_action('admin_print_styles-' . $page, array(
			$this,
			'settings_assets'
		));
	}

	/**
	 * Load settings JS & CSS
	 * @return void
	 */
	public function settings_assets() {
		wp_register_script($this->parent->_token . '-settings-js', $this->parent->assets_url . 'js/settings.js', array(
			'jquery'
		) , '1.0.0');
		wp_enqueue_script($this->parent->_token . '-settings-js');
		wp_register_script($this->parent->_token . '-datatables-js', $this->parent->assets_url . 'js/datatables.min.js');
		wp_enqueue_script($this->parent->_token . '-datatables-js');
		wp_register_style($this->parent->_token . '-datatables', $this->parent->assets_url . 'css/datatables.min.css');
		wp_enqueue_style($this->parent->_token . '-datatables');
	}

	/**
	 * Add settings link to plugin list table
	 * @param  array $links Existing links
	 * @return array Modified links
	 */
	public function add_settings_link($links) {
		$settings_link = '<a href="options-general.php?page=' . $this->parent->_token . '_settings">' . __('Settings', 'pagespeed-today') . '</a>';
		array_push($links, $settings_link);
		return $links;
	}

	/**
	 * Build settings fields
	 * @return array Fields to be displayed on settings page
	 */
	private function settings_fields() {
		$settings['dashboard'] = array(
			'title' => __('Dashboard', 'pagespeed-today')
		);
		$settings['optimization'] = array(
			'title' => __('Optimization', 'pagespeed-today')
		);
		$settings['backups'] = array(
			'title' => __('Backups', 'pagespeed-today')
		);
		$settings['settings'] = array(
			'title' => __('Settings', 'pagespeed-today') ,
			'description' => __('<br />Only applies to resources hosted locally. Resources hosted externally are not optimized by this plugin.<br />', 'pagespeed-today') ,
			'fields' => array(
				array(
					'id' => 'image',
					'label' => __('Optimize images', 'pagespeed-today') ,
					'description' => __('This option is recommended when PageSpeed Insights detects that the images on the page can be optimized to reduce their filesize without significantly impacting their visual quality.', 'pagespeed-today') ,
					'type' => 'checkbox',
					'default' => ''
				) ,
				array(
					'id' => 'css',
					'label' => __('Minify CSS', 'pagespeed-today') ,
					'description' => __('This option is recommended when PageSpeed Insights detects that the size of some of your CSS resources could be reduced through minification.', 'pagespeed-today') ,
					'type' => 'checkbox',
					'default' => ''
				) ,
				array(
					'id' => 'js',
					'label' => __('Minify JavaScript', 'pagespeed-today') ,
					'description' => __('This option is recommended when PageSpeed Insights detects that the size of some of your JavaScript resources could be reduced through minification.', 'pagespeed-today') ,
					'type' => 'checkbox',
					'default' => ''
				),
				array(
					'id' => 'cache',
					'label' => __('Leverage Browser Caching', 'pagespeed-today') ,
					'description' => __('This option is recommended when PageSpeed Insights detects that the response from your server does not include caching headers or if the resources are specified to be cached for only a short time.', 'pagespeed-today') ,
					'type' => 'checkbox',
					'default' => ''
				),
				array(
					'id' => 'compression',
					'label' => __('Enable compression', 'pagespeed-today') ,
					'description' => __('This option is recommended when PageSpeed Insights detects that compressible resources were served without gzip compression.', 'pagespeed-today') ,
					'type' => 'checkbox',
					'default' => ''
				)
			)
		);
		
		$settings = apply_filters($this->parent->_token . '_settings_fields', $settings);
		return $settings;
	}

	/**
	 * Register plugin settings
	 * @return void
	 */
	public function register_settings() {
		if (is_array($this->settings)) {

			// Check posted/selected tab
			$current_section = '';
			if (isset($_POST['tab']) && $_POST['tab']) {
				$current_section = $_POST['tab'];
			} else {
				if (isset($_GET['tab']) && $_GET['tab']) {
					$current_section = $_GET['tab'];
				}
			}

			foreach($this->settings as $section => $data) {
				if ($current_section && $current_section != $section) {
					continue;
				}

				// Add section to page
				add_settings_section($section, @$data['title'], array(
					$this,
					'settings_section'
				) , $this->parent->_token . '_settings');
				if (isset($data['fields']) && !empty($data['fields'])) {
					foreach($data['fields'] as $field) {

						// Validation callback for field
						$validation = '';
						if (isset($field['callback'])) {
							$validation = $field['callback'];
						}

						// Register field
						$option_name = $this->base . $field['id'];
						register_setting($this->parent->_token . '_settings', $option_name, $validation);

						// Add field to page
						add_settings_field($field['id'], $field['label'], array(
							$this->parent->admin,
							'display_field'
						) , $this->parent->_token . '_settings', $section, array(
							'field' => $field,
							'prefix' => $this->base
						));
					}
				}
			}
		}
	}

	/**
	 * Display settings section content
	 * @return void
	 */
	public function settings_section($section) {
		$html = '<p> ' . $this->settings[$section['id']]['description'] . '</p>' . "\n";
		echo $html;
	}

	/**
	 * Display optimization section content
	 * @return string with html content
	 */
	public function settings_section_optimization() {
		$data = get_option($this->parent->_token . '_data');
		$html = '';
		
		if (isset($data['license']) && $data['license'] == 1) {
			$html .= '<div class="controls">
                    <button id="bulk-scan" class="button-secondary">Bulk Scan</button>
                    <button id="bulk-optimization" class="button-secondary">Bulk Optimization</button>
                    <div class="progress">
                        <progress max="100" value="0"></progress>
                        <div class="progress-value"></div>
                        <div class="progress-bg">
                            <div class="progress-bar"></div>
                        </div>
                    </div>
                    <p class="message">' . __('The page will reload itself once the process has been successfully completed.', 'pagespeed-today') . '</p>
                </div>';
		}
		
       $html .= '<table id="post-table" class="display" cellspacing="0" width="100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>PageSpeed Score</th>
                            <th>Optimized</th>
                            <th>Saved</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>PageSpeed Score</th>
                            <th>Optimized</th>
                            <th>Saved</th>
                            <th>Action</th>
                        </tr>
                    </tfoot>
                    <tbody>';
		if (get_option('page_on_front') == 0) {
			$page_url = $this->parent->site_url;
			$html .= '<tr>';
			$html .= '<td>' . 0 . '</td>';
			$html .= '<td><a href="' . $page_url . '">Home page</a></td>';
			$html .= '<td data-sort="' . (isset($data[0]['score']) ? $data[0]['score'] : '0') . '">' . (isset($data[0]['score']) ? $data[0]['score'] : 'Scan required') . ' ' . (isset($data[0]['prev_score']) && $data[0]['score'] > $data[0]['prev_score'] ? '<span class="green">&uarr;</span>' : (isset($data[0]['prev_score']) && $data[0]['score'] < $data[0]['prev_score'] ? '<span class="red">&darr;</span>' : (isset($data[0]['prev_score']) && $data[0]['score'] == $data[0]['prev_score'] ? '<span class="yellow">&#9679;</span>' : ''))) . '</td>';
			$html .= '<td>' . (isset($data[0]['count']) ? $data[0]['count'] : '0') . '</td>';
			$html .= '<td data-sort="' . (isset($data[0]['size']) ? $data[0]['size'] : '0') . '">' . (isset($data[0]['size']) ? $this->bytes_to_string($data[0]['size']) : '0') . '</td>';
			$html .= '<td>
                        <form method="post" action="options-general.php?page=pagespeed_today_settings&tab=optimization">
                            <input type="hidden" name="pagespeed_today_url" value="' . $page_url . '">
                            <input type="hidden" name="pagespeed_today_id" value="' . 0 . '">
                            <input type="hidden" name="pagespeed_today_post_title" value="Home page">
                            <button class="button-secondary" name="pagespeed_today_action" value="scan">Scan</button>
                            <button class="button-primary" name="pagespeed_today_action" value="process">Optimize</button>
                        </form>
                      </td>';
			$html .= '</tr>';
		}

		foreach(get_pages() as $page) {
			$html .= '<tr>';
			$html .= '<td>' . $page->ID . '</td>';
			$html .= '<td><a href="' . get_permalink($page->ID) . '">' . (isset($page->post_title) ? esc_html(strip_tags($page->post_title)) : '(Empty title)') . '</a></td>';
			$html .= '<td data-sort="' . (isset($data[$page->ID]['score']) ? $data[$page->ID]['score'] : '0') . '">' . (isset($data[$page->ID]['score']) ? $data[$page->ID]['score'] : 'Scan required') . ' ' . (isset($data[$page->ID]['prev_score']) && $data[$page->ID]['score'] > $data[$page->ID]['prev_score'] ? '<span class="green">&uarr;</span>' : (isset($data[$page->ID]['prev_score']) && $data[$page->ID]['score'] < $data[$page->ID]['prev_score'] ? '<span class="red">&darr;</span>' : (isset($data[$page->ID]['prev_score']) && $data[$page->ID]['score'] == $data[$page->ID]['prev_score'] ? '<span class="yellow">&#9679;</span>' : ''))) . '</td>';
			$html .= '<td>' . (isset($data[$page->ID]['count']) ? $data[$page->ID]['count'] : '0') . '</td>';
			$html .= '<td data-sort="' . (isset($data[$page->ID]['size']) ? $data[$page->ID]['size'] : '0') . '">' . (isset($data[$page->ID]['size']) ? $this->bytes_to_string($data[$page->ID]['size']) : '0') . '</td>';
			$html .= '<td>
                        <form method="post" action="options-general.php?page=pagespeed_today_settings&tab=optimization">
                            <input type="hidden" name="pagespeed_today_url" value="' . get_permalink($page->ID) .  '">
                            <input type="hidden" name="pagespeed_today_id" value="' . $page->ID . '">
                            <input type="hidden" name="pagespeed_today_post_title" value="' . esc_html(strip_tags($page->post_title)) . '">
                            <button class="button-secondary" name="pagespeed_today_action" value="scan">Scan</button>
                            <button class="button-primary" name="pagespeed_today_action" value="process">Optimize</button>
                        </form>
                      </td>';
			$html .= '</tr>';
		}

		foreach(get_posts(['numberposts' => - 1]) as $post) {
			$html .= '<tr>';
			$html .= '<td>' . $post->ID . '</td>';
			$html .= '<td><a href="' . get_permalink($post->ID) . '">' . (isset($post->post_title) ? esc_html(strip_tags($post->post_title)) : '(Empty title)') . '</a></td>';
			$html .= '<td data-sort="' . (isset($data[$post->ID]['score']) ? $data[$post->ID]['score'] : '0') . '">' . (isset($data[$post->ID]['score']) ? $data[$post->ID]['score'] : 'Scan required') . ' ' . (isset($data[$post->ID]['prev_score']) && $data[$post->ID]['score'] > $data[$post->ID]['prev_score'] ? '<span class="green">&uarr;</span>' : (isset($data[$post->ID]['prev_score']) && $data[$post->ID]['score'] < $data[$post->ID]['prev_score'] ? '<span class="red">&darr;</span>' : (isset($data[$post->ID]['prev_score']) && $data[$post->ID]['score'] == $data[$post->ID]['prev_score'] ? '<span class="yellow">&#9679;</span>' : ''))) . '</td>';
			$html .= '<td>' . (isset($data[$post->ID]['count']) ? $data[$post->ID]['count'] : '0') . '</td>';
			$html .= '<td data-sort="' . (isset($data[$post->ID]['size']) ? $data[$post->ID]['size'] : '0') . '">' . (isset($data[$post->ID]['size']) ? $this->bytes_to_string($data[$post->ID]['size']) : '0') . '</td>';
			$html .= '<td>
                        <form method="post" action="options-general.php?page=pagespeed_today_settings&tab=optimization">
                            <input type="hidden" name="pagespeed_today_url" value="' . get_permalink($post->ID) . '">
                            <input type="hidden" name="pagespeed_today_id" value="' . $post->ID . '">
                            <input type="hidden" name="pagespeed_today_post_title" value="' . esc_html(strip_tags($post->post_title)) . '">
                            <button class="button-secondary" name="pagespeed_today_action" value="scan">Scan</button>
                            <button class="button-primary" name="pagespeed_today_action" value="process">Optimize</button>
                        </form>
                      </td>';
			$html .= '</tr>';
		}
		
		foreach(get_posts(['post_type' => 'product', 'numberposts' => - 1]) as $product) {
			$html .= '<tr>';
			$html .= '<td>' . $product->ID . '</td>';
			$html .= '<td><a href="' . get_permalink($product->ID) . '">' . (isset($product->post_title) ? esc_html(strip_tags($product->post_title)) : '(Empty title)') . '</a></td>';
			$html .= '<td data-sort="' . (isset($data[$product->ID]['score']) ? $data[$product->ID]['score'] : '0') . '">' . (isset($data[$product->ID]['score']) ? $data[$product->ID]['score'] : 'Scan required') . ' ' . (isset($data[$product->ID]['prev_score']) && $data[$product->ID]['score'] > $data[$product->ID]['prev_score'] ? '<span class="green">&uarr;</span>' : (isset($data[$product->ID]['prev_score']) && $data[$product->ID]['score'] < $data[$product->ID]['prev_score'] ? '<span class="red">&darr;</span>' : (isset($data[$product->ID]['prev_score']) && $data[$product->ID]['score'] == $data[$product->ID]['prev_score'] ? '<span class="yellow">&#9679;</span>' : ''))) . '</td>';
			$html .= '<td>' . (isset($data[$post->ID]['count']) ? $data[$post->ID]['count'] : '0') . '</td>';
			$html .= '<td data-sort="' . (isset($data[$product->ID]['size']) ? $data[$product->ID]['size'] : '0') . '">' . (isset($data[$product->ID]['size']) ? $this->bytes_to_string($data[$product->ID]['size']) : '0') . '</td>';
			$html .= '<td>
                        <form method="post" action="options-general.php?page=pagespeed_today_settings&tab=optimization">
                            <input type="hidden" name="pagespeed_today_url" value="' . get_permalink($product->ID) . '">
                            <input type="hidden" name="pagespeed_today_id" value="' . $product->ID . '">
                            <input type="hidden" name="pagespeed_today_post_title" value="' . esc_html(strip_tags($product->post_title)) . '">
                            <button class="button-secondary" name="pagespeed_today_action" value="scan">Scan</button>
                            <button class="button-primary" name="pagespeed_today_action" value="process">Optimize</button>
                        </form>
                      </td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table>';
		
		return $html;
	}

	/**
	 * Display backups section content
	 * @return string with html content
	 */
	public function settings_section_backups() {
		$data = get_option($this->parent->_token . '_data');
		$html = '<div class="controls">
                    <button id="bulk-restore" class="button-secondary">Bulk Restore</button>
                    <div class="progress">
                        <progress max="100" value="0"></progress>
                        <div class="progress-value"></div>
                        <div class="progress-bg">
                            <div class="progress-bar"></div>
                    	</div>
                    </div>
                    <p class="message">' . __('The page will reload itself once the process has been successfully completed.', 'pagespeed-today') . '</p>
                </div>
                <table id="backup-table" class="display" cellspacing="0" width="100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </tfoot>
                    <tbody>';
		if ($data && isset($data['backups']) && !empty($data['backups'])) {
			foreach($data['backups'] as $key => $backup) {
				$html .= '<tr>';
				$html .= '<td>' . $backup['id'] . '</td>';
				$html .= '<td><a href="' . $backup['id'] . '">' . $backup['post_title'] . '</a></td>';
				$html .= '<td>' . $backup['date'] . '</td>';
				$html .= '<td>
                            <form method="post" action="options-general.php?page=pagespeed_today_settings&tab=backups">
                                <input type="hidden" name="pagespeed_today_backup_key" value="' . $key . '">
                                <button class="button-secondary" name="pagespeed_today_action" value="restore_backup">Restore</button>
                                <button class="button-primary" name="pagespeed_today_action" value="delete_backup">Delete</button>
                            </form>
                        </td>';
				$html .= '</tr>';
			}
		}

		$html .= '</tbody></table>';
		
		return $html;
	}
	
	/**
	 * Display settings section content
	 * @return string with html content
	 */
	public function settings_section_settings() {
		$tab = '';
		if (isset($_GET['tab']) && $_GET['tab']) {
			$tab .= $_GET['tab'];
		}
		
		$this->cache_process();
		$this->compression_process();
		
		$html = '<form method="post" action="options.php" enctype="multipart/form-data">' . "\n";
	
		// Get settings fields
		ob_start();
		settings_fields($this->parent->_token . '_settings');
		do_settings_sections($this->parent->_token . '_settings');
		$html .= ob_get_clean();
		
		$html .= '<p class="submit">' . "\n";
		$html .= '<input type="hidden" name="tab" value="' . esc_attr($tab) . '" />' . "\n";
		$html .= '<input name="Submit" type="submit" class="button-primary" value="' . esc_attr(__('Save Settings', 'pagespeed-today')) . '" />' . "\n";
		$html .= '</p>' . "\n";
		$html .= '</form>' . "\n";	
		
		$html .= '<div class="premium_notice">';
		$html .= '<h1>Premium Features include:</h1>
				<ul>
					<li> - Unlimited Daily Page Scans</li>
					<li> - Unlimited Daily Page Optimizations</li>
					<li> - Premium Support</li>
			    </ul>
			    <p>Optimization process takes place on our server. To be able to sustain the optimization load from all of our customers we only allow premium users to optimize unlimited number of pages daily.
			    </br>
				<form method="post" action="options-general.php?page=pagespeed_today_settings&tab=settings">
					<input type="hidden" name="pagespeed_today_url" value="' . site_url() . '">
					<a class="button-secondary" target="_blank" href="https://pagespeed.today/order?url=' . $_SERVER['HTTP_HOST'] . '">Upgrade To Premium</a>
                   	<button class="button-primary" name="pagespeed_today_action" value="check_license">Check License</button>
				</form>';
		$html .= '</div>';
		
		return $html;
	}

	/**
	 * Load settings page content
	 * @return void
	 */
	public function settings_page() {

		// Build page HTML
		$html = '<div class="wrap" id="' . $this->parent->_token . '_settings">' . "\n";
		$html .= '<div class="loading">Loading&#8230;</div>' . "\n";
		$html .= '<img src="' . $this->parent->assets_url . 'img/logo.png">' . "\n";
		$tab = '';
		if (isset($_GET['tab']) && $_GET['tab']) {
			$tab .= $_GET['tab'];
		}

		// Show page tabs
		if (is_array($this->settings) && 1 < count($this->settings)) {
			$html .= '<h2 class="nav-tab-wrapper">' . "\n";
			$c = 0;
			foreach($this->settings as $section => $data) {

				// Set tab class
				$class = 'nav-tab';
				if (!isset($_GET['tab'])) {
					if (0 == $c) {
						$class.= ' nav-tab-active';
					}
				} else {
					if (isset($_GET['tab']) && $section == $_GET['tab']) {
						$class.= ' nav-tab-active';
					}
				}

				// Set tab link
				$tab_link = add_query_arg(array(
					'tab' => $section
				));
				if (isset($_GET['settings-updated'])) {
					$tab_link = remove_query_arg('settings-updated', $tab_link);
				}

				// Output tab
				$html .= '<a href="' . $tab_link . '" class="' . esc_attr($class) . '">' . esc_html($data['title']) . '</a>' . "\n";
				++$c;
			}

			$html .= '</h2>' . "\n";
		}

		switch ($tab) {
			case 'optimization':
				$html .= $this->settings_section_optimization();
				break;
	
			case 'backups':
				$html .= $this->settings_section_backups();
				break;
	
			case 'settings':
				$html .= $this->settings_section_settings();
				break;
	
			default:
				$data = get_option($this->parent->_token . '_data');
				if (!isset($data['total']['files']) || empty($data['total']['files'])) {
					$data['total']['files'] = 0;
				}
	
				if (!isset($data['total']['size']) || empty($data['total']['size'])) {
					$data['total']['size'] = 0;
				}
				
				if (isset($data['license']) && $data['license'] == 1) {
	                 $html .= "<div>
	                        <div class='stats'>
	                            <div class='content'>
	                                <h2>Welcome to PageSpeed.today.</h2>
	                                <h3>This plugin helps you optimize your images and minify CSS & JS resources across the whole website.</h3>
	                                <p style='color:#3483de;font-weight: 600;'>5 simple steps to getting started:</p>
	                                <p>1. Go to <span style='color:#3483de'>\"Settings\"</span>, pick the optimization options and click <span style='color:#3483de'>\"Save Settings\"</span>.</p>
	                                <p>2. Go to <span style='color:#3483de'>\"Optimization\"</span> tab and click <span style='color:#3483de'>\"Bulk scan\"</span> to scan all of your web pages to check for the current PageSpeed scores. Make sure to leave the browser tab open until the scan is completed. Once that is done, your page will refresh itself and you should see your current Desktop <span style='color:#3483de'>\"PageSpeed\"</span> scores next to the web page URL.</p>
	                                <p>3. In the <span style='color:#3483de'>\"Optimization\"</span> window you can now perform <span style='color:#3483de'>\"Bulk Optimization\"</span>. This process can take a while as it goes through each one of your pages optimising it's resources and images. Keep the tab open for the process to complete successfully. Once that is done, your page will refresh itself.</p>
	                                <p>4. Go to the <span style='color:#3483de'>\"Dashboard\"</span> (which is where you are reading this now) and in the right field you should be able to see the total number of files optimized and the amount of space saved for your hosting server (accumulative page size reduction across the whole website).</p>
	                                <p>5. Wait 24 hours before running a <span style='color:#3483de'>\"Bulk Scan\"</span> again to check for the improvement in your scores.</p>
	                                <p>&nbsp</p>
	                                <p>If you have any questions or suggestions, email us at: <a href='mailto:hello@pagespeed.today'>hello@pagespeed.today</a>.</p>
	                                <p>All the best from <a target='_blank' href='https://pagespeed.today'>PageSpeed.today</a> development team!</p>
	                            </div>
	                        </div>
	                        <div class='stats'>";
				} else {
					$html .= "<div>
	                        <div class='stats'>
	                            <div class='content'>
	                                <h2>Welcome to PageSpeed.today.</h2>
	                                <h3>This plugin helps you optimize your images and minify CSS & JS resources across the whole website.</h3>
	                                <p style='color:#3483de;font-weight: 600;'>5 simple steps to getting started:</p>
	                                <p>1. Go to <span style='color:#3483de'>\"Settings\"</span>, pick the optimization options and click <span style='color:#3483de'>\"Save Settings\"</span>.</p>
	                                <p>2. Go to <span style='color:#3483de'>\"Optimization\"</span> tab and click <span style='color:#3483de'>\"Scan\"</span> to scan the page of your choice for it's PageSpeed scores. Make sure to leave the browser tab open until the scan is completed. Once that is done, your page will refresh itself and you should see your current Desktop <span style='color:#3483de'>\"PageSpeed\"</span> scores next to the web page URL. Free version includes 20 scans per day.</p>
	                                <p>3. In the <span style='color:#3483de'>\"Optimization\"</span> window you can now perform <span style='color:#3483de'>\"Optimization\"</span>. Keep the tab open for the process to complete successfully. Once that is done, your page will refresh itself. Free version includes 5 Optimization credits daily.</p>
	                                <p>4. Go to the <span style='color:#3483de'>\"Dashboard\"</span> (which is where you are reading this now) and in the right field you should be able to see the total number of files optimized and the amount of space saved for your hosting server (accumulative page size reduction across all optimized URLs).</p>
	                                <p>5. Wait 24 hours before running a <span style='color:#3483de'>\"Scan\"</span> for optimized pages again to check for the improvement in your scores.</p>
	                                <p>&nbsp</p>
	                                <p>If you have any questions or suggestions, email us at: <a href='mailto:hello@pagespeed.today'>hello@pagespeed.today</a>.</p>
	                                <p>All the best from <a target='_blank' href='https://pagespeed.today'>PageSpeed.today</a> development team!</p>
	                            </div>
	                        </div>
	                        <div class='stats'>";
				}
	
				if (isset($data['total']['files']) && !empty($data['total']['files']) && isset($data['total']['size']) && !empty($data['total']['size'])) {
					$html .= "<div class='content'>
								<h2>Total Optimization results</h2>
								<h3>Total files optimized: " . $data['total']['files'] . "</h3>
	                            <h3>Total website size reduced by: " . $this->bytes_to_string($data['total']['size']) . "</h3>
	                            <p>These statistics show the number of images, CSS and JavaScript resources that have been successfully optimized and how much space has been saved on your server.</p>
	                        </div>";
				} else {
					$html .= "<div class='content'>
								<h2>Total Optimization results</h2>
								<h3>Here you will be able to see exactly how many resources have been optimized and by how much the size of the website has been reduced after you complete <span style='color:#3483de'>\"Step 3\"</span> of the Welcome guide.</h3>
							</div>";
				}
	
				$html .= "</div></div>";
				break;
		}

		$html .= '</div>' . "\n";
		
		echo $html;
	}

	/**
	 * Bytes to string function
	 * @return string
	 */
	public function bytes_to_string($bytes = null) {
		if (!is_null($bytes)) {
			if ($bytes != 0) {
				$base = log($bytes) / log(1024);
				$suffix = array(
					"B",
					"KB",
					"MB",
					"GB",
					"TB"
				);
				$f_base = floor($base);
				return round(pow(1024, $base - floor($base)) , 1) . ' ' . $suffix[$f_base];
			} else {
				return 0 . ' B';
			}
		}
	}

	/**
	 * Main PageSpeed_today_Settings Instance
	 *
	 * Ensures only one instance of PageSpeed_today_Settings is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see PageSpeed_today()
	 * @return Main PageSpeed_today_Settings instance
	 */
	public static function instance($parent) {
		if (is_null(self::$_instance)) {
			self::$_instance = new self($parent);
		}

		return self::$_instance;
	} // End instance()
	
	/**
	 * Cloning is forbidden.
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?') , $this->parent->_version);
	} // End __clone()
	
	/**
	 * Unserializing instances of this class is forbidden.
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?') , $this->parent->_version);
	} // End __wakeup()
}
