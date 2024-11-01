<?php
/**
 * Versions compatibility.
 * Class EnitureShippingAccountCaptureGuard
 */
if ( ! class_exists( 'EnitureShippingAccountCaptureGuard' ) ) {

	class EnitureShippingAccountCaptureGuard {

		private $plugin_name;
		private $php_version;
		private $wp_version;
		private $wc_version;
		private $errors;
		private $warnings;

		/**
		 * @param string      $plugin_name
		 * @param string      $php_version
		 * @param string      $wp_version
		 * @param string|null $wc_version
		 * @return void
		 */
		private function __construct( $plugin_name, $php_version, $wp_version, $wc_version ) {
			$this->plugin_name = $plugin_name;
			$this->php_version = $php_version;
			$this->wp_version  = $wp_version;
			$this->wc_version  = $wc_version;

			// Hook admin_notices always since errors can be added lately
			add_action( 'admin_notices', array( $this, 'eniture_show_for_notices' ) );
		}

		/**
		 * @param string      $plugin_name
		 * @param string      $php_version
		 * @param string      $wp_version
		 * @param string|null $wc_version
		 * @return void
		 */
		public static function eniture_check_prerequisites( $plugin_name, $php_version, $wp_version, $wc_version ) {
			$instance = new self( $plugin_name, $php_version, $wp_version, $wc_version );
			return $instance->eniture_check_for_prerequisites();
		}

		/**
		 * Show notices
		 */
		public function eniture_show_for_notices() {
			$this->eniture_show_notices( $this->errors, 'error' );
			$this->eniture_show_notices( $this->warnings, 'warning' );
		}

		/**
		 * Check for errors.
		 */
		public function eniture_check_woocommerce_for_version() {
			$wc_version = defined( 'WC_VERSION' ) ? WC_VERSION : null;

			if ( ! isset( $wc_version ) || version_compare( $wc_version, $this->wc_version, '<' ) ) {
				$this->errors[] = "You are running an outdated WooCommerce version".(isset($wc_version) ? " ".$wc_version : null).".
                 {plugin_name} requires WooCommerce {wc_version}+.
                 Consider updating to a modern WooCommerce version.";
				return;
			}

			if(empty(WC_Blocks_Utils::has_block_in_page( wc_get_page_id('checkout'), 'woocommerce/checkout' ))){
				$this->errors[] = "The plugin {plugin_name} only works on WooCommerce Checkout block.";
				return;
			}
		}

		/**
		 * @return void
		 */
		public function eniture_check_for_prerequisites() {
			global $wp_version;
			$this->errors   = array();
			$this->warnings = array();

			if ( version_compare( $phpv = PHP_VERSION, $this->php_version, '<' ) ) {
				$this->errors[] = "You are running an outdated PHP version {$phpv}. 
                 {plugin_name} requires PHP {php_version}+. 
                 Contact your hosting support to switch to a newer PHP version.";
			}

			if ( isset( $wp_version ) && version_compare( $wp_version, $this->wp_version, '<' ) ) {
				$this->errors[] = "You are running an outdated WordPress version {$wp_version}.
                 {plugin_name} is tested with WordPress {wp_version}+.
                 Consider updating to a modern WordPress version.";
			}

			if ( isset( $this->wc_version ) ) {
				if ( ! self::is_woocommerce_active() ) {
					$this->errors[] = 'WooCommerce is not active. 
                     {plugin_name} requires WooCommerce to be installed and activated.';
				} elseif ( defined( 'WC_VERSION' ) || did_action( 'woocommerce_loaded' ) ) {
						$this->eniture_check_woocommerce_for_version();
				} else {
					add_action( 'woocommerce_loaded', array( $this, 'eniture_check_woocommerce_for_version' ) );
				}
			}

			return $this->errors;
		}

		/**
		 * Version compatibility errors list
		 *
		 * @param array $errors
		 * @return array
		 */
		public function eniture_version_compatibility( $errors ) {
			return array_merge( $errors, $this->errors );
		}

		/**
		 * Show notices
		 *
		 * @param array $notices
		 * @param $kind
		 */
		public function eniture_show_notices( $notices, $kind ) {
			if ( $notices ) {
				foreach ( $notices as $eniture_dismiss_id => $notice ) :
					?>
					<?php
					$eniture_dismiss_class = null;
					$eniture_dismiss_attr  = null;
					if ( is_string( $eniture_dismiss_id ) && ! empty( $eniture_dismiss_id ) ) {
						$eniture_dismiss_class = 'is-dismissible';
						$eniture_dismiss_attr  = 'data-dismissible=' . esc_html( $eniture_dismiss_id );
					}
					?>
					<div class="notice notice-<?php echo esc_html( $kind ); ?> <?php echo esc_attr( $eniture_dismiss_class ); ?>"
						<?php echo esc_attr($eniture_dismiss_attr); ?>
					>
						<?php
						$notice = strtr(
							$notice,
							array(
								'{plugin_name}' => $this->plugin_name,
								'{php_version}' => $this->php_version,
								'{wp_version}'  => $this->wp_version,
								'{wc_version}'  => $this->wc_version,
							)
						);
						?>
						<p><?php echo esc_html( $notice ); ?></p>
					</div>
				<?php endforeach; ?>
				<?php
			}
		}

		/**
		 * Condition check WooCommerce is active or not.
		 *
		 * @return bool
		 */
		public static function is_woocommerce_active() {
			static $active_plugins;

			if ( ! isset( $active_plugins ) ) {
				$active_plugins = (array) get_option( 'active_plugins', array() );
				if ( is_multisite() ) {
					$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
				}
			}

			return in_array( 'woocommerce/woocommerce.php', $active_plugins ) ||
				array_key_exists( 'woocommerce/woocommerce.php', $active_plugins );
		}
	}

}
