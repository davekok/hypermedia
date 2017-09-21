<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity\TestUnit1;

use Sturdy\Activity\Response\Created;
use Sturdy\Activity\Response\OK;
use Sturdy\Activity\Meta\Field;
use Sturdy\Activity\Meta\Get;
use Sturdy\Activity\Meta\Post;

class ResourceCreated
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
	 * @Post("created")
	 */
	public function bar(Created $response, $di): void
	{
	
	}
}
