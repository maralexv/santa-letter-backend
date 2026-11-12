
    <style>
        html {
            margin: 3rem;
            font-family: Arial, Helvetica, sans-serif;
            color: whitesmoke;
            background-color: #006400;
        }

        div {
            overflow: auto;
            padding: 1em;
            margin:  5px;
            display:  block;
            width:  100%;
        }

        p {
            text-align: justify;
            width: 90%;
        }
        input {
            margin-right: 8px;
        }

        textarea {
            width: 95%;
            height: auto;
            margin-right: 8px;
        }

        table, th, td {
            margin-left: auto;
            margin-right: auto;
            border: 1px solid grey;
            border-collapse: collapse;
	    padding: 2px 1em 2px 1em;
        }

        table {
            width: 87%;
        }

        footer {
            position: fixed;
            left: 0;
            bottom: 0px;
            width: 100%;
            text-align: center;
            background-color: #006400;
            z-index: 1;
        }

        .del {
            float: right;
            margin-right: 15px;
            margin-left: 10px;
        }

        .note {
            margin-top: 5px;
            margin-bottom: 5px;
            padding: 1rem;
            border-bottom: 1px lightgrey solid;
        }
    </style>

    <script>

        // Function to validate email input
        function validateEmail(email) {
            const re = /\S+@\S+\.\S+/;
            return re.test(String(email).toLowerCase());
        };

        // Function to identify returning user or to register new user
        function getUser() {
            if (user.length == 0) {
                document.getElementById("useremail").innerHTML = "";
            } else {
                const user = document.getElementById("user").value;
                console.log(user);

                //Validate email input 
                let checkEmail = validateEmail(user);
                console.log(checkEmail);
                if (checkEmail == false) {
                    document.getElementById("useremail").innerHTML = "";
                    alert("Email format is invalid. Please enter valid email.");
                    return false;
                };

                let xmlhttp = new XMLHttpRequest();
                xmlhttp.onreadystatechange = function() {
                    if (this.readyState == 4 && this.status == 200) {
                        let result = JSON.parse(this.responseText);
                        document.getElementById("welcome").innerHTML = result[0] + " ";
                        document.getElementById("useremail").innerHTML = result[1];
                        document.getElementById("userout").style.display = "block";
                        document.getElementById("newnoteform").style.display = "block";
                        document.getElementById("login").style.display = "none";

                        // Set the user ID in local storage
                        localStorage.setItem('uid', result[2]);

                        fetchNotes(result[2]);
                    }
                };
                xmlhttp.open("GET", "getuser.php?q=" + user, true);
                xmlhttp.send();
            }
         };

        // Function to fetch old notes of the user (only current year) from the db
        function fetchNotes(data) {

            if (data.length == 0) {
                document.getElementById("yn").innerHTML = "";
            } else {
                document.getElementById("yn").firstChild.innerHTML = "Your Christmas wish list for this year:";
                let xmlhttp = new XMLHttpRequest();
                xmlhttp.onreadystatechange = function() {
                    if (this.readyState == 4 && this.status == 200) {
                        let result = JSON.parse(this.responseText);

                        if (result !== null) {
                        result.forEach(displayNote);
                        document.getElementById("yn").style.display = "block";
                        document.getElementById("notes").style.display = "block";
                        }
                    }
                };
                xmlhttp.open("GET", "fetchnotes.php?q=" + data, true);
                xmlhttp.send();
            }
        };

        // Function to add notes, retrived from the db to the 'notes' div
        function displayNote(item) {
            // Creat del button
            let delb = document.createElement('button');
                delb.innerHTML = "del";
                // Apply css style to the del button
                delb.classList.add('del');
                delb.onclick = function(e) {var target = e.target; target.parentNode.parentNode.removeChild(target.parentNode); removeNote(item.NoteID)};

            // Create new paragraph element for the note
            let p = document.createElement('p');
                // Add note text to just created paragraph^^
                p.innerHTML = item.NoteText;
                // Apply css style to the note
                p.classList.add('note');
                // Add del buton to the paragraph
                p.append(delb);

            // Append 'notes' div with newly created note
            document.getElementById("notes").append(p);
        };

        // Function to send note id to server to remove note from db
        function removeNote(id) {

            let xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    let result = this.responseText;
                    console.log(result);
                };
            };
            xmlhttp.open("GET", "removenote.php?q=" + id, true);
            xmlhttp.send();
            
        };

        // Fucntion to record new note in db
        function recordNewNote(note, uid) {
            
            const data = [note, uid];
            // console.log(data);
            let jdata = JSON.stringify(data);
            // console.log(jdata);

            let xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    let result = this.responseText;
                    console.log(result);
                }
            };
            xmlhttp.open("GET", "recordnewnote.php?q=" + jdata, true);
            xmlhttp.send();
        };

        // Wait for page to load
        document.addEventListener('DOMContentLoaded', function() {
            // Add new notes
            // Select the submit button for new notes and input to be used later
            const submit = document.querySelector('#submit');
            const newNote = document.querySelector('#newnotetxt');

            // Disable submit button by default:
            submit.disabled = true;

            // Listen for input to be typed into the input field
            newNote.onkeyup = () => {
                if (newNote.value.length > 0) {
                    submit.disabled = false;
                }
                else {
                    submit.disabled = true;
                }
            }

            // Listen for submission of form newnote
            document.querySelector('form').onsubmit = () => {

                // Find the note text the user just submitted
                let note = newNote.value;
                let uid = localStorage.getItem('uid');
                console.log(uid);

                // Create new html node for the new note and add the note text to it
                let p = document.createElement('p');
                p.innerHTML = note;
                p.classList.add('note');

                // Insert new element to the top of notes list:
                // Get the parent element
                let parent = document.querySelector('#notes');
                // Get the parent's first child
                let topChild = parent.firstChild;
                // Insert the new element before the first child
                parent.insertBefore(p, topChild);
                document.getElementById("yn").style.display = "block";
                document.getElementById("notes").style.display = "block";

                recordNewNote(note, uid);

                // Clear out input field:
                newNote.value = '';

                // Disable the submit button again:
                submit.disabled = true;

                // Stop form from submitting
                return false;
            }

        });
    </script>

