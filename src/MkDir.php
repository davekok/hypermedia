<?php declare(strict_types=1);

namespace Sturdy\Activity;

trait MkDir
{
	/**
	 * Make directory if not exists.
	 *
	 * @param $dir  the directory to make
	 */
	public function mkdir($dir): void
	{
		if (!file_exists($dir)) {
			if (!mkdir($dir, 02775, true)) {
				throw new Exception("Unable to make directory $dir.");
			}
		} elseif (!is_dir($dir)) {
			throw new Exception("$dir is not a directory.");
		} elseif (!is_executable($dir)) {
			throw new Exception("$dir is not accessable.");
		} elseif (!is_writable($dir)) {
			throw new Exception("$dir is not writable.");
		}
	}
}
