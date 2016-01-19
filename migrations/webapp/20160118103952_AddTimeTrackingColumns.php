<?php

class AddTimeTrackingColumns extends Ruckusing_Migration_Base
{
    public function up()
    {
    	$this->add_column('tasks','responded_at','datetime');
    	$this->add_column('tasks','finished_at','datetime');
    }//up()

    public function down()
    {
    }//down()
}
