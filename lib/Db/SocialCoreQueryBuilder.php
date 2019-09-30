<?php
declare(strict_types=1);


/**
 * Nextcloud - Social Support
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2018, Maxence Lange <maxence@artificial-owl.com>
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


namespace OCA\Social\Db;


use daita\MySmallPhpTools\Db\ExtendedQueryBuilder;
use OCA\Social\Model\ActivityPub\Actor\Person;


/**
 * Class SocialCoreQueryBuilder
 *
 * @package OCA\Social\Db
 */
class SocialCoreQueryBuilder extends ExtendedQueryBuilder {


	/** @var Person */
	private $viewer = null;


	/**
	 * @return bool
	 */
	public function hasViewer(): bool {
		return ($this->viewer !== null);
	}

	/**
	 * @param Person $viewer
	 */
	public function setViewer(Person $viewer): void {
		$this->viewer = $viewer;
	}

	/**
	 * @return Person
	 */
	public function getViewer(): Person {
		return $this->viewer;
	}


	/**
	 * @param string $id
	 *
	 * @return string
	 */
	public function prim(string $id): string {
		if ($id === '') {
			return '';
		}

		return hash('sha512', $id);
	}

}

