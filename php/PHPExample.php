<?php
// *******************************************************************************************
// Server Side Code for demonstrating integrating with Learning Recommendations via the API.
// SLI Systems Inc.
// Feb 2016
// This code is intended for demonstration purposes only and is not intended to be used in production.
// There are several settings in the code that refer to test environment variables - these need to be
// set correctly for the particular implementation, client and site.
// *******************************************************************************************

// Step 1 of 7: Enter client id, provided by SLI
$SLI_CLIENT_ID = xxxx;
// Step 2 of 7: Enter site id, provided by SLI
$SLI_SITE_ID = x;
// Step 3 of 7: Enter strategy id, provided by SLI e.g. 5570c93c0cf27fb4c007e9da
$SLI_STRATEGY_ID = "xxxxxxxxxxxxx";
// Step 4 of 7: input SKUs for this strategy, if applicable (eg. bought-also-bought)
$SLI_STRATEGY_INPUT_SKUS = "congi-2505-0328";
// Step 5 of 7: Enter number of recommendations to show
$SLI_RECOMMENDATION_REQUEST_COUNT = 4;
// Step 6 of 7: Enter the fields required for recommendation display
$SLI_PRODUCT_ATTRIBUTES = "&att=image&att=url&att=price&att=title&att=sku";
// Step 7 of 7: Enter the top level domain of the web site.
// if the whole site is on www.<mysite>.com then use www.<mysite>.com
// but if some pages are served on subdomains then this needs to
// be the highest common domain.
$SLI_DOMAIN = ".mysite.com";


$cookieSuffix = fletcher32($SLI_DOMAIN);
$COOKIE_EXPIRES_SESSION = 0;
$COOKIE_EXPIRES_30_MIN = time() + 1800; ## 30 Minutes
$COOKIE_EXPIRES_6_MONTHS = time() + (86400 * 180); ## 6 MONTHS
$COOKIE_EXPIRES_2_YEARS = time() + (86400 * 730); ## 2 YEARS
$SLI_COOKIE_1 = "SLI1" . $cookieSuffix;
$SLI_COOKIE_2 = "SLI2" . $cookieSuffix;
$SLI_COOKIE_4 = "SLI4" . $cookieSuffix;
$SLI_COOKIE_BEACON = "SLIBeacon" . $cookieSuffix;

// if the 30 minute cookie still exists then use this for the user session
// else check the 2 year cookie and use this to create a new session
// otherwise request a new session from the recommendations server
$userHasSessionId = false;
$userSession = null;
$sessionStartTime = null;
$pageViews = null;
$searches = null;

// Learning Recommendations expects the user session in this format:
// ADBCEDEFGHIJKLMNOPQRSTUVXYZABCD.1234567890123.123.123
$COOKIE_REGEX = "/[\w]{31}\.[\d]{13}\.[\d]+\.[\d]+/";

if (isset ($_COOKIE[$SLI_COOKIE_1])) {
    $existingSessionId = $_COOKIE[$SLI_COOKIE_1];
    if (isset($existingSessionId) && preg_match($COOKIE_REGEX, $existingSessionId)) {
        $parts = explode(".", $existingSessionId);
        if ($parts && is_array($parts) && count($parts) == 4) {
            $userSession = $parts[0];
            $sessionStartTime = $parts[1];
            $pageViews = $parts[2] + 1;
            $searches = $parts[3];
        }
    }
} elseif (isset($_COOKIE[$SLI_COOKIE_BEACON])) {
    $userSession = $_COOKIE[$SLI_COOKIE_BEACON];
    $sessionStartTime = time() * 1000;
    $pageViews = 1;
    $searches = 0;
}
$newSessionId = createNewSession($userSession, $sessionStartTime, $pageViews, $searches);

if (isset($newSessionId) && preg_match($COOKIE_REGEX, $newSessionId)) {
    $userHasSessionId = true;
}

$url = "http://$SLI_CLIENT_ID-$SLI_SITE_ID.sli-r.com/r-api/1/r.json?sid=$SLI_STRATEGY_ID&c=$SLI_RECOMMENDATION_REQUEST_COUNT&att=$SLI_PRODUCT_ATTRIBUTES&sku=$SLI_STRATEGY_INPUT_SKUS";
if ($userHasSessionId) {
    $url .= "&sn=$newSessionId";
}

// Make the request - in this case it is json so decode it and
// use the returned sessionId value
$recommendationJson = getRecommendations($url);
// for this example let's assume it always works
$recommendations = json_decode($recommendationJson);

$newSessionIsValid = false;
// if there were no cookies then we can get a new sessionId from the learning recommendations response
if ($userHasSessionId === false) {
    $parts = explode(".", $recommendations->sessionId);
    if ($parts && is_array($parts) && count($parts) == 4) {
        $userSession = $parts[0];
        $sessionStartTime = $parts[1];
        $pageViews = $parts[2];
        $searches = $parts[3];
        $newSessionId = createNewSession($userSession, $sessionStartTime, $pageViews, $searches);
    }
}
// New session value has the same format as the previous session
if (isset($newSessionId) && preg_match($COOKIE_REGEX, $newSessionId)) {
    $newSessionIsValid = true;
}

if ($newSessionIsValid) {
    setcookie($SLI_COOKIE_1, $newSessionId, $COOKIE_EXPIRES_30_MIN, "/", $SLI_DOMAIN); // 30 min
    setcookie($SLI_COOKIE_2, $newSessionId, $COOKIE_EXPIRES_SESSION, "/", $SLI_DOMAIN); // session cookie
    setcookie($SLI_COOKIE_4, $sessionStartTime, $COOKIE_EXPIRES_6_MONTHS, "/", $SLI_DOMAIN); // 6 months
    setcookie($SLI_COOKIE_BEACON, $userSession, $COOKIE_EXPIRES_2_YEARS, "/", $SLI_DOMAIN); // 2 years
}
print("<pre>");
print($recommendationJson);
print("</pre>");

//
/**
 * Create a new session from the alphanumeric part of the session
 *
 * @param $userSession
 * @param $sessionStartTime
 * @param $pageViews
 * @param $searches
 * @return string
 */
function createNewSession($userSession, $sessionStartTime, $pageViews, $searches)
{
    return "$userSession.$sessionStartTime.$pageViews.$searches";
}

/**
 * Simplified version of fletcher32 since does not have to work with long strings
 *
 * @param $str
 * @return string
 */
function fletcher32($str)
{
    $sum1 = 0xffff;
    $sum2 = 0xffff;
    for ($i = 0; $i < strlen($str); $i++) {
        $sum2 += $sum1 += ord(substr($str, $i, 1));
    }
    $sum1 = ($sum1 & 0xffff) + ($sum1 >> 16);
    $sum2 = ($sum2 & 0xffff) + ($sum2 >> 16);
    return '_' . abs($sum2 << 16 | $sum1);
}

/**
 * Use curl to request the recommendations server side.
 *
 * @param $url
 * @return mixed
 */
function getRecommendations($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}

?>