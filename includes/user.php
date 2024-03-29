<?php

include 'api-keys.php';
include 'twitter-wrapper.php';


// Set Celsius and kmph as default units
if (empty($_GET['units'])) {
  $_GET['units'] = 'metric';
}

// Reset error message
$errorMessage = '';

// Check if cached data exists
$twitterCachePath = '../cache/user' . $_GET['handle'] . '.txt';

// Invalidate users cache after 5 days
if (file_exists($twitterCachePath) && time() - filemtime($twitterCachePath) < 432000) {
  // Get friends from cache
  $friends = file_get_contents($twitterCachePath);
  $friends = json_decode($friends);
} else {
  // Get friends from Twitter API
  $twitter = new TwitterAPIExchange($settings);
  // Prepare request parameters
  $friendsURL = 'https://api.twitter.com/1.1/friends/list.json';
  $requestMethod = 'GET';
  $userParam = '?screen_name=' .$_GET['handle'];

  // Make the request
  $friends = $twitter
    ->setGetfield($userParam)
    ->buildOauth($friendsURL, $requestMethod)
    ->performRequest();

  $friends = json_decode($friends);

  // Check if request was successful
  if (isset($friends->errors[0]->message)) {
    $errorMessage = $friends->errors[0]->message;
  }
  //$errorMessage = $friends->errors[0]->message ? : '';
  if ($errorMessage === '') {
    // Cache for future sessions
    file_put_contents($twitterCachePath, json_encode($friends));
  } else {
    // Redirect to homepage
    header('Location: ../?error=' . $errorMessage);
    exit();
  }

}

if (gettype($friends) === 'string') {
  $friends = json_decode($friends);
}

// Get locations from friends list
$locations = [];

foreach ($friends->users as $_user) {

  // Save location if it exists
  if ($_user->location !== '') {

    // Identify location
    $cityCachePath = '../cache/city' . md5($_user->location) . '.txt';
    // Never invalidate locations cache
    if (file_exists($cityCachePath)) {
      // Get maps data from cache if available
      $mapsData = file_get_contents($cityCachePath);
      $mapsData = json_decode($mapsData);
    } else {

      // Find location with Google Maps API
      $mapsAPI = 'https://maps.googleapis.com/maps/api/geocode/json?address=';
      $mapsURL = $mapsAPI . $_user->location . '&key=' . $mapsKey;
      $mapsURL = str_replace(' ', '%20', $mapsURL);
      $mapsData = file_get_contents($mapsURL);
      $mapsData = json_decode($mapsData);

      // Check if place exists
      if (count($mapsData->results) > 0) {
        // Cache for future sessions
        file_put_contents($cityCachePath, json_encode($mapsData));
      }
    }

    // Normalize location name
    if (count($mapsData->results) > 0) {
      // Save location name
      $normalizedLocation = $mapsData->results[0]->address_components[0]->long_name;
      // Save location country
      $country = '';
      foreach ($mapsData->results[0]->address_components as $_addressComponent) {
        if ($_addressComponent->types[0] === 'country') {
          if ($country !== $normalizedLocation) {
            $country = $_addressComponent->long_name;
          }
        }
      }
    }

    // $mapsData->results[0]->geometry->location

    if (isset($normalizedLocation) && !isset($locations[$normalizedLocation])) {
      // Create location object
      $locations[$normalizedLocation] = new stdClass();
      $locations[$normalizedLocation]->name = $normalizedLocation;
      $locations[$normalizedLocation]->country = $country;
      // Save geographical coordinates
      $locations[$normalizedLocation]->coords = $mapsData->results[0]->geometry->location;
      // Create array of users living there
      $locations[$normalizedLocation]->friends = ['@' . $_user->screen_name];
    } else if (isset($normalizedLocation)) {
      // Add user to location's array
      array_push($locations[$normalizedLocation]->friends, '@' . $_user->screen_name);
    }
  }
}

// Display error if user has no friends
if (count($locations) === 0) {
  $errorMessage = 'This user doesn\'t have enough friends';
  header('Location: ../?error=' . $errorMessage);
  exit();
}

// Prepare location sorting by friends amount
function sortLocationsByPopulation($locationA, $locationB) {
  // Get friends amount for each location
  $friendsA = count($locationA->friends);
  $friendsB = count($locationB->friends);
  
  if ($friendsA === $friendsB) {
    return 0;
  }
  return $friendsA > $friendsB ? -1 : 1;
}

// Filter locations by amount of friends
usort($locations, 'sortLocationsByPopulation');

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <link rel="stylesheet" href="../styles/reset.min.css">
  <link rel="stylesheet" href="../styles/style.css">
  <title>@<?= $_GET['handle'] ?>'s friends forecast - Social Weather</title>
</head>
<body>
  <div class="container">
    <header class="small">
      <a href="../" class="logo" title="Home - Social Weather">Social Weather</a>
      <?php include 'twitter-form.php' ?>
    </header>
    <h1 class="user-welcome">@<?= $_GET['handle'] ?>'s friends forecast</h1>
    <section class="cities">
      <?php foreach ($locations as $_location) {
        include 'city.php';
      } ?>
    </section>
  </div>
</body>
</html>