<?php declare(strict_types=1);

namespace Sturdy\Activity\Response;

trait NoLocationTrait
{
	/**
	 * Get location header
	 *
	 * @return string  location
	 */
	public function getLocation(): ?string
	{
		return null;
	}

}
