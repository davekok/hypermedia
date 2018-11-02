<?php declare(strict_types=1);

namespace Sturdy\Activity\Response;

trait ProtocolVersionTrait
{
	private $protocolVersion;

	/**
	 * Set protocol version
	 *
	 * @param string $protocolVersion
	 * @return self
	 */
	public function setProtocolVersion(string $protocolVersion): self
	{
		$this->protocolVersion = $protocolVersion;
		return $this;
	}

	/**
	 * Get protocol version
	 *
	 * @return string
	 */
	public function getProtocolVersion(): string
	{
		return $this->protocolVersion;
	}
}
