<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity\TestUnit2;

use Sturdy\Activity\Meta\Action;

class Activity1
{
	/**
	 * @Action("start >action2 #route=/ #role=customer")
	 */
	public function action1()
	{
	}

	/**
	 * @Action("end")
	 */
	public function action2()
	{
	}
}
