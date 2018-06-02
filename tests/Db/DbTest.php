<?php
/**
 * File description here
 * @author      PCS Team
 * @copyright   (C) Powerful Community System
 * @package     PCS
 * @since       2018-05-11
 */

namespace Db;

use PHPUnit\Framework\TestCase;

class DbTest extends TestCase
{
    public $tableName;

    public function setUp()
    {
        $this->tableName = 'unit_dbtest';
    }

    public function testTemporaryTable()
    {
        $db = \PCS\Db::i();
        try {
            $db->createTable([
                'name' => $this->tableName,
                'temporary' => TRUE,
                'columns' => [
                    [
                        'name' => 'id',
                        'type' => 'BIGINT',
                        'auto_increment' => TRUE,
                        'key' => TRUE
                    ],
                    [
                        'name' => 'name',
                        'type' => 'VARCHAR',
                        'length' => 255
                    ],
                    [
                        'name' => 'surname',
                        'type' => 'VARCHAR',
                        'length' => 255
                    ],
                    [
                        'name' => 'age',
                        'type' => 'INT'
                    ]
                ]
            ]);
        } catch (\PCS\Db\Exception $e) {
            $this->fail("Failed to create temporary table ({$e->getMessage()})");
        }
        $this->assertTrue(TRUE);
        return $db;
    }

    /**
     * @depends testTemporaryTable
     */
    public function testInsertDefaultValues($db)
    {
        try {
            $db->insert($this->tableName, ['name' => 'John', 'surname' => 'Doe', 'age' => 13]);
            $db->insert($this->tableName, ['name' => 'Daniel', 'surname' => 'Cook', 'age' => 27]);
            $db->insert($this->tableName, ['name' => 'Emma', 'surname' => 'Jones', 'age' => 75]);
            $db->insert($this->tableName, ['name' => 'Jennifer', 'surname' => 'Davis', 'age' => 46]);
            $db->insert($this->tableName, ['name' => 'Vincent', 'surname' => 'Rodriguez', 'age' => 94]);
        } catch (\PCS\Db\Exception $e) {
            $this->fail("Failed to insert default values ({$e->getMessage()})");
        }
        $this->assertTrue(count($db->select("*", "unit_dbtest")) == 5);
        return $db;
    }

    /**
     * @depends testInsertDefaultValues
     */
    public function testUpdateAge($db)
    {
        $db->update($this->tableName, ['age' => $db->rawParam('age+1')], ['surname=?', 'Doe']);
        $this->assertEquals(14, $db->select('age', $this->tableName, ['surname=?', 'Doe'])->first());
        return $db;
    }

    /**
     * @depends testInsertDefaultValues
     */
    public function testDeleteOld($db)
    {
        $db->delete($this->tableName, ['age>=?', 60]);
        try {
            $db->select('*', $this->tableName, ['age>=?', 60])->first();
        } catch (\UnderflowException $e) {
            $this->assertTrue(TRUE);
            return;
        }
        $this->assertTrue(FALSE);
        return $db;
    }

    /**
     * @depends testUpdateAge
     * @depends testDeleteOld
     */
    public function testSelect($db)
    {
        $this->assertEquals(87, $db->select('SUM(age)', $this->tableName)->first());
    }
}
