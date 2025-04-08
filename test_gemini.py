#!/usr/bin/env python3
import os
import google.generativeai as genai
from dotenv import load_dotenv
import time

# Load biến môi trường từ file .env
load_dotenv()

# Lấy API key từ biến môi trường
GOOGLE_API_KEY = os.getenv("GOOGLE_API_KEY")
if not GOOGLE_API_KEY:
    print("Lỗi: Không tìm thấy GOOGLE_API_KEY trong file .env")
    exit(1)

# Cấu hình Gemini
genai.configure(api_key=GOOGLE_API_KEY)
model = genai.GenerativeModel('gemini-2.0-flash')

def chat_with_gemini():
    print("\n===== CHAT VỚI GEMINI API =====")
    print("Nhập 'exit' hoặc 'quit' để thoát")
    print("==============================\n")
    
    # Khởi tạo chat
    chat = model.start_chat(history=[])
    
    while True:
        # Nhập tin nhắn từ người dùng
        user_input = input("\nBạn: ")
        
        # Kiểm tra điều kiện thoát
        if user_input.lower() in ['exit', 'quit']:
            print("\nKết thúc chat!")
            break
        
        try:
            # Đo thời gian xử lý
            start_time = time.time()
            
            # Gửi tin nhắn và nhận phản hồi
            response = chat.send_message(user_input)
            
            # Tính thời gian xử lý
            elapsed_time = time.time() - start_time
            
            # Hiển thị phản hồi
            print(f"\nGemini: {response.text}")
            print(f"\nThời gian xử lý: {elapsed_time:.2f} giây")
            
        except Exception as e:
            print(f"\nLỗi: {str(e)}")

if __name__ == "__main__":
    chat_with_gemini() 