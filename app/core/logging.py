import logging

def get_logger(name):
    logger = logging.getLogger(name)
    if not logger.handlers:
        # Configure basic logging
        logging.basicConfig(
            level=logging.INFO,
            format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
            datefmt='%Y-%m-%d %H:%M:%S'
        )
    return logger


