<?php declare(strict_types=1);

namespace Sturdy\Activity;

class NoClassInSourceException extends \Exception
{
	public function __construct(string $message = null, int $code = 0)
	{
		parent::__construct($message ?? "No class found in source.", $code);
	}
}
