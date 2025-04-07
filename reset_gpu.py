import torch
import gc
import time
import os

def reset_gpu():
    print("===== RESET GPU MEMORY =====")
    # Giải phóng bộ nhớ CUDA
    if torch.cuda.is_available():
        print(f"CUDA available: {torch.cuda.is_available()}")
        print(f"CUDA device: {torch.cuda.get_device_name(0)}")
        
        # Thông tin trước khi giải phóng
        allocated_before = torch.cuda.memory_allocated() / 1024**2
        reserved_before = torch.cuda.memory_reserved() / 1024**2
        print(f"Memory before cleanup: allocated={allocated_before:.2f}MB, reserved={reserved_before:.2f}MB")
        
        # Giải phóng bộ nhớ
        torch.cuda.empty_cache()
        gc.collect()
        
        # Thông tin sau khi giải phóng
        allocated_after = torch.cuda.memory_allocated() / 1024**2
        reserved_after = torch.cuda.memory_reserved() / 1024**2
        print(f"Memory after cleanup: allocated={allocated_after:.2f}MB, reserved={reserved_after:.2f}MB")
        
        print(f"Memory freed: allocated={allocated_before-allocated_after:.2f}MB, reserved={reserved_before-reserved_after:.2f}MB")
        
        return True
    else:
        print("CUDA not available")
        return False

if __name__ == "__main__":
    reset_gpu() 