#!/usr/local/bin/php
<?php

declare(strict_types=1);

array_shift($argv);
$callback = array_shift($argv);

$inotify = inotify_init();
stream_set_blocking($inotify, true);
foreach ($argv as $dir) {
	inotify_add_watch($inotify, $dir,
		IN_CREATE
		|IN_DELETE
		|IN_ISDIR
		|IN_MOVED_TO
		|IN_MOVED_FROM
		|IN_CLOSE_WRITE);
}

$lasttime = time();
for (;;) {
	inotify_read($inotify);
	$currenttime = time();
	if ($lasttime != $currenttime) {
		echo date("[Y:m:d H:i:s]", $currenttime)," Updating...\n";
	}
	$lasttime = $currenttime;
	passthru($callback);
}
