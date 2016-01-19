<?php

//this is a workaround the codeception not having "not null" as an option

$I = new FunctionalTester($scenario);
$I->wantTo('Show how twilio requests can affect responded_at column');

$I->sendPOST("/api/twilio?from=%2B55%20555-555-555&body=ok");

$I->seeInDatabase('tasks',array('assigned_phone' => '+55 555-555-555', 'status'=> 'accepted'));

$I->dontSeeInDatabase('tasks',array('assigned_phone' => '+55 555-555-555', 'status'=> 'accepted', 'responded_at' => null));

$I->seeResponseCodeIs(200);