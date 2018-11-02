<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Throwable;

class Log
{
	private static $logger;
	private static $backlog;

	public static function installErrorHandler(string $rootdir = null): void
	{
		if ($rootdir === null) {
			$rootdir = dirname(dirname(dirname(dirname(__DIR__)))); // guessing, should work with default composer layout
		}

		set_exception_handler(function(Throwable $e){
			mb_substitute_character(0xFFFD);
			$class = get_class($e);
			$message = mb_convert_encoding($e->getMessage(), 'UTF-8', 'UTF-8');
			$trace = mb_convert_encoding("## ".$e->getFile()."(".$e->getLine().")\n".$e->getTraceAsString(), 'UTF-8', 'UTF-8');
			file_put_contents("php://stderr", "Uncaught exception $class: $message\n$trace\n", FILE_APPEND);
		});

		// set up logging so all errors are send to http_log
		set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline)use($rootdir) {
			if (strpos($errstr, "unlink(") === 0) { // ignore unlink errors
				return;
			}
			if (strpos($errstr, "Doctrine\Common\ClassLoader is deprecated.") === 0) { // ignore this error
				return;
			}
			$errstr = trim($errstr, ". ");
			$errfile = substr($errfile, strlen($rootdir));
			switch ($errno) {
				case E_ERROR:
				case E_CORE_ERROR:
				case E_COMPILE_ERROR:
				case E_RECOVERABLE_ERROR:
				case E_USER_ERROR:
					$errstr = "Error: $errstr";
					break;

				case E_WARNING:
				case E_CORE_WARNING:
				case E_COMPILE_WARNING:
				case E_USER_WARNING:
					$errstr = "Warning: $errstr";
					break;

				case E_NOTICE:
				case E_USER_NOTICE:
					$errstr = "Notice: $errstr";
					break;

				case E_STRICT:
					$errstr = "Strict: $errstr";
					break;

				case E_DEPRECATED:
				case E_USER_DEPRECATED:
					$errstr = "Deprecated: $errstr";
					break;
			}
			file_put_contents("php://stderr", "$errstr in $errfile:$errline\n", FILE_APPEND);
			Log::log("$errstr in $errfile:$errline");
		});
	}

	public static function setLogger($logger)
	{
		self::$logger = $logger;
		if (isset(self::$backlog)) {
			foreach (self::$backlog as $data) {
				self::$logger->log(...$data);
			}
			self::$backlog = null;
		}
	}

	public static function getLogger()
	{
		return self::$logger;
	}

	public static function log(...$args)
	{
		if (empty(self::$logger)) {
			self::$backlog[] = $args;
		} else {
			self::$logger->log(...$args);
		}
	}

	public static function useSysLog()
	{
		self::setLogger(new class() {
			public function log(int $priority, ...$args) {
				if (is_int($args[0])) {
					$priority = array_shift($args);
				} else {
					$priority = LOG_DEBUG;
				}
				syslog($priority, implode(" ", $args));
			}
		});
	}

	public static function useEchoLog()
	{
		self::setLogger(new class() {
			public function log(...$args) {
				echo date("[d-M-Y H:i:s] ");
				if (is_int($args[0])) {
					$priority = array_shift($args);
					switch ($args[0]) {
						case LOG_EMERG: echo "EMERGENCY: "; break;
						case LOG_ALERT: echo "ALERT: "; break;
						case LOG_CRIT: echo "CRITICAL: "; break;
						case LOG_ERR: echo "ERROR: "; break;
						case LOG_WARNING: echo "WARNING: "; break;
						case LOG_NOTICE: echo "NOTICE: "; break;
						case LOG_INFO: echo "INFO: "; break;
						case LOG_DEBUG: echo "DEBUG: "; break;
						default: echo $args[0] . " "; break;
					}
				}
				echo implode(" ", $args);
			}
		});
	}
}
