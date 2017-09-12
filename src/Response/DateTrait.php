<?php declare(strict_types=1);

namespace Sturdy\Activity\Response;

use DateTime;

trait DateTrait
{
	private $date;

	/**
	 * Get date
	 *
	 * @return DateTime  the date
	 */
	public function getDate(): DateTime
	{
		if (empty($this->date)) {
			$this->date = \DateTime::createFromFormat('U', time());
		}
		return $this->date;
	}
}
