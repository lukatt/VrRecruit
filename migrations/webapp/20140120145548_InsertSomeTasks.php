<?php

class InsertSomeTasks extends Ruckusing_Migration_Base
{
    public function up()
    {
        foreach ([1,2,3] as $i) {
            $this->execute("INSERT INTO tasks
                (`deadline`, `assigned_name`, `assigned_phone`,
                     `created_at`, `updated_at`)
                VALUES
                (
                    '".(new \DateTime("+$i days"))->format('Y-m-d H:i:s')."',
                    'John Doe',
                    '+55 555-555-555',
                    '".(new \DateTime("+$i days"))->format('Y-m-d H:i:s')."',
                    '".(new \DateTime("+$i days"))->format('Y-m-d H:i:s')."'
                )");
        }
    }//up()

    public function down()
    {
    }//down()
}
