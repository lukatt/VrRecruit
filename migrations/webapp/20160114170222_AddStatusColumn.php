<?php

class AddStatusColumn extends Ruckusing_Migration_Base
{
    public function up()
    {
        $this->add_column('tasks','status','text');
        $this->execute("UPDATE `tasks` SET `status` = 'pending';");
    }//up()

    public function down()
    {
    }//down()
}
