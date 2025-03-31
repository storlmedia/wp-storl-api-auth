<?php

namespace Storl\WpApiAuth\Repository;

use Storl\WpApiAuth\Entity\UserMappingEntity;
use WP_User;
use WP_Site;

class UserMappingRepository
{
	private $table;
	private $wpdb;

	public function __construct()
	{
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table = "{$wpdb->base_prefix}storl_user_mappings";
	}

	private function get_lock_mode(?string $param = null): string
	{
		if ($param === 'pessimistic_write') {
			return 'FOR UPDATE';
		}
		if ($param === 'pessimistic_read') {
			return 'FOR SHARE';
		}
		return '';
	}

	/**
	 * @param string[] $sort Column sort enum for query result.
	 *
	 * @return string ORDER BY clause to add to query
	 */
	private function get_order_by(array $sort): string
	{
		$cols = [];
		foreach ($sort as $sort) {
			$matches = [];
			if (preg_match('/^([a-z0-9_]+)_(desc|asc)$/i', $sort, $matches)) {
				$col = strtolower($matches[1]);
				$mode = strtoupper($matches[2]);
				$cols[] = "{$col} {$mode}";
			}
		}
		return $cols ? "ORDER BY " . join(', ', $cols) : '';
	}


	/**
	 * @return int Id of created user mapping.
	 */
	public function insert(array $row): int
	{
		$affected_rows = $this->wpdb->insert($this->table, $row);

		if ($affected_rows === false) {
			throw new \Exception('User mapping could not be created');
		}

		return $this->wpdb->insert_id;
	}

	public function get(int $user_id, $params = []): ?UserMappingEntity
	{
		$params = array_merge([
			'lock_mode' => null,
		], $params);

		$query = "
            SELECT
                *
			FROM
                {$this->table}
			WHERE
                {$this->table}.user_id = %d
			{$this->get_lock_mode($params['lock_mode'])}
            ;
        ";

		$row = $this->wpdb->get_row($this->wpdb->prepare($query, $user_id), 'ARRAY_A');

		return empty($row) ? null : UserMappingEntity::from_state($row);
	}


	/**
	 * @param array $params
	 * @param bool $with_count Fetch total user mapping count
	 *
	 * @return array{0:UserMappingEntity[],1:?int} User mappings and total count
	 */
	public function find(array $params, bool $with_count = false): array
	{
		$params = array_merge([
			'per_page' => 20,
			'page' => 1,
			'filter' => [],
			'sort' => [],
			'lock_mode' => null,
		], $params);
		$where = [];
		$where_args = [];

		if ($params['filter']) {
			$filter = $params['filter'];
			if (isset($filter['external_user_id'])) {
				$where[] = "`external_user_id` = %s";
				$where_args[] = $filter['external_user_id'];
			}
		}
		$where = $where ? 'WHERE ' . join(" AND ", $where) : '';
		$query = "
            SELECT
                *
            FROM
                `{$this->table}`
			{$where}
			{$this->get_order_by($params['sort'])}
            LIMIT %d, %d
			{$this->get_lock_mode($params['lock_mode'])}
            ;
        ";

		$rows = $this->wpdb->get_results($this->wpdb->prepare($query, [
			...$where_args,
			($params['page'] - 1) * $params['per_page'], // page num to offset
			$params['per_page'],
		]), 'ARRAY_A');

		$items = array_map(fn ($row) => UserMappingEntity::from_state($row), $rows);

		$count = null;
		if ($with_count) {
			$query_count = "
				SELECT
					COUNT(1) as count
				FROM
					`{$this->table}`
				{$where}
				;
			";

			if ($where) {
				$query_count = $this->wpdb->prepare($query_count, $where_args);
			}
			$row = $this->wpdb->get_row($query_count, 'ARRAY_A');
			$count = $row['count'];
		}

		return [$items, $count];
	}

	public function find_one(array $params): ?UserMappingEntity
	{
		list($items) = $this->find(array_merge($params, [
			'page' => 1,
			'per_page' => 1,
			'sort' => [],
		]));
		return $items[0] ?? null;
	}

	public function find_one_or_fail(array $params): UserMappingEntity
	{
		$entity = $this->find_one($params);
		if (!$entity) {
			throw new \Exception('user mapping not found');
		}
		return $entity;
	}

	public function save(UserMappingEntity $user_mapping): void
	{
		$row = $user_mapping->to_array();

		$affected_rows = $this->wpdb->update($this->table, $row, ['id' => $row['id']]);

		if ($affected_rows === false) {
			throw new \Exception('user mapping could not be saved');
		}
	}

	public function upsert(array $user_mapping): bool
	{
		$affected_rows = $this->wpdb->replace($this->table, $user_mapping);

		if ($affected_rows === false) {
			throw new \Exception('user mapping could not be upserted');
		}

		return $affected_rows > 0;
	}

	public function delete(int $id): bool
	{
		$affected_rows = $this->wpdb->delete($this->table, ['id' => $id]);

		if ($affected_rows === false) {
			throw new \Exception('user mapping could not be deleted');
		}

		return $affected_rows > 0;
	}

	/**
	 * @return int Affected rows by query
	 */
	public function delete_many(array $ids): int
	{
		$id_list = join(',', array_map(fn ($val) => intval($val), $ids));
		$query = "
            DELETE FROM
                `{$this->table}`
			WHERE
				id IN ({$id_list})
            ;
        ";

		return $this->wpdb->query($query, 'ARRAY_A');
	}
}
