<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity\TestUnit2;

use Sturdy\Activity\Annotation\Action;
use Sturdy\Activity\Annotation\Dimension as Dim;

class Activity1
{
	/**
	 * @Action("start =>action2 #route=/ #role=customer")
	 */
	public function action1()
	{
	}

	/**
	 * @Action()
	 */
	public function action2()
	{
	}
}
