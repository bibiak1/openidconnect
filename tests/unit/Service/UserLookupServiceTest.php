<?php
/**
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @copyright Copyright (c) 2020, ownCloud GmbH
 * @license GPL-2.0
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\OpenIdConnect\Tests\Unit\Service;

use OC\HintException;
use OC\User\LoginException;
use OCA\OpenIdConnect\Client;
use OCA\OpenIdConnect\Service\AutoProvisioningService;
use OCA\OpenIdConnect\Service\UserLookupService;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IUser;
use OCP\IUserManager;
use OCP\User\NotPermittedActionException;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class UserLookupServiceTest extends TestCase {

	/**
	 * @var UserLookupService
	 */
	private $userLookup;
	/**
	 * @var MockObject | Client
	 */
	private $client;
	/**
	 * @var MockObject | IUserManager
	 */
	private $manager;

	protected function setUp(): void {
		parent::setUp();
		$this->client = $this->createMock(Client::class);
		$this->manager = $this->createMock(IUserManager::class);
		$autoProvisioningService = $this->createMock(AutoProvisioningService::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')
			->willReturnCallback(function ($text, $parameters = []) {
				return \vsprintf($text, $parameters);
			});
		$logger = $this->createMock(ILogger::class);

		$this->userLookup = new UserLookupService(
			$this->manager,
			$this->client,
			$autoProvisioningService,
			$l10n,
			$logger
		);
	}

	/**
	 * @throws LoginException
	 * @throws NotPermittedActionException
	 */
	public function testNotConfigured(): void {
		$this->expectException(HintException::class);
		$this->expectExceptionMessage('OpenIdConnect: Missing configuration');

		$this->userLookup->lookupUser(null);
	}

	/**
	 * @throws HintException
	 * @throws NotPermittedActionException
	 */
	public function testLookupByEMailNotFound(): void {
		$this->expectException(LoginException::class);
		$this->expectExceptionMessage('User with foo@example.com is not known.');
		$this->client->method('getOpenIdConfig')->willReturn([]);
		$this->client->method('mode')->willReturn('email');
		$this->client->method('getIdentityClaim')->willReturn('email');
		$this->userLookup->lookupUser((object)['email' => 'foo@example.com']);
	}

	/**
	 * @throws HintException
	 * @throws NotPermittedActionException
	 */
	public function testLookupByEMailNotUnique(): void {
		$this->expectException(LoginException::class);
		$this->expectExceptionMessage('foo@example.com is not unique.');
		$this->client->method('getOpenIdConfig')->willReturn([]);
		$this->manager->method('getByEmail')->willReturn([1, 2]);
		$this->client->method('mode')->willReturn('email');
		$this->client->method('getIdentityClaim')->willReturn('email');
		$this->userLookup->lookupUser((object)['email' => 'foo@example.com']);
	}

	/**
	 * @throws LoginException
	 * @throws HintException
	 * @throws NotPermittedActionException
	 */
	public function testLookupByEMail(): void {
		$this->client->method('getOpenIdConfig')->willReturn([]);
		$this->client->method('mode')->willReturn('email');
		$this->client->method('getIdentityClaim')->willReturn('email');
		$user = $this->createMock(IUser::class);
		$this->manager->method('getByEmail')->willReturn([$user]);
		$return = $this->userLookup->lookupUser((object)['email' => 'foo@example.com']);
		self::assertEquals($user, $return);
	}

	/**
	 * @throws HintException
	 * @throws NotPermittedActionException
	 */
	public function testLookupByUserIdNotFound(): void {
		$this->expectException(LoginException::class);
		$this->expectExceptionMessage('User alice is not known.');
		$this->client->method('getOpenIdConfig')->willReturn(['mode' => 'userid', 'search-attribute' => 'preferred_username']);
		$this->client->method('getIdentityClaim')->willReturn('preferred_username');
		$this->client->method('mode')->willReturn('userid');
		$this->userLookup->lookupUser((object)['preferred_username' => 'alice']);
	}

	/**
	 * @throws LoginException
	 * @throws HintException
	 * @throws NotPermittedActionException
	 */
	public function testLookupByUserId(): void {
		$this->client->method('getOpenIdConfig')->willReturn(['mode' => 'userid', 'search-attribute' => 'preferred_username']);
		$this->client->method('mode')->willReturn('userid');
		$this->client->method('getIdentityClaim')->willReturn('preferred_username');
		$user = $this->createMock(IUser::class);
		$this->manager->method('get')->willReturn($user);
		$return = $this->userLookup->lookupUser((object)['preferred_username' => 'alice']);
		self::assertEquals($user, $return);
	}

	/**
	 * @throws HintException
	 * @throws NotPermittedActionException
	 */
	public function testInvalidUserBackEnd(): void {
		$this->client->method('getOpenIdConfig')->willReturn(['mode' => 'userid', 'search-attribute' => 'preferred_username', 'allowed-user-backends' => ['LDAP']]);
		$this->client->method('getIdentityClaim')->willReturn('preferred_username');
		$this->client->method('mode')->willReturn('userid');
		$user = $this->createMock(IUser::class);
		$user->method('getBackendClassName')->willReturn('Database');
		$this->manager->method('get')->willReturn($user);

		$this->expectException(LoginException::class);
		$this->userLookup->lookupUser((object)['preferred_username' => 'alice']);
	}

	/**
	 * @throws LoginException
	 * @throws HintException
	 * @throws NotPermittedActionException
	 */
	public function testValidUserBackEnd(): void {
		$this->client->method('getOpenIdConfig')->willReturn(['allowed-user-backends' => ['LDAP']]);
		$this->client->method('getIdentityClaim')->willReturn('preferred_username');
		$this->client->method('mode')->willReturn('userid');
		$user = $this->createMock(IUser::class);
		$user->method('getBackendClassName')->willReturn('LDAP');
		$this->manager->method('get')->willReturn($user);
		$return = $this->userLookup->lookupUser((object)['preferred_username' => 'alice']);
		self::assertEquals($user, $return);
	}
}
