// Open Messenger
document.getElementById("openMessenger").addEventListener("click", function() {
    document.getElementById("messengerContainer").style.display = "block";
    document.getElementById("doctorList").style.display = "block";
    document.getElementById("chatUI").style.display = "none";
});

// Handle Doctor Selection
document.querySelectorAll(".doctor-item").forEach(item => {
    item.addEventListener("click", function() {
        const doctorId = this.getAttribute("data-doctor-id");
        selectDoctor(doctorId);
    });
});

function selectDoctor(doctorId) {
    // Get the selected doctor button by id
    const doctorElem = document.querySelector(`.doctor-item[data-doctor-id="${doctorId}"]`);
    const doctorName = doctorElem ? doctorElem.getAttribute('data-doctor-name') || doctorElem.innerText.trim() : 'Doctor';

    // Update the header text to the selected doctor's name
    document.getElementById('messengerHeader').textContent = doctorName;

    // Hide doctor list and show chat UI
    document.getElementById('doctorList').style.display = 'none';
    document.getElementById('chatUI').style.display = 'block';

    // Load messages and start polling
    loadMessages(doctorId);
    startPolling(doctorId);
}


// Function to load messages (AJAX call to get chat history)
function loadMessages(doctorId) {
    // Clear previous messages
    document.getElementById("messengerBody").innerHTML = '';

    // Make an AJAX call to fetch chat history
    fetch('get_messages.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ doctor_id: doctorId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // If we have messages, append them to the messenger body
            document.getElementById("messengerBody").innerHTML = data.messages;
            document.getElementById("messengerBody").scrollTop = document.getElementById("messengerBody").scrollHeight; // Scroll to the bottom
        } else {
            document.getElementById("messengerBody").innerHTML = '<p>Error loading messages.</p>';
        }
    })
    .catch(error => {
        console.error("Error loading messages:", error);
    });
}

// Send message (this can be further enhanced with AJAX to send and receive messages)
document.getElementById("sendMessageBtn").addEventListener("click", function() {
    const message = document.getElementById("messageInput").value;
    const doctorId = document.querySelector(".doctor-item").getAttribute("data-doctor-id");
    
    if (message) {
        sendMessageToDoctor(doctorId, message);
        document.getElementById("messengerBody").innerHTML += "<div>You: " + message + "</div>";
        document.getElementById("messageInput").value = '';  // Clear the input field
    }
});

// Function to send message to doctor
function sendMessageToDoctor(doctorId, message) {
    fetch('send_message.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
            doctor_id: doctorId,
            message: message
        })
    })
    .then(response => response.json())
    .then(data => {
        console.log(data); // Check the response from PHP
        if (data.status === 'success') {
            document.getElementById("messengerBody").innerHTML += "<div>You: " + message + "</div>";
        } else {
            alert('Failed to send message');
        }
    })
    .catch(error => {
        console.error("Error sending message:", error);
    });
}

// Periodic polling for new messages
let messagePollingInterval;

function startPolling(doctorId) {
    // Poll for new messages every 5 seconds
    messagePollingInterval = setInterval(() => {
        loadMessages(doctorId);  // Fetch the latest messages
    }, 3000); // Fetch messages every 5 seconds
}

// Stop polling when the messenger is closed
document.getElementById('closeMessenger').addEventListener('click', function() {
    // Hide the entire messenger container
    document.getElementById('messengerContainer').style.display = 'none';

    // Stop polling for new messages
    if (messagePollingInterval) {
        clearInterval(messagePollingInterval);
    }
});


document.addEventListener('DOMContentLoaded', function () {
    const doctorList = document.getElementById('doctorList');
    const chatUI = document.getElementById('chatUI');
    const backToDoctors = document.getElementById('backToDoctors');

    // Handle doctor selection buttons
    document.querySelectorAll('.doctor-item').forEach(button => {
        button.addEventListener('click', function () {
            doctorList.style.display = 'none';
            chatUI.style.display = 'block';
            backToDoctors.style.display = 'inline-block';
        });
    });

    // Back button click
    backToDoctors.addEventListener('click', function () {
        chatUI.style.display = 'none';
        doctorList.style.display = 'block';
        backToDoctors.style.display = 'none';
    });
});


backToDoctors.addEventListener('click', function () {
    // Reset header text
    document.getElementById('messengerHeader').textContent = 'Consult with the Doctor';

    // Show doctor list and hide chat UI
    chatUI.style.display = 'none';
    doctorList.style.display = 'block';
    backToDoctors.style.display = 'none';

    // Stop polling if active
    if (messagePollingInterval) {
        clearInterval(messagePollingInterval);
    }
});
