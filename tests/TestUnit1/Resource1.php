<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity\TestUnit1;

use Sturdy\Activity\HyperMedia;
use Sturdy\Activity\Meta\Field;
use Sturdy\Activity\Meta\Get;
use Sturdy\Activity\Meta\Post;

class Resource1
{
	/**
	 * @Field("string required")
	 */
	public $name;

	/**
	 * @Get
	 */
	public function foo(HyperMedia $hm, $di): void
	{
	}

	/**
	 * @Post("no-content")
	 */
	public function bar(HyperMedia $hm, $di): void
	{
	}
}
