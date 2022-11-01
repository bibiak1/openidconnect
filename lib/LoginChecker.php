<?php
/**
 * @author Juan Pablo Villafañez Ramos <jvillafanez@owncloud.com>
 *
 * @copyright Copyright (c) 2022, ownCloud GmbH
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
namespace OCA\OpenIdConnect;

use OCP\IL10N;
use OC\Helper\UserTypeHelper;
use OC\User\LoginException;

class LoginChecker {
	/** @var UserTypeHelper */
	private $userTypeHelper;
	/** @var IL10N*/
	private $l10n;

	public function __construct(UserTypeHelper $userTypeHelper, IL10N $l10n) {
		$this->userTypeHelper = $userTypeHelper;
		$this->l10n = $l10n;
	}

	/**
	 * @param string $uid the uid to check if it's a guest or not
	 * @throws LoginException if the uid isn't a guest
	 */
	public function ensurePasswordLoginJustForGuest($loginType, $uid) {
		if (!$this->userTypeHelper->isGuestUser($uid) && $loginType === 'password') {
			throw new LoginException($this->l10n->t('Only guests are allowed through this authentication mechanism'));
		}
	}
}