<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

interface ResourceBuilder
{
	/**
	 * Get the resources
	 *
	 * @return Resource[]  the resources
	 */
	public function getResources(): iterable;
}
