<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

use Throwable;
use Exception;
use Generator;
use ReflectionClass;
use Doctrine\Common\Annotations\Reader;
use Ds\Set;

/**
 * Create a source unit from one or more directories and
 * scan those for PHP files and annotations within.
 */
final class SourceUnitFactory
{
	private $actionParser;
	private $annotationReader;
	private $di;

	/**
	 * Constructor
	 *
	 * @param $annotationReader  a annotation reader
	 */
	public function __construct(Reader $annotationReader, $di)
	{
		// make sure the annotation classes are loaded
		class_exists('Sturdy\Activity\Meta\Action');
		class_exists('Sturdy\Activity\Meta\Field');
		class_exists('Sturdy\Activity\Meta\Get');
		class_exists('Sturdy\Activity\Meta\Post');
		$this->actionParser = new ActionParser();
		$this->annotationReader = $annotationReader;
		$this->di = $di;
	}

	/**
	 * Create a source unit from source code
	 *
	 * @param $unit       the unit to update
	 * @param $dirs       the directories to scan for sources
	 */
	public function createSourceUnit(string $unitName, string $dirs): SourceUnit
	{
		// clear annotation cache, if possible
		if (method_exists($this->annotationReader, "reset")) {
			$this->annotationReader->reset();
		}
		$classes = new Set();
		$unit = new SourceUnit($unitName);
		foreach ($this->iterateDirectory($dirs, ["php"]) as $file) {
			try {
				$source = file_get_contents($file);
				if ($source === false) continue;
				$className = $this->getClassNameFromSource($source);
				if (!class_exists($className)) {
					require($file);
					if (!class_exists($className, false)) {
						continue;
					}
				}
				if (is_subclass_of($className, ResourceBuilder::class)) {
					foreach ((new $className($this, $this->di))->getResources() as $resource) {
						$classes->add($resource->getClass());
						$unit->addResource($resource);
					}
					$resource = null;
				} else if (!$classes->contains($className)) { // don't overwrite classes already added
					$classes->add($className);
					$reflect = new ReflectionClass($className);
					foreach ($this->annotationReader->getClassAnnotations($reflect) as $annotation) {
						if ($annotation instanceof Hints) {
							if (!isset($resource)) $resource = new Resource($className, $this->getDescription($reflect->getDocComment()?:""));
							$resource->addHints($annotation);
						}
						if ($annotation instanceof Order) {
							if (!isset($resource)) $resource = new Resource($className, $this->getDescription($reflect->getDocComment()?:""));
							$resource->addOrder($annotation);
						}
					}
					$this->reflectProperties($reflect, $resource);
					$this->reflectMethods($reflect, $resource, $activity);
				}
				if (isset($activity)) {
					$unit->addActivity($activity);
					$activity = null;
				}
				if (isset($resource)) {
					$unit->addResource($resource);
					$resource = null;
				}
			} catch (NoClassInSourceException $e) {
				// skip file, assume it is a helper file
			} catch (Throwable $e) {
				echo "\n",$e->getMessage()," (",$e->getFile(),":",$e->getLine(),")\n";
			}
		}
		return $unit;
	}

	public function reflectProperties(ReflectionClass $reflect, ?Resource &$resource)
	{
		$defaults = $reflect->getDefaultProperties();
		foreach ($reflect->getProperties() as $property) {
			foreach ($this->annotationReader->getPropertyAnnotations($property) as $annotation) {
				if ($annotation instanceof Field) {
					$annotation->setName($property->getName());
					$annotation->setDefaultValue($defaults[$property->getName()]);
					$annotation->setDescription($this->getDescription($property->getDocComment()?:""));
					if (!isset($resource)) $resource = new Resource($reflect->getName(), $this->getDescription($reflect->getDocComment()?:""));
					$resource->addField($annotation);
				}
			}
		}
	}

	public function reflectMethods(ReflectionClass $reflect, ?Resource &$resource, ?Activity &$activity): void
	{
		foreach ($reflect->getMethods() as $method) {
			foreach ($this->annotationReader->getMethodAnnotations($method) as $annotation) {
				if ($annotation instanceof Action) {
					$annotation->setName($method->getName());
					$annotation->setDescription($this->getDescription($method->getDocComment()?:""));
					if (!isset($activity)) $activity = new Activity($reflect->getName(), $this->getDescription($reflect->getDocComment()?:""));
					$activity->addAction($this->actionParser->parse($annotation));
				} else if ($annotation instanceof Verb) {
					$annotation->setMethod($method->getName());
					$annotation->setDescription($this->getDescription($method->getDocComment()?:""));
					if (!isset($resource)) $resource = new Resource($reflect->getName(), $this->getDescription($reflect->getDocComment()?:""));
					$resource->addVerb($annotation);
				}
			}
		}
	}

	/**
	 * Iterate a directory with its subdirectories and return all files with matching extensions.
	 *
	 * @param $dirs   the directories separated by a colon to scan for files
	 * @param $exts   extensions to filter on
	 * @yield string  a file that matched the requested extensions
	 * @return Generator<string>
	 */
	public static function iterateDirectory(string $dirs, array $exts): Generator
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
					yield from self::iterateDirectory($file, $exts);
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
	public static function getClassNameFromSource(string $source): string
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

	/**
	 * Get the description from a document comment.
	 *
	 * Strips the document start /** and side character *.
	 * Only returns the text from the document comment up
	 * to the first line that starts with an annotation.
	 *
	 * @param  string  $doccomment  the doc comment
	 * @return string               the description
	 */
	public static function getDescription(string $doccomment): string
	{
		$nl = '\R';
		$text = '\V*';
		$space = '\h*';
		$docstart = '^' . preg_quote('/**', '/') . $nl;
		$docline = $space . preg_quote('*', '/') . $space . "($text)" . $nl;
		$docend = $space . preg_quote('*/', '/') . '$';
		if (preg_match("/$docstart/", $doccomment, $matches)) {
			$doccomment = substr($doccomment, strlen($matches[0])); // strip doccomment start marker
			if (preg_match("/$docend/", $doccomment, $matches)) {
				$doccomment = substr($doccomment, 0, -strlen($matches[0])); // strip doccomment end marker
				$doccomment = preg_replace("/$docline/", "$1\n", $doccomment); // strip doccomment left side marker

				// match up to first annotation and return
				if (preg_match("/^(?:(?!@[A-Za-z]+)$text$nl)+/", $doccomment, $matches)) {
					return trim($matches[0])."\n";
				}
			}
		}
		return "";
	}
}
