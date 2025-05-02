import disable_pickle
disable_pickle.disable()

# Sửa lỗi OpenAI proxies
try:
    import fix_openai_proxy
    fix_openai_proxy.patch_openai()
    print("Đã áp dụng patch để sửa lỗi OpenAI proxies")
except ImportError:
    print("Không tìm thấy module fix_openai_proxy, tiếp tục không có patch")
except Exception as e:
    print(f"Lỗi khi áp dụng patch OpenAI: {str(e)}")

import uvicorn
from fastapi import FastAPI, Request, HTTPException
from fastapi.responses import HTMLResponse
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates
import os
from fastapi.responses import FileResponse
from app.core.config import settings
from fastapi.middleware.cors import CORSMiddleware
import google.generativeai as genai
import openai
from openai import OpenAI
from langchain_community.embeddings import HuggingFaceEmbeddings
from langchain_community.vectorstores import FAISS
import torch
import gc
import time
import logging
import traceback
import json
from pydantic import BaseModel
from typing import Optional, Dict, Any
from fastapi.middleware import Middleware
from starlette.middleware.base import BaseHTTPMiddleware
from dotenv import load_dotenv
import pickle
import importlib
import re
from langchain_community.document_loaders import TextLoader, PyPDFLoader, Docx2txtLoader
from langchain.text_splitter import RecursiveCharacterTextSplitter
import shutil

# Constants and Environment Variables
VECTOR_DB_PATH = os.getenv("DB_FAISS_PATH", "vector_db")
EMBEDDING_MODEL = os.getenv("EMBEDDING_MODEL", "sentence-transformers/all-MiniLM-L6-v2")
GEMINI_MODEL = os.getenv("GOOGLE_MODEL", "gemini-1.5-pro")
OPENAI_MODEL = os.getenv("OPENAI_MODEL", "gpt-4o-mini")
API_TYPE = os.getenv("API_TYPE", "google").lower()  # google hoặc openai

# Cấu hình logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler("app.log"),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)
logging = logger  # Make logger available as logging for consistency

# Thiết lập các biến môi trường
os.environ["USE_API"] = "true"  # Luôn sử dụng API
os.environ["API_TYPE"] = API_TYPE

# Middleware để xử lý encoding
class EncodingMiddleware(BaseHTTPMiddleware):
    async def dispatch(self, request: Request, call_next):
        response = await call_next(request)
        if response.headers.get("content-type", "").startswith("application/json"):
            response.headers["content-type"] = "application/json; charset=utf-8"
        return response

# Tạo ứng dụng FastAPI với middleware
app = FastAPI(
    title=settings.PROJECT_NAME,
    description=f"API cho ChatBotKinhTe sử dụng {API_TYPE.capitalize()} API",
    version="1.0.0",
    docs_url=f"{settings.API_V1_STR}/docs",
    redoc_url=f"{settings.API_V1_STR}/redoc",
    middleware=[Middleware(EncodingMiddleware)]
)

# Cấu hình CORS
origins = [
    "http://localhost:8000",  # Laravel default port
    "http://127.0.0.1:8000",
    "http://localhost:3000",  # React default port
    "http://127.0.0.1:3000",
    "http://localhost:8080",  # FastAPI port
    "http://127.0.0.1:8080",
]

# Thêm origins từ env nếu có
if os.environ.get("CORS_ORIGINS"):
    try:
        custom_origins = json.loads(os.environ.get("CORS_ORIGINS", '["*"]'))
        if isinstance(custom_origins, list):
            origins.extend(custom_origins)
    except Exception as e:
        logger.error(f"Error parsing CORS_ORIGINS: {e}")

app.add_middleware(
    CORSMiddleware,
    allow_origins=origins,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
    expose_headers=["X-Process-Time"],
)

# Thêm logging cho CORS
logger.info(f"CORS enabled for origins: {origins}")

# Classes cho request và response
class ChatRequest(BaseModel):
    query: str
    user_id: Optional[int] = None

class ChatResponse(BaseModel):
    success: bool
    answer: str
    query: str
    error: Optional[str] = None

class ReloadResponse(BaseModel):
    success: bool
    message: str
    error: Optional[str] = None

class ConfigRequest(BaseModel):
    api_type: str
    google_api_key: Optional[str] = None
    google_model: Optional[str] = None
    openai_api_key: Optional[str] = None
    openai_model: Optional[str] = None

class DocumentProcessRequest(BaseModel):
    file_path: str
    document_id: int
    title: Optional[str] = None
    description: Optional[str] = None
    file_type: Optional[str] = None
    absolute_path: Optional[str] = None
    chunk_size: Optional[int] = 1000
    chunk_overlap: Optional[int] = 200

class DocumentProcessResponse(BaseModel):
    success: bool
    message: str
    document_id: int
    error: Optional[str] = None

# Biến global cho các components
gemini_model = None
openai_client = None
vector_retriever = None

# Vô hiệu hóa pickle.load có điều kiện
_original_load = pickle.load

def _safe_load(*args, **kwargs):
    # Kiểm tra biến môi trường ALLOW_PICKLE
    if os.getenv("ALLOW_PICKLE", "false").lower() == "true":
        logging.info("ALLOW_PICKLE=true, enabling pickle loading for vector database")
        return _original_load(*args, **kwargs)
    else:
        # Nếu không cho phép, log cảnh báo và raise lỗi
        logging.error("PICKLE LOAD BLOCKED FOR SECURITY (set ALLOW_PICKLE=true to enable)")
        raise ValueError("Pickle loading is disabled for security. Set ALLOW_PICKLE=true to enable.")

# Thay thế pickle.load bằng phiên bản có điều kiện
pickle.load = _safe_load

# Khởi tạo vector database
def initialize_vector_db():
    """Initialize vector database from FAISS."""
    try:
        logging.info(f"Attempting to initialize vector database from {VECTOR_DB_PATH}")
        
        # Check if vector store files exist
        if os.path.exists(os.path.join(VECTOR_DB_PATH, "index.faiss")) and os.path.exists(os.path.join(VECTOR_DB_PATH, "index.pkl")):
            logging.info(f"Found vector store files at {VECTOR_DB_PATH}")
            try:
                # Try to load the vector store
                logging.info("Loading vector store...")
        
                embedding = HuggingFaceEmbeddings(model_name=EMBEDDING_MODEL)
                
                # Explicitly allow dangerous deserialization for mounted vector database
                try:
                    vectorstore = FAISS.load_local(
                        VECTOR_DB_PATH,
                        embedding,
                        allow_dangerous_deserialization=True
                    )
                    logging.info("Vector store loaded successfully with allow_dangerous_deserialization=True")
                    return vectorstore
                except TypeError as e:
                    # If TypeError occurs, try without the parameter
                    if "unexpected keyword argument 'allow_dangerous_deserialization'" in str(e):
                        logging.info("Trying to load vector store without allow_dangerous_deserialization parameter")
                        vectorstore = FAISS.load_local(
                            VECTOR_DB_PATH,
                            embedding
                        )
                        logging.info("Vector store loaded successfully without allow_dangerous_deserialization parameter")
                        return vectorstore
                    else:
                        raise e
                        
            except Exception as e:
                logging.error(f"Error loading vector store: {e}")
                logging.error(traceback.format_exc())
                raise e
        else:
            logging.warning(f"Vector store files not found at {VECTOR_DB_PATH}")
            raise FileNotFoundError(f"Vector store files not found at {VECTOR_DB_PATH}")
            
    except Exception as e:
        logging.error(f"Error initializing vector database: {e}")
        logging.error(traceback.format_exc())
        
        # Try creating a dummy vector store with sample data
        try:
            logging.info("Creating a dummy vector store with sample data")
            embedding = HuggingFaceEmbeddings(model_name=EMBEDDING_MODEL)
        
            # Sample data about Vietnamese economy
            texts = [
                "Việt Nam có nền kinh tế thị trường định hướng xã hội chủ nghĩa.",
                "Các ngành kinh tế chính của Việt Nam bao gồm nông nghiệp, công nghiệp và dịch vụ.",
                "Xuất khẩu chính của Việt Nam bao gồm điện thoại, dệt may, điện tử, giày dép, gạo, cà phê, và thủy sản.",
                "Việt Nam có tốc độ tăng trưởng GDP khoảng 6-7% trong những năm gần đây trước đại dịch COVID-19.",
                "Việt Nam là thành viên của ASEAN, APEC, và WTO.",
                "Việt Nam đã ký kết nhiều hiệp định thương mại tự do với các đối tác lớn như EU, Hàn Quốc, Nhật Bản.",
                "Du lịch là một trong những ngành dịch vụ quan trọng đóng góp vào GDP của Việt Nam.",
                "Việt Nam đang phấn đấu trở thành nước công nghiệp hiện đại vào năm 2045."
            ]
            
            vectorstore = FAISS.from_texts(texts, embedding)
            vectorstore.save_local(VECTOR_DB_PATH)
            logging.info("Dummy vector store created and saved successfully")
            return vectorstore
            
        except Exception as e2:
            logging.error(f"Error creating dummy vector store: {e2}")
            logging.error(traceback.format_exc())
            raise e  # Raise the original error

# Khởi tạo Gemini model
def initialize_gemini():
    """Initialize Gemini model."""
    try:
        logging.info("Initializing Gemini model...")
        
        # Load API key
        api_key = os.getenv("GOOGLE_API_KEY")
        if not api_key:
            logging.error("GOOGLE_API_KEY environment variable not set")
            return None
        
        # Log first and last 5 characters of API key (for debugging, not the whole key for security)
        masked_key = f"{api_key[:5]}...{api_key[-5:]}" if len(api_key) > 10 else "***"
        logging.info(f"Using API key: {masked_key} (middle part hidden)")
        
        # Set API key
        genai.configure(api_key=api_key)
        
        # Initialize model
        logging.info(f"Initializing Gemini model: {GEMINI_MODEL}")
        model = genai.GenerativeModel(GEMINI_MODEL)
        
        # Test connection
        test_response = model.generate_content("Test connection")
        logging.info(f"Gemini model test response: {test_response.text}")
        
        return model
        
    except Exception as e:
        logging.error(f"Error initializing Gemini model: {e}")
        logging.error(traceback.format_exc())
        return None

# Khởi tạo OpenAI model
def initialize_openai():
    """Initialize OpenAI model."""
    try:
        logging.info("Initializing OpenAI model...")
        
        # Load API key
        api_key = os.getenv("OPENAI_API_KEY")
        if not api_key:
            logging.error("OPENAI_API_KEY environment variable not set")
            return None
            
        # Log first and last 4 characters of API key (for debugging, not the whole key for security)
        masked_key = f"{api_key[:4]}...{api_key[-4:]}" if len(api_key) > 8 else "***"
        logging.info(f"Using API key: {masked_key} (middle part hidden)")
        
        # Khởi tạo client với cách phù hợp với phiên bản OpenAI
        logging.info("Creating OpenAI client...")
        try:
            # Tạo client với các tham số tối thiểu
            client = OpenAI(
                api_key=api_key,
            )
            logging.info("OpenAI client created successfully")
        except TypeError as e:
            # Nếu gặp lỗi về tham số không hợp lệ
            logging.warning(f"Modern OpenAI client initialization failed: {e}")
            logging.info("Trying alternative initialization method")
            
            # Cách khởi tạo thay thế
            openai.api_key = api_key
            client = openai
            logging.info("OpenAI client initialized with alternative method")
        
        # Test connection
        try:
            logging.info(f"Testing OpenAI connection with model: {OPENAI_MODEL}")
            
            # Kiểm tra kiểu client
            if hasattr(client, 'chat') and hasattr(client.chat, 'completions'):
                # Client mới (instance của lớp OpenAI)
                test_response = client.chat.completions.create(
                    model=OPENAI_MODEL,
                    messages=[{"role": "user", "content": "Test connection"}],
                    max_tokens=5
                )
                if hasattr(test_response, 'choices') and len(test_response.choices) > 0:
                    logging.info(f"OpenAI model test response: {test_response.choices[0].message.content}")
                    logging.info("Test successful with modern client")
            else:
                # Client cũ
                logging.info("Using legacy client for test")
                test_response = client.ChatCompletion.create(
                    model=OPENAI_MODEL,
                    messages=[{"role": "user", "content": "Test connection"}],
                    max_tokens=5
                )
                if hasattr(test_response, 'choices') and len(test_response.choices) > 0:
                    logging.info(f"OpenAI model test response: {test_response.choices[0].message.content}")
                    logging.info("Test successful with legacy client")
            
            return client
        except Exception as test_err:
            error_msg = str(test_err)
            logging.error(f"Error testing OpenAI connection: {error_msg}")
            
            if "429" in error_msg:
                logging.error("Rate limit exceeded. API rate limits reached.")
                # Có thể dùng client sau vì có thể chỉ là lỗi tạm thời
                return client
            
            logging.error(traceback.format_exc())
            return None
    
    except Exception as e:
        logging.error(f"Error initializing OpenAI model: {e}")
        logging.error(traceback.format_exc())
        return None

# Xử lý chat
async def process_chat(query: str, user_id: Optional[int] = None) -> Dict[str, Any]:
    try:
        start_time = time.time()
        
        # Kiểm tra global variables
        global gemini_model, openai_client, vector_retriever
        if ((API_TYPE == "google" and gemini_model is None) or 
            (API_TYPE == "openai" and openai_client is None) or 
            vector_retriever is None):
            # Thử khởi tạo lại nếu cần
            if API_TYPE == "google" and gemini_model is None:
                logger.info("Gemini model not initialized, trying to reinitialize...")
                try:
                    gemini_model = initialize_gemini()
                except Exception as e:
                    logger.error(f"Failed to initialize Gemini model: {str(e)}")
            
            if API_TYPE == "openai" and openai_client is None:
                logger.info("OpenAI client not initialized, trying to reinitialize...")
                try:
                    openai_client = initialize_openai()
                except Exception as e:
                    logger.error(f"Failed to initialize OpenAI client: {str(e)}")
            
            if vector_retriever is None:
                logger.info("Vector retriever not initialized, trying to reinitialize...")
                vector_db = initialize_vector_db()
                vector_retriever = vector_db.as_retriever(
                    search_type="mmr",  # Maximum Marginal Relevance
                    search_kwargs={
                        "k": 2,  # Số lượng kết quả cuối cùng
                        "fetch_k": 5,  # Lấy 15 tài liệu có liên quan nhất trước khi chọn k
                        "lambda_mult": 0.8,  # 0.7 = 70% trọng số cho tính liên quan, 30% cho tính đa dạng
                        "score_threshold": 0.5  # Chỉ lấy các kết quả có độ tương tự > 0.5
                    }
                )
                
        # Tìm kiếm ngữ cảnh từ vector database
        try:
            # Thiết lập số lượng tài liệu tối đa cần truy xuất
            max_results = 8  # Tăng số lượng lên để có nhiều context hơn
            
            docs = vector_retriever.get_relevant_documents(query)
            
            # Giới hạn số lượng tài liệu để tránh quá nhiều thông tin không cần thiết
            if len(docs) > max_results:
                docs = docs[:max_results]
                
            context = "\n\n".join([doc.page_content for doc in docs])
        
            # Log context để debug
            logger.info(f"Found {len(docs)} relevant documents for query: {query}")
            
            # Log document content for debugging
            for i, doc in enumerate(docs):
                preview = doc.page_content[:100] + "..." if len(doc.page_content) > 100 else doc.page_content
                score = doc.metadata.get('score', 'N/A')  # Thử lấy điểm tương tự nếu có
                logger.info(f"Document {i+1} (score: {score}): {preview}")
        except Exception as e:
            logger.error(f"Error retrieving documents: {str(e)}")
            logger.error(traceback.format_exc())
            # Provide empty context if retriever fails
            docs = []
            context = ""
            
        # Nếu không tìm thấy tài liệu nào, sử dụng nội dung mặc định
        if not docs or not context.strip():
            logger.warning(f"No relevant documents found for query: {query}")
            context = "Không có thông tin cụ thể về chủ đề này trong cơ sở dữ liệu."
        
        # Tạo prompt với ngữ cảnh
        prompt = f"""BẮT BUỘC trả lời dựa HOÀN TOÀN trên thông tin được cung cấp dưới đây.
KHÔNG ĐƯỢC sử dụng kiến thức bên ngoài dưới bất kỳ hình thức nào.
Nếu không có thông tin liên quan, hãy nói "Tôi không tìm thấy thông tin liên quan trong cơ sở dữ liệu".

THÔNG TIN THAM KHẢO:
{context}

CÂU HỎI: {query}

YÊU CẦU ĐẶC BIỆT:
1. PHẢI bắt đầu câu trả lời của bạn với "Theo dữ liệu tìm được: " và kèm theo trích dẫn trực tiếp từ thông tin trên.
2. Nếu có thông tin liên quan, PHẢI trích dẫn ít nhất một đoạn từ thông tin trên.
3. Nếu không có thông tin liên quan, hãy nói rõ ràng "Tôi không tìm thấy thông tin liên quan trong cơ sở dữ liệu".
4. KHÔNG ĐƯỢC thêm bất kỳ thông tin nào không có trong dữ liệu được cung cấp.
"""

        # Thay đổi prompt để tận dụng tốt hơn nhiều tài liệu liên quan
        api_prompt = f"""Dựa trên thông tin được cung cấp, hãy trả lời câu hỏi sau.
Thông tin có thể chứa nhiều đoạn văn bản từ các tài liệu khác nhau. Hãy tổng hợp tất cả thông tin liên quan để đưa ra câu trả lời đầy đủ nhất.

THÔNG TIN THAM KHẢO:
{context}

CÂU HỎI: {query}

Hướng dẫn:
1. Bắt đầu câu trả lời với "Theo dữ liệu tìm được:"
2. Tổng hợp thông tin từ tất cả các nguồn liên quan
3. Nếu các nguồn có thông tin trái ngược, hãy trình bày các quan điểm khác nhau
4. Trích dẫn cụ thể thông tin quan trọng từ dữ liệu
5. Phân tích và kết nối các thông tin để tạo ra câu trả lời toàn diện
6. Nếu không tìm thấy thông tin chính xác, hãy sử dụng thông tin gần đúng hoặc liên quan
"""
        
        answer = ""
        model_info = {}
        # Gửi prompt đến API dựa theo cấu hình
        try:
            if API_TYPE == "google" and gemini_model is not None:
                # Gửi prompt đến Gemini API
                response = gemini_model.generate_content(api_prompt)
                answer = response.text
                model_info = {"model": GEMINI_MODEL}
                logger.info(f"Received response from Gemini API ({GEMINI_MODEL})")
            elif API_TYPE == "openai" and openai_client is not None:
                # Gửi prompt đến OpenAI API
                try:
                    # Try using the modern client (OpenAI class instance)
                    if hasattr(openai_client, 'chat') and hasattr(openai_client.chat, 'completions'):
                        response = openai_client.chat.completions.create(
                            model=OPENAI_MODEL,
                            messages=[
                                {"role": "system", "content": "Bạn là trợ lý AI có kiến thức chuyên sâu về kinh tế Việt Nam. Trả lời dựa chỉ trên dữ liệu được cung cấp."},
                                {"role": "user", "content": api_prompt}
                            ],
                            temperature=float(os.getenv("TEMPERATURE", 0.2)),
                            max_tokens=int(os.getenv("MAX_TOKENS", 512))
                        )
                        answer = response.choices[0].message.content
                    else:
                        # Try using legacy client (openai global)
                        logger.info("Using legacy OpenAI API call method")
                        response = openai_client.ChatCompletion.create(
                            model=OPENAI_MODEL,
                            messages=[
                                {"role": "system", "content": "Bạn là trợ lý AI có kiến thức chuyên sâu về kinh tế Việt Nam. Trả lời dựa chỉ trên dữ liệu được cung cấp."},
                                {"role": "user", "content": api_prompt}
                            ],
                            temperature=float(os.getenv("TEMPERATURE", 0.2)),
                            max_tokens=int(os.getenv("MAX_TOKENS", 512))
                        )
                        answer = response.choices[0].message.content
                    
                    model_info = {"model": OPENAI_MODEL}
                    logger.info(f"Received response from OpenAI API ({OPENAI_MODEL})")
                except AttributeError as e:
                    logger.error(f"OpenAI client structure error: {e}")
                    # Fallback to simple response
                    if context.strip() == "Không có thông tin cụ thể về chủ đề này trong cơ sở dữ liệu.":
                        answer = "Theo dữ liệu tìm được: Tôi không tìm thấy thông tin liên quan trong cơ sở dữ liệu."
                    else:
                        answer = f"Theo dữ liệu tìm được: {context}"
                    model_info = {"model": "fallback", "error": f"OpenAI client structure error: {e}"}
            else:
                # Fallback mode khi không có model
                logger.warning(f"Using fallback mode (no {API_TYPE} model available)")
                if context.strip() == "Không có thông tin cụ thể về chủ đề này trong cơ sở dữ liệu.":
                    answer = "Theo dữ liệu tìm được: Tôi không tìm thấy thông tin liên quan trong cơ sở dữ liệu."
                else:
                    # Kiểm tra chất lượng context trước khi trả về
                    if len(context.split()) > 10:  # Nếu context đủ dài và có ý nghĩa
                        # Trả về context với prefix
                        answer = f"Theo dữ liệu tìm được: {context}"
                    else:
                        # Context quá ngắn, trả về thông báo chung
                        answer = "Theo dữ liệu tìm được: Tôi không tìm thấy thông tin đầy đủ về chủ đề này trong cơ sở dữ liệu."
                
                # Thêm thông tin về trạng thái của API
                if API_TYPE == "google":
                    model_info = {"model": "fallback", "api_status": "Gemini API không khả dụng"}
                else:
                    model_info = {"model": "fallback", "api_status": "OpenAI API không khả dụng"}
        except Exception as e:
            logger.error(f"Error generating content from model: {str(e)}")
            logger.error(traceback.format_exc())
            
            # Fallback mode với thông báo lỗi chi tiết hơn
            error_msg = str(e)
            
            if "rate limit" in error_msg.lower() or "429" in error_msg:
                api_error = "API đã vượt quá giới hạn tốc độ. Vui lòng thử lại sau."
            elif "authentication" in error_msg.lower() or "401" in error_msg:
                api_error = "Lỗi xác thực API. Vui lòng kiểm tra cấu hình API key."
            elif "not found" in error_msg.lower() or "404" in error_msg:
                api_error = f"Model '{GEMINI_MODEL if API_TYPE == 'google' else OPENAI_MODEL}' không tìm thấy."
            elif "timeout" in error_msg.lower():
                api_error = "Kết nối API bị timeout. Vui lòng kiểm tra kết nối mạng."
            else:
                api_error = "Lỗi API không xác định."
            
            if context.strip() == "Không có thông tin cụ thể về chủ đề này trong cơ sở dữ liệu.":
                answer = "Theo dữ liệu tìm được: Tôi không tìm thấy thông tin liên quan trong cơ sở dữ liệu."
            else:
                # Nếu có context, sử dụng context làm câu trả lời
                if len(context.split()) > 10:  # Có đủ context
                    answer = f"Theo dữ liệu tìm được: {context}"
                else:
                    answer = "Theo dữ liệu tìm được: Tôi không tìm thấy thông tin đầy đủ về chủ đề này."
            
            model_info = {"model": "fallback", "error": api_error}
            
        elapsed_time = time.time() - start_time
        
        # Đảm bảo câu trả lời bắt đầu với "Theo dữ liệu tìm được: "
        if answer and not answer.startswith("Theo dữ liệu tìm được:"):
            answer = "Theo dữ liệu tìm được: " + answer
        
        # Chuẩn bị response
        result = {
            "success": True,
            "answer": answer,
            "query": query,
            "processing_time": f"{elapsed_time:.2f} seconds"
        }
        
        # Thêm model info
        result.update(model_info)
        
        return result
        
    except Exception as e:
        logger.error(f"Error in chat processing: {str(e)}")
        logger.error(traceback.format_exc())
        return {
            "success": False,
            "answer": "Xin lỗi, có lỗi xảy ra trong quá trình xử lý. Vui lòng thử lại sau.",
            "query": query,
            "error": str(e)
        }

# Endpoints
@app.get("/api/v1/chat/test-connection")
async def test_connection():
    """Test connection endpoint for Laravel"""
    return {
        "status": "success",
        "message": "Connection successful",
        "server_time": time.strftime("%Y-%m-%d %H:%M:%S")
    }

@app.post("/api/v1/chat/chat-direct")
async def chat_direct(request: Request):
    """Chat endpoint for Laravel integration"""
    try:
        # Log request headers
        logger.info(f"Request headers: {dict(request.headers)}")
        
        # Đọc raw body trước
        raw_body = await request.body()
        logger.info(f"Raw request body length: {len(raw_body)}")
        
        # Thử decode với UTF-8
        try:
            body_str = raw_body.decode('utf-8')
            logger.info("Used utf-8 encoding for request body")
        except UnicodeDecodeError:
            # Nếu không decode được UTF-8, thử các encoding khác
            try:
                body_str = raw_body.decode('latin-1')
                logger.info("Used latin-1 encoding for request body")
            except UnicodeDecodeError:
                body_str = raw_body.decode('cp1252', errors='ignore')
                logger.info("Used cp1252 encoding with error ignore for request body")
        
        # Parse JSON từ string
        try:
            body = json.loads(body_str)
            logger.info(f"Parsed JSON: {body}")
        except json.JSONDecodeError as e:
            # Log một phần của body để debug
            preview = body_str[:100] if len(body_str) > 100 else body_str
            logger.error(f"JSON parsing error: {str(e)}, preview: {preview}")
            return {
                "success": False,
                "answer": "Invalid JSON format",
                "query": "",
                "error": f"JSON decode error: {str(e)}"
            }
        
        # Extract fields
        query = body.get("message", "")  # Thay đổi từ "query" thành "message"
        user_id = body.get("user_id", None)
        
        logger.info(f"Extracted message: '{query}', user_id: {user_id}")
        
        if not query:
            return {
                "success": False,
                "answer": "Message is required",
                "query": "",
                "error": "Missing message parameter"
            }
        
        # Process chat
        result = await process_chat(query, user_id)
        
        # Log response
        logger.info(f"Chat response: {result}")
        
        # Đảm bảo response được encode đúng
        return {
            "success": result["success"],
            "response": result["answer"],  # Thay đổi từ "answer" thành "response"
            "query": result["query"],
            "error": result.get("error", None)
        }
    except Exception as e:
        logger.error(f"Error in chat_direct: {str(e)}")
        logger.error(traceback.format_exc())
        return {
            "success": False,
            "response": "Server error processing request",
            "query": "",
            "error": str(e)
        }

@app.get("/api/v1/chat/service-info")
async def service_info():
    """Get service information"""
    return {
        "service_type": "API",
        "api_type": API_TYPE.capitalize(),
        "model": GEMINI_MODEL if API_TYPE == "google" else OPENAI_MODEL,
        "status": "active" if (API_TYPE == "google" and gemini_model) or (API_TYPE == "openai" and openai_client) else "unavailable"
    }

@app.get("/api/v1/chat/health")
async def health_check():
    """Health check endpoint"""
    return {
        "status": "healthy",
        "components": {
            "api": "active" if (API_TYPE == "google" and gemini_model) or (API_TYPE == "openai" and openai_client) else "inactive",
            "vector_db": "active" if vector_retriever else "inactive",
            "api_type": API_TYPE
        }
    }

@app.get("/health")
async def root_health_check():
    """Root health check endpoint for Laravel admin integration"""
    return {
        "status": "healthy",
        "api_version": "1.0.0",
        "timestamp": time.strftime("%Y-%m-%d %H:%M:%S"),
        "resources": {
            "api": "online",
            "model": "available",
            "vector_db": "available"
        }
    }

@app.post("/chat-direct")
async def chat_direct_redirect(request: Request):
    """Redirect endpoint for Laravel integration"""
    logger.info("Request received at /chat-direct - redirecting to /api/v1/chat/chat-direct")
    return await chat_direct(request)

@app.post("/api/chat/send")
async def chat_send_redirect(request: Request):
    """Redirect endpoint for Laravel integration"""
    logger.info("Request received at /api/chat/send - redirecting to /api/v1/chat/chat-direct")
    return await chat_direct(request)

@app.post("/")
async def root_redirect(request: Request):
    """Root endpoint to handle requests to /"""
    logger.info("Request received at root (/) - redirecting to /api/v1/chat/chat-direct")
    return await chat_direct(request)

@app.post("/api/v1/chat/simple-chat")
async def simple_chat(request: Request):
    """Simple chat endpoint that doesn't rely on Gemini"""
    try:
        # Read raw body first to handle encoding properly
        raw_body = await request.body()
        logger.info(f"Raw request body length: {len(raw_body)}")
        
        # Try to decode with different encodings
        try:
            body_str = raw_body.decode('utf-8')
            logger.info("Used utf-8 encoding for request body")
        except UnicodeDecodeError:
            try:
                body_str = raw_body.decode('latin-1')
                logger.info("Used latin-1 encoding for request body")
            except UnicodeDecodeError:
                body_str = raw_body.decode('cp1252', errors='ignore')
                logger.info("Used cp1252 encoding with error ignore for request body")
        
        # Parse JSON from string
        try:
            body = json.loads(body_str)
            logger.info(f"Parsed JSON: {body}")
        except json.JSONDecodeError as e:
            # Log a part of body for debugging
            preview = body_str[:100] if len(body_str) > 100 else body_str
            logger.error(f"JSON parsing error: {str(e)}, preview: {preview}")
            return {
                "success": False,
                "response": "Invalid JSON format",
                "error": f"JSON decode error: {str(e)}"
            }
        
        query = body.get("message", "")
        
        # Chấp nhận nhiều tham số document ID khác nhau từ client
        support_doc_ids = body.get("support_doc_ids", [])
        doc_ids = body.get("doc_ids", [])
        document_ids = body.get("document_ids", [])
        context_document_ids = body.get("context_document_ids", [])
        
        # Kết hợp tất cả document IDs từ các tham số khác nhau
        all_doc_ids = []
        if support_doc_ids:
            all_doc_ids.extend(support_doc_ids)
        if doc_ids:
            all_doc_ids.extend(doc_ids)
        if document_ids:
            all_doc_ids.extend(document_ids)
        if context_document_ids:
            all_doc_ids.extend(context_document_ids)
            
        # Loại bỏ trùng lặp
        all_doc_ids = list(set(all_doc_ids))
        
        if not query:
            return {
                "success": False,
                "response": "No message provided",
                "query": ""
            }
            
        logger.info(f"Simple chat request received with message: {query}")
        if all_doc_ids:
            logger.info(f"Request includes document IDs: {all_doc_ids}")
        
        # Get relevant documents using vector retriever
        try:
            docs = []
            
            # Nếu có document IDs, ưu tiên tìm kiếm trong các tài liệu đã chọn trước
            if all_doc_ids:
                # Duyệt qua từng document_id được chọn
                for doc_id in all_doc_ids:
                    # Đường dẫn tới vector database của tài liệu cụ thể
                    document_vectors_path = os.path.join(VECTOR_DB_PATH, f"doc_{doc_id}")
                    
                    # Kiểm tra xem vector database có tồn tại không
                    if os.path.exists(document_vectors_path) and os.path.exists(os.path.join(document_vectors_path, "index.faiss")):
                        try:
                            logger.info(f"Searching in document ID {doc_id}")
                            # Tải vector database của tài liệu
                            embedding = HuggingFaceEmbeddings(model_name=EMBEDDING_MODEL)
                            try:
                                doc_db = FAISS.load_local(document_vectors_path, embedding, allow_dangerous_deserialization=True)
                            except TypeError:
                                doc_db = FAISS.load_local(document_vectors_path, embedding)
                                
                            # Tìm kiếm trong tài liệu cụ thể
                            doc_retriever = doc_db.as_retriever(
                                search_type="mmr",  # Sử dụng MMR thay vì đơn thuần similarity
                                search_kwargs={
                                    "k": 2,  # Số lượng kết quả cuối cùng
                                    "fetch_k": 15,  # Lấy 15 đoạn văn có liên quan nhất trước khi chọn k
                                    "lambda_mult": 0.8,  # 0.7 = 70% trọng số cho tính liên quan, 30% cho tính đa dạng
                                    "score_threshold": 0.5  # Chỉ lấy các kết quả có độ tương tự > 0.5
                                }
                            )
                            doc_results = doc_retriever.get_relevant_documents(query)
                            
                            # Nếu tìm thấy kết quả, thêm vào docs
                            if doc_results:
                                logger.info(f"Found {len(doc_results)} results in document ID {doc_id}")
                                for doc in doc_results:
                                    if 'document_id' not in doc.metadata:
                                        doc.metadata['document_id'] = doc_id
                                docs.extend(doc_results)
                        except Exception as e:
                            logger.error(f"Error searching in document ID {doc_id}: {e}")
                            logger.error(traceback.format_exc())
            
            # Nếu không tìm thấy kết quả trong các tài liệu đã chọn, hoặc không có tài liệu được chọn
            # thì tìm kiếm trong vector database chung
            if not docs:
                # Chỉ tìm kiếm trong vector database chung khi không có tài liệu nào được chọn
                if not all_doc_ids and vector_retriever:
                    logger.info("No documents selected, searching in main vector database")
                docs = vector_retriever.get_relevant_documents(query)
                logger.info(f"Retrieved {len(docs)} documents from main vector database for query: {query}")
                
                # Log document content for debugging
                for i, doc in enumerate(docs):
                    preview = doc.page_content[:100] + "..." if len(doc.page_content) > 100 else doc.page_content
                    logger.info(f"Document {i+1}: {preview}")
            elif all_doc_ids:
                logger.info("No results found in selected documents, but user has selected specific documents so not searching in main vector database")
            # Các trường hợp khác: không có all_doc_ids và không có vector_retriever
            
            # If documents found, use their content as response
            if docs:
                # Combine all document contents
                content = "\n\n".join([doc.page_content for doc in docs])
                
                # Thêm thông tin nguồn tài liệu nếu có
                if all_doc_ids and any('document_id' in doc.metadata for doc in docs):
                    doc_sources = set(doc.metadata.get('document_id') for doc in docs if 'document_id' in doc.metadata)
                    source_info = f"\n\nThông tin từ tài liệu IDs: {', '.join(map(str, doc_sources))}"
                    response = f"Tìm thấy thông tin liên quan trong tài liệu đã chọn:\n\n{content}{source_info}"
                else:
                    response = f"Tìm thấy thông tin liên quan:\n\n{content}"
            else:
                # No documents found
                if all_doc_ids:
                    response = "Không tìm thấy thông tin liên quan đến câu hỏi của bạn trong các tài liệu đã chọn. Vui lòng thử lại với từ khóa khác hoặc chọn tài liệu khác."
                else:
                    response = "Không tìm thấy thông tin liên quan đến câu hỏi của bạn trong cơ sở dữ liệu."
                
            return {
                "success": True,
                "response": response,
                "query": query,
                "doc_count": len(docs)
            }
            
        except Exception as e:
            logger.error(f"Error in simple chat vector search: {str(e)}")
            logger.error(traceback.format_exc())
            return {
                "success": False,
                "response": "Xin lỗi, có lỗi xảy ra khi tìm kiếm thông tin. Vui lòng thử lại sau.",
                "query": query,
                "error": str(e)
            }
            
    except Exception as e:
        logger.error(f"Error in simple chat request parsing: {str(e)}")
        logger.error(traceback.format_exc())
        return {
            "success": False,
            "response": "Xin lỗi, có lỗi xảy ra khi xử lý yêu cầu. Vui lòng thử lại sau.",
            "error": str(e)
        }

@app.post("/api/v1/admin/reload-config")
async def reload_config(config: Optional[ConfigRequest] = None):
    """Reload configuration settings from environment variables or direct config."""
    try:
        logger.info("Reloading configuration settings...")
        
        # Reload settings module
        from app.core import config as settings
        importlib.reload(settings)
        
        # Update global variables
        global API_TYPE, GEMINI_MODEL, OPENAI_MODEL
        
        # If config is provided, use it directly instead of environment variables
        if config:
            logger.info("Using provided configuration directly.")
            API_TYPE = config.api_type.lower()
            
            # Temporarily override the environment variables
            if API_TYPE == "google" and config.google_model:
                GEMINI_MODEL = config.google_model
                # Set API key if provided
                if config.google_api_key:
                    os.environ["GOOGLE_API_KEY"] = config.google_api_key
            elif API_TYPE == "openai" and config.openai_model:
                OPENAI_MODEL = config.openai_model
                # Set API key if provided
                if config.openai_api_key:
                    os.environ["OPENAI_API_KEY"] = config.openai_api_key
        else:
            # Use environment variables
            logger.info("Using environment variables for configuration.")
            API_TYPE = os.getenv("API_TYPE", "google").lower()
            GEMINI_MODEL = os.getenv("GOOGLE_MODEL", "gemini-1.5-pro")
            OPENAI_MODEL = os.getenv("OPENAI_MODEL", "gpt-4o-mini")
        
        # Set global API_TYPE environment variable
        os.environ["API_TYPE"] = API_TYPE
        
        # Reinitialize API clients
        global gemini_model, openai_client
        if API_TYPE == "google":
            logging.info("Reinitializing Gemini model...")
            try:
                gemini_model = initialize_gemini()
                if gemini_model:
                    logging.info("Gemini model reinitialized successfully")
                else:
                    logging.warning("Gemini model reinitialization returned None")
                    return ReloadResponse(
                        success=False,
                        message="Không thể khởi tạo lại model Gemini",
                        error="Gemini model reinitialization returned None"
                    )
            except Exception as e:
                logging.error(f"Error during Gemini reinitialization: {e}")
                logging.error(traceback.format_exc())
                return ReloadResponse(
                    success=False,
                    message="Lỗi khi khởi tạo lại model Gemini",
                    error=str(e)
                )
        elif API_TYPE == "openai":
            logging.info("Reinitializing OpenAI model...")
            try:
                openai_client = initialize_openai()
                if openai_client:
                    logging.info("OpenAI model reinitialized successfully")
                else:
                    logging.warning("OpenAI model reinitialization returned None")
                    return ReloadResponse(
                        success=False,
                        message="Không thể khởi tạo lại model OpenAI",
                        error="OpenAI model reinitialization returned None"
                    )
            except Exception as e:
                logging.error(f"Error during OpenAI reinitialization: {e}")
                logging.error(traceback.format_exc())
                return ReloadResponse(
                    success=False,
                    message="Lỗi khi khởi tạo lại model OpenAI",
                    error=str(e)
                )
        else:
            logging.error(f"Unknown API_TYPE: {API_TYPE}, supported types are 'google' and 'openai'")
            return ReloadResponse(
                success=False,
                message=f"Loại API không được hỗ trợ: {API_TYPE}",
                error=f"Unknown API_TYPE: {API_TYPE}, supported types are 'google' and 'openai'"
            )
        
        # Update CORS settings
        origins = [
            "http://localhost:8000",
            "http://127.0.0.1:8000",
            "http://localhost:3000",
            "http://127.0.0.1:3000", 
            "http://localhost:8080",
            "http://127.0.0.1:8080",
        ]
        
        if os.environ.get("CORS_ORIGINS"):
            try:
                custom_origins = json.loads(os.environ.get("CORS_ORIGINS", '["*"]'))
                if isinstance(custom_origins, list):
                    origins.extend(custom_origins)
                    logger.info(f"Updated CORS origins: {origins}")
            except Exception as e:
                logger.error(f"Error parsing CORS_ORIGINS: {e}")
        
        # Return success response
        return ReloadResponse(
            success=True,
            message="Cấu hình đã được tải lại thành công",
            error=None
        )
    except Exception as e:
        logger.error(f"Error reloading configuration: {e}")
        logger.error(traceback.format_exc())
        return ReloadResponse(
            success=False,
            message="Lỗi khi tải lại cấu hình",
            error=str(e)
        )

@app.post("/api/v1/documents/process")
async def process_document(request: DocumentProcessRequest):
    """
    Xử lý tài liệu được upload và tạo vector embeddings
    """
    logger.info(f"Processing document ID: {request.document_id}, File path: {request.file_path}")
    
    try:
        # Ưu tiên dùng đường dẫn tuyệt đối nếu có
        full_path = getattr(request, 'absolute_path', None)
        
        # Nếu không có đường dẫn tuyệt đối, thì dùng đường dẫn tương đối
        if not full_path or not os.path.exists(full_path):
            full_path = os.path.join(os.getenv("STORAGE_PATH", "storage"), request.file_path)
            logger.info(f"Sử dụng đường dẫn tương đối: {full_path}")
        else:
            logger.info(f"Sử dụng đường dẫn tuyệt đối: {full_path}")
        
        # Kiểm tra file có tồn tại không
        if not os.path.exists(full_path):
            logger.error(f"File not found: {full_path}")
            return DocumentProcessResponse(
                success=False,
                message="File không tồn tại",
                document_id=request.document_id,
                error=f"File not found: {full_path}"
            )
        
        # Khởi tạo document loader dựa trên loại file
        logger.info(f"Loading document: {full_path}")
        loader = None
        
        if request.file_type:
            mime_type = request.file_type
        else:
            # Đoán mime type từ đuôi file
            file_ext = os.path.splitext(full_path)[1].lower()
            if file_ext == '.pdf':
                mime_type = 'application/pdf'
            elif file_ext in ['.docx', '.doc']:
                mime_type = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            elif file_ext == '.txt':
                mime_type = 'text/plain'
            elif file_ext == '.md':
                mime_type = 'text/markdown'
            else:
                mime_type = 'application/octet-stream'
        
        # Chọn loader phù hợp
        if 'pdf' in mime_type:
            logger.info("Using PyPDFLoader")
            loader = PyPDFLoader(full_path)
        elif 'word' in mime_type or 'docx' in mime_type or 'doc' in mime_type:
            logger.info("Using Docx2txtLoader")
            loader = Docx2txtLoader(full_path)
        elif 'text' in mime_type or 'markdown' in mime_type or 'plain' in mime_type:
            logger.info("Using TextLoader")
            loader = TextLoader(full_path)
        else:
            logger.error(f"Unsupported file type: {mime_type}")
            return DocumentProcessResponse(
                success=False,
                message=f"Loại file không được hỗ trợ: {mime_type}",
                document_id=request.document_id,
                error=f"Unsupported file type: {mime_type}"
            )
        
        # Tải document
        try:
            docs = loader.load()
            logger.info(f"Document loaded successfully, pages/chunks: {len(docs)}")
        except Exception as e:
            logger.error(f"Error loading document: {e}")
            return DocumentProcessResponse(
                success=False,
                message="Không thể đọc nội dung file",
                document_id=request.document_id,
                error=f"Error loading document: {e}"
            )
        
        # Chia nhỏ văn bản
        text_splitter = RecursiveCharacterTextSplitter(
            chunk_size=request.chunk_size,
            chunk_overlap=request.chunk_overlap,
            length_function=len,
        )
        
        chunks = text_splitter.split_documents(docs)
        logger.info(f"Document split into {len(chunks)} chunks with chunk_size={request.chunk_size}, chunk_overlap={request.chunk_overlap}")
        
        # Tạo hoặc cập nhật vector store
        embedding = HuggingFaceEmbeddings(model_name=EMBEDDING_MODEL)
        
        # Tạo thư mục lưu trữ vector riêng cho document
        document_vectors_path = os.path.join(VECTOR_DB_PATH, f"doc_{request.document_id}")
        os.makedirs(document_vectors_path, exist_ok=True)
        
        # Tạo FAISS database từ chunks
        logger.info(f"Creating vector store at {document_vectors_path}")
        db = FAISS.from_documents(chunks, embedding)
        db.save_local(document_vectors_path)
        
        # Kiểm tra kết quả
        if os.path.exists(os.path.join(document_vectors_path, "index.faiss")) and os.path.exists(os.path.join(document_vectors_path, "index.pkl")):
            logger.info(f"Vector store created successfully for document {request.document_id}")
            
            # Kiểm tra bằng tìm kiếm thử
            try:
                loaded_db = FAISS.load_local(document_vectors_path, embedding)
                query = request.title or "Kinh tế"
                docs = loaded_db.similarity_search(query, k=1)
                logger.info(f"Test query successful, found {len(docs)} results")
            except Exception as e:
                logger.error(f"Test query failed: {e}")
                # Không fail cả quá trình nếu test query thất bại
            
            # Đường dẫn vector để lưu vào DB
            vector_path = f"doc_{request.document_id}"
            
            return DocumentProcessResponse(
                success=True,
                message="Đã xử lý tài liệu và tạo vector thành công",
                document_id=request.document_id
            )
        else:
            logger.error(f"Vector store creation failed for document {request.document_id}")
            return DocumentProcessResponse(
                success=False,
                message="Không thể tạo vector cho tài liệu",
                document_id=request.document_id,
                error="Vector store files not found after creation"
            )
            
    except Exception as e:
        logger.error(f"Error processing document: {e}")
        logger.error(traceback.format_exc())
        return DocumentProcessResponse(
            success=False,
            message="Lỗi xử lý tài liệu",
            document_id=request.document_id,
            error=str(e)
        )

# Khởi tạo components khi server start
@app.on_event("startup")
async def startup_event():
    """Run on application startup."""
    global vector_retriever, gemini_model, openai_client
        
    try:
        # Initialize vector database
        logging.info("Initializing vector database...")
        vector_retriever = initialize_vector_db()
        
        if vector_retriever:
            # Configure retriever với các tham số mới - sử dụng MMR để cân bằng giữa tính liên quan và đa dạng
            vector_retriever = vector_retriever.as_retriever(
                search_type="mmr",  # Sử dụng Maximum Marginal Relevance
                search_kwargs={
                    "k": 2,  # Số lượng kết quả cuối cùng
                    "fetch_k": 5,  # Lấy 15 tài liệu có liên quan nhất trước khi chọn k
                    "lambda_mult": 0.8,  # 0.7 = 70% trọng số cho tính liên quan, 30% cho tính đa dạng
                    "score_threshold": 0.5  # Chỉ lấy các kết quả có độ tương tự > 0.5
                }
            )
            logging.info("Vector database retriever configured with MMR for better diversity and relevance")
            
            # Test retriever
            try:
                logging.info("Testing retriever with a simple query")
                test_docs = vector_retriever.get_relevant_documents("kinh tế")
                logging.info(f"Retriever test successful, found {len(test_docs)} document(s)")
            except Exception as e:
                logging.error(f"Retriever test failed: {e}")
                logging.error(traceback.format_exc())
                
                # Initialize dummy retriever as fallback
                logging.info("Initializing dummy retriever after test failure")
                class DummyRetriever:
                    def get_relevant_documents(self, query):
                        logging.warning(f"Using dummy retriever for query: {query}")
                        from langchain.schema import Document
                        return [Document(page_content="Không có thông tin.", metadata={})]
                vector_retriever = DummyRetriever()
                logging.info("Dummy retriever initialized successfully")
        else:
            # Initialize dummy retriever if vector database failed
            logging.info("Initializing dummy retriever as vector database failed")
            class DummyRetriever:
                def get_relevant_documents(self, query):
                    logging.warning(f"Using dummy retriever for query: {query}")
                    from langchain.schema import Document
                    return [Document(page_content="Không có thông tin.", metadata={})]
            vector_retriever = DummyRetriever()
            logging.info("Dummy retriever initialized successfully")
        # Khởi tạo model API dựa vào cấu hình
        if API_TYPE == "google":
            logging.info("Initializing Gemini model...")
            try:
                gemini_model = initialize_gemini()
                if gemini_model:
                    logging.info("Gemini model initialized successfully")
                else:
                    logging.warning("Gemini model initialization returned None, continuing without model")
                    gemini_model = None
            except Exception as e:
                logging.error(f"Error during startup: {e}")
                logging.error(traceback.format_exc())
                logging.warning("Continuing without Gemini model")
                gemini_model = None
        elif API_TYPE == "openai":
            logging.info("Initializing OpenAI model...")
            try:
                openai_client = initialize_openai()
                if openai_client:
                    logging.info("OpenAI model initialized successfully")
                else:
                    logging.warning("OpenAI model initialization returned None, continuing without model")
            except Exception as e:
                logging.error(f"Error during startup: {e}")
                logging.error(traceback.format_exc())
                logging.warning("Continuing without OpenAI model")
                openai_client = None
        else:
            logging.error(f"Unknown API_TYPE: {API_TYPE}, supported types are 'google' and 'openai'")
            
    except Exception as e:
        logging.error(f"Error during startup: {e}")
        logging.error(traceback.format_exc())
        # Set up fallbacks to ensure API still works
        vector_retriever = None
        gemini_model = None
        openai_client = None

def integrate_document_vectors(document_id: int) -> bool:
    """
    Tích hợp vector của tài liệu vào vector database chính
    """
    try:
        logger.info(f"Integrating document vectors for document ID: {document_id}")
        
        # Đường dẫn tới vector database của tài liệu
        document_vectors_path = os.path.join(VECTOR_DB_PATH, f"doc_{document_id}")
        
        # Kiểm tra có tồn tại không
        if not os.path.exists(document_vectors_path) or not os.path.exists(os.path.join(document_vectors_path, "index.faiss")):
            logger.error(f"Document vector path not found: {document_vectors_path}")
            return False
        
        # Tải vector database chính
        embedding = HuggingFaceEmbeddings(model_name=EMBEDDING_MODEL)
        
        # Nếu vector database chính không tồn tại, tạo mới
        main_vector_path = VECTOR_DB_PATH
        
        if not os.path.exists(os.path.join(main_vector_path, "index.faiss")):
            logger.info("Main vector database not found, creating empty one")
            empty_texts = ["Đây là vector database ban đầu."]
            main_db = FAISS.from_texts(empty_texts, embedding)
            main_db.save_local(main_vector_path)
        
        # Tải vector database chính và vector database của tài liệu
        try:
            main_db = FAISS.load_local(main_vector_path, embedding, allow_dangerous_deserialization=True)
        except TypeError:
            main_db = FAISS.load_local(main_vector_path, embedding)
            
        try:
            doc_db = FAISS.load_local(document_vectors_path, embedding, allow_dangerous_deserialization=True)
        except TypeError:
            doc_db = FAISS.load_local(document_vectors_path, embedding)
        
        # Kết hợp hai vector database
        logger.info("Merging document vectors into main vector database")
        main_db.merge_from(doc_db)
        
        # Lưu lại vector database chính
        logger.info("Saving merged vector database")
        main_db.save_local(main_vector_path)
        
        # Kiểm tra kết quả
        if os.path.exists(os.path.join(main_vector_path, "index.faiss")):
            logger.info(f"Document vectors integrated successfully for document {document_id}")
            return True
        else:
            logger.error(f"Failed to integrate document vectors for document {document_id}")
            return False
            
    except Exception as e:
        logger.error(f"Error integrating document vectors: {e}")
        logger.error(traceback.format_exc())
        return False

@app.post("/api/v1/documents/integrate")
async def integrate_document(document_id: int):
    """
    Tích hợp vector của tài liệu vào vector database chính
    """
    try:
        logger.info(f"Integrating document ID: {document_id}")
        
        # Tích hợp vectors
        success = integrate_document_vectors(document_id)
        
        if success:
            # Khởi động lại vector retriever
            global vector_retriever
            vector_retriever = initialize_vector_db()
            vector_retriever = vector_retriever.as_retriever(
                search_type="mmr",  # Sử dụng MMR thay vì đơn thuần similarity
                search_kwargs={
                    "k": 2,  # Số lượng kết quả cuối cùng
                    "fetch_k": 5,  # Lấy 15 đoạn văn có liên quan nhất trước khi chọn k
                    "lambda_mult": 0.8,  # 0.7 = 70% trọng số cho tính liên quan, 30% cho tính đa dạng
                    "score_threshold": 0.5  # Chỉ lấy các kết quả có độ tương tự > 0.5
                }
            )
            
            return {
                "success": True,
                "message": f"Đã tích hợp vector của tài liệu {document_id} thành công",
                "document_id": document_id
            }
        else:
            return {
                "success": False,
                "message": f"Không thể tích hợp vector của tài liệu {document_id}",
                "document_id": document_id,
                "error": "Integration failed"
            }
    except Exception as e:
        logger.error(f"Error in integrate_document endpoint: {e}")
        logger.error(traceback.format_exc())
        return {
            "success": False,
            "message": "Lỗi khi tích hợp vector",
            "document_id": document_id,
            "error": str(e)
        }

# Chạy ứng dụng
if __name__ == "__main__":
    port = int(os.getenv("API_PORT", 8080))
    host = os.getenv("API_HOST", "0.0.0.0")
    
    print(f"\n==== STARTING CHATBOT API SERVER ====")
    print(f"Host: {host}")
    print(f"Port: {port}")
    print(f"API documentation: http://localhost:{port}/docs")
    print(f"Health check: http://localhost:{port}/api/v1/chat/health")
    print(f"Chat endpoint: http://localhost:{port}/api/v1/chat/chat-direct")
    print(f"==== SERVER READY ====\n")
    
    uvicorn.run(
        app,
        host=host,
        port=port,
        log_level="info"
    ) 