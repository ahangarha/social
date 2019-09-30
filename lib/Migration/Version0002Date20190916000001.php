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


namespace OCA\Social\Migration;


use Closure;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\SchemaException;
use Exception;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;


/**
 * Class Version0002Date20190916000001
 *
 * @package OCA\Social\Migration
 */
class Version0002Date20190916000001 extends SimpleMigrationStep {


	/** @var IDBConnection */
	private $connection;


	/**
	 * @param IDBConnection $connection
	 */
	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}


	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 *
	 * @return ISchemaWrapper
	 * @throws SchemaException
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options
	): ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('social_a2_follows');
		if (!$table->hasColumn('follow_id_prim')) {
			$table->addColumn(
				'follow_id_prim', 'string',
				[
					'notnull' => true,
					'length'  => 128,
				]
			);
		}
		if (!$table->hasColumn('actor_id_prim')) {
			$table->addColumn(
				'actor_id_prim', 'string',
				[
					'notnull' => true,
					'length'  => 128,
				]
			);
		}
		if (!$table->hasColumn('object_id_prim')) {
			$table->addColumn(
				'object_id_prim', 'string',
				[
					'notnull' => true,
					'length'  => 128,
				]
			);
		}

		if (!$schema->hasTable('social_a2_stream_dest')) {
			$table = $schema->createTable('social_a2_stream_dest');

			$table->addColumn(
				'stream_id', 'string',
				[
					'notnull' => true,
					'length'  => 128,
				]
			);
			$table->addColumn(
				'actor_id', 'string',
				[
					'notnull' => true,
					'length'  => 128,
				]
			);
			$table->addColumn(
				'type', 'string',
				[
					'notnull' => false,
					'length'  => 15,
				]
			);
			$table->addColumn(
				'subtype', 'string',
				[
					'notnull' => false,
					'length'  => 7,
				]
			);

			if (!$table->hasIndex('sat')) {
				$table->addUniqueIndex(['stream_id', 'actor_id', 'type'], 'sat');
				$table->addIndex(['type', 'subtype'], 'ts');
			}

		}

		$table = $schema->getTable('social_a2_stream');
		if (!$table->hasColumn('object_id_prim')) {
			$table->addColumn(
				'object_id_prim', 'string',
				[
					'notnull' => true,
					'length'  => 128,
				]
			);
		}
		if (!$table->hasColumn('attributed_to_prim')) {
			$table->addColumn(
				'attributed_to_prim', 'string',
				[
					'notnull' => true,
					'length'  => 128,
				]
			);
		}


		$table = $schema->getTable('social_a2_stream_action');
		if (!$table->hasColumn('actor_id_prim')) {
			$table->addColumn(
				'actor_id_prim', 'string',
				[
					'notnull' => true,
					'length'  => 128,
				]
			);
		}
		if (!$table->hasColumn('stream_id_prim')) {
			$table->addColumn(
				'stream_id_prim', 'string',
				[
					'notnull' => true,
					'length'  => 128,
				]
			);
		}

		return $schema;
	}


	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 *
	 * @throws Exception
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->updateTableStream($schema);
		$this->updateTableFollows($schema);
		$this->updateTableStreamActions($schema);
		$this->fillTableStreamDest($schema);
	}


	/**
	 * @param ISchemaWrapper $schema
	 */
	private function updateTableFollows(ISchemaWrapper $schema) {
		if (!$schema->hasTable('social_a2_follows')) {
			return;
		}

		$qb = $this->connection->getQueryBuilder();
		$qb->select('*')
		   ->from('social_a2_follows');

		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			$this->updateFollowPrim($data);
		}

		$cursor->closeCursor();
	}

	/**
	 * @param array $data
	 */
	private function updateFollowPrim(array $data) {
		if ($data['follow_id_prim'] !== '') {
			return;
		}

		$update = $this->connection->getQueryBuilder();
		$update->update('social_a2_follows');
		$update->set('follow_id_prim', $update->createNamedParameter(hash('sha512', $data['follow_id'])));
		$update->set('object_id_prim', $update->createNamedParameter(hash('sha512', $data['object_id'])));
		$update->set('actor_id_prim', $update->createNamedParameter(hash('sha512', $data['actor_id'])));

		$expr = $update->expr();
		$update->where($expr->eq('id_prim', $update->createNamedParameter($data['id_prim'])));

		$update->execute();
	}


	/**
	 * @param ISchemaWrapper $schema
	 */
	private function updateTableStreamActions(ISchemaWrapper $schema) {
		if (!$schema->hasTable('social_a2_stream_action')) {
			return;
		}

		$qb = $this->connection->getQueryBuilder();
		$qb->select('*')
		   ->from('social_a2_stream_action');

		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			$this->updateStreamActionsPrim($data);
		}

		$cursor->closeCursor();
	}

	/**
	 * @param array $data
	 */
	private function updateStreamActionsPrim(array $data) {
		if ($data['actor_id_prim'] !== '') {
			return;
		}

		$update = $this->connection->getQueryBuilder();
		$update->update('social_a2_stream_action');
		$update->set('stream_id_prim', $update->createNamedParameter(hash('sha512', $data['stream_id'])));
		$update->set('actor_id_prim', $update->createNamedParameter(hash('sha512', $data['actor_id'])));

		$expr = $update->expr();
		$update->where($expr->eq('stream_id', $update->createNamedParameter($data['stream_id'])));
		$update->andWhere($expr->eq('actor_id', $update->createNamedParameter($data['actor_id'])));

		$update->execute();
	}


	/**
	 * @param ISchemaWrapper $schema
	 */
	private function updateTableStream(ISchemaWrapper $schema) {
		if (!$schema->hasTable('social_a2_stream')) {
			return;
		}

		$qb = $this->connection->getQueryBuilder();
		$qb->select('id_prim', 'object_id', 'attributed_to', 'attributed_to_prim')
		   ->from('social_a2_stream');

		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			$this->updateStreamPrim($data);
		}

		$cursor->closeCursor();
	}


	/**
	 * @param array $data
	 */
	private function updateStreamPrim(array $data) {
		if ($data['attributed_to_prim'] !== '') {
			return;
		}

		$update = $this->connection->getQueryBuilder();
		$update->update('social_a2_stream');
		if ($data['object_id'] !== '') {
			$update->set('object_id_prim', $update->createNamedParameter(hash('sha512', $data['object_id'])));
		}
		$update->set(
			'attributed_to_prim', $update->createNamedParameter(hash('sha512', $data['attributed_to']))
		);

		$expr = $update->expr();
		$update->where($expr->eq('id_prim', $update->createNamedParameter($data['id_prim'])));

		$update->execute();
	}


	/**
	 * @param ISchemaWrapper $schema
	 */
	private function fillTableStreamDest(ISchemaWrapper $schema) {
		if (!$schema->hasTable('social_a2_stream')) {
			return;
		}

		$start = 0;
		$limit = 1000;
		while (true) {
			$qb = $this->connection->getQueryBuilder();
			$qb->select('id_prim', 'to', 'to_array', 'cc', 'bcc', 'attributed_to')
			   ->from('social_a2_stream')
			   ->setMaxResults(1000)
			   ->setFirstResult($start);

			$cursor = $qb->execute();
			$count = 0;
			while ($data = $cursor->fetch()) {
				$count++;

				$this->insertStreamDest($data);
			}
			$cursor->closeCursor();

			$start += $count;
			if ($count < $limit) {
				break;
			}
		}
	}

	private function insertStreamDest($data) {
		$recipients = [];
		$recipients['to'] =
			array_merge(json_decode($data['to_array'], true), [$data['to']], [$data['attributed_to']]);
		$recipients['cc'] = array_merge(json_decode($data['cc'], true), json_decode($data['bcc'], true));

		$streamId = $data['id_prim'];
		foreach (array_keys($recipients) as $dest) {
			$subtype = $dest;
			foreach ($recipients[$dest] as $actorId) {
				if ($actorId === '') {
					continue;
				}
				$insert = $this->connection->getQueryBuilder();
				$insert->insert('social_a2_stream_dest');

				$insert->setValue('stream_id', $insert->createNamedParameter($streamId));
				$insert->setValue('actor_id', $insert->createNamedParameter(hash('sha512', $actorId)));
				$insert->setValue('type', $insert->createNamedParameter('recipient'));
				$insert->setValue('subtype', $insert->createNamedParameter($subtype));

				try {
					$insert->execute();
				} catch (UniqueConstraintViolationException $e) {
					\OC::$server->getLogger()
								->log(3, 'Social - Duplicate recipient on Stream ' . json_encode($data));
				}
			}
		}
	}

}
