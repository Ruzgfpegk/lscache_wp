<?php
/**
 * The object cache class
 *
 *
 * @since      	1.8
 * @package    	LiteSpeed_Cache
 * @subpackage 	LiteSpeed_Cache/inc
 * @author     	LiteSpeed Technologies <info@litespeedtech.com>
 */

class LiteSpeed_Cache_Object
{
	private static $_instance ;

	private $_oc_data_file ;
	private $_conn ;
	private $_cfg_enabled ;
	private $_cfg_host ;
	private $_cfg_port ;
	private $_cfg_admin ;
	private $_default_life = 360 ;

	private $_global_groups ;
	private $_non_persistent_groups ;

	/**
	 * Init
	 *
	 * @since  1.8
	 * @access private
	 */
	private function __construct( $cfg = false )
	{
		defined( 'LSCWP_LOG' ) && LiteSpeed_Cache_Log::debug2( 'Object: init' ) ;

		$this->_oc_data_file = WP_CONTENT_DIR . '/.object-cache.ini' ;

		if ( $cfg ) {
			$this->_cfg_host = $cfg[ LiteSpeed_Cache_Config::OPID_CACHE_OBJECT_HOST ] ;
			$this->_cfg_port = $cfg[ LiteSpeed_Cache_Config::OPID_CACHE_OBJECT_PORT ] ;
			$this->_cfg_life = $cfg[ LiteSpeed_Cache_Config::OPID_CACHE_OBJECT_LIFE ] ;
			$this->_cfg_admin = $cfg[ LiteSpeed_Cache_Config::OPID_CACHE_OBJECT_ADMIN ] ;
			$this->_global_groups = explode( "\n", $cfg[ LiteSpeed_Cache_Config::ITEM_OBJECT_GLOBAL_GROUPS ] ) ;
			$this->_non_persistent_groups = explode( "\n", $cfg[ LiteSpeed_Cache_Config::ITEM_OBJECT_NON_PERSISTENT_GROUPS ] ) ;
			$this->_cfg_enabled = $cfg[ LiteSpeed_Cache_Config::OPID_CACHE_OBJECT ] && class_exists( 'Memcached' ) && $this->_cfg_host && $this->_cfg_port ;

			defined( 'LSCWP_LOG' ) && LiteSpeed_Cache_Log::debug( 'Object: init with cfg result : ', $this->_cfg_enabled ) ;
		}
		elseif ( class_exists( 'LiteSpeed_Cache' ) ) {
			$this->_cfg_host = LiteSpeed_Cache::config( LiteSpeed_Cache_Config::OPID_CACHE_OBJECT_HOST ) ;
			$this->_cfg_port = LiteSpeed_Cache::config( LiteSpeed_Cache_Config::OPID_CACHE_OBJECT_PORT ) ;
			$this->_cfg_life = LiteSpeed_Cache::config( LiteSpeed_Cache_Config::OPID_CACHE_OBJECT_LIFE ) ;
			$this->_cfg_admin = LiteSpeed_Cache::config( LiteSpeed_Cache_Config::OPID_CACHE_OBJECT_ADMIN ) ;
			$this->_global_groups = explode( "\n", get_option( LiteSpeed_Cache_Config::ITEM_OBJECT_GLOBAL_GROUPS ) ) ;
			$this->_non_persistent_groups = explode( "\n", get_option( LiteSpeed_Cache_Config::ITEM_OBJECT_NON_PERSISTENT_GROUPS ) ) ;
			$this->_cfg_enabled = LiteSpeed_Cache::config( LiteSpeed_Cache_Config::OPID_CACHE_OBJECT ) && class_exists( 'Memcached' ) && $this->_cfg_host && $this->_cfg_port ;
		}
		elseif ( file_exists( $this->_oc_data_file ) ) { // Get cfg from oc_data_file
			$cfg = parse_ini_file( $this->_oc_data_file, true ) ;
			$this->_cfg_host = $cfg[ 'object_cache' ][ 'host' ] ;
			$this->_cfg_port = $cfg[ 'object_cache' ][ 'port' ] ;
			$this->_cfg_life = ! empty( $cfg[ 'object_cache' ][ 'life' ] ) ? $cfg[ 'object_cache' ][ 'life' ] : $this->_default_life ;
			$this->_cfg_admin = ! empty( $cfg[ 'object_cache' ][ 'cache_admin' ] ) ? $cfg[ 'object_cache' ][ 'cache_admin' ] : false ;
			$this->_global_groups = ! empty( $cfg[ 'object_cache' ][ 'global_groups' ] ) ? explode( ',', $cfg[ 'object_cache' ][ 'global_groups' ] ) : array() ;
			$this->_non_persistent_groups = ! empty( $cfg[ 'object_cache' ][ 'non_persistent_groups' ] ) ? explode( ',', $cfg[ 'object_cache' ][ 'non_persistent_groups' ] ) : array() ;
			$this->_cfg_enabled = class_exists( 'Memcached' ) && $this->_cfg_host && $this->_cfg_port ;
		}
		else {
			$this->_cfg_enabled = false ;
		}
	}

	/**
	 * Maintain WP object cache file
	 *
	 * @since  1.8
	 * @access public
	 */
	public function update_file( $keep, $options = false )
	{
		$wp_file = WP_CONTENT_DIR . '/object-cache.php' ;
		$ori_file = LSCWP_DIR . 'lib/object-cache.php' ;

		// To keep file
		if ( $keep ) {
			// Update data file
			$data = "[object_cache]"
				. "\nhost = " . $options[ LiteSpeed_Cache_Config::OPID_CACHE_OBJECT_HOST ]
				. "\nport = " . $options[ LiteSpeed_Cache_Config::OPID_CACHE_OBJECT_PORT ]
				. "\nlife = " . $options[ LiteSpeed_Cache_Config::OPID_CACHE_OBJECT_LIFE ]
				. "\ncache_admin = " . $options[ LiteSpeed_Cache_Config::OPID_CACHE_OBJECT_ADMIN ]
				. "\nglobal_groups = " . implode( ',', explode( "\n", $options[ LiteSpeed_Cache_Config::ITEM_OBJECT_GLOBAL_GROUPS ] ) )
				. "\nnon_persistent_groups = " . implode( ',', explode( "\n", $options[ LiteSpeed_Cache_Config::ITEM_OBJECT_NON_PERSISTENT_GROUPS ] ) )
				;
			Litespeed_File::save( $this->_oc_data_file, $data ) ;

			// Update cls file
			if ( ! file_exists( $wp_file ) || md5_file( $wp_file ) !== md5_file( $ori_file ) ) {
				defined( 'LSCWP_LOG' ) && LiteSpeed_Cache_Log::debug( 'Object: copying object-cache.php file to ' . $wp_file ) ;
				copy( $ori_file, $wp_file ) ;
			}
		}
		else {
			// To delete file
			if ( file_exists( $wp_file ) ) {
				defined( 'LSCWP_LOG' ) && LiteSpeed_Cache_Log::debug( 'Object: removing ' . $wp_file ) ;
				unlink( $wp_file ) ;
			}
			file_exists( $this->_oc_data_file ) && unlink( $this->_oc_data_file ) ;
		}
	}

	/**
	 * Try to build connection
	 *
	 * @since  1.8
	 * @access public
	 */
	public function test_connection()
	{
		if ( ! class_exists( 'Memcached' ) || ! $this->_cfg_host || ! $this->_cfg_port ) {
			return null ;
		}

		return $this->_connect() ;
	}

	/**
	 * Force to connect with this setting
	 * @return [type] [description]
	 */
	public function reconnect( $cfg )
	{
		defined( 'LSCWP_LOG' ) && LiteSpeed_Cache_Log::debug( 'Object: Reconnecting' ) ;

		self::$_instance = new self( $cfg ) ;

		$this->_conn = null ;
	}

	/**
	 * Connect to Memcached server
	 *
	 * @since  1.8
	 * @access private
	 */
	private function _connect()
	{
		if ( is_object( $this->_conn ) ) {
			return true ;
		}

		defined( 'LSCWP_LOG' ) && LiteSpeed_Cache_Log::debug( 'Object: connecting to ' . $this->_cfg_host . ':' . $this->_cfg_port ) ;
		$this->_conn = new Memcached ;
		$this->_conn->addServer( $this->_cfg_host, $this->_cfg_port ) ;

		// Check connection
		$memList = $this->_conn->getStats() ;
		if ( $memList[ $this->_cfg_host . ':' . $this->_cfg_port ][ 'pid' ] <= 0 ) {
			defined( 'LSCWP_LOG' ) && LiteSpeed_Cache_Log::debug( 'Object: failed to connect memcached server!' ) ;
			$this->_cfg_enabled = false ;

			return false ;
		}

		defined( 'LSCWP_LOG' ) && LiteSpeed_Cache_Log::debug2( 'Object: connected' ) ;

		return true ;
	}

	/**
	 * Get cache
	 *
	 * @since  1.8
	 * @access public
	 */
	public function get( $key )
	{
		if ( ! $this->_cfg_enabled ) {
			return null ;
		}

		if ( ! $this->_can_cache() ) {
			return null ;
		}

		$this->_connect() ;

		defined( 'LSCWP_LOG' ) && LiteSpeed_Cache_Log::debug2( 'Object: get ' . $key ) ;

		$res = $this->_conn->get( $key ) ;

		return $res ;
	}

	/**
	 * Set cache
	 *
	 * @since  1.8
	 * @access public
	 */
	public function set( $key, $data, $expire )
	{
		if ( ! $this->_cfg_enabled ) {
			return null ;
		}

		if ( ! $this->_can_cache() ) {
			return null ;
		}

		$this->_connect() ;

		defined( 'LSCWP_LOG' ) && LiteSpeed_Cache_Log::debug2( 'Object: set ' . $key ) ;

		$res = $this->_conn->set( $key, $data, $expire ?: $this->_cfg_life ) ;

		return $res ;
	}

	/**
	 * Check if can cache or not
	 *
	 * @since  1.8
	 * @access private
	 */
	private function _can_cache()
	{
		if ( ! $this->_cfg_admin && defined( 'WP_ADMIN' ) ) {
			return false ;
		}
		return true ;
	}

	/**
	 * Delete cache
	 *
	 * @since  1.8
	 * @access public
	 */
	public function delete( $key )
	{
		if ( ! $this->_cfg_enabled ) {
			return null ;
		}

		$this->_connect() ;

		defined( 'LSCWP_LOG' ) && LiteSpeed_Cache_Log::debug2( 'Object: delete ' . $key ) ;

		$res = $this->_conn->delete( $key ) ;

		return $res ;
	}

	/**
	 * Clear all cache
	 *
	 * @since  1.8
	 * @access public
	 */
	public function flush()
	{
		if ( ! $this->_cfg_enabled ) {
			defined( 'LSCWP_LOG' ) && LiteSpeed_Cache_Log::debug( 'Object: bypass flushing' ) ;
			return null ;
		}

		$this->_connect() ;

		defined( 'LSCWP_LOG' ) && LiteSpeed_Cache_Log::debug( 'Object: flush!' ) ;

		$res = $this->_conn->flush() ;

		return $res ;
	}

	/**
	 * Add global groups
	 *
	 * @since 1.8
	 * @access public
	 */
	public function add_global_groups( $groups )
	{
		if ( ! is_array( $groups ) ) {
			$groups = array( $groups ) ;
		}

		$this->_global_groups = array_merge( $this->_global_groups, $groups ) ;
		$this->_global_groups = array_unique( $this->_global_groups ) ;
	}

	/**
	 * Check if is in global groups or not
	 *
	 * @since 1.8
	 * @access public
	 */
	public function is_global( $group )
	{
		return in_array( $group, $this->_global_groups ) ;
	}

	/**
	 * Add non persistent groups
	 *
	 * @since 1.8
	 * @access public
	 */
	public function add_non_persistent_groups( $groups )
	{
		if ( ! is_array( $groups ) ) {
			$groups = array( $groups ) ;
		}

		$this->_non_persistent_groups = array_merge( $this->_non_persistent_groups, $groups ) ;
		$this->_non_persistent_groups = array_unique( $this->_non_persistent_groups ) ;
	}

	/**
	 * Check if is in non persistent groups or not
	 *
	 * @since 1.8
	 * @access public
	 */
	public function is_non_persistent( $group )
	{
		return in_array( $group, $this->_non_persistent_groups ) ;
	}

	/**
	 * Get the current instance object.
	 *
	 * @since 1.8
	 * @access public
	 * @return Current class instance.
	 */
	public static function get_instance()
	{
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new self() ;
		}

		return self::$_instance ;
	}
}