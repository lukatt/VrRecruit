<?php
$I = new FunctionalTester($scenario);
$I->wantTo('Show how contoller behaves when there are no recognised words in message');

$I->sendPOST("/api/twilio?from=%2B55%20555-555-554&body=something");

$I->seeResponseCodeIs(203);
