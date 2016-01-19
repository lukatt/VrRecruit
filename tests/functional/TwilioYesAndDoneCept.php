<?php
$I = new FunctionalTester($scenario);
$I->wantTo('Show how twilio requests can affect the database with yes and done');

$I->dontSeeInDatabase('tasks',array('assigned_phone' => '+55 555-555-555', 'status'=> 'accepted'));

$I->sendPOST("/api/twilio?from=%2B55%20555-555-555&body=yes");

$I->seeInDatabase('tasks',array('assigned_phone' => '+55 555-555-555', 'status'=> 'accepted'));

$I->seeResponseCodeIs(200);

$I->dontSeeInDatabase('tasks',array('assigned_phone' => '+55 555-555-555', 'status'=> 'done'));

$I->sendPOST("/api/twilio?from=%2B55%20555-555-555&body=done");

$I->seeInDatabase('tasks',array('assigned_phone' => '+55 555-555-555', 'status'=> 'done'));

$I->seeResponseCodeIs(200);
