<?php

namespace Vorkfork\Core\Exceptions;

use Vorkfork\Core\Templates\TemplateRenderer;
use SebastianBergmann\Diff\Exception;
use Symfony\Component\HttpFoundation\Response;

class CustomPageException extends \Exception
{
	protected TemplateRenderer $templateRenderer;

	/**
	 * @throws \Twig\Error\RuntimeError
	 * @throws \Twig\Error\SyntaxError
	 * @throws \Twig\Error\LoaderError
	 */
	public function __construct(string $message = "", int $statusCode = 500, ?\Throwable $previous = null)
	{
		parent::__construct($message, $statusCode, $previous);
		$this->templateRenderer = new TemplateRenderer();
		try {
			$content = $this->templateRenderer->loadTemplate('errors/exception', [
				'code' => $this->getCode(),
				'message' => $this->getMessage(),
				'file' => $this->getFile(),
				'line' => $this->getLine(),
				'trace' => $this->getTraceAsString(),
			]);
			$r = new Response($content, $statusCode);
			$r->send();
		} catch (Exception $exception) {
			dd($exception);
		}

	}
}