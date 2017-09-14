<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity\TestUnit1;

use Sturdy\Activity\Response\OK;
use Sturdy\Activity\Response\NoContent;
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
	public function foo(OK $response, $di): void
	{
	}

	/**
	 * @Post("no-content")
	 */
	public function bar(NoContent $response, $di): void
	{
	}
}
