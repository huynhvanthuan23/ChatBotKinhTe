import ctypes
import os
from typing import List
import time

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

# Test với llama-cpp
def test_llama_cpp():
    from langchain_community.llms import LlamaCpp
    
    # Đường dẫn model
    model_path = "models/mistral-7b-instruct-v0.1.Q2_K.gguf"
    
    print("Testing with different GPU layer configurations:")
    
    for n_gpu_layers in [1, 8, 16, 32, -1]:
        print(f"\nTesting with n_gpu_layers={n_gpu_layers}")
        
        try:
            # Khởi tạo model
            llm = LlamaCpp(
                model_path=model_path,
                temperature=0.0,
                n_gpu_layers=n_gpu_layers,
                n_batch=512,
                n_ctx=2048,
                f16_kv=True,
                verbose=True
            )
            
            # Test prompt đơn giản
            prompt = "Cho tôi biết 2+2 bằng bao nhiêu?"
            
            # Đo thời gian
            start_time = time.time()
            output = llm(prompt)
            end_time = time.time()
            
            print(f"Output: {output[:50]}...")
            print(f"Inference time: {end_time - start_time:.4f} seconds")
            
            # Nếu dưới 1 giây, có thể đang sử dụng GPU
            if end_time - start_time < 1.0:
                print("GPU acceleration appears to be working!")
            else:
                print("Seems to be running on CPU (slow inference)")
                
        except Exception as e:
            print(f"Error testing with n_gpu_layers={n_gpu_layers}: {e}")

if __name__ == "__main__":
    has_cuda = check_cuda_devices()
    if has_cuda:
        test_llama_cpp()
    else:
        print("No CUDA devices detected. Cannot proceed with GPU test.") 