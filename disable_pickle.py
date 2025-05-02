import pickle
import builtins
import logging
import os

logger = logging.getLogger(__name__)

# Lưu pickle thật để debugging
original_pickle = pickle

# Kiểm tra biến môi trường để xác định có cho phép dùng pickle hay không
ALLOW_PICKLE = os.environ.get('ALLOW_PICKLE', 'false').lower() == 'true'

# Tạo pickle giả không làm gì hoặc có điều kiện
class SafePickle:
    @staticmethod
    def load(*args, **kwargs):
        if ALLOW_PICKLE and kwargs.get('allow_dangerous_deserialization', False):
            logger.warning("Allowing dangerous pickle load with explicit permission")
            return original_pickle.load(*args, **kwargs)
        else:
            logger.warning("Pickle load attempted but blocked for security")
            return {}
            
    @staticmethod
    def loads(*args, **kwargs):
        if ALLOW_PICKLE and kwargs.get('allow_dangerous_deserialization', False):
            logger.warning("Allowing dangerous pickle loads with explicit permission")
            return original_pickle.loads(*args, **kwargs)
        else:
            logger.warning("Pickle loads attempted but blocked for security")
            return {}
            
    @staticmethod
    def dump(*args, **kwargs):
        if ALLOW_PICKLE and os.environ.get('ALLOW_PICKLE_SAVE', 'false').lower() == 'true':
            logger.warning("Allowing pickle dump with explicit permission")
            return original_pickle.dump(*args, **kwargs)
        else:
            logger.warning("Pickle dump attempted but blocked for security")
            return None
            
    @staticmethod
    def dumps(*args, **kwargs):
        if ALLOW_PICKLE and os.environ.get('ALLOW_PICKLE_SAVE', 'false').lower() == 'true':
            logger.warning("Allowing pickle dumps with explicit permission")
            return original_pickle.dumps(*args, **kwargs)
        else:
            logger.warning("Pickle dumps attempted but blocked for security")
            return b""

# Thay thế pickle thật bằng pickle giả
builtins.pickle = SafePickle

def disable():
    """Call this to disable pickle globally"""
    builtins.pickle = SafePickle
    logger.info(f"Pickle security enabled, ALLOW_PICKLE={ALLOW_PICKLE}")
    return "Pickle disabled successfully"

def enable_for_vector_store_creation():
    """Enable pickle for vector store creation"""
    builtins.pickle = original_pickle
    logger.info("Pickle enabled for vector store creation")
    return "Pickle enabled for vector store creation"

def restore():
    """Restore original pickle"""
    builtins.pickle = original_pickle
    logger.info("Original pickle restored")
    return "Original pickle restored"
