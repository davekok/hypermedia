<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity\TestUnit2;

use Sturdy\Activity\Action;

class Activity2
{
	/**
	 * @Action("start readonly >action2")
	 */
	public function action1()
	{
	}

	/**
	 * @Action("readonly")
	 */
	public function action2()
	{
	}
}
