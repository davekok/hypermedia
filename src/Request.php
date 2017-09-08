<?php declare(strict_types=1);

namespace Sturdy\Activity;

/**
 * A request interface for getting the request
 */
interface Request
{
	/**
	 * Get the protocol version
	 *
	 * @return string  the protocol version
	 */
	public function getProtocolVersion(): string;
	public function getVerb(): string;
	public function getPath(): string;
	public function getQuery(): string;
	public function getContentType(): ?string;
	public function getContent(): ?string;
}
