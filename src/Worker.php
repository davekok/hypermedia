<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Exception, DateTime;

/**
 * A worker implementation
 *
 * Usefull for running stuff in the background.
 *
 * Example:
 *   // include your autoloader
 *   Worker::init(Worker::args());
 */
final class Worker
{
	private static $worker;
	private $stdin = STDIN;
	private $stdout = STDOUT;
	private $stderr = STDERR;
	private $detached = false;
	private $name;
	private $instance;
	private $background;
	private $redirect;
	private $pidfile;
	private $outputfile;
	private $inputfile;
	private $errorfile;
	private $user;
	private $group;
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
			$this->instance = $config['instance'];
		} else {
			$this->instance = $this->name[0];
		}
		$this->background = $config['background'] ?? true;
		$this->redirect = $config['redirect'] ?? true;
		$this->pidfile = $config['pidfile'] ?? sys_get_temp_dir()."/{$this->name}.pid";
		$this->outputfile = $config['outputfile'] ?? sys_get_temp_dir()."/{$this->name}.log";
		$this->inputfile = $config['inputfile'] ?? "/dev/zero";
		$this->errorfile = $config['errorfile'] ?? $this->outputfile;
		$this->user = $config['user'] ?? null;
		$this->group = $config['group'] ?? null;
		if ($this->group === null && $this->user !== null) {
			$user = posix_getpwnam($this->user);
			$gr = posix_getgrgid($user["gid"]);
			$this->group = $gr["name"];
		}
		$this->safeEnvironmentVariables = $config['safeEnvironmentVariables'] ?? ["ENVIRONMENT", "LANG"];
		$this->environmentVariableDefaults = $config['environmentVariableDefaults'] ?? ["ENVIRONMENT"=>"prod", "LANG"=>"en_US.UTF-8"];
	}

	/**
	 * Boot the worker.
	 */
	public function boot(): void
	{
		$this->cleanEnvironment();

		if (null !== $pid = $this->getPid()) {
			exit("{$this->name} is already running under process id $pid, quiting\n");
		}

		// reset umask, adopting user's default umask may mess things up
		umask(022);

		// should the script boot into the background
		if ($this->background) {
			$this->detach();
			$this->writePid();
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
			exit("failed to change directory\n");

		cli_set_process_title($this->name);

		if ($this->user !== null) {
			$this->setUser($this->user, $this->group);
		}
	}

	/**
	 * Kill the worker instance
	 */
	public function kill(): void
	{
		$pid = $this->getPid();
		if ($pid !== null) {
			$key = ftok(__FILE__, $this->instance[0]);
			if ($key >= 0) {
				$sem = sem_get($key);
				sem_acquire($sem);
			}
			posix_kill($pid, SIGTERM);
			sleep(1);
			if (null !== $pid = $this->getPid()) {
				posix_kill($pid, SIGKILL);
			}
			unlink($this->pidfile);
			if ($key >= 0) {
				sem_release($sem);
			}
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

		$cenv = getenv();
		$env = $unsafe;

		// check unsafe environment variables
		foreach ($unsafe as $key => $value) {
			if (!isset($cenv[$key]) || $cenv[$key] != $value) {
				$reload = true;
			}
		}

		// strip the unsafe keys from safe
		$safe = array_diff($this->safeEnvironmentVariables, array_keys($unsafe));

		// check environment
		foreach ($cenv as $key => $value) {
			if (in_array($key, $safe)) {
				// remember value of safe environment variable in case reload is necessary
				$env[$key] = $value;
			} elseif (!isset($unsafe[$key])) {
				// if any unsafe variables are found reload script
				$reload = true;
			}
		}

		// set default environment variables if allowed by safe but are not already set
		foreach ($this->environmentVariableDefaults as $key => $value) {
			if (!isset($cenv[$key]) && in_array($key, $safe)) {
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
	 * Get the pid of currently running worker.
	 *
	 * @return int  the process id
	 */
	public function getPid(): ?int
	{
		// scan proc directory for worker
		foreach (glob("/proc/*/cmdline") as $file) {
			$cmdline = trim(file_get_contents($file));
			if ($cmdline === $this->name) {
				return (int)substr($file, 6, -8);
			}
		}
		return null;
	}

	/**
	 * Get status of current instance
	 *
	 * @return array if running, false otherwise
	 */
	public function getStatus()
	{
		$pid = $this->getPid();
		if ($pid !== null) {
			$piddir = "/proc/$pid";
			if (file_exists($piddir)) {
				$st = stat($piddir);
				$status = [];
				$status["pid"] = $pid;
				$status["starttime"] = date("Y-m-d H:i:s", $st[10]);
				$interval = (new DateTime("now"))->diff(new DateTime($status["starttime"]));
				$diff = "";
				if ($interval->y) $diff.= $interval->y . "Y ";
				if ($interval->m) $diff.= $interval->m . "M ";
				if ($interval->d) $diff.= $interval->d . "D ";
				if ($interval->h) $diff.= $interval->h . "H ";
				if ($interval->i) $diff.= $interval->i . "m ";
				if ($interval->s) $diff.= $interval->s . "s ";
				if (empty($diff)) $diff.= "0s";
				$status["uptime"] = trim($diff);
				$user = posix_getpwuid($st[4]);
				$status["uid"] = $st[4];
				$status["user"] = $user["name"];
				$group = posix_getgrgid($st[5]);
				$status["gid"] = $st[5];
				$status["group"] = $group["name"];
				if (posix_getuid() === 0) {
					$status["inputfile"] = readlink("$piddir/fd/0");
					$status["outputfile"] = readlink("$piddir/fd/1");
					$status["errorfile"] = readlink("$piddir/fd/2");
				}
			}
		}
		return $status ?? false;
	}

	/**
	 * Get status as string
	 *
	 * @return string  status
	 */
	public function getStatusAsString(): string
	{
		if ($this->getStatus()) {
			$r = "# {$this->name} (running)\n";
			foreach ($this->getStatus() as $key => $value) {
				$r.= "$key: $value\n";
			}
		} else {
			$r = "# {$this->name} (not running)\n";
		}
		return $r;
	}

	/**
	 * Detach from controlling process.
	 */
	public function detach(): void
	{
		if ($this->detached === true)
			exit("process is already detached\n");

		// fork process, so parent can exit
		if (!$this->fork()) {
			// parent process exits, to detach from controlling process
			exit();
		}

		// become session leader
		if (posix_setsid() < 0) {
			exit("failed to become session leader\n");
		}

		// fork again, so parent can exit
		if (!$this->fork()) {
			// parent process which is the session leader exits
			exit();
		}

		// we are now without a session leader
		// no longer can we regain a controlling process

		$this->detached = true;
	}

	/**
	 * Set the user this process should run under.
	 *
	 * @param string $user   the user name
	 * @param string $group  the group name
	 */
	public function setUser(string $user, string $group = null): void
	{
		if (posix_geteuid() === 0) { // are we root
			$pw = posix_getpwnam($user);
			if ($pw === false) {
				exit("$user not found or insufficient privileges to search for users.");
			}

			if ($group !== null) {
				$gr = posix_getgrnam($group);
				if ($gr === false) {
					exit("$group not found or insufficient privileges to search for groups.");
				}
				posix_setgid($gr['gid']); // set group id of process
			} else {
				posix_setgid($pw['gid']); // set group id of process
			}

			posix_setuid($pw['uid']); // set user id of process
		}
	}

	/**
	 * Write pid to pid file
	 */
	public function writePid(): void
	{
		// write new pid to pidfile as previous one is no longer valid
		if ($this->pidfile) {
			$rundir = dirname($this->pidfile);
			if (!is_dir($rundir) && !mkdir($rundir, 0755, true))
				exit("unable to make directory $rundir\n");
			if (file_put_contents($this->pidfile, posix_getpid()) === false)
				exit("unable to write to {$this->pidfile}\n");
			if (posix_getuid() === 0) {
				chown($this->pidfile, $this->user);
				chgrp($this->pidfile, $this->group);
			}
			chmod($this->pidfile, 0644);
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
			if (!is_dir($inputdir) && !mkdir($inputdir, 0755, true)) {
				// file_put_contents("php://stderr", "unable to make directory $inputdir\n", FILE_APPEND);
				file_put_contents($this->outputfile, "unable to make directory $inputdir\n", FILE_APPEND);
				exit(1);
			}

			// close file descriptor slot 0, stdin
			if (fclose($this->stdin) === false) {
				// file_put_contents("php://stderr", "failed to close stdin\n", FILE_APPEND);
				file_put_contents($this->outputfile, "failed to close stdin\n", FILE_APPEND);
				exit(1);
			}

			// first empty file descriptor slot will be used, which is slot 0, the stdin
			$this->stdin = fopen($this->inputfile, "r");
			if ($this->stdin === false) {
				// file_put_contents("php://stderr", "failed to reopen stdin\n", FILE_APPEND);
				file_put_contents($this->outputfile, "failed to reopen stdin\n", FILE_APPEND);
				exit(1);
			}
		}

		if ($this->outputfile) {
			$outputdir = dirname($this->outputfile);
			if (!is_dir($outputdir) && !mkdir($outputdir, 0755, true)) {
				// file_put_contents("php://stderr", "unable to make directory $outputdir\n", FILE_APPEND);
				file_put_contents($this->outputfile, "unable to make directory $outputdir\n", FILE_APPEND);
				exit(1);
			}
			if (!file_exists($this->outputfile)) {
				file_put_contents($this->outputfile, "");
				if (posix_getuid() === 0) {
					if ($this->user) chown($this->outputfile, $this->user);
					if ($this->group) chgrp($this->outputfile, $this->group);
				}
			}

			// close file descriptor slot 1, stdout
			if (fclose($this->stdout) === false) {
				// file_put_contents("php://stderr", "failed to close stdout\n", FILE_APPEND);
				file_put_contents($this->outputfile, "failed to close stdout\n", FILE_APPEND);
				exit(1);
			}

			// first empty file descriptor slot will be used, which is slot 1, the stdout
			$this->stdout = fopen($this->outputfile, "a");
			if ($this->stdout === false) {
				// file_put_contents("php://stderr", "failed to reopen stdout\n", FILE_APPEND);
				file_put_contents($this->outputfile, "failed to reopen stdout\n", FILE_APPEND);
				exit(1);
			}
		}

		if ($this->errorfile) {
			$errordir = dirname($this->errorfile);
			if (!is_dir($errordir) && !mkdir($errordir, 0755, true)) {
				// file_put_contents("php://stdout", "unable to make directory $errordir\n", FILE_APPEND);
				file_put_contents($this->outputfile, "unable to make directory $errordir\n", FILE_APPEND);
				exit(1);
			}
			if (!file_exists($this->errorfile)) {
				file_put_contents($this->errorfile, "");
				if (posix_getuid() === 0) {
					if ($this->user) chown($this->errorfile, $this->user);
					if ($this->group) chgrp($this->errorfile, $this->group);
				}
			}

			// close file descriptor slot 2, stderr
			if (fclose($this->stderr) === false) {
				// file_put_contents("php://stdout", "failed to close stderr\n", FILE_APPEND);
				file_put_contents($this->outputfile, "failed to close stderr\n", FILE_APPEND);
				exit(1);
			}

			if ($this->outputfile === $this->errorfile) {
				// as per documentation, when using the php:// wrapper the stream is duplicated
				// thus opening php://stdout should duplicate it to the first empty file descriptor slot
				// which is slot 2 which is reserved for stderr file descriptor
				// thus opening php://stdout now will duplicate slot 1 to slot 2 making both php://stdout
				// and php://stderr point to the same stream
				$this->stderr = fopen("php://stdout", "a"); // so this is not a typo
				if ($this->stderr === false) {
					// file_put_contents("php://stdout", "failed to duplicate stderr\n", FILE_APPEND);
					file_put_contents($this->outputfile, "failed to duplicate stderr\n", FILE_APPEND);
					exit(1);
				}
			} else {
				// first empty file descriptor slot will be used, which is slot 2, the stderr
				$this->stderr = fopen($this->errorfile, "a");
				if ($this->stderr === false) {
					// file_put_contents("php://stdout", "failed to reopen stderr\n", FILE_APPEND);
					file_put_contents($this->outputfile, "failed to reopen stderr\n", FILE_APPEND);
					exit(1);
				}
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
		if ($pid < 0) exit(pcntl_strerror(pcntl_errno()));
		$ischild = $pid === 0;
		return $ischild;
	}

	/**
	 * Parse the command line arguments.
	 *
	 * @param array $defaults
	 * @return array with arguments found
	 */
	public static function args(array $defaults = []): array
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
		$usage.= " -v, --var-dir\n";
		$usage.= "        set var dir\n";
		$usage.= " -p, --pid-file\n";
		$usage.= "        set process id file\n";
		$usage.= " -l, --log-file\n";
		$usage.= "        set log file\n";
		$usage.= " -u, --user\n";
		$usage.= "        set user\n";
		$usage.= "\n";
		$l = count($argv);
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
			} else if ($arg === "--var-dir") {
				$vardir = $argv[++$i];
			} else if ($arg === "--pid-file") {
				$pidfile = $argv[++$i];
			} else if ($arg === "--log-file") {
				$logfile = $argv[++$i];
			} else if ($arg === "--user") {
				$user = $argv[++$i];
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
							$env = $argv[++$i];
							break;
						case "v":
							$vardir = $argv[++$i];
							break;
						case "p":
							$pidfile = $argv[++$i];
							break;
						case "l":
							$logfile = $argv[++$i];
							break;
						case "u":
							$user = $argv[++$i];
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
			} else if ($arg === "quit") {
				$command = "stop";
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
		if ($vardir = $vardir ?? $defaults["vardir"] ?? false) {
			if (!isset($pidfile)) {
				$pidfile = "{$vardir}/run/{$name}.pid";
			}
			if (!isset($logfile)) {
				$logfile = "{$vardir}/log/{$name}.log";
			}
		}
		return [
			"name" => $name ?? $defaults["name"] ?? "default",
			"command" => $command ?? $defaults["command"] ?? "status",
			"instance" => $instance ?? $defaults["instance"] ?? null,
			"env" => $env ?? $defaults["env"] ?? getenv("ENVIRONMENT") ?: "prod",
			"debug" => $debug ?? $defaults["debug"] ?? false,
			"background" => $background ?? $defaults["background"] ?? true,
			"redirect" => $redirect ?? $defaults["redirect"] ?? true,
			"pidfile" => $pidfile ?? $defaults["pidfile"] ?? null,
			"inputfile" => $defaults["inputfile"] ?? null,
			"outputfile" => $logfile ?? $defaults["outputfile"] ?? null,
			"errorfile" => $defaults["errorfile"] ?? null,
			"user" => $user ?? $defaults["user"] ?? null,
			"group" => $defaults["group"] ?? null,
		];
	}

	/**
	 * Initialize worker
	 *
	 * @param array $config  the config
	 */
	public static function init(array $config): self
	{
		$worker = self::createWorker($config);
		switch ($config["command"]) {
			case "restart":
				$worker->kill();
				// no break
			case "start":
				$worker->boot();
				return $worker;

			case "stop":
				$worker->kill();
				exit;

			case "status":
				if ($worker->getStatus()) {
					echo "# ", $worker->name, " (running)\n";
					foreach ($worker->getStatus() as $key => $value) {
						echo "$key: $value\n";
					}
				} else {
					echo "# ", $worker->name, " (not running)\n";
				}
				exit;

			default:
				exit;
		}
	}

	/**
	 * Get the initialized worker.
	 *
	 * @param array  $config
	 * @return Worker  the worker
	 */
	public static function createWorker(array $config): self
	{
		if (!self::$worker) {
			self::$worker = new self($config);
		}
		return self::$worker;
	}

	/**
	 * Get the initialized worker.
	 *
	 * @return Worker  the worker
	 */
	public static function getWorker(): self
	{
		return self::$worker;
	}
}
