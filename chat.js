document.getElementById('chat-form').addEventListener('submit', function (e) {
    e.preventDefault();
    let input = document.getElementById('chat-input');
    let message = input.value.trim();
    if (message === '') return;

    addMessage('user', message);
    input.value = '';

    // Show loading animation once
    if (!document.querySelector('.loading-animation')) {
        showLoadingAnimation();
    }

    fetch('api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ message: message })
    })
    .then(response => response.json())
    .then(data => {
        removeLoadingAnimation();
        addMessage('bot', data.response);
    })
    .catch(error => {
        removeLoadingAnimation();
        console.error('Error:', error);
        addMessage('bot', 'Error processing your request.');
    });
});

// Add message
function addMessage(sender, text) {
    let chatBox = document.getElementById('chat-box');
    let messageDiv = document.createElement('div');
    messageDiv.classList.add('message', sender);

    // Regex for YouTube links
    const youtubeRegex = /(https?:\/\/)?(www\.)?(youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/g;

    // Replace YouTube links with embed
    text = text.replace(youtubeRegex, (match, p1, p2, p3, videoId) => `
        <div class="youtube-container">
            <iframe class="youtube-video"
                    src="https://www.youtube.com/embed/${videoId}"
                    allowfullscreen>
            </iframe>
        </div>
    `);

    // Add message to chat
    let textDiv = document.createElement('div');
    textDiv.classList.add('text');
    textDiv.innerHTML = text;
    messageDiv.appendChild(textDiv);

    chatBox.appendChild(messageDiv);
    chatBox.scrollTop = chatBox.scrollHeight;
}

// Show loading animation (only once)
function showLoadingAnimation() {
    let chatBox = document.getElementById('chat-box');
    let loadingDiv = document.createElement('div');
    loadingDiv.classList.add('message', 'bot', 'loading-animation');
    loadingDiv.innerHTML = '<span class="dot"></span><span class="dot"></span><span class="dot"></span>';
    chatBox.appendChild(loadingDiv);
    chatBox.scrollTop = chatBox.scrollHeight;
}

// Remove loading animation
function removeLoadingAnimation() {
    const loading = document.querySelector('.loading-animation');
    if (loading) loading.remove();
}
