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
		$root;
		foreach ($resource->getVerbs() as $key => $verbs) {
			$verb = $matcher->findBestMatch($verbs);
			if ($verb) {
				$verbs = [
					$key,
					$verb->getMethod(),
					$verb->getStatus(),
					$verb->getLocation(),
					$verb->getSelf()
				];
				$root = $verb->getRoot();
			}
		}

		if ($root && count($verbs) > 1) {
			throw new Exception("A root resource may only have one verb.");
		}

		$item = $root ? new CacheItem_RootResource() : new CacheItem_Resource();
		$item->setClass($resource->getClass());
		$item->setTags($matcher->getTags());

		foreach ($resource->getFields() as $key => $fields) {
			$field = $matcher->findBestMatch($fields);
			if ($field) {
				$item->setField(
					$key,
					$field->getType(),
					$field->getDefault(),
					$field->getFlags()->toInt(),
					$field->getAutocomplete(),
					$field->getValidation(),
					$field->getLink()
				);
			}
		}

		foreach ($resource->getVerbs() as $key => $verbs) {
			$verb = $matcher->findBestMatch($verbs);
			if ($verb) {
				$item->setVerb(
					$key,
					$verb->getMethod(),
					$verb->getStatus(),
					$verb->getLocation(),
					$verb->getSelf()
				);
			}
		}

		return $item;
	}
}
