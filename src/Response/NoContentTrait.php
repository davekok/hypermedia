<?php declare(strict_types=1);

namespace Sturdy\Activity\Response;

use DateTime;

trait NoContentTrait
{
	/**
	 * Get the content type
	 *
	 * @return string  content type
	 */
	public function getContentType(): ?string
	{
		return null;
	}

	/**
	 * Get the content
	 *
	 * @return string  content
	 */
	public function getContent(): ?string
	{
		return null;
	}
}
