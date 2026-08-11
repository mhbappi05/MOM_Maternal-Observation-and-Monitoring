document.querySelector(".chatbot-toggle").addEventListener("click", () => {
    document.querySelector(".chatbox").style.display = "block";
});

document.querySelector(".close-chat").addEventListener("click", () => {
    document.querySelector(".chatbox").style.display = "none";
});

document.getElementById("send-btn").addEventListener("click", sendMessage);
document.getElementById("chat-input").addEventListener("keypress", function (e) {
    if (e.key === "Enter") sendMessage();
});

function sendMessage() {
    const inputField = document.getElementById("chat-input");
    const message = inputField.value.trim();
    if (!message) return;

    addMessage(message, "user-message");
    inputField.value = "";

    // Show loading message
    const loadingId = addMessage("Typing...", "bot-message");

    // Call PHP backend
    fetch("chat.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ message })
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById(loadingId).remove();
        const botReply = data.choices?.[0]?.message?.content || "Sorry, I couldn't get a response.";
        addMessage(botReply, "bot-message");
    })
    .catch(err => {
        document.getElementById(loadingId).remove();
        addMessage("Error connecting to server.", "bot-message");
    });
}

function addMessage(text, className) {
    const chatboxBody = document.getElementById("chatbox-body");
    const messageDiv = document.createElement("div");
    messageDiv.classList.add(className);
    messageDiv.textContent = text;
    chatboxBody.appendChild(messageDiv);
    chatboxBody.scrollTop = chatboxBody.scrollHeight;
    return messageDiv.id = "msg-" + Date.now(), messageDiv.id;
}

