<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OC\Security\IdentityProof;

use OC\Files\AppData\Factory;
use OCP\Files\IAppData;
use OCP\IUser;
use OCP\Security\ICrypto;

class Manager {
	/** @var IAppData */
	private $appData;
	/** @var ICrypto */
	private $crypto;

	/**
	 * @param Factory $appDataFactory
	 * @param ICrypto $crypto
	 */
	public function __construct(Factory $appDataFactory,
								ICrypto $crypto) {
		$this->appData = $appDataFactory->get('identityproof');
		$this->crypto = $crypto;
	}

	/**
	 * Calls the openssl functions to generate a public and private key.
	 * In a separate function for unit testing purposes.
	 *
	 * @return array [$publicKey, $privateKey]
	 */
	protected function generateKeyPair() {
		$config = [
			'digest_alg' => 'sha512',
			'private_key_bits' => 2048,
		];

		// Generate new key
		$res = openssl_pkey_new($config);
		openssl_pkey_export($res, $privateKey);

		// Extract the public key from $res to $pubKey
		$publicKey = openssl_pkey_get_details($res);
		$publicKey = $publicKey['key'];

		return [$publicKey, $privateKey];
	}

	/**
	 * Generate a key for $user
	 * Note: If a key already exists it will be overwritten
	 *
	 * @param IUser $user
	 * @return Key
	 */
	protected function generateKey(IUser $user) {
		list($publicKey, $privateKey) = $this->generateKeyPair();

		// Write the private and public key to the disk
		try {
			$this->appData->newFolder($user->getUID());
		} catch (\Exception $e) {}
		$folder = $this->appData->getFolder($user->getUID());
		$folder->newFile('private')
			->putContent($this->crypto->encrypt($privateKey));
		$folder->newFile('public')
			->putContent($publicKey);

		return new Key($publicKey, $privateKey);
	}

	/**
	 * Get public and private key for $user
	 *
	 * @param IUser $user
	 * @return Key
	 */
	public function getKey(IUser $user) {
		try {
			$folder = $this->appData->getFolder($user->getUID());
			$privateKey = $this->crypto->decrypt(
				$folder->getFile('private')->getContent()
			);
			$publicKey = $folder->getFile('public')->getContent();
			return new Key($publicKey, $privateKey);
		} catch (\Exception $e) {
			return $this->generateKey($user);
		}
	}
}
