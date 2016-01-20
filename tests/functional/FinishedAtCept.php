<?php

//this is a workaround the codeception not having "not null" as an option

$I = new FunctionalTester($scenario);
$I->wantTo('Show how twilio requests can affect finished_at column');

$I->sendPOST("/api/twilio?from=%2B55%20555-555-555&body=si");
$I->sendPOST("/api/twilio?from=%2B55%20555-555-555&body=finito");

$I->seeInDatabase('tasks',array('assigned_phone' => '+55 555-555-555', 'status'=> 'done'));

$I->dontSeeInDatabase('tasks',array('assigned_phone' => '+55 555-555-555', 'status'=> 'done', 'finished_at' => null));

$I->seeResponseCodeIs(200);
