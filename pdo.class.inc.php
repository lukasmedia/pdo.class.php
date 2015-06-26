<?php

class db
{
	private $_db		  = null;       // pointer to db
	private $_dbname	= '';         // databasename, needed in queries
  private $_stmt		= false;      // result of statements, prepared queries
  private $_vendor   = 'mssql';   // default vender. You could extend this to oracle etc.

	private function __connect()
	{
    $u = "";
    $p = "";

    if ($this->_db === null)
		{
			if ($this->_vendor == 'mssql')
			{
        $this->_dbname = MSSSQL_DB;
        $u = MSSQL_USER;
        $p = MSSQL_PASS;
        $connStr = 'dblib:host='.MSSSQL_HOST.':'.MSSQL_PORT.';dbname=' . MSSSQL_DB;
		  }
			else if ($this->_vendor == 'mysql')
			{
        $this->_dbname = MYSQL_DB;
        $u = MYSQL_USER;
        $p = MYSQL_PASS;
        $connStr = 'mysql:dbname='.MYSQL_DB.';port=3306;host=' . MYSQL_HOST;
			}
      else
          die('Please provide connection string for ' . $this->_vendor);
      
      try
      {
        $this->_db = new PDO($connStr, $u, $p);
        $this->_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      }
      catch(PDOException $e)
      {
        die(__FILE__ . " " . $e->getMessage());
      }
		}
	}

	function __construct($dbvendor = 'mssql')
	{
      if (!in_array($dbvendor, array('mssql', 'mysql')))
        die('wrong db vendor: ' . $dbvendor);
 
      if ($dbvendor == 'mssql')
      {
        if (!defined('MSSSQL_HOST'))  die('Error: MSSSQL_HOST not defined');
        if (!defined('MSSSQL_DB'))    die('Error: MSSSQL_DB not defined');
        if (!defined('MSSQL_USER'))   die('Error: MSSQL_USER not defined');
        if (!defined('MSSQL_PASS'))   die('Error: MSSQL_PASS not defined');
      }
      else if ($dbvendor == 'mysql')
      {
        if (!defined('MYSQL_HOST'))  die('Error: MYSQL_HOST not defined');
        if (!defined('MYSQL_DB'))    die('Error: MYSQL_DB not defined');
        if (!defined('MYSQL_USER'))   die('Error: MYSQL_USER not defined');
        if (!defined('MYSQL_PASS'))   die('Error: MYSQL_PASS not defined');
      }

      $this->_vendor = $dbvendor;
	}

	############################################# 

	  public function q($q, $params=array())
	  {
      try
      {
		    $this->__connect();
		    $this->_stmt = $this->_db->prepare($q);
		    $this->_stmt->execute($params);
      }
        catch(PDOException $e)
        {
          echo $q . "<hr />\n" . $e->getMessage();
        }
	  }

    /* 13-02-2014 wrapper for backwards compatible */
    public function query($q, $params=array())
    {
        $this->q($q, $params);
    }
    
    // 16-02-2015
    public function affectedRows()
    {
        return $this->_stmt->rowCount();
    }

	############################################# 

	public function getResults($q, $params=array()) // singlerow
	{
		$this->q($q, $params);
		return $this->_stmt->fetch(PDO::FETCH_ASSOC);
	}

	public function getResultsSet($q, $params=array()) // alles
	{
		$this->q($q, $params);
		return $this->_stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	############################################# 

	public function update($table=false, $update=false, $where="")
	{
		if (!$table || !$update)
			die('Error in update');

		// de waarden eruit halen
		$updateValues = array_values($update);
		// vervangen voor ? tekens
		foreach($update as $key => $value)
			$update[$key] = '?';
		// query van maken
		$update = http_build_query($update, '', ' AND ');
		
		if ($where) // zelfde riedel voor optionele where
		{
			$whereValues = array_values($where);
			foreach($where as $key => $value)
				$where[$key] = '?';
			$where = http_build_query($where, '', ' AND ');
			$updateValues = array_merge($updateValues, $whereValues);
		}

		$q = urldecode(" UPDATE {$this->_dbname}.dbo.{$table} SET {$update} WHERE {$where} ");
		$this->q($q, $updateValues);
	}

	############################################# 

	private function prepareGet($table=false, $where=false, $multipleRows=false)
	{
		if (!$table)
			die('Error in getRow(s)');
		
		if ($where)
		{
			$whereValues = array_values($where);
			foreach($where as $key => $value)
				$where[$key] = '?';
			$where = " WHERE " . http_build_query($where, '', ' AND ');
		}

		$q = urldecode(" SELECT * FROM {$this->_dbname}.dbo.{$table} " . $where);

		if ($multipleRows)
			return $this->getResultsSet($q, $whereValues);
		else
			return $this->getResults($q, $whereValues);
	}

	public function getRows($table=false, $where=false)
	{
		return $this->prepareGet($table, $where, true);
	}

	public function getRow($table=false, $where=false)
	{
		return $this->prepareGet($table, $where, false);
	}
    
    public function findBy($table, $select, $name, $value, $orderBy = null, $smartFetch = true, $like = false) {
        $this->__connect();
        
        if($like) {
            $matchingOperator = 'LIKE';
        }
        else {
            $matchingOperator = '=';
        }
        
        if($this->_mssql) {
            $select = 'TOP 1000 ' . $select;
        }
        
        if(is_array($select)) {
            $select = implode(', ', $select);
        }
        
        $query = "SELECT " . $select . " FROM " . $table . " WHERE ";
        if(is_array($name)) {
            foreach($name as $selector) {
                $query .= $selector . " " . $matchingOperator . " ? AND ";
            }
            $query = rtrim($query, " AND ");
        }
        else {
            $query .= $name . " " . $matchingOperator . " ?";
        }
        
        if($orderBy) {
            $query .= " ORDER BY " . $orderBy;
        }
        
        if(!$this->_mssql) {
            $query .= ' LIMIT 1000';
        }
        
        $this->_stmt = $this->_db->prepare($query);
        
        if(is_array($value)) {
            $this->_stmt->execute($value);
        }
        else {
            $this->_stmt->execute(array($value));
        }
        
        // rowCount returns -1 when using a MSSQL database..? FETCH_NUM not giving expected results either.
        $results = $this->_stmt->fetchAll(PDO::FETCH_ASSOC);
        if(count($results) > 1) {
            return $results;
        }
        elseif(count($results == 1) && $smartFetch) {
            return reset($results);
        }
        elseif(count($result == 1) && !$smartFetch) {
            return $results;
        }
        else {
            return array();
        }
    }

	function getLastInsertId()
	{
		return $this->_db->lastInsertId();
	}
}
