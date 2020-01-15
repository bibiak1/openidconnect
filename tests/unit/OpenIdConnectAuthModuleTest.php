<?php

namespace OCA\OpenIdConnect\Tests\Unit;

use Jumbojett\OpenIDConnectClientException;
use OC\HintException;
use OC\Memcache\ArrayCache;
use OCA\OpenIdConnect\Client;
use OCA\OpenIdConnect\OpenIdConnectAuthModule;
use OCA\OpenIdConnect\Service\UserLookupService;
use OCP\ICacheFactory;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class OpenIdConnectAuthModuleTest extends TestCase {

	/**
	 * @var OpenIdConnectAuthModule
	 */
	private $authModule;
	/**
	 * @var MockObject | IUserManager
	 */
	private $manager;
	/**
	 * @var MockObject | ILogger
	 */
	private $logger;
	/**
	 * @var MockObject | ICacheFactory
	 */
	private $cacheFactory;
	/**
	 * @var MockObject | UserLookupService
	 */
	private $lookupService;
	/**
	 * @var MockObject | Client
	 */
	private $client;

	protected function setUp(): void {
		parent::setUp();
		$this->manager = $this->createMock(IUserManager::class);
		$this->logger = $this->createMock(ILogger::class);
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->lookupService = $this->createMock(UserLookupService::class);
		$this->client = $this->createMock(Client::class);
		$this->authModule = new OpenIdConnectAuthModule($this->manager, $this->logger, $this->cacheFactory, $this->lookupService, $this->client);
	}

	public function testNoBearer(): void {
		$request = $this->createMock(IRequest::class);

		$return = $this->authModule->auth($request);
		self::assertNull($return);
	}

	public function testNotConfigured(): void {
		$request = $this->createMock(IRequest::class);
		$request->method('getHeader')->willReturn('Bearer 1234567890');

		$return = $this->authModule->auth($request);
		self::assertNull($return);
	}

	public function testInvalidToken(): void {
		$this->client->method('getOpenIdConfig')->willReturn([]);
		$this->cacheFactory->method('create')->willReturn(new ArrayCache());
		$request = $this->createMock(IRequest::class);
		$request->method('getHeader')->willReturn('Bearer 1234567890');
		$this->logger->method('logException')->with(new OpenIDConnectClientException('Token cannot be verified.'));

		$return = $this->authModule->auth($request);
		self::assertNull($return);
	}

	public function testInvalidTokenWithIntrospection(): void {
		$this->client->method('getOpenIdConfig')->willReturn(['use-token-introspection-endpoint' => true]);
		$this->client->method('introspectToken')->willReturn((object)['error' => 'expired']);
		$this->cacheFactory->method('create')->willReturn(new ArrayCache());
		$request = $this->createMock(IRequest::class);
		$request->method('getHeader')->willReturn('Bearer 1234567890');
		$this->logger->method('logException')->with(new OpenIDConnectClientException('Verifying token failed: expired'));

		$return = $this->authModule->auth($request);
		self::assertNull($return);
	}

	public function testInvalidTokenWithIntrospectionNotActive(): void {
		$this->client->method('getOpenIdConfig')->willReturn(['use-token-introspection-endpoint' => true]);
		$this->client->method('introspectToken')->willReturn((object)['active' => false]);
		$this->cacheFactory->method('create')->willReturn(new ArrayCache());
		$request = $this->createMock(IRequest::class);
		$request->method('getHeader')->willReturn('Bearer 1234567890');
		$this->logger->method('logException')->with(new OpenIDConnectClientException('Token (as per introspection) is inactive'));

		$return = $this->authModule->auth($request);
		self::assertNull($return);
	}

	public function testExpiredTokenWithIntrospection(): void {
		$this->client->method('getOpenIdConfig')->willReturn(['use-token-introspection-endpoint' => true]);
		$this->client->method('introspectToken')->willReturn((object)['active' => true, 'exp' => \time() + 3600]);
		$this->client->method('requestUserInfo')->willReturn((object)['email' => 'foo@example.com']);
		$this->cacheFactory->method('create')->willReturn(new ArrayCache());
		$user = $this->createMock(IUser::class);
		$this->lookupService->expects(self::once())->method('lookupUser')->willReturn($user);
		$request = $this->createMock(IRequest::class);
		$request->method('getHeader')->willReturn('Bearer 1234567890');

		$return = $this->authModule->auth($request);
		self::assertEquals($user, $return);
	}
}