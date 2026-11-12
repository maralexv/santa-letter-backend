<?php
// Get parameter form the URL
$q = test_input($_REQUEST["q"]);

if ($q !== "") {

    $dbhost = "localhost";
    $dbuser = "alex";
    $dbpass = "easter2000";
    $db = "notes";

    // Create connection to MariaDB server
    $conn = new mysqli($dbhost, $dbuser, $dbpass, $db);

    // Check connection
    if($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    // echo "Connected successfully";

    $currentyear = date("Y");

    $sql = "SELECT NoteID, NoteText, Time 
            FROM notes WHERE User_id='$q' AND YEAR(Time)=$currentyear
            ORDER BY NoteID
            DESC;";
    $result = $conn->query($sql);

    // loop through the notes array of the user 
    if ($result->num_rows > 0) {
        $notes_array = array();

        while ($row = $result->fetch_assoc()) {
            // Build array fo the notes
            array_push($notes_array, $row);
        }
    }

    // Encode notes array into json object and return
    echo json_encode($notes_array);

    $result->free_result();
    // Close database connection
    $conn->close();
}

function test_input ($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
?>