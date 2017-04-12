<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Throwable;
use Exception;
use ReflectionClass;
use Generator;

final class Cache
{
	use MkDir;
	use ClassNameFromSource;

	private static $units = [];
	private $annotationReader;
	private $cacheDir;
	private $fileMask;

	/**
	 * Constructor
	 *
	 * @param $annotationReader  annotation reader
	 * @param $cacheDir          the cache dir
	 * @param $fileMask          the file mask, for directory and file creation
	 */
	public function __construct(
		\Doctrine\Common\Annotations\Reader $annotationReader,
		string $cacheDir = null,
		int $fileMask = 00002)
	{
		$this->annotationReader = $annotationReader;
		$this->cacheDir = $this->filterDir($cacheDir, 'cache');
		$this->fileMask = $fileMask;
		$this->mkdir($this->cacheDir, $this->fileMask);
	}

	/**
	 * Get a unit by name.
	 *
	 * @param $unitName  the unit to register
	 */
	public function getUnit(string $unitName): Unit
	{
		if (isset(self::$units[$unitName])) {
			return self::$units[$unitName];
		}
		$cacheFile = $this->cacheDir.DIRECTORY_SEPARATOR.$unitName.".object";
		if (file_exists($cacheFile)) {
			return unserialize(file_get_contents($cacheFile));
		}
	}

	/**
	 * Update a unit
	 *
	 * @param $unit  the unit to update
	 * @param $dirs  the directories to scan for sources
	 */
	public function updateUnit(string $unitName, string $dirs): Unit
	{
		$unit = new Unit($unitName);
		foreach ($this->iterateDirectory($dirs, "php") as $file) {
			try {
				$source = file_get_contents($file);
				if ($source === false) continue;
				$className = $this->getClassNameFromSource($source);
				if (!class_exists($className)) {
					require($file);
					if (!class_exists($className)) {
						continue;
					}
				}
				$reflect = new ReflectionClass($className);
				foreach ($reflect->getMethods() as $method) {
					$annotations = $this->annotationReader->getMethodAnnotations($method);
					foreach ($annotations as $annotation) {
						if (!$annotation instanceof Annotation\Action) continue;
						$next = $annotation->getNext();
						if (is_string($next)) {
							if (strpos($next, "::") === false) {
								$next = "$className::$next";
							}
						} elseif (is_array($next)) {
							foreach ($next as $value => &$action) {
								if (strpos($action, "::") === false) {
									$action = "$className::$action";
								}
							}
						}
						$unit->addAction(
							$className,
							$method->getName(),
							$annotation->getStart(),
							$next,
							$annotation->getDimensions());
					}
				}
			} catch (Throwable $e) {
				echo "\n",$e->getMessage()," (",$e->getFile(),":",$e->getLine(),")\n";
			}
		}
		$cacheFile = $this->cacheDir.DIRECTORY_SEPARATOR."$unitName.object";
		file_put_contents($cacheFile, serialize($unit));
		if (isset(self::$units[$unitName])) {
			self::$units[$unitName] = $unit;
		}
		return $unit;
	}

	/**
	 * Write activities to cache directory.
	 *
	 * @param $unit  unit to write to cache directory
	 */
	public function cacheActivities(Unit $unit): void
	{
		$cacheDir = $this->mkdir($this->cacheDir.DIRECTORY_SEPARATOR.$unit->getName(), $this->fileMask);
		foreach ($unit->getActions() as $dimensions => $actions) {
			$filename = trim("activity $dimensions");
			$old = umask($this->fileMask);
			$file = fopen($cacheDir.DIRECTORY_SEPARATOR.$filename.".php", "w");
			umask($old);
			fwrite($file, "<?php\nreturn [\n");
			foreach ($actions as $action => $next) {
				fwrite($file, "'$action' => ".var_export($next, true).",\n");
			}
			fwrite($file, "];\n");
			fclose($file);
		}
	}

	/**
	 * Iterate a directory with its subdirectories and return all files with matching extensions.
	 *
	 * @param $dirs   the directories separated by a colon to scan for files
	 * @param $exts   extensions separated by a colon
	 * @yield string  a file that matched the requested extensions
	 * @return Generator<string>
	 */
	public function iterateDirectory(string $dirs, string $exts): Generator
	{
		$exts = explode(PATH_SEPARATOR, $exts);
		foreach (explode(PATH_SEPARATOR, $dirs) as $dir) {
			$dr = opendir($dir);
			if ($dr === false) return;
			while (($entry = readdir($dr)) !== false) {
				if ($entry[0] == ".") continue;
				$file = $dir.DIRECTORY_SEPARATOR.$entry;
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
	}
}

// register annotation namespace
if (class_exists('\Doctrine\Common\Annotations\AnnotationRegistry')) {
	\Doctrine\Common\Annotations\AnnotationRegistry::registerFile(__DIR__.DIRECTORY_SEPARATOR.'Annotation'.DIRECTORY_SEPARATOR.'Action.php');
}
