from langchain_community.document_loaders import DirectoryLoader
from langchain.text_splitter import CharacterTextSplitter
from langchain_community.embeddings import HuggingFaceEmbeddings
from langchain_community.vectorstores import FAISS
import nltk

# Tải các tài nguyên NLTK cần thiết
print("Đang tải các tài nguyên NLTK...")
nltk.download('punkt')
nltk.download('averaged_perceptron_tagger')
nltk.download('maxent_ne_chunker')
nltk.download('words')
print("Đã tải xong các tài nguyên NLTK!")

# Cấu hình
DATA_PATH = "data"
DB_FAISS_PATH = "vector_db"

# 1. Load documents
print("Đang tải documents...")
loader = DirectoryLoader(DATA_PATH, glob="*.txt")
documents = loader.load()
print(f"Đã tải được {len(documents)} documents")

# 2. Chunk documents
print("Đang chia nhỏ documents...")
text_splitter = CharacterTextSplitter(chunk_size=1000, chunk_overlap=100)
texts = text_splitter.split_documents(documents)
print(f"Đã chia thành {len(texts)} chunks")

# 3. Tạo embeddings và vector store
print("Đang tạo embeddings và vector store...")
embeddings = HuggingFaceEmbeddings(model_name="sentence-transformers/all-MiniLM-L6-v2")
db = FAISS.from_documents(texts, embeddings)

# 4. Lưu vector store
print(f"Đang lưu vector store vào {DB_FAISS_PATH}...")
db.save_local(DB_FAISS_PATH)
print("Đã tạo và lưu vector store thành công!")