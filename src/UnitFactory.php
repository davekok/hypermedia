<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Throwable;
use Exception;
use Generator;
use ReflectionClass;
use Doctrine\Common\Annotations\Reader;

/**
 * Unit factory
 */
final class UnitFactory
{
	private $annotationReader;

	/**
	 * Constructor
	 *
	 * @param $annotationReader  a annotation reader
	 */
	public function __construct(Reader $annotationReader)
	{
		class_exists('Sturdy\Activity\Annotation\Action'); // make sure annotation class is loaded
		$this->annotationReader = $annotationReader;
	}

	/**
	 * Create a unit from source
	 *
	 * @param $unit  the unit to update
	 * @param $dirs  the directories to scan for sources
	 */
	public function createUnitFromSource(string $unitName, string $dirs): Unit
	{
		$unit = new Unit($unitName);
		foreach ($this->iterateDirectory($dirs, ["php"]) as $file) {
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
							$annotation->getConst(),
							$next,
							$annotation->getDimensions());
					}
				}
			} catch (NoClassInSourceException $e) {
				//
			} catch (Throwable $e) {
				echo "\n",$e->getMessage()," (",$e->getFile(),":",$e->getLine(),")\n";
			}
		}
		return $unit;
	}

	/**
	 * Iterate a directory with its subdirectories and return all files with matching extensions.
	 *
	 * @param $dirs   the directories separated by a colon to scan for files
	 * @param $exts   extensions to filter on
	 * @yield string  a file that matched the requested extensions
	 * @return Generator<string>
	 */
	public function iterateDirectory(string $dirs, array $exts): Generator
	{
		foreach (explode(PATH_SEPARATOR, $dirs) as $dir) {
			$dr = opendir($dir);
			if ($dr === false) return;
			while (($entry = readdir($dr)) !== false) {
				if ($entry[0] == ".") continue; // skip hidden files
				$file = $dir.DIRECTORY_SEPARATOR.$entry;
				if (!is_readable($file)) continue;
				if (is_dir($file)) {
					if (!is_executable($file)) continue;
					yield from $this->iterateDirectory($file, $exts);
				}
				if ($exts && in_array(pathinfo($file, PATHINFO_EXTENSION), $exts)) {
					yield $file;
				}
			}
			closedir($dr);
		}
	}

	/**
	 * Get the class defined in the PHP source.
	 *
	 * Note only one class per source expected.
	 *
	 * @param $source  the source to scan
	 * @return the full class name
	 */
	public function getClassNameFromSource(string $source): string
	{
		$className = "";
		$state = 0;
		foreach (token_get_all($source, TOKEN_PARSE) as $token) {
			switch ($state) {
				case 0; // find namespace or class
					if ($token[0] === T_NAMESPACE || $token[0] === T_CLASS) {
						$state = 1;
						$type = $token[0];
					}
					break;
				case 1: // append strings to $className until another token is found
					switch ($token[0]) {
						case T_WHITESPACE: // skip white space
							break;
						case T_STRING: // part of the class name
							$className .= $token[1];
							break;
						case T_NS_SEPARATOR: // name space separator
							$className .= '\\';
							break;
						default: // start of another token
							if ($type === T_CLASS) {
								return $className;
							}
							$className .= '\\';
							$state = 0;
							break;
					}
			}
		}
		throw new NoClassInSourceException();
	}
}
