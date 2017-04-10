<?php declare(strict_types=1);

namespace Sturdy\Activity;

trait Utils
{
	/**
	 * Iterate a directory with its subdirectories and return all files with matching extensions.
	 *
	 * @param $dir    the directory to scan
	 * @param $exts   an array of extensions that should be matched
	 * @yield string  a file that matched the requested extensions
	 * @return Generator<string>
	 */
	public function iterateDirectory(string $dir, array $exts): Generator
	{
		$dr = opendir($dir);
		if ($dr === false) return;
		while (($entry = readdir($dr)) !== false) {
			if ($entry[0] == ".") continue;
			$file = "$dir/$entry";
			if (!is_readable($file)) continue;
			if (is_dir($file)) {
				if (!is_executable($file)) continue;
				yield from $this->iterateDirectory($file, $exts);
			}
			if (in_array(pathinfo($file, PATHINFO_EXTENSION), $exts)) {
				yield $file;
			}
		}
		closedir($dr);
	}

	/**
	 * Make directory if not exists.
	 *
	 * @param $dir  the directory to make
	 */
	public function mkdir($dir): void
	{
		if (!file_exists($dir))
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
