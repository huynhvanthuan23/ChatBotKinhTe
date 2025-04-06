import os
import time
from langchain_community.llms import LlamaCpp
import torch
import gc

# Hàm kiểm tra GPU
def check_gpu():
    if torch.cuda.is_available():
        print(f"CUDA available: Yes")
        print(f"CUDA device count: {torch.cuda.device_count()}")
        print(f"CUDA device name: {torch.cuda.get_device_name(0)}")
        print(f"CUDA memory allocated: {torch.cuda.memory_allocated(0) / 1024**2:.2f} MB")
        print(f"CUDA memory reserved: {torch.cuda.memory_reserved(0) / 1024**2:.2f} MB")
        return True
    else:
        print("CUDA is not available")
        return False

# Hàm đo thời gian xử lý
def test_inference(llm, prompt):
    start_time = time.time()
    result = llm(prompt)
    end_time = time.time()
    print(f"Inference time: {end_time - start_time:.4f} seconds")
    print(f"First few words of result: {result[:50]}...")
    return end_time - start_time

# Hàm khởi tạo model với số lượng layer GPU khác nhau
def init_model(model_path, n_gpu_layers, n_batch=512, n_ctx=2048):
    print(f"\nTesting with n_gpu_layers={n_gpu_layers}, n_batch={n_batch}, n_ctx={n_ctx}")
    
    # Dọn dẹp bộ nhớ GPU trước khi khởi tạo
    torch.cuda.empty_cache()
    gc.collect()
    
    # Khởi tạo model
    llm = LlamaCpp(
        model_path=model_path,
        temperature=0.0,  # Đặt 0 để tăng tốc
        max_tokens=128,   # Giảm xuống để tiết kiệm bộ nhớ
        n_ctx=n_ctx,
        n_gpu_layers=n_gpu_layers,
        n_batch=n_batch,
        f16_kv=True,      # Tiết kiệm bộ nhớ GPU
        verbose=False
    )
    
    # Kiểm tra bộ nhớ GPU sau khi khởi tạo
    print(f"GPU memory after initialization:")
    check_gpu()
    
    return llm

# Chạy test
def run_tests(model_path):
    # Đảm bảo GPU trống trước khi bắt đầu
    torch.cuda.empty_cache()
    gc.collect()
    
    # Kiểm tra GPU ban đầu
    print("Initial GPU state:")
    has_gpu = check_gpu()
    if not has_gpu:
        print("No GPU detected. Exiting.")
        return
    
    # Prompt đơn giản
    test_prompt = "Cho tôi biết 2+2 bằng bao nhiêu?"
    
    # Thử nghiệm với các cấu hình khác nhau
    configs = [
        # n_gpu_layers, n_batch, n_ctx
        (1, 256, 1024),    # Ít VRAM nhất 
        (4, 256, 1024),    # Cấu hình tiết kiệm
        (8, 512, 2048),    # Cấu hình trung bình
        (16, 512, 2048),   # Cấu hình cao hơn
        (24, 512, 2048),   # Cấu hình khá cao
        (32, 512, 2048),   # Cấu hình rất cao
    ]
    
    results = []
    
    for n_gpu_layers, n_batch, n_ctx in configs:
        try:
            # Khởi tạo model
            llm = init_model(model_path, n_gpu_layers, n_batch, n_ctx)
            
            # Thử nghiệm
            inference_time = test_inference(llm, test_prompt)
            
            # Thu thập kết quả
            gpu_memory = torch.cuda.memory_allocated(0) / 1024**2
            results.append({
                "n_gpu_layers": n_gpu_layers,
                "n_batch": n_batch,
                "n_ctx": n_ctx,
                "inference_time": inference_time,
                "gpu_memory_mb": gpu_memory
            })
            
            # Dọn dẹp
            del llm
            torch.cuda.empty_cache()
            gc.collect()
            
        except Exception as e:
            print(f"Error with config (n_gpu_layers={n_gpu_layers}, n_batch={n_batch}, n_ctx={n_ctx}): {str(e)}")
    
    # Hiển thị kết quả
    print("\n========= TEST RESULTS =========")
    for result in results:
        print(f"n_gpu_layers={result['n_gpu_layers']}, n_batch={result['n_batch']}, n_ctx={result['n_ctx']}")
        print(f"    Inference time: {result['inference_time']:.4f} seconds")
        print(f"    GPU memory: {result['gpu_memory_mb']:.2f} MB")

if __name__ == "__main__":
    # Đọc đường dẫn model từ biến môi trường hoặc sử dụng mặc định
    model_path = os.getenv("MODEL_PATH", "models/mistral-7b-instruct-v0.1.Q2_K.gguf")
    
    print(f"Testing model: {model_path}")
    run_tests(model_path) 