<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Exception;

/**
 * A worker implementation
 *
 * Usefull for running stuff in the background.
 *
 * Example:
 *   $worker = new Worker(["name"=>"myscript", "instance"=>1]);
 *   $worker->boot(); // boot your script into the background, if not already running
 *   // do your stuff
 *   $worker->shutdown();
 *
 * Example:
 *   $worker = new Worker(["name"=>"myscript", "instance"=>1]);
 *   $worker->stop(); // stop running worker
 *
 * Complete example:
 *   // include your autoloader
 *   $args = Worker::args();
 *   $worker = new Worker(["name"=>"myscript", "instance"=>1]);
 *   switch ($args['command']) {
 *       case "restart":
 *           $worker->stop();
 *       case "start":
 *           try {
 *               $worker->boot();
 *               // do your stuff
 *           } catch(Throwable $e) {
 *               echo $e->getMessage(), "\n";
 *           } finally {
 *               $worker->shutdown();
 *           }
 *           break;
 *       case "stop":
 *           $worker->stop();
 *           break;
 *       case "status":
 *           if ($worker->checkRunning()) {
 *               // connect to worker some how and output status
 *           } else {
 *               // worker not running
 *           }
 *           break;
 *   }
 */
final class Worker
{
	private $stdin = STDIN;
	private $stdout = STDOUT;
	private $stderr = STDERR;
	private $detached = false;
	private $name;
	private $background;
	private $redirect;
	private $pidfile;
	private $outputfile;
	private $inputfile;
	private $errorfile;
	private $safeEnvironmentVariables;
	private $environmentVariableDefaults;

	/**
	 * Constructor
	 *
	 * @param array  $config  worker configuration
	 */
	public function __construct(array $config = [])
	{
		$this->name = $config['name'] ?? basename(tempnam(sys_get_temp_dir(), "worker"));
		if (isset($config['instance'])) {
			$this->name .= "-".$config['instance'];
		}
		$this->background = $config['background'] ?? true;
		$this->redirect = $config['redirect'] ?? true;
		$this->pidfile = $config['pidfile'] ?? sys_get_temp_dir()."/{$this->name}.pid";
		$this->outputfile = $config['outputfile'] ?? sys_get_temp_dir()."/{$this->name}.log";
		$this->inputfile = $config['inputfile'] ?? "/dev/zero";
		$this->errorfile = $config['errorfile'] ?? $this->outputfile;
		$this->safeEnvironmentVariables = $config['safeEnvironmentVariables'] ?? ["LANG"];
		$this->environmentVariableDefaults = $config['environmentVariableDefaults'] ?? ["LANG"=>"en_US.UTF-8"];
	}

	/**
	 * Boot the worker.
	 */
	public function boot(): void
	{
		$this->cleanEnvironment();

		if ($this->checkRunning($this->pidfile)) {
			throw new Exception("{$this->name} is already running, quiting");
		}

		// reset umask, adopting user's default umask may mess things up
		umask(0);

		// should the script boot into the background
		if ($this->background) {
			$this->detach();
		}

		if ($this->redirect) {
			$this->redirectStdIO($this->outputfile, $this->inputfile, $this->errorfile);
		}

		// disable time limit
		set_time_limit(0);

		// change dir to root, so no directories are occupied
		// by this script, otherwise unmounting or deleting that
		// directory will fail, which is annoying to debug for
		// a system administrator
		if (chdir("/") === false)
			throw new Exception("failed to change directory");

		cli_set_process_title($this->name);
	}

	/**
	 * Clean stuff up, simply removes the pidfile.
	 */
	public function shutdown(): void
	{
		// remove pid file
		if ($this->pidfile) {
			unlink($this->pidfile);
		}
	}

	/**
	 * Stop the worker instance
	 */
	public function stop(): void
	{
		if ($this->checkRunning($this->pidfile)) {
			$pid = (int)file_get_contents($this->pidfile);
			posix_kill($pid, SIGTERM);
		}
	}

	/**
	 * Clean environment
	 *
	 * Restarts program if environment is not clean. So call this function
	 * before anything else, to prevent any side effects.
	 *
	 * Use this to harden the security of your scripts. As it prevents
	 * injection through environment variables.
	 */
	public function cleanEnvironment(): void
	{
		global $argv;

		// hard set the PATH and SHELL to sane defaults [security]
		// never rely on PATH and SHELL variables in your script
		// never rely on the shell
		$unsafe = [
			'PATH' => '/bin:/usr/bin',
			'SHELL' => '/bin/sh',
		];

		$reload = false;

		$env = $unsafe;

		// check unsafe environment variables
		foreach ($unsafe as $key => $value) {
			if (!isset($_ENV[$key]) || $_ENV[$key] != $value) {
				$reload = true;
			}
		}

		// strip the unsafe keys from safe
		$safe = array_diff($this->safeEnvironmentVariables, array_keys($unsafe));

		// check environment
		foreach ($_ENV as $key=>$value) {
			if (in_array($key, $safe)) {
				// remember value of safe environment variable in case reload is necessary
				$env[$key] = $value;
			} elseif (!isset($unsafe[$key])) {
				// if any unsafe variables are found reload script
				$reload = true;
			}
		}

		// set default environment variables if allowed by safe but are not already set
		foreach ($this->environmentVariableDefaults as $key=>$value) {
			if (!isset($_ENV[$key]) && in_array($key, $safe)) {
				$env[$key] = $value;
				$reload = true;
			}
		}

		if ($reload) {
			// reload script with clean environment
			pcntl_exec(PHP_BINARY, $argv, $env);
			exit("reload failed");
		}
	}

	/**
	 * Check if script is already running.
	 *
	 * @param $pidfile  the path where the process id of the program may be stored
	 * @return boolean  indicating that program is running or not
	 */
	public function checkRunning(): bool
	{
		$pidfile = $this->pidfile??(sys_get_temp_dir()."/".basename($_SERVER['PHP_SELF'],'.php').".pid");
		$rundir = dirname($pidfile);
		if (!is_dir($rundir) && !mkdir($rundir, 0775, true))
			throw new Exception("unable to make directory ".$rundir);

		if (file_exists($pidfile) && file_exists("/proc/".file_get_contents($pidfile))) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Detach from controlling process.
	 */
	public function detach(): void
	{
		if ($this->detached === true)
			throw new Exception("process is already detached");

		// fork process, so parent can exit
		if (!$this->fork()) {
			// parent process exits, to detach from controlling process
			exit();
		}

		// become session leader
		if (posix_setsid() < 0) {
			throw new Exception("failed to become session leader");
		}

		// fork again, so parent can exit
		if (!$this->fork()) {
			// parent process which is the session leader exits
			exit();
		}

		// we are now without a session leader
		// no longer can we regain a controlling process

		$this->detached = true;

		// write new pid to pidfile as previous one is no longer valid
		if ($this->pidfile) {
			file_put_contents($this->pidfile, posix_getpid());
		}
	}

	/**
	 * Redirect standard input, output and error streams.
	 *
	 * @param $outputfile  the file to write to
	 * @param $inputfile   the file to read from
	 * @param $errorfile   the file to write errors to
	 */
	public function redirectStdIO(): void
	{
		// please note that closing a file descriptor and then
		// immediately open a new one will put the new file
		// descriptor in the same slot as the old one, this is
		// a POSIX requirement and adhered to by the Linux kernel
		// thus closing STDIN and then opening a stream will make
		// that stream the new STDIN

		if ($this->inputfile) {
			$inputdir = dirname($this->inputfile);
			if (!is_dir($inputdir) && !mkdir($inputdir, 02770, true))
				throw new Exception("unable to make directory ".$inputdir);

			// close file descriptor slot 0, stdin
			if (fclose($this->stdin) === false)
				throw new Exception("failed to close stdin");

			// first empty file descriptor slot will be used, which is slot 0, the stdin
			$this->stdin = fopen($this->inputfile, "r");
			if ($this->stdin === false)
				throw new Exception("failed to reopen stdin");
		}

		if ($this->outputfile) {
			$outputdir = dirname($this->outputfile);
			if (!is_dir($outputdir) && !mkdir($outputdir, 02770, true))
				throw new Exception("unable to make directory ".$outputdir);

			// close file descriptor slot 1, stdout
			if (fclose($this->stdout) === false)
				throw new Exception("failed to close stdout");

			// first empty file descriptor slot will be used, which is slot 1, the stdout
			$this->stdout = fopen($this->outputfile, "a");
			if ($this->stdout === false)
				throw new Exception("failed to reopen stdout");
		}

		if ($this->errorfile) {
			$errordir = dirname($this->errorfile);
			if (!is_dir($errordir) && !mkdir($errordir, 02770, true))
				throw new Exception("unable to make directory ".$errordir);

			// close file descriptor slot 2, stderr
			if (fclose($this->stderr) === false)
				throw new Exception("failed to close stderr");

			if ($this->outputfile === $this->errorfile) {
				// as per documentation, when using the php:// wrapper the stream is duplicated
				// thus opening php://stdout should duplicate it to the first empty file descriptor slot
				// which is slot 2 which is reserved for stderr file descriptor
				// thus opening php://stdout now will duplicate slot 1 to slot 2 making both php://stdout
				// and php://stderr point to the same stream
				$this->stderr = fopen("php://stdout", "a"); // so this is not a typo
			} else {
				// first empty file descriptor slot will be used, which is slot 2, the stderr
				$this->stderr = fopen($this->errorfile, "a");
				if ($this->stderr === false)
					throw new Exception("failed to reopen stderr");
			}
		}
	}

	/**
	 * Fork the process.
	 *
	 * This recipe is dedicated for forking at boot time.
	 * Don't use it for normal forking.
	 *
	 * @param $pid  the process identifier
	 * @return boolean, true if child, false if parent
	 * @throws Exception when fork failed
	 */
	private function fork(int &$pid = null): bool
	{
		// ignore SIGCHLD so children get auto reaped
		pcntl_signal(SIGCHLD, SIG_IGN);
		$pid = pcntl_fork();
		if ($pid < 0) throw new Exception(pcntl_strerror(pcntl_errno()), pcntl_errno());
		$ischild = $pid === 0;
		return $ischild;
	}

	/**
	 * Parse the command line arguments.
	 *
	 * @return array with arguments found
	 */
	public static function args(): array
	{
		global $argv;
		$progname = basename($argv[0]);
		$usage = "Usage: $progname [-bBrRdDh?] [-e ENVIRONMENT] NAME [INSTANCE] [start|stop|restart|status]\n";
		$usage.= "\n";
		$usage.= "Options:\n";
		$usage.= " -b, --background\n";
		$usage.= "        boot into the background, default\n";
		$usage.= " -B, --no-background\n";
		$usage.= "        don't boot into the background\n";
		$usage.= " -d, --debug\n";
		$usage.= "        turn debugging on\n";
		$usage.= " -D, --no-debug\n";
		$usage.= "        turn debugging off\n";
		$usage.= " -e, --env ENVIRONMENT\n";
		$usage.= "        set environment\n";
		$usage.= " -h, -?, --help, --usage\n";
		$usage.= "        display this help screen\n";
		$usage.= " -r, --redirect\n";
		$usage.= "        redirect output, default\n";
		$usage.= " -R, --no-redirect\n";
		$usage.= "        don't redirect output\n";
		$usage.= "\n";
		$l = count($argv);
		$args = [];
		for ($i = 1; $i < $l; ++$i) {
			$arg = $argv[$i];
			if ($arg === "--env") {
				$env = $argv[++$i];
			} else if ($arg === "--debug") {
				$debug = true;
			} else if ($arg === "--no-debug") {
				$debug = false;
			} else if ($arg === "--background") {
				$background = true;
			} else if ($arg === "--no-background") {
				$background = false;
			} else if ($arg[0] == "-" && $arg[1] != "-") {
				$cl = strlen($arg);
				for ($c = 1; $c < $cl; ++$c) {
					switch ($arg[$c]) {
						case "d":
							$debug = true;
							break;
						case "D":
							$debug = false;
							break;
						case "b":
							$background = true;
							break;
						case "B":
							$background = false;
							break;
						case "r":
							$redirect = true;
							break;
						case "R":
							$redirect = false;
							break;
						case "e":
							$env = $args[++$c];
							break;
						default:
							echo "unknown option {$arg[$c]}\n";
						case "?":
						case "h":
							exit($usage);
					}
				}
			} else if (in_array($arg, ["help", "--help", "usage", "--usage"])) {
				exit($usage);
			} else if (in_array($arg, ["start", "stop", "restart", "status"])) {
				$command = $arg;
			} else if ($arg === "reload") {
				$command = "restart";
			} else if ($arg[0] == "-") {
				echo "unknown option: $arg\n";
				exit($usage);
			} else if (!isset($name)) {
				$name = $arg;
			} else if (!isset($instance)) {
				$instance = $arg;
			} else {
				exit($usage);
			}
		}
		return [
			"name" => $name ?? "default",
			"command" => $command ?? "start",
			"instance" => $instance ?? null,
			"env" => $env ?? getenv("ENVIRONMENT") ?: "prod",
			"debug" => $debug ?? false,
			"background" => $background ?? true,
			"redirect" => $redirect ?? $background ?? true,
		];
	}

	public static function init(): array
	{
		$args = self::args();
		$worker = new \Sturdy\Activity\Worker($args);
		switch ($args["command"]) {
			case "restart":
				$worker->stop();
				// no break
			case "start":
				$worker->boot();
				return $args;

			case "stop":
				$worker->stop();
				exit;

			case "status":
				if ($worker->checkRunning()) {
					echo $args["name"] . " is running.\n";
				} else {
					echo $args["name"] . " is not running.\n";
				}
				exit;

			default:
				exit;
		}
	}
}
