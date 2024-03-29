<?php

namespace Vorkfork\Core;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Vorkfork\Application\ApplicationUtilities;
use Vorkfork\Auth\Auth;
use Vorkfork\Core\Exceptions\CustomPageException;
use Vorkfork\Core\Exceptions\EntityManagerNotDefinedException;
use Vorkfork\Core\Exceptions\ValidationExceptionResponse;
use Vorkfork\Core\Router\MainRouter;
use Vorkfork\Core\Translator\Translate;
use Vorkfork\Database\CustomEntityManager;
use Vorkfork\Database\Database;
use Vorkfork\Database\IDatabase;
use Vorkfork\File\File;
use Vorkfork\Router\IRouter;
use Dotenv\Dotenv;
use \Exception;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Throwable;

class Application
{
	/**
	 * @var IRouter
	 */
	protected IRouter $router;
	protected ?IDatabase $connection = null;
	protected Dotenv $env;
	protected File $filesystem;
	protected ?CustomEntityManager $entityManager = null;
	protected ApplicationUtilities $utilities;
	public static string $configKey = 'core';
	private EventDispatcher $dispatcher;
	protected Translate $translate;
	protected static Application|null $instance = null;

	public function __construct()
	{
		$this->filesystem = new File(realpath('../'));
		$this->env = Dotenv::createImmutable(realpath('../'));
		$errEnv = false;
		try {
			$this->env->load();
		} catch (Exception $e) {
			$errEnv = true;
		};
		$this->utilities = new ApplicationUtilities();
		if (!$errEnv) {
			try {
				$this->connection = $this->initDatabaseConnection();
				$this->entityManager = $this->connection->getEntityManager();
				if (!is_null($this->entityManager)) {
					$this->utilities->setEntityManager($this->entityManager);
				}
			} catch (\Doctrine\DBAL\Exception $e) {
			}
		}
		$this->translate = new Translate();
		$this->dispatcher = new EventDispatcher();
		$this->utilities->setDispatcher($this->dispatcher);
		self::$instance = $this;
	}

	public function setRouter(MainRouter $router)
	{
		$this->router = $router;
		$this->router->setDispatcher($this->dispatcher);
		$this->utilities->setRouter($this->router);
	}

	public function registerApplications()
	{
		$this->utilities->findApps(); // Register applications
	}

	public function checkUpdates(): void
	{
		if (!is_null($this->entityManager)) {
			try {
				$this->utilities->checkVersion();
			} catch (EntityManagerNotDefinedException|Throwable $e) {
			}
		}

	}

	/**
	 * @throws Throwable
	 */
	//TODO add Router redirect to update process...
	public function isAppInstalled(): bool
	{
		return !$this->filesystem->exists('../config/NOT_INSTALLED')
			&& $this->entityManager instanceof CustomEntityManager
			&& $this->utilities->getVersion() === $this->utilities->getDatabaseAppVersion()->value;
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	private function initDatabaseConnection(): IDatabase
	{
		return new Database();
	}

	/**
	 * @throws Throwable
	 */
	public function watch()
	{
		$request = Request::createFromGlobals();
		try {
			$matcher = $this->router->matchRoute($_SERVER['REQUEST_URI']);
		} catch (ResourceNotFoundException $exception) {
			return new CustomPageException($exception->getMessage(), 404);
		} catch (MethodNotAllowedException $exception) {
			return (new Response($exception->getMessage(), 403, []))->send();
		}

		if (!$this->isAppInstalled() && !$matcher['public']) {
			return $this->router->redirectTo('/install');
		} else {
			if ($this->isAppInstalled() && $matcher['_route'] === '/install/{step}') {
				return $this->router->redirectTo('/');
			}
		}

		if (isset($matcher['auth']) && $matcher['auth'] === true) {
			if (!Auth::isAuthenticated()) {
				return $this->router->redirectTo('/login');
			}
		}
		$controllerResolver = new ControllerResolver();
		$argumentResolver = new ArgumentResolver();
		$request->attributes->add($matcher);
		$kernel = new HttpKernel(
			$this->dispatcher,
			$controllerResolver,
			new RequestStack(),
			$argumentResolver);
		try {
			$response = $kernel->handle($request);
			$response->send();
			$kernel->terminate($request, $response);
		} catch (ValidationFailedException $exception) {
			return (new ValidationExceptionResponse($exception->getViolations()))->send();
		}
	}

	public static function getTranslator(): ?Translate
	{
		return self::$instance?->translate;
	}

	private static function JsonResponseException(Exception|\Error $exception, $statusCode = 500): void
	{
		$response = new JsonResponse([
			'code' => $exception->getCode(),
			'message' => $exception->getMessage()
		], $statusCode);
		$response->send();
	}


}
