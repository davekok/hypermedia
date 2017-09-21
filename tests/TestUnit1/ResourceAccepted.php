<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity\TestUnit1;

use Sturdy\Activity\Response\Accepted;
use Sturdy\Activity\Response\OK;
use Sturdy\Activity\Meta\Field;
use Sturdy\Activity\Meta\Get;
use Sturdy\Activity\Meta\Post;

class ResourceAccepted
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
	 * @Post("accepted")
	 */
	public function bar(Accepted $response, $di): void
	{
	
	}
}
