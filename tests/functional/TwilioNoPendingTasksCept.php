<?php
$I = new FunctionalTester($scenario);
$I->wantTo('Show how contoller behaves when there are no pending tasks for a provider');

$I->dontSeeInDatabase('tasks',array('assigned_phone' => '+55 555-555-554', 'status'=> 'pending'));


$I->sendPOST("/api/twilio?from=%2B55%20555-555-554&body=yes");

$I->seeResponseCodeIs(201);
