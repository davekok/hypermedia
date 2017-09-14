<?php declare(strict_types=1);

namespace Sturdy\Activity\Response;

use Exception;

abstract class Error extends Exception implements Response
{
	use ProtocolVersionTrait;
	use DateTrait;
	use NoLocationTrait;

	/**
	 * Get the content type
	 *
	 * @return string  content type
	 */
	public function getContentType(): ?string
	{
		return "application/json";
	}

	/**
	 * Get content
	 *
	 * @return string  content
	 */
	public function getContent(): string
	{
		$error = ["message"=>$this->getMessage()];
		$code = $this->getCode();
		if ($code > 0) {
			$error["code"] = $code;
		}
		$previous = $this->getPrevious();
		if ($previous) {
			$error["previous"] = [
				"message" => $previous->getMessage(),
				"trace" => explode("\n", $previous->getTraceAsString())
			];
		}
		return json_encode(["error"=>$error], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
	}
}
