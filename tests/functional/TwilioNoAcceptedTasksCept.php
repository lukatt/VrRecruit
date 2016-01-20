<?php
$I = new FunctionalTester($scenario);
$I->wantTo('Show how contoller behaves when there are no accepted tasks for a provider');

$I->dontSeeInDatabase('tasks',array('assigned_phone' => '+55 555-555-554', 'status'=> 'accepted'));


$I->sendPOST("/api/twilio?from=%2B55%20555-555-554&body=done");

$I->seeResponseCodeIs(200);
$I->see("You don't have any accepted tasks");

