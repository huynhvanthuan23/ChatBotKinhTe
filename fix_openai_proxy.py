"""
Plugin xử lý vấn đề proxies trong OpenAI
"""

import sys
import logging
import inspect

# Thiết lập logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(message)s')
logger = logging.getLogger(__name__)

def patch_openai():
    """Patch openai library để loại bỏ lỗi proxies"""
    try:
        import openai
        import httpx
        
        logger.info("Bắt đầu patch OpenAI để xử lý lỗi proxies...")
        
        # Phiên bản OpenAI 1.3.7 sử dụng httpx trực tiếp
        # Lưu lại constructor gốc của httpx.Client
        original_init = httpx.Client.__init__
        
        # Định nghĩa constructor mới loại bỏ tham số proxies
        def new_init(self, *args, **kwargs):
            # Loại bỏ tham số proxies nếu có
            if 'proxies' in kwargs:
                logger.info("Đã loại bỏ tham số 'proxies' từ httpx client")
                del kwargs['proxies']
            
            # Gọi constructor gốc với kwargs đã sửa
            return original_init(self, *args, **kwargs)
        
        # Thay thế constructor
        httpx.Client.__init__ = new_init
        
        # Reset các client đã tạo
        if hasattr(openai, "_client"):
            openai._client = None
        
        logger.info("Đã patch thành công httpx.Client!")
        return True
    
    except Exception as e:
        logger.error(f"Lỗi khi patch OpenAI: {str(e)}")
        logger.error(f"Chi tiết: {inspect.trace()}")
        return False

if __name__ == "__main__":
    success = patch_openai()
    if success:
        logger.info("Patch OpenAI thành công!")
        sys.exit(0)
    else:
        logger.error("Patch OpenAI thất bại!")
        sys.exit(1) 