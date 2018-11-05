<?php declare(strict_types=1);

namespace Sturdy\Activity\Response;

use Exception;
use Throwable;

abstract class Error extends Exception implements Response
{
	use ProtocolVersionTrait;
	use DateTrait;
	use NoLocationTrait;

	/**
	 * Error constructor.
	 * @param string $message
	 * @param int $code
	 * @param Throwable|null $previous
	 */
	public function __construct($message = "", $code = 0, Throwable $previous = null)
	{
		parent::__construct($message, $code, $previous);
	}

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
		$code = $this->getCode();
		if ($code > 0) {
			$error["code"] = $code;
		}
		$message = $this->getMessage();
		if ($message) {
			$error["message"] = $this->message;
		}
		$previous = $this->getPrevious();
		if ($previous) {
			$trace = $previous->getTraceAsString();
			mb_substitute_character(0xFFFD);
			$error["previous"] = [
				"class" => get_class($previous),
				"message" => mb_convert_encoding($previous->getMessage(), 'UTF-8', 'UTF-8'),
				"trace" => explode("\n", mb_convert_encoding("## ".$previous->getFile()."(".$previous->getLine().")\n".$previous->getTraceAsString(), 'UTF-8', 'UTF-8'))
			];
		}
		return json_encode(["error" => $error], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}
}
