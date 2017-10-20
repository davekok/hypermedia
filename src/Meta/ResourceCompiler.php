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
		$verbs = [];
		$root = false;
		foreach ($resource->getVerbs() as $name => $variants) {
			$verb = $matcher->findBestMatch($variants);
			if ($verb) {
				$verbs[$name] = [
					$verb->getMethod(),
					$verb->getStatus(),
					$verb->getLocation(),
					$verb->getSelf(),
					$verb->getData(),
				];
				$root = $verb->getRoot();
			}
		}

		if ($root && count($verbs) > 1) {
			throw new Exception("A root resource may only have one verb.");
		}

		$type = $resource->getObjectType();
		$this->compileObjectType($type, $matcher);

		$item = $root ? new CacheItem_RootResource : new CacheItem_Resource;
		$item->setClass($resource->getClass());
		$item->setTags($matcher->getTags());
		foreach ($type->getFieldDescriptors() as $name => $descriptor) {
			$item->setField($name, ...$descriptor);
		}
		foreach ($verbs as $name => [$method, $status, $location, $self, $data]) {
			$item->setVerb($name, $method, $status, $location, $self, $data);
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
					$field->getAutocomplete()
				);
			}
		}
	}
}
