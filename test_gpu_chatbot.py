import asyncio
import os
import torch
import time
from dotenv import load_dotenv
from app.services.chatbot import ChatbotService

async def main():
    # Hiển thị thông tin GPU
    print("CUDA available:", torch.cuda.is_available())
    if torch.cuda.is_available():
        print("CUDA device count:", torch.cuda.device_count())
        print("CUDA device name:", torch.cuda.get_device_name(0))
        print("CUDA memory allocated:", torch.cuda.memory_allocated() / (1024**2), "MB")
        print("CUDA memory reserved:", torch.cuda.memory_reserved() / (1024**2), "MB")
    
    # Khởi tạo chatbot service
    print("\nKhởi tạo ChatbotService...")
    chatbot = ChatbotService()
    
    # Test với câu hỏi về kinh tế
    test_questions = [
        "Kinh tế học là gì?",
        "Giải thích về lạm phát và ảnh hưởng của nó?",
        "Thế nào là tăng trưởng kinh tế bền vững?"
    ]
    
    # Test các câu hỏi
    for question in test_questions:
        print(f"\n----- Test câu hỏi: '{question}' -----")
        
        # Đo thời gian xử lý
        start_time = time.time()
        
        # Lấy câu trả lời
        response = await chatbot.get_answer(question)
        
        # Tính thời gian hoàn thành
        end_time = time.time()
        process_time = end_time - start_time
        
        # Hiển thị kết quả
        print(f"Câu hỏi: {question}")
        print(f"Câu trả lời: {response['answer']}")
        print(f"Thời gian xử lý: {process_time:.2f} giây")
        
        # Hiển thị thông tin GPU sau mỗi lần xử lý
        if torch.cuda.is_available():
            print(f"CUDA memory allocated: {torch.cuda.memory_allocated() / (1024**2):.2f} MB")
            print(f"CUDA memory reserved: {torch.cuda.memory_reserved() / (1024**2):.2f} MB")
            
        # Chờ 2 giây trước khi xử lý câu hỏi tiếp theo
        await asyncio.sleep(2)
    
    print("\nKết thúc test!")

if __name__ == "__main__":
    # Load environment variables
    load_dotenv()
    
    # Chạy test bất đồng bộ
    asyncio.run(main()) 