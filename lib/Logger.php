<?php
/**
 * Log handler
 *
 * @link       http://log.pt/
 * @since      1.2.0
 *
 * @package    Replicast
 * @subpackage Replicast/lib
 */

namespace Replicast;

/**
 * Log handler.
 *
 * @since      1.2.0
 * @package    Replicast
 * @subpackage Replicast/lib
 * @author     log.OSCON, Lda. <engenharia@log.pt>
 */
class Logger {

	/**
	 * The logger's instance.
	 *
	 * @since  1.2.0
	 * @access protected
	 * @var    \Monolog\Logger
	 */
	protected $logger;

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 */
	public function __construct( $channel = 'basic' ) {
		$this->logger = new \Monolog\Logger( $channel );

		// Define the output format
		$date      = 'Y-m-d H:i:s';
		$output    = "[%datetime%] %channel%.%level_name%: %message% %context%\n";
		$formatter = new \Monolog\Formatter\LineFormatter( $output, $date );

		// Create the log handler
		// FIXME: I should be able to get the plugin name instead of hardcoding it
		$log_path = sprintf(
			'%1$s/replicast-logs/replicast.log',
			\untrailingslashit( REPLICAST_LOG_DIR )
		);

		$stream = new \Monolog\Handler\StreamHandler( $log_path, \Monolog\Logger::DEBUG );
		$stream->setFormatter( $formatter );

		$this->logger->pushHandler( $stream );
	}

	/**
	 * Retrieve the logger instance.
	 *
	 * @since  1.2.0
	 * @return \Monolog\Logger The logger instance.
	 */
	public function log() {
		return $this->logger;
	}
}
