<?php
/**
 * @namespace
 */
namespace CronManager\Ftp;

use CronManager\Ftp\Directory,
    CronManager\Ftp\File,
    CronManager\Ftp\Exception;

/**
 * Class Ftp
 * @package CronManager\Ftp
 */
class Ftp
{
	/**
	 * ASCII transfer mode
	 */
	const MODE_ASCII = FTP_ASCII;
	 
	/**
	 * Binary transfer mode
	 */
	const MODE_BINARY = FTP_BINARY;
	 
	/**
	 * Automatic transfer mode detection
	 */
	const MODE_AUTO = 4;
	 
	/**
	 * The FTP connection
	 *
	 * @var resource
	 */
	protected $_connection = null;
	 
	/**
	 * The FTP host
	 *
	 * @var string
	 */
	protected $_host = null;
	 
	/**
	 * The FTP username
	 *
	 * @var string
	 */
	protected $_username = null;
	 
	/**
	 * The FTP password
	 *
	 * @var string
	 */
	protected $_password = null;
	 
	/**
	 * The FTP port
	 *
	 * @var int
	 */
	protected $_port = 21;
	 
	/**
	 * The command timeout
	 *
	 * @var int
	 */
	protected $_timeout = 90;
	 
	/**
	 * The current transfer mode
	 *
	 * @var int
	 */
	protected $_currentMode = self::MODE_AUTO;
	 
	/**
	 * The current path
	 *
	 * @var string
	 */
	protected $_currentPath = null;
	 
	/**
	 * The current directory
	 *
	 * @var Directory
	 */
	protected $_currentDirectory = null;
	 
	/**
	 * Whether or not to use passive mode
	 *
	 * @var boolean
	 */
	protected $_passive = false;
	 
	/**
	 * Whether or not to use an SSL connection
	 *
	 * @var boolean
	 */
	protected $_ssl = false;
	 
	/**
	 * File types to be transferred in ASCII mode when using automatic detection
	 *
	 * @var array
	 */
	protected $_asciiTypes = array('txt', 'html', 'htm', 'php', 'phtml');
	 
	/**
	 * Instantiate
	 *
	 * @param string $host The FTP host
	 * @param string $username The login username
	 * @param string $password The login password
	 * @param int $port [optional] The port to connect to
	 * @param int $timeout [optional] The command timeout
	 */
	public function __construct($host, $username, $password, $port = 21, $timeout = 90)
	{
		$this->_host = $host;
		$this->_username = $username;
		$this->_password = $password;
		$this->_port = $port;
		$this->_timeout = $timeout;
	}
	 
	/**
	 * Connect to the FTP server and login
	 */
	protected function _connect()
	{
		if ($this->_connection === null) {
			if ($this->_ssl) {
				$connection = @ftp_ssl_connect($this->_host, $this->_port, $this->_timeout);
			} else {
				$connection = @ftp_connect($this->_host, $this->_port, $this->_timeout);
			}
			if ($connection === false) {
				throw new Exception('Unable to connect to host "' . $this->_host . '" on port ' . $this->_port);
			}
			 
			$this->_connection = $connection;
			 
			$login = @ftp_login($this->_connection, $this->_username, $this->_password);
			if ($login === false) {
				throw new Exception('Unable to login with username "' . $this->_username);
			}
			 
			if ($this->_passive) {
				$this->_setPassive();
			}
			 
			$path = @ftp_pwd($this->_connection);
			if ($path === false) {
				throw new Exception('Unable to get current directory');
			}
			 
			$this->_currentPath = $path;
		}
	}
	 
	/**
	 * Whether or not it's connected to the server
	 *
	 * @return boolean
	 */
	public function isConnected()
	{
		return $this->_connection === null;
	}
	 
	/**
	 * Get the FTP connection
	 *
	 * @return resource
	 */
	public function getConnection()
	{
		$this->_connect();
		 
		return $this->_connection;
	}
	 
	/**
	 * Get a directory given an absolute pathname
	 *
	 * @param string $filename The directory to get
	 * @return Directory
	 */
	public function getDirectory($filename = '')
	{
		if (empty($filename)) {
			return $this->getCurrentDirectory();
		}
		 
		$this->_connect();

		return new Directory($filename, $this);
	}
	 
	/**
	 * Get a file given an absolute pathname
	 *
	 * @param string $filename The file to get
	 * @return File
	 */
	public function getFile($filename)
	{
		$this->_connect();

		return new File($filename, $this);
	}
	 
	/**
	 * Set the command timeout period in seconds
	 *
	 * @param int $timeout The timeout period
	 * @return Ftp
	 */
	public function setTimeout($timeout)
	{
		$this->_timeout = $timeout;
		if ($this->_connection !== null) {
			$option = @ftp_set_option($this->_connection, FTP_TIMEOUT_SEC, $this->_timeout);
			if ($option === false) {
				throw new Exception('Unable to set timeout');
			}
		}
		 
		return $this;
	}
	 
	/**
	 * Set whether or not to use an SSL connection
	 *
	 * @param boolean $ssl [optional]
	 * @return Ftp
	 */
	public function setSecure($ssl = true)
	{
		$this->_ssl = $ssl;
		 
		return $this;
	}
	 
	/**
	 * Turn passive mode on or off
	 *
	 * @param boolean $passive [optional] Whether or not to use passive mode
	 * @return Ftp
	 */
	public function setPassive($passive = true)
	{
		$this->_passive = $passive;
		$this->_setPassive();
		 
		return $this;
	}
	 
	/**
	 * Send the PASV command
	 *
	 * @return Ftp
	 */
	protected function _setPassive()
	{
		if ($this->_connection !== null) {
			$pasv = @ftp_pasv($this->_connection, $this->_passive);
			if ($pasv === false) {
				throw new Exception('Unable to set passive mode');
			}
		}
		 
		return $this;
	}
	 
	/**
	 * Set the default transfer mode
	 *
	 * @param int $mode The transfer mode
	 * @return Ftp
	 */
	public function setMode($mode)
	{
		switch ($mode) {
			case self::MODE_ASCII:
			case self::MODE_BINARY:
			case self::MODE_AUTO:
				$this->_currentMode = $mode;
				break;
			default:
				throw new Exception('Unknown FTP transfer mode');
		}
		 
		return $this;
	}
	 
	/**
	 * Get the current Directory
	 *
	 * @return Directory
	 */
	public function getCurrentDirectory()
	{
		if ($this->_currentDirectory === null) {
			$this->_connect();
			 
			$this->_currentDirectory = new Directory($this->_currentPath, $this);
		}
		 
		return $this->_currentDirectory;
	}
	 
	/**
	 * Determine the transfer mode for the given filename
	 *
	 * @param string $filename
	 * @return int
	 */
	public function determineMode($filename)
	{
		if ($this->_currentMode == self::MODE_AUTO) {
			$extension = pathinfo($filename, PATHINFO_EXTENSION);
			if (in_array($extension, $this->_asciiTypes)) {
				return self::MODE_ASCII;
			}
			return self::MODE_BINARY;
		}
		return $this->_currentMode;
	}
	 
	/**
	 * Set the ASCII file types for automatic transfer mode
	 *
	 * @param array $types
	 * @return Ftp
	 */
	public function setAsciiTypes($types)
	{
		$this->_asciiTypes = array_unique($types);
		 
		return $this;
	}
	 
	/**
	 * Add an ASCII file type for automatic transfer mode
	 *
	 * @param string $type
	 * @return Ftp
	 */
	public function addAsciiType($type)
	{
		$types = $this->_asciiTypes;
		$types[] = $type;
		$this->setAsciiTypes($types);
		 
		return $this;
	}
	 
	/**
	 * Disconnect if connected
	 */
	public function __destruct()
	{
		if ($this->_connection !== null) {
			@ftp_close($this->_connection);
		}
	}
	 
	/**
	 * Change the permissions of a file or directory
	 *
	 * @param string $path The file or directory
	 * @param int|string $permissions The permissions as an octal e.g. 0777 or string e.g. 'rwxrwxrwx'
	 * @return
	 */
	public function chmod($path, $permissions)
	{
		$chmod = @ftp_chmod($this->_connection, $this->_parsePermissions($permissions), $path);
		if ($chmod === false) {
			// For some reason ftp_chmod will return false even if it's successful so we need to check manually
			//throw new Exception('Unable to change permissions of "' . $path . '"');
		}
		 
		return $this;
	}
	 
	/**
	 * Converts string permissions into octal format
	 *
	 * @param int|string $permissions The permissions
	 * @return integer
	 */
	protected function _parsePermissions($permissions)
	{
		if (!is_int($permissions) && 0 == preg_match('/^[rwx\-]{9}$/', $permissions)) {
			throw new Exception('Invalid permissions format');
		}
		$perms = array(
				'-' => 0,
				'r' => 1,
				'w' => 2,
				'x' => 4,
		);
		if (is_string($permissions)) {
			$parts = str_split($permissions, 1);
			$owner = $perms[$parts[0]] + $perms[$parts[1]] + $perms[$parts[2]];
			$group = $perms[$parts[3]] + $perms[$parts[4]] + $perms[$parts[5]];
			$world = $perms[$parts[6]] + $perms[$parts[7]] + $perms[$parts[8]];
			$permString = '0' . $owner . $group . $world;
			eval('$permissions = ' . $permString . ';');
		}
		return $permissions;
	}
	 
	/**
	 * Utility method to create an instance for chaining
	 *
	 * @param string $host The FTP host
	 * @param string $username The login username
	 * @param string $password The login password
	 * @param int $port [optional] The port to connect to
	 * @param int $timeout [optional] The command timeout
	 * @return Ftp
	 */
	public static function connect($host, $username, $password, $port = 21, $timeout = 90)
	{
		return new self($host, $username, $password, $port, $timeout);
	}
}