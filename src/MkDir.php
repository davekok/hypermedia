<?php declare(strict_types=1);

namespace Sturdy\Activity;

trait MkDir
{
	/**
	 * Filter the directory.
	 */
	public function filterDir(?string $dir, string $type): string
	{
		if ($dir === null) {
			$dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.$type;
		}
		return $dir.DIRECTORY_SEPARATOR.strtolower(strtr(__NAMESPACE__, "\\", DIRECTORY_SEPARATOR));
	}

	/**
	 * Make directory if not exists or check that it is usable.
	 *
	 * @param $dir   the directory to make
	 * @param $mask  the mask to use for creating directories
	 */
	public function mkdir(string $dir, int $mask = 00002): string
	{
		if (!file_exists($dir)) {
			if (!mkdir($dir, 02777 & (~$mask), true)) {
				throw new Exception("Unable to make directory $dir.");
			}
		} elseif (!is_dir($dir)) {
			throw new InvalidArgumentException("File '$dir' is not a directory.");
		} elseif (!is_executable($dir)) {
			throw new InvalidArgumentException("Directory '$dir' is not accessable.");
		} elseif (!is_writable($dir)) {
			throw new InvalidArgumentException("Directory '$dir' is not writable.");
		}
		return $dir;
	}
}
