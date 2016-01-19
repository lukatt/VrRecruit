<?php
$I = new FunctionalTester($scenario);
$I->wantTo('Show how twilio requests can affect the database with no');

$I->dontSeeInDatabase('tasks',array('assigned_phone' => '+55 555-555-555', 'status'=> 'denied'));

$I->sendPOST("/api/twilio?from=%2B55%20555-555-555&body=no");

$I->seeInDatabase('tasks',array('assigned_phone' => '+55 555-555-555', 'status'=> 'denied'));

$I->seeResponseCodeIs(200);