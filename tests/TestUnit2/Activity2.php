<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity\TestUnit2;

use Sturdy\Activity\Annotation\Action;

class Activity2
{
	/**
	 * @Action("start const =>action2")
	 */
	public function action1()
	{
	}

	/**
	 * @Action("const")
	 */
	public function action2()
	{
	}
}
