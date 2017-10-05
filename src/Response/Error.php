<?php declare(strict_types=1);

namespace Sturdy\Activity\Response;

use Exception;
use Throwable;

abstract class Error extends Exception implements Response
{
	use ProtocolVersionTrait;
	use DateTrait;
	use NoLocationTrait;
	
	private $messages;
	
	/**
	 * Error constructor.
	 * @param string $message
	 * @param int $code
	 * @param Throwable|null $previous
	 */
	public function __construct($message = "", $code = 0, Throwable $previous = null)
	{
		parent::__construct($message, $code, $previous);
		if($message) {
			$this->messages = [$message];
		}
	}
	
	public function addMessage(string $message)
	{
		$this->messages[] =	$message;
	}
	
	public function hasMessages(): bool
	{
		return ($this->messages > 0);
	}
	
	public function getMessages(): array
	{
		return $this->messages;
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
		switch(count($this->messages)){
			case 0:
				break;
			case 1:
				$error["message"] = $this->messages[0];
				break;
			default:
				$error["messages"] = $this->messages;
		}
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
		return json_encode(["error" => $error], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}
}
