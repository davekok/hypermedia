<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Throwable;
use Exception;
use ReflectionClass;
use Sturdy\Service\ClassName;
use Doctrine\Common\Annotations\Reader;

class Cache
{
	use Utils;

	private $docReader;

	public function __construct(Reader $docReader)
	{
		$this->docReader = $docReader;
	}

	/**
	 * Scan dirs for activities.
	 *
	 * @param $unit  the unit to register
	 * @param $dirs  the directories to scan
	 */
	public function scan(string $unit, array $dirs): Unit
	{
		$unit = new Unit($unit);
		foreach ($dirs as $dir) {
			foreach ($this->iterateDirectory($dir, [".php"]) as $file) {
				try {
					$source = file_get_contents($file);
					if ($source === false) continue;
					$className = ClassName::getClassNameFromSource($source);
					if (!class_exists($className)) {
						require($file);
						if (!class_exists($className)) {
							continue;
						}
					}
					$reflect = new ReflectionClass($className);
					$annotations = $this->docReader->getClassAnnotations($reflect);
					foreach ($annotations as $annotation) {
						if ($annotation instanceof Annotation\State) {
							$unit->setStateClass($className);
						}
					}
					foreach ($reflect->getMethods() as $method) {
						$annotations = $this->docReader->getMethodAnnotations($method);
						foreach ($annotations as $annotation) {
							if (!$annotation instanceof Annotation\Action) continue;
							$unit->addAction($className, $method->getName(), $annotation->getNext(), $annotation->getDimensions());
						}
					}
				} catch (Throwable $e) {
				}
			}
		}
		return $unit;
	}

	/**
	 * Write activities to cache directory.
	 *
	 * @param $unit  unit to write to cache directory
	 */
	public function cacheActivities(Unit $unit, string $cacheDir): void
	{
		$cacheDir = $cacheDir."/".$unit->getName();
		$this->mkdir($cacheDir);
		foreach ($unit->getActions() as $dimensions => $actions) {
			$file = fopen($cacheDir."/activity $dimensions.php", "w");
			fwrite($file, "<?php\nreturn [\n");
			foreach ($actions as $action => $next) {
				fwrite($file, "'$action' => ".var_export($next, true).",\n");
			}
			fwrite($file, "];\n");
			fclose($file);
		}
	}
}
