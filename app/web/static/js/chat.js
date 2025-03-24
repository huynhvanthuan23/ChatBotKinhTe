document.addEventListener("DOMContentLoaded", function() {
    const chatForm = document.getElementById("chat-form");
    const userInput = document.getElementById("user-input");
    const chatMessages = document.getElementById("chat-messages");

    chatForm.addEventListener("submit", async function(e) {
        e.preventDefault();
        
        const message = userInput.value.trim();
        if (!message) return;
        
        // Thêm tin nhắn người dùng vào khung chat
        addMessage(message, "user");
        userInput.value = "";
        
        // Hiển thị typing indicator
        const typingIndicator = document.createElement("div");
        typingIndicator.className = "typing-indicator";
        typingIndicator.innerHTML = '<span></span><span></span><span></span>';
        chatMessages.appendChild(typingIndicator);
        chatMessages.scrollTop = chatMessages.scrollHeight;
        
        try {
            // Gọi API
            const response = await fetch("/api/v1/chat/chat", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({ message })
            });
            
            if (!response.ok) {
                throw new Error(`Lỗi HTTP: ${response.status}`);
            }
            
            const data = await response.json();
            
            // Xóa typing indicator
            chatMessages.removeChild(typingIndicator);
            
            // Thêm phản hồi từ bot
            addMessage(data.answer, "bot");
        } catch (error) {
            // Xóa typing indicator
            if (typingIndicator.parentNode === chatMessages) {
                chatMessages.removeChild(typingIndicator);
            }
            
            // Hiển thị lỗi
            addMessage("Đã xảy ra lỗi khi kết nối với chatbot. Vui lòng thử lại sau.", "bot");
            console.error("Error:", error);
        }
    });
    
    function addMessage(text, sender) {
        const messageDiv = document.createElement("div");
        messageDiv.className = `message ${sender}`;
        
        const messageContent = document.createElement("div");
        messageContent.className = "message-content";
        
        // Xử lý xuống dòng
        const paragraphs = text.split('\n').filter(para => para.trim() !== '');
        
        if (paragraphs.length > 1) {
            paragraphs.forEach(paragraph => {
                const p = document.createElement("p");
                p.textContent = paragraph;
                messageContent.appendChild(p);
            });
        } else {
            messageContent.textContent = text;
        }
        
        messageDiv.appendChild(messageContent);
        chatMessages.appendChild(messageDiv);
        
        // Cuộn xuống tin nhắn mới nhất
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
}); 