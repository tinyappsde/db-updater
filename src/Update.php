<?php

namespace TinyApps\DbUpdater;

use stdClass;
use TinyApps\DbUpdater\Exceptions\InvalidConfigException;

class Update {

	protected string $id;
	protected array $queries;

	public function __construct(string $id, array $queries) {
		$this->id = $id;
		$this->queries = $queries;
	}

	/**
	 * Returns a new Update object from a config row/array item
	 *
	 * @param stdClass|array $row
	 *
	 * @throws InvalidConfigException
	 *
	 * @return self
	 */
	public static function fromConfigRow(mixed $row): self {
		$row = (object) $row;

		if (empty($row->id)) {
			throw new InvalidConfigException('Update has no valid ID');
		} else if (empty($row->queries)) {
			throw new InvalidConfigException('Update has no queries');
		}

		return new self(
			$row->id,
			$row->queries,
		);
	}

	/**
	 * Returns the unique ID of the update
	 *
	 * @return void
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Set the unique ID of the update
	 *
	 * @param string $id
	 *
	 * @return Update
	 */
	public function setId(string $id): self {
		$this->id = $id;

		return $this;
	}

	/**
	 * Returns the update's queries
	 *
	 * @return string[]
	 */
	public function getQueries(): array {
		return $this->queries;
	}

	/**
	 * Set the update's queries
	 *
	 * @param string[] $queries
	 *
	 * @return self
	 */
	public function setQueries(array $queries): self {
		$this->queries = $queries;

		return $this;
	}

	public static function randomUniqueId(): string {
		return date('Y-m-d-') . bin2hex(random_bytes(8));
	}
}
