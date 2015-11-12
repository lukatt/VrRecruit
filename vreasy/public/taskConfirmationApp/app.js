angular.module('taskConfirmationApp',  ['ui.router', 'ngResource'])
.config(function($stateProvider, $urlRouterProvider, $locationProvider) {
  // Use hashtags in URL
  $locationProvider.html5Mode(false);

  $urlRouterProvider.otherwise("/");
  $stateProvider
  .state('index', {
    url: "/",
    templateUrl: "/taskConfirmationApp/templates/index.html",
    controller: 'TaskCtrl'
  });
})
.factory('Task', function($resource) {
  return $resource('/api/tasks/:id',
    {id:'@id'},
    {
      'get': {method:'GET'},
      'save': {method: 'PUT'},
      'create': {method: 'POST'},
      'query':  {method:'GET', isArray:true},
    }
    );
})
.controller('TaskCtrl', function($scope, Task) {
  $scope.tasks = Task.query();
});
