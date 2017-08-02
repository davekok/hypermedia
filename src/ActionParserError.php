<?php declare(strict_types=1);

namespace Sturdy\Activity;

class ActionParserError extends \Exception
{
	public function __construct(string $message)
	{
		parent::__construct($message);
	}
}
