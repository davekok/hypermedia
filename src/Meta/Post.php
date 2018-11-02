<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

use Doctrine\Common\Annotations\Annotation\{Annotation,Target,Attributes,Attribute};
use Exception;


/**
 * The get annotation, mark a method as a POST HTTP verb.
 *
 * Post annotation is only allowed in resources.
 *
 * @Annotation
 * @Target({"METHOD"})
 * @Attributes({
 *   @Attribute("value", type = "string"),
 * })
 */
final class Post extends Verb
{
	/**
	 * Get name
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return "POST";
	}
}
