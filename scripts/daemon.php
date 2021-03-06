#!/usr/bin/env php
<?php
/**
 * @file scripts/daemon.php
 * @brief Run the worker from a daemon.
 *
 * This script was taken from http://php.net/manual/en/function.pcntl-fork.php
 */
function shutdown() {
	posix_kill(posix_getpid(), SIGHUP);
}

if (in_array("start", $_SERVER["argv"])) {
	$mode = "start";
}

if (in_array("stop", $_SERVER["argv"])) {
	$mode = "stop";
}

if (in_array("status", $_SERVER["argv"])) {
	$mode = "status";
}

if (!isset($mode)) {
	die("Please use either 'start', 'stop' or 'status'.\n");
}

if (empty($_SERVER["argv"][0])) {
	die("Unexpected script behaviour. This message should never occur.\n");
}

// Fetch the base directory
$directory = dirname($_SERVER["argv"][0]);

if (substr($directory, 0, 1) != "/") {
	$directory = $_SERVER["PWD"]."/".$directory;
}
$directory = realpath($directory."/..");

@include($directory."/.htconfig.php");

if (!isset($pidfile)) {
	die('Please specify a pid file in the variable $pidfile in the .htconfig.php. For example:'."\n".
		'$pidfile = "/path/to/daemon.pid";'."\n");
}

if (in_array($mode, array("stop", "status"))) {
	$pid = @file_get_contents($pidfile);

	if (!$pid) {
		die("Pidfile wasn't found. Is the daemon running?\n");
	}
}

if ($mode == "status") {
	if (posix_kill($pid, 0)) {
		die("Daemon process $pid is running.\n");
	}

	unlink($pidfile);

	die("Daemon process $pid isn't running.\n");
}

if ($mode == "stop") {
	posix_kill($pid, SIGTERM);

	unlink($pidfile);

	die("Worker daemon process $pid was killed.\n");
}

echo "Starting worker daemon.\n";

if (isset($a->config['php_path'])) {
	$php = $a->config['php_path'];
} else {
	$php = "php";
}

// Switch over to daemon mode.
if ($pid = pcntl_fork())
	return;     // Parent

fclose(STDIN);  // Close all of the standard
fclose(STDOUT); // file descriptors as we
fclose(STDERR); // are running as a daemon.

register_shutdown_function('shutdown');

if (posix_setsid() < 0)
	return;

if ($pid = pcntl_fork())
	return;     // Parent

$pid = getmypid();
file_put_contents($pidfile, $pid);

// Now running as a daemon.
while (true) {
	// Just to be sure that this script really runs endlessly
	set_time_limit(0);

	// Call the worker
	$cmdline = $php.' scripts/worker.php';

	$executed = false;

	if (function_exists('proc_open')) {
		$resource = proc_open($cmdline . ' &', array(), $foo, $directory);

		if (is_resource($resource)) {
			$executed = true;
			proc_close($resource);
		}
	}

	if (!$executed) {
		exec($cmdline.' spawn');
	}

	// Now sleep for 5 minutes
	sleep(300);
}
