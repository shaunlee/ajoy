<?php

/**
 *
 */
class AjoyDatabase extends AjoyComponent implements IAjoyDatabase
{
    public $dsn;
    public $user;
    public $password;
    public $charset;
    public $prefix;

    private $dbconn;

    public function db()
    {
        return $this->dbconn;
    }

    public function is($rdbms)
    {
        return strpos($this->dsn, $rdbms . ':') === 0;
    }

    /**
     *
     */
    public function init()
    {
        $db = $this->dbconn = new PDO($this->dsn, $this->user, $this->password, array(
            #PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ));

        if (isset($this->charset) && $this->is('mysql'))
            $db->exec('SET NAMES ' . $this->charset);
    }

    private function solvePrefix($sql)
    {
        return preg_replace('/\{(\w+)\}/', ($this->prefix ? $this->prefix : '') . '$1', $sql);
    }

    public function begin()
    {
        return $this->db()->beginTransaction();
    }

    public function commit()
    {
        return $this->db()->commit();
    }

    public function rollback()
    {
        return $this->db()->rollBack();
    }

    public function execute($sql)
    {
        $args = func_get_args();
        $sql = array_shift($args);
        if (count($args) && is_array($args[0]))
            $args = array_shift($args);

        $sth = $this->db()->prepare($this->solvePrefix($sql));
        $sth->execute($args);
    }

    /**
     *
     * @example
     *      app()->db->insert('{table}', array('foo' => 'bar'))
     *
     * @param string $table
     * @param object $fields
     *
     * @return int last inserted id
     */
    public function insert($table, array $fields)
    {
        if (empty($fields))
            app()->raise('Fields cannot be empty.');

        $table = $this->solvePrefix($table);
        $names = array();
        $marks = array();
        foreach ($fields as $k => $v) {
            $names[] = $k;
            $marks[] = ':' . $k;
        }

        $sql = 'INSERT INTO ' . $table
            . ' (' . implode(', ', $names)
            . ') VALUES (' . implode(', ', $marks) . ')';

        $sth = $this->db()->prepare($sql);

        if ($sth->execute($fields)) {
            $sequence = null;
            if ($this->is('pgsql')) {
                # TODO:
                $sequence = $table . '_id_seq';
                if (!$this->scalar('SELECT 1 FROM pg_statio_user_sequences WHERE relname = ?', array($sequence)))
                    $sequence = null;
            }
            return $this->db()->lastInsertId($sequence);
        }
    }

    /**
     *
     * @example
     *      app()->db->update('{table}', array('foo' => 'bar'), array('uid' => 1))
     *
     */
    public function update($table, array $fields, array $conditions)
    {
        if (empty($fields))
            app()->raise('Fields cannot be empty.');
        if (empty($conditions))
            app()->raise('Conditions cannot be empty.');

        $table = $this->solvePrefix($table);
        $sets = array();
        $where = array();
        foreach ($fields as $k => $v) {
            $sets[] = $k . ' = :' . $k;
        }
        foreach ($conditions as $k => $v) {
            $where[] = $k . ' = :' . $k;
        }
        $values = array_merge($fields, $conditions);

        # UPDATE $table SET f = v, f = v, ... WHERE k = v OR k = v AND k = v OR k BETWEEN v1 AND v2
        $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $sets) . ' WHERE ' . implode(' AND ', $where);

        $sth = $this->db()->prepare($sql);
        $sth->execute($values);
    }

    /**
     *
     * @example
     *      app()->db->delete('{table}', array('uid' => 1))
     *
     */
    public function delete($table, array $conditions)
    {
        if (empty($conditions))
            app()->raise('Conditions cannot be empty.');

        $table = $this->solvePrefix($table);
        $where = array();
        foreach ($conditions as $k => $v) {
            $where[] = $k . ' = :' . $k;
        }

        # DELETE FROM $table WHERE k = v OR k = v AND k = v OR k BETWEEN v1 AND v2
        $sql = 'DELETE FROM ' . $table . ' WHERE ' . implode(' AND ', $where);
        $sth = $this->db()->prepare($sql);
        $sth->execute($conditions);
    }

    /**
     *
     * @example
     *      app()->db->one('SELECT * FROM {table} LIMIT 1')
     *      app()->db->one('SELECT * FROM {table} WHERE uid = ?', 1)
     *      app()->db->one('SELECT * FROM {table} WHERE uid = :uid', array('uid' => 1))
     *
     */
    public function one($sql)
    {
        $args = func_get_args();
        $sql = array_shift($args);
        if (count($args) && is_array($args[0]))
            $args = array_shift($args);

        $sth = $this->db()->prepare($this->solvePrefix($sql));
        $sth->execute($args);
        return $sth->fetch();
    }

    /**
     *
     * @example
     *      app()->db->all('SELECT * FROM {table}')
     *      app()->db->all('SELECT * FROM {table} WHERE uid > ?', 1)
     *      app()->db->all('SELECT * FROM {table} WHERE uid > :uid', array('uid' => 1))
     *
     */
    public function all($sql)
    {
        $args = func_get_args();
        $sql = array_shift($args);
        if (count($args) && is_array($args[0]))
            $args = array_shift($args);

        $sth = $this->db()->prepare($this->solvePrefix($sql));
        $sth->execute($args);
        return $sth->fetchAll();
    }

    /**
     *
     * @example
     *      app()->db->scalar('SELECT count(*) FROM {table}')
     *      app()->db->scalar('SELECT count(*) FROM {table} WHERE uid > ?', 1)
     *      app()->db->scalar('SELECT count(*) FROM {table} WHERE uid > :uid', array('uid' => 1))
     *
     */
    public function scalar($sql)
    {
        $args = func_get_args();
        $sql = array_shift($args);
        if (count($args) && is_array($args[0]))
            $args = array_shift($args);

        $sth = $this->db()->prepare($this->solvePrefix($sql));
        $sth->execute($args);
        return $sth->fetchColumn();
    }

}
