import ctypes
import os
from typing import List
import time
import torch
from langchain_community.llms import LlamaCpp
from dotenv import load_dotenv

load_dotenv()

# Tải thư viện CUDA Runtime
def load_cuda_runtime():
    try:
        if os.name == 'nt':  # Windows
            libcudart = ctypes.windll.LoadLibrary('cudart64_11.dll')
        else:  # Linux/Mac
            libcudart = ctypes.cdll.LoadLibrary('libcudart.so')
        return libcudart
    except Exception as e:
        print(f"Failed to load CUDA Runtime: {e}")
        return None

# Kiểm tra GPU
def check_cuda_devices():
    libcudart = load_cuda_runtime()
    if not libcudart:
        return False
    
    try:
        count = ctypes.c_int()
        result = libcudart.cudaGetDeviceCount(ctypes.byref(count))
        if result == 0:  # cudaSuccess
            print(f"Found {count.value} CUDA device(s)")
            for i in range(count.value):
                props = ctypes.c_void_p()
                libcudart.cudaGetDeviceProperties(ctypes.byref(props), i)
                # Thông tin chi tiết về device có thể truy xuất từ props
                print(f"  Device {i}: Available")
            return count.value > 0
        else:
            print(f"cudaGetDeviceCount failed with error code {result}")
            return False
    except Exception as e:
        print(f"Error checking CUDA devices: {e}")
        return False

# Lấy các tham số từ .env
MODEL_PATH = os.getenv("MODEL_PATH", "models/mistral-7b-instruct-v0.1.Q2_K.gguf")
N_GPU_LAYERS = int(os.getenv("N_GPU_LAYERS", "32"))
N_BATCH = int(os.getenv("N_BATCH", "512"))
F16_KV = os.getenv("F16_KV", "true").lower() == "true"
USE_MMAP = os.getenv("USE_MMAP", "false").lower() == "true"
USE_MLOCK = os.getenv("USE_MLOCK", "false").lower() == "true"

def check_cuda():
    """Kiểm tra CUDA khả dụng"""
    if torch.cuda.is_available():
        print(f"CUDA khả dụng: {torch.cuda.get_device_name(0)}")
        print(f"CUDA version: {torch.version.cuda}")
        print(f"GPU memory: {torch.cuda.get_device_properties(0).total_memory / 1024**2:.2f} MB")
        return True
    else:
        print("CUDA không khả dụng")
        return False

def test_model():
    """Kiểm tra model có sử dụng GPU không"""
    print(f"Tải model từ {MODEL_PATH}")
    print(f"Cấu hình: n_gpu_layers={N_GPU_LAYERS}, n_batch={N_BATCH}")
    print(f"f16_kv={F16_KV}, use_mmap={USE_MMAP}, use_mlock={USE_MLOCK}")
    
    try:
        model = LlamaCpp(
            model_path=MODEL_PATH,
            temperature=0.1,
            max_tokens=10,
            n_ctx=2048,
            n_gpu_layers=N_GPU_LAYERS,
            n_batch=N_BATCH,
            f16_kv=F16_KV,
            use_mlock=USE_MLOCK,
            use_mmap=USE_MMAP,
            verbose=True
        )
        
        # Chạy một test đơn giản
        prompt = "Tính 2+2 bằng bao nhiêu?"
        
        # Kiểm tra thời gian thực thi
        start_time = time.time()
        result = model(prompt)
        end_time = time.time()
        
        print(f"Kết quả: {result}")
        
        # Phân tích thời gian
        inference_time = end_time - start_time
        print(f"Thời gian thực thi: {inference_time:.4f} giây")
        
        if inference_time < 1.0:
            print("GPU hoạt động tốt! (thời gian thực thi nhanh)")
        else:
            print(f"GPU có thể không hoạt động (thời gian thực thi chậm: {inference_time:.4f}s)")
            
        # Kiểm tra thêm
        if torch.cuda.is_available():
            mem_allocated = torch.cuda.memory_allocated() / 1024**2
            mem_reserved = torch.cuda.memory_reserved() / 1024**2
            print(f"GPU memory allocated: {mem_allocated:.2f} MB")
            print(f"GPU memory reserved: {mem_reserved:.2f} MB")
            
            if mem_allocated > 100:  # Nếu sử dụng > 100MB
                print("GPU đang được sử dụng tích cực!")
            else:
                print("GPU không được sử dụng nhiều cho mô hình.")
        
    except Exception as e:
        print(f"Lỗi khi tải model: {str(e)}")

if __name__ == "__main__":
    print("===== Kiểm tra GPU =====")
    cuda_available = check_cuda()
    
    if cuda_available:
        print("\n===== Kiểm tra model =====")
        test_model()
    else:
        print("Không thể kiểm tra model vì CUDA không khả dụng") 