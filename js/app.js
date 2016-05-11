var app = angular.module('tweeter', []);

app.controller('MainController', function($scope, $http){
    $http.get('api').then(function (response) {
        console.log(response.data);
        $scope.tweets = response.data;
    })
});
