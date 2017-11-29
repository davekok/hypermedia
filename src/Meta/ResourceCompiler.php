<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

use Exception;

/**
 * Compile a resource
 */
class ResourceCompiler
{
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
				$verbs[$name] = [$verb->getMethod(), $verb->getFlags()->toInt(), $verb->getLocation()];
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
			$item->setHints($hints->getLabel(), $hints->getSection(), $hints->getComponent(), $hints->getLayout());
		}
		$item->setTags($matcher->getTags());
		foreach ($type->getFieldDescriptors() as $name => $descriptor) {
			$item->setField($name, ...$descriptor);
		}
		foreach ($verbs as $name => [$method, $flags, $location]) {
			$item->setVerb($name, $method, $flags, $location);
		}

		return $item;
	}

	private function compileObjectType(Type\ObjectType $type, TagMatcher $matcher)
	{
		foreach ($type->getFields() as $name => $variants) {
			$field = $matcher->findBestMatch($variants);
			if ($field) {
				$subtype = $field->getType();
				if ($subtype instanceof Type\ObjectType) {
					$this->compileObjectType($subtype, $matcher);
				}
				$type->setFieldDescriptor(
					$name,
					$field->getType()->getDescriptor(),
					$field->getDefaultValue(),
					$field->getFlags()->toInt(),
					$field->getAutocomplete(),
					$field->getLabel()
				);
			}
		}
	}
}
