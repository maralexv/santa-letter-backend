<?php
// Initiate the session
session_start();

// Set cookie if not set yet
$cookiename = "usr";
$cookievalue;
if(!isset($_COOKIE[$cookiename])) {
    setcookie($cookiename, $cookievalue,  time()+(60*60*24*30), "/var/www/html"); // 60*60*24*30 = 30 days
    // echo "(number of cookies: " . count($_COOKIE) . ")";
}
?>

<!DOCTYPE html>
<html lang="en">
<head> 
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Santa Letter</title>

    <?php require 'header.php';?>

</head>

<body>

<div id="login">
    <!-- <form id="loginform" action=""> -->
        <label for="user">Type in your email please:</label>
        <input type="email" id="user" name="user" placeholder="name@email.com">
        <button onclick= "getUser()">submit</button>
    <!-- </form> -->
</div>

<div id="userout" style="display: none;">
    <p><span id="welcome"></span><strong><span id="useremail"></span></strong></p>
</div>

<div id="newnoteform" style="display: none;">
    <form id="newnote" action="">
        <!-- <label for="newnotetxt">add your new note:</label> -->
        <textarea id="newnotetxt" type="text" name="newnote" rows="2" cols="64">
Type here and add to your Christmas wish list...
        </textarea>
        <input id="submit" type="submit" value="add">
    </form>
</div>

<div id="yn" style="display: none;"><span></span></div>
<div id="notes" style="display: none;"></div>

<footer>
    <a href="results.php"><button>click here to see all whishes</button></a><br><br>
    Copyright scarvesfamily&copy; <?php echo date("Y");?>
</footer><br>

</body>
</html>
