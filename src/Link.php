<?php declare(strict_types=1);

namespace Sturdy\Activity;

/**
 * A HyperMedia Link
 */
final class Link
{
	private $href;
	private $templated;

	public function __construct(string $href, bool $templated = false)
	{
		$this->href = $href;
		$this->templated = $templated;
	}

	public function getHref(): string
	{
		return $this->href;
	}

	public function getTemplated(): bool
	{
		return $this->templated;
	}

	public function toJson(): string
	{
		if ($this->templated) {
			return json_encode(["href"=>$this->href,"templated"=>$this->templated], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
		} else {
			return json_encode(["href"=>$this->href], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
		}
	}
}
