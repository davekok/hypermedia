<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

use Exception;
use Doctrine\Common\Annotations\Annotation\{
	Annotation,
	Target,
	Attributes,
	Attribute
};

/**
 * Activity meta class
 */
interface SourceUnitItem
{
	/**
	 * Get all taggables
	 *
	 * @return iterable  the taggables
	 */
	public function getTaggables(): iterable;
}
