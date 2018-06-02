<?php
/**
 * File description here
 * @author      Mikołaj
 * @copyright   (C) Mikołaj
 * @package     PCS
 * @since       2018-05-13
 */

namespace Db;

use PHPUnit\Framework\TestCase;

class ActiveRecordTest extends TestCase
{
    public function testCreateTable()
    {
        $db = \PCS\Db::i();
        try {
            $db->createTable([
                'name' => "unit_activerecord",
                'temporary' => TRUE,
                'columns' => [
                    [
                        'name' => 'client_id',
                        'type' => 'BIGINT',
                        'auto_increment' => TRUE,
                        'key' => TRUE
                    ],
                    [
                        'name' => 'client_name',
                        'type' => 'VARCHAR',
                        'length' => 255
                    ],
                    [
                        'name' => 'client_surname',
                        'type' => 'VARCHAR',
                        'length' => 255
                    ],
                    [
                        'name' => 'client_register_date',
                        'type' => 'BIGINT'
                    ],
                    [
                        'name' => 'client_balance',
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
     * @depends testCreateTable
     * @param $db \PCS\Db
     */
    public function testInsert($db)
    {
        $client = new Client();
        $client->name = "Jan";
        $client->surname = "Kowalski";
        $client->joined_at = 0;
        $client->balance = 37;
        $client->save();
        $this->assertEquals(1, $db->select("COUNT(*)", "unit_activerecord")->first());
    }
}

class Client extends \PCS\Patterns\ActiveRecord
{
    static $databaseTable = 'unit_activerecord';
    static $databaseColumnPrefix = 'client_';

    /**
     * Column mappings
     *
     * @return array
     */
    public function mappings()
    {
        return ['name' => 'name', 'surname' => 'surname', 'joined_at' => 'register_date', 'balance' => 'balance'];
    }

    public function get_full_name()
    {
        return "{$this->name} {$this->surname}";
    }
}
