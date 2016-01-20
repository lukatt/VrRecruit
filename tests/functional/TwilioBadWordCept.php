<?php
$I = new FunctionalTester($scenario);
$I->wantTo('Show how contoller behaves when there are no recognised words in message');

$I->sendPOST("/api/twilio?from=%2B55%20555-555-554&body=something");

$I->seeResponseCodeIs(200);

$I->see("We can't understand your message, please check for errors and try again");
