<?php

namespace Storl\WpApiAuth\Entity;

use DateTime;
use DateTimeZone;
use InvalidArgumentException;

class UserMappingEntity
{

	private DateTime $created_at;

	private int $user_id;

	private string $external_user_id;

	private static array $required_fields = [
		'created_at',
		'user_id',
		'external_user_id',
	];

	public function __construct(array $params)
	{
		if ($missing = array_diff(self::$required_fields, array_keys($params))) {
			throw new InvalidArgumentException('Following fields are required for UserMappingEntity but are missing: ' . join(', ', $missing));
		}

		$this->created_at = $params['created_at'];
		$this->user_id = $params['user_id'];
		$this->external_user_id = $params['external_user_id'];
	}

	public static function from_state(array $state): self
	{
		$state['created_at'] = (new DateTime($state['created_at'], new DateTimeZone('UTC')))
			->setTimezone(\wp_timezone());

		return new self($state);
	}

	public function to_array(): array
	{
		return [
			'created_at' => (clone $this->created_at)->setTimeZone(new \DateTimeZone('UTC'))->format(DateTime::W3C),
			'user_id' => $this->user_id,
			'external_user_id' => $this->external_user_id,
		];
	}


	public function get_user_id(): int
	{
		return $this->user_id;
	}

	public function set_user_id(int $user_id): self
	{
		$this->user_id = $user_id;
		return $this;
	}

	public function get_external_user_id(): string
	{
		return $this->external_user_id;
	}

	public function set_external_user_id(string $external_user_id): self
	{
		$this->external_user_id = $external_user_id;
		return $this;
	}

	public function get_created_at(): DateTime
	{
		return $this->created_at;
	}
}
