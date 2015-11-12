<?php
$I = new FunctionalTester($scenario);
$I->wantTo('List all the tasks');

$I->sendGET('/api/tasks');
$I->seeResponseCodeIs(200);
