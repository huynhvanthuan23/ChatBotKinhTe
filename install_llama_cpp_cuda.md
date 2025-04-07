# Hướng dẫn cài đặt llama-cpp-python với CUDA trên Windows

## Yêu cầu hệ thống
- Windows 10/11
- Python 3.8-3.11
- NVIDIA GPU với driver đã cài đặt
- CUDA Toolkit 11.8 hoặc 12.x

## Các bước cài đặt

### 1. Cài đặt CUDA Toolkit
Nếu bạn chưa cài đặt CUDA Toolkit, hãy tải về và cài đặt từ trang web NVIDIA:
- [CUDA Toolkit 11.8](https://developer.nvidia.com/cuda-11-8-0-download-archive)

### 2. Cài đặt PyTorch với CUDA
```bash
pip install torch torchvision torchaudio --index-url https://download.pytorch.org/whl/cu118
```

### 3. Kiểm tra CUDA có hoạt động với PyTorch
```bash
python -c "import torch; print(f'CUDA available: {torch.cuda.is_available()}')"
```
Nếu kết quả là `CUDA available: True`, PyTorch đã nhận diện được GPU.

### 4. Cài đặt các dependencies cần thiết
```bash
pip install cmake scikit-build setuptools-rust
```

### 5. Cài đặt llama-cpp-python với CUDA

#### Cách 1: Sử dụng biến môi trường để cài đặt
```bash
set CMAKE_ARGS=-DLLAMA_CUBLAS=on
set FORCE_CMAKE=1
pip uninstall -y llama-cpp-python
pip install llama-cpp-python==0.2.19 --no-cache-dir
```

#### Cách 2: Nếu Cách 1 không hoạt động, thử cài từ source
```bash
git clone https://github.com/abetlen/llama-cpp-python.git
cd llama-cpp-python
set CMAKE_ARGS=-DLLAMA_CUBLAS=on
set FORCE_CMAKE=1
pip install -e .
```

### 6. Kiểm tra cài đặt
Để kiểm tra xem llama-cpp-python có sử dụng được GPU hay không, chạy file `verify_gpu.py`:
```bash
python verify_gpu.py
```

### 7. Xử lý lỗi thường gặp

#### Lỗi: "No CUDA GPU available"
- Kiểm tra driver NVIDIA đã cài đặt đúng chưa
- Kiểm tra CUDA Toolkit đã cài đặt đúng chưa
- Kiểm tra biến môi trường PATH có chứa đường dẫn đến CUDA không

#### Lỗi: "Could not load dynamic library 'cudart64_110.dll'"
- Cài đặt CUDA Toolkit 11.x và thêm vào PATH

#### Lỗi: "GPU memory overflow"
- Giảm giá trị n_gpu_layers xuống (ví dụ: 16 thay vì 32)
- Sử dụng model quantization thấp hơn (ví dụ: Q4_K thay vì Q5_K)

## Kiểm tra hiệu suất

Sau khi cài đặt thành công, bạn có thể so sánh thời gian xử lý giữa:
- Chỉ sử dụng CPU (`n_gpu_layers=0`)
- Sử dụng GPU một phần (`n_gpu_layers=16`)
- Sử dụng GPU tối đa (`n_gpu_layers=32`)

## Tối ưu hóa cấu hình

Điều chỉnh các tham số sau để tối ưu hiệu suất:
- `n_gpu_layers`: Số lớp được offload lên GPU (càng cao càng tốt nếu VRAM đủ)
- `n_batch`: Kích thước batch (512-1024 là tốt cho GPU hiện đại)
- `n_ctx`: Context window size (4096 là tốt cho hầu hết GPU)
- `f16_kv`: Sử dụng f16 để tiết kiệm bộ nhớ

## Tham khảo
- [Tài liệu chính thức llama-cpp-python](https://github.com/abetlen/llama-cpp-python)
- [CUDA Toolkit Documentation](https://docs.nvidia.com/cuda/)
- [Hướng dẫn cài đặt PyTorch](https://pytorch.org/get-started/locally/) 