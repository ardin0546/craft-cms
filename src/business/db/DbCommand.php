<?php
namespace Blocks;

/**
 * Extends CDbCommand
 */
class DbCommand extends \CDbCommand
{
	/**
	 * @param $table
	 * @param $column
	 * @param $type
	 * @return mixed
	 */
	public function addColumnFirst($table, $column, $type)
	{
		$type = DatabaseHelper::generateColumnDefinition($type);
		return $this->setText($this->connection->schema->addColumnFirst($this->_addTablePrefix($table), $column, $type))->execute();
	}

	/**
	 * @param $table
	 * @param $column
	 * @param $type
	 * @param $after
	 * @return mixed
	 */
	public function addColumnAfter($table, $column, $type, $after)
	{
		$type = DatabaseHelper::generateColumnDefinition($type);
		return $this->setText($this->connection->schema->addColumnAfter($this->_addTablePrefix($table), $column, $type, $after))->execute();
	}

	/**
	 * @param $table
	 * @param $column
	 * @param $type
	 * @param $before
	 * @return mixed
	 */
	public function addColumnBefore($table, $column, $type, $before)
	{
		$type = DatabaseHelper::generateColumnDefinition($type);
		return $this->setText($this->connection->schema->addColumnBefore($this->_addTablePrefix($table), $column, $type, $before))->execute();
	}

	/**
	 * @param $table
	 * @param $columns
	 * @param $vals
	 * @return int
	 */
	public function insertAll($table, $columns, $vals)
	{
		$queryParams = $this->connection->schema->insertAll($this->_addTablePrefix($table), $columns, $vals);
		return $this->setText($queryParams['query'])->execute($queryParams['params']);
	}

	/**
	 * @return int
	 */
	public function getUUID()
	{
		$result = $this->setText($this->connection->schema->getUUID())->queryRow();
		return $result['UUID'];
	}

	/**
	 * @param $tables
	 * @return \CDbCommand
	 */
	public function from($tables)
	{
		return parent::from($this->_addTablePrefix($tables));
	}

	/**
	 * @param $table
	 * @param $conditions
	 * @param array $params
	 * @return \CDbCommand
	 */
	public function join($table, $conditions, $params=array())
	{
		return parent::join($this->_addTablePrefix($table), $conditions, $params);
	}

	/**
	 * @param $table
	 * @param $conditions
	 * @param array $params
	 * @return \CDbCommand
	 */
	public function leftJoin($table, $conditions, $params=array())
	{
		return parent::leftJoin($this->_addTablePrefix($table), $conditions, $params);
	}

	/**
	 * @param $table
	 * @param $conditions
	 * @param array $params
	 * @return \CDbCommand
	 */
	public function rightJoin($table, $conditions, $params=array())
	{
		return parent::rightJoin($this->_addTablePrefix($table), $conditions, $params);
	}

	/**
	 * @param $table
	 * @return \CDbCommand
	 */
	public function crossJoin($table)
	{
		return parent::crossJoin($this->_addTablePrefix($table));
	}

	/**
	 * @param $table
	 * @return \CDbCommand
	 */
	public function naturalJoin($table)
	{
		return parent::naturalJoin($this->_addTablePrefix($table));
	}

	/**
	 * @param $table
	 * @param $columns
	 * @return int
	 */
	public function insert($table, $columns)
	{
		return parent::insert($this->_addTablePrefix($table), $columns);
	}

	/**
	 * @param $table
	 * @param $columns
	 * @param string $conditions
	 * @param array $params
	 * @return int
	 */
	public function update($table, $columns, $conditions='', $params=array())
	{
		return parent::update($this->_addTablePrefix($table), $columns, $conditions, $params);
	}

	/**
	 * @param $table
	 * @param string $conditions
	 * @param array $params
	 * @return int
	 */
	public function delete($table, $conditions='', $params=array())
	{
		return parent::delete($this->_addTablePrefix($table), $conditions, $params);
	}

	/**
	 * Adds `id`, `date_created`, `date_update`, and `uid` columns to $columns,
	 * packages up the column definitions into strings,
	 * and then passes it back to CDbCommand->createTable()
	 *
	 * @param      $table
	 * @param      $columns
	 * @param null $options
	 * @return int
	 */
	public function createTable($table, $columns, $options=null)
	{
		$columns = array_merge(
			array('id' => AttributeType::PK),
			$columns,
			array(
				'date_created' => array('type' => AttributeType::Int, 'required' => true, 'default' => 0),
				'date_updated' => array('type' => AttributeType::Int, 'required' => true, 'default' => 0),
				'uid'          => array('type' => AttributeType::Char, 'maxLength' => 36, 'required' => true, 'default' => 0)
			)
		);

		foreach ($columns as $col => $settings)
		{
			$columns[$col] = DatabaseHelper::generateColumnDefinition($settings);
		}

		// Create the table
		$return = parent::createTable($this->_addTablePrefix($table), $columns, $options);

		// Add the language FK
		if (array_key_exists('language', $columns))
			$this->addForeignKey("{$table}_languages_fk", $table, 'language', 'languages', 'language');

		// Add the INSERT and UPDATE triggers
		DatabaseHelper::createInsertAuditTrigger($table);
		DatabaseHelper::createUpdateAuditTrigger($table);

		return $return;
	}

	/**
	 * Creates a content table
	 * @param string $table The content table name
	 * @param string $refTable The reference table name
	 * @param string $fk The foreign key column name to the reference table
	 * @return int
	 */
	public function createContentTable($table, $refTable, $fk)
	{
		$columns = array(
			$fk        => array('type' => AttributeType::Int, 'required' => true),
			'language' => AttributeType::Language
		);

		// Create the table
		$return = $this->createTable($table, $columns);

		// Create the unique constraint
		$this->createIndex("{$table}_{$fk}_language_unique_idx", $table, "{$fk},language", true);

		// Add the foreign key
		$this->addForeignKey("{$table}_{$refTable}_fk", $table, $fk, $refTable, 'id');

		return $return;
	}

	/**
	 * Creates a blocks join table
	 * @param string $table The blocks join table name
	 * @param string $refTable The reference table name
	 * @param string $fk The foreign key column name to the reference table
	 * @return int
	 */
	public function createBlocksJoinTable($table, $refTable, $fk)
	{
		$columns = array(
			$fk          => array('type' => AttributeType::Int, 'required' => true),
			'block_id'   => array('type' => AttributeType::Int, 'required' => true)
		);

		// Create the table
		$return = $this->createTable($table, $columns);

		// Create the unique constraint
		$this->createIndex("{$table}_{$fk}_block_id_unique_idx", $table, "{$fk},block_id", true);

		// Add the foreign keys
		$this->addForeignKey("{$table}_{$refTable}_fk", $table, $fk,        $refTable, 'id');
		$this->addForeignKey("{$table}_blocks_fk",      $table, 'block_id', 'blocks',  'id');

		return $return;
	}

	/**
	 * Creates a settings table
	 * @param string $table The settings table name
	 * @param string $refTable The reference table name
	 * @param string $fk The foreign key column name to the reference table
	 */
	public function createSettingsTable($table, $refTable, $fk)
	{
		$columns = array(
			$fk     => array('type' => AttributeType::Int, 'required' => true),
			'name'  => array('type' => AttributeType::Varchar, 'maxLength' => 100, 'required' => true),
			'value' => AttributeType::Text
		);

		// Create the table
		$this->createTable($table, $columns);

		// Create the unique constraint
		$this->createIndex("{$table}_{$fk}_name_unique_idx", $table, "{$fk},name", true);

		// Add the foreign key
		$this->addForeignKey("{$table}_{$refTable}_fk", $table, $fk, $refTable, 'id');
	}

	/**
	 * @param $table
	 * @param $newName
	 * @return int
	 */
	public function renameTable($table, $newName)
	{
		return parent::renameTable($this->_addTablePrefix($table), $this->_addTablePrefix($newName));
	}

	/**
	 * @param $table
	 * @return int
	 */
	public function dropTable($table)
	{
		return parent::dropTable($this->_addTablePrefix($table));
	}

	/**
	 * @param $table
	 * @return int
	 */
	public function truncateTable($table)
	{
		return parent::truncateTable($this->_addTablePrefix($table));
	}

	/**
	 * @param $table
	 * @param $column
	 * @param $type
	 * @return mixed
	 */
	public function addColumn($table, $column, $type)
	{
		$type = DatabaseHelper::generateColumnDefinition($type);
		return $this->addColumnBefore($table, $column, $type, 'date_created');
	}

	/**
	 * @param $table
	 * @param $column
	 * @return int
	 */
	public function dropColumn($table, $column)
	{
		return parent::dropColumn($this->_addTablePrefix($table), $column);
	}

	/**
	 * @param $table
	 * @param $name
	 * @param $newName
	 * @return int
	 */
	public function renameColumn($table, $name, $newName)
	{
		return parent::renameColumn($this->_addTablePrefix($table), $name, $newName);
	}

	/**
	 * @param      $table
	 * @param      $column
	 * @param      $type
	 * @param null $newName
	 * @param      $after
	 * @return int
	 */
	public function alterColumn($table, $column, $type, $newName = null, $after = null)
	{
		$table = $this->_addTablePrefix($table);
		$type = DatabaseHelper::generateColumnDefinition($type);
		return $this->setText($this->connection->schema->alterColumn($table, $column, $type, $newName, $after))->execute();
	}

	/**
	 * @param $name
	 * @param $table
	 * @param $columns
	 * @param $refTable
	 * @param $refColumns
	 * @param null $delete
	 * @param null $update
	 * @return int
	 */
	public function addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete=null, $update=null)
	{
		return parent::addForeignKey($this->_addTablePrefix($name), $this->_addTablePrefix($table), $columns, $this->_addTablePrefix($refTable), $refColumns, $delete, $update);
	}

	/**
	 * @param $name
	 * @param $table
	 * @return int
	 */
	public function dropForeignKey($name, $table)
	{
		return parent::dropForeignKey($name, $this->_addTablePrefix($table));
	}

	/**
	 * @param $name
	 * @param $table
	 * @param $column
	 * @param bool $unique
	 * @return int
	 */
	public function createIndex($name, $table, $column, $unique=false)
	{
		return parent::createIndex($this->_addTablePrefix($name), $this->_addTablePrefix($table), $column, $unique);
	}

	/**
	 * @param $name
	 * @param $table
	 * @return int
	 */
	public function dropIndex($name, $table)
	{
		return parent::dropIndex($name, $this->getTableName($table));
	}

	/**
	 * Prepares a table name for Yii to add its table prefix
	 * @param mixed $table The table name or an array of table names
	 * @return mixed The modified table name(s)
	 */
	private function _addTablePrefix($table)
	{
		if (is_array($table))
		{
			foreach ($table as &$t)
			{
				$t = $this->_addTablePrefix($t);
			}
		}
		else
			$table = preg_replace('/^\w+/', b()->config->tablePrefix.'\0', $table);

		return $table;
	}
}
