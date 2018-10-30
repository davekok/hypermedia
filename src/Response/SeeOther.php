<?php declare(strict_types=1);

namespace Sturdy\Activity\Response;

use Sturdy\Activity\Resource;
use UriTemplate\UriTemplate;

final class SeeOther implements Response
{
	use ProtocolVersionTrait;
	use DateTrait;
	use NoContentTrait;

	/**
	 * The resource
	 * @var Resource
	 */
	private $resource;

	/**
	 * The location to redirect to
	 * @var string
	 */
	private $location;

	/**
	 * The contructor
	 *
	 * @param ?Resource $resource  the resource
	 */
	public function __construct(?Resource $resource = null)
	{
		$this->resource = $resource;
	}

	/**
	 * Get the response status code
	 *
	 * @return int  the status code
	 */
	public function getStatusCode(): int
	{
		return 303;
	}

	/**
	 * Get the response status text
	 *
	 * @return string  the status text
	 */
	public function getStatusText(): string
	{
		return "See Other";
	}

	/**
	 * Set location
	 *
	 * @param string $class   the class of the resource to redirect to
	 * @param array  $values  any values to pass to resource
	 */
	public function setLocation(string $class, array $values = []): void
	{
		$link = $this->resource->createLink($class);
		if ($link === null) throw new InternalServerError("Resource $class not found.");
		$this->location = $link->expand($values, false)->href;
	}

	/**
	 * Set location URL
	 *
	 * @param string $url   the url to redirect to
	 */
	public function setLocationURL(string $url, array $parameters = null): void
	{
		if (empty($parameters)) {
			$this->location = $url;
		} else {
			$this->location = UriTemplate::expand($url, $parameters)
		}
	}

	/**
	 * Get location
	 *
	 * @return string  the location on which the resource has been created
	 */
	public function getLocation(): string
	{
		return $this->location;
	}
}
