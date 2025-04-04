from langchain_community.llms import LlamaCpp
import time
import os
import psutil
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

# Lấy đường dẫn model từ biến môi trường
model_path = os.getenv("MODEL_PATH", "models/Llama-3.2-1B-Instruct-Q5_K_M.gguf")

# Kiểm tra model tồn tại
if not os.path.exists(model_path):
    print(f"Model không tồn tại: {model_path}")
    exit(1)

print(f"Sử dụng model: {model_path}")
print("Thông tin GPU: NVIDIA GeForce GTX 1650 (4GB VRAM)")
print(f"CPU Cores: {psutil.cpu_count(logical=False)} (Physical), {psutil.cpu_count()} (Logical)")
print(f"RAM: {round(psutil.virtual_memory().total / (1024.0 ** 3), 2)} GB")
print("Đang tải mô hình LlamaCpp...")

# Tạo đối tượng LlamaCpp với cấu hình phù hợp
start_time = time.time()

llm = LlamaCpp(
    model_path=model_path,
    temperature=0.7,
    max_tokens=500,
    n_ctx=2048,
    n_gpu_layers=-1,      # Sử dụng tất cả layers trên GPU (-1 = tất cả)
    n_batch=512,          # Giảm batch size để tránh lỗi hết bộ nhớ
    f16_kv=True,          # Sử dụng half precision cho key/values
    verbose=True,         # Bật verbose mode để xem log chi tiết
    n_threads=4,          # Giảm số thread CPU vì sẽ dùng GPU nhiều hơn
    use_mlock=True,
    repeat_penalty=1.1,   # Giảm lặp lại
    top_k=40,             # Thêm top_k
    top_p=0.9,            # Thêm top_p
    seed=42               # Cố định seed để kết quả nhất quán
)

load_time = time.time() - start_time
print(f"Đã tải mô hình trong {load_time:.2f} giây")

# Chuẩn bị prompt theo định dạng Llama 3
prompt = """<|begin_of_text|><|system|>
Bạn là một trợ lý AI thông minh và hữu ích. Bạn có nhiệm vụ trả lời các câu hỏi về kinh tế và tài chính.
Hãy trả lời câu hỏi sau bằng tiếng Việt một cách rõ ràng, có cấu trúc và chính xác.
</|system|>

<|user|>
Kinh tế học là gì và nó nghiên cứu về vấn đề gì?
</|user|>

<|assistant|>"""

# Sinh văn bản
print(f"\nPrompt: {prompt}")
print("Đang sinh văn bản...")

start_time = time.time()
output = llm(prompt)
generate_time = time.time() - start_time

print(f"\nKết quả ({generate_time:.2f} giây):")
print(output)

# Test thêm câu hỏi phức tạp hơn
prompt2 = """<|begin_of_text|><|system|>
Bạn là một trợ lý AI thông minh và hữu ích. Bạn có nhiệm vụ trả lời các câu hỏi về kinh tế và tài chính.
Hãy trả lời câu hỏi sau bằng tiếng Việt một cách rõ ràng, có cấu trúc và chính xác.
</|system|>

<|user|>
Giải thích về lạm phát, nguyên nhân và tác động của nó đến nền kinh tế một quốc gia.
</|user|>

<|assistant|>"""

print(f"\nPrompt: {prompt2}")
print("Đang sinh văn bản...")

start_time = time.time()
output = llm(prompt2)
generate_time = time.time() - start_time

print(f"\nKết quả ({generate_time:.2f} giây):")
print(output) 