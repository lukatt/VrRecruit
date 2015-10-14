<?php
$I = new FunctionalTester($scenario);
$I->wantTo('Show a task assigned to Jane');
$task = $I->haveTask(['assigned_name' => 'Jane Doe']);

$I->sendGET("/api/tasks/{$task->id}");
$I->seeResponseCodeIs(200);
$I->seeResponseContains('"assigned_name":"Jane Doe"');
