<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

use Exception;

/**
 * Compile a resource
 */
class ResourceCompiler
{
	private $fieldDescriptors;

	/**
	 * Compile resource
	 *
	 * @return CacheItem_Resource
	 */
	public function compile(Resource $resource, TagMatcher $matcher): CacheItem_Resource
	{
		$hints = $matcher->findBestMatch($resource->getHints());

		$verbs = [];
		$root = false;
		foreach ($resource->getVerbs() as $name => $variants) {
			$verb = $matcher->findBestMatch($variants);
			if ($verb) {
				$verbs[$name] = [$verb->getMethod(), $verb->getFlags()->toInt()];
				$root = $verb->getFlags()->getRoot();
			}
		}

		if ($root && count($verbs) > 1) {
			throw new Exception("A root resource may only have one verb.");
		}

		$type = $resource->getObjectType();
		$this->compileObjectType($type, $matcher);

		$item = $root ? new CacheItem_RootResource : new CacheItem_Resource;
		$item->setClass($resource->getClass());
		if ($hints) {
			$item->setHints($hints->getLabel(), $hints->getIcon(), $hints->getSection(), $hints->getComponent(), $hints->getLayout());
		}
		$item->setTags($matcher->getTags());
		foreach ($type->getFieldDescriptors() as $descriptor) {
			$item->addField(...$descriptor);
		}
		foreach ($verbs as $name => [$method, $flags]) {
			$item->setVerb($name, $method, $flags);
		}

		return $item;
	}

	private function compileObjectType(Type\ObjectType $object, TagMatcher $matcher): void
	{
		$fieldDescriptors = [];
		foreach ($object->getFields() as $name => $variants) {
			$field = $matcher->findBestMatch($variants);
			if ($field) {
				$type = $field->getType();
				if ($type instanceof Type\ObjectType) {
					$this->compileObjectType($type, $matcher);
				}
				$fieldDescriptors[] = [
					$name,
					$type->getDescriptor(),
					$field->getDefaultValue(),
					$field->getFlags()->toInt(),
					$field->getAutocomplete(),
					$field->getLabel(),
					$field->getIcon(),
				];
			}
		}
		$object->setFieldDescriptors($fieldDescriptors);
	}

	/**
	 * @param string $name          name
	 * @param string $type          the type descriptor
	 * @param mixed  $defaultValue  the default value
	 * @param int    $flags         field flags
	 * @param string $autocomplete  autocomplete expression
	 * @param string $label         label
	 */
}
