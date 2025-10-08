import os
import sys
import time
import shutil
from langchain_community.embeddings import HuggingFaceEmbeddings
from langchain_community.vectorstores import FAISS
from langchain.text_splitter import RecursiveCharacterTextSplitter
from langchain_community.document_loaders import TextLoader, PyPDFLoader, Docx2txtLoader
import torch

def create_vector_from_file(file_path, doc_id, chunk_size=1000, chunk_overlap=150):
    """
    Tạo vector database từ một file
    
    Args:
        file_path: Đường dẫn đến file
        doc_id: ID của tài liệu
        chunk_size: Kích thước mỗi chunk
        chunk_overlap: Độ chồng lấp giữa các chunk
    """
    print(f"\n{'='*50}")
    print(f"TẠO VECTOR CHO TÀI LIỆU ID: {doc_id}")
    print(f"FILE: {file_path}")
    print(f"{'='*50}\n")
    
    start_time = time.time()
    
    # Kiểm tra file có tồn tại không
    if not os.path.exists(file_path):
        print(f"LỖI: File {file_path} không tồn tại!")
        return False
    
    # Lấy định dạng file
    _, file_ext = os.path.splitext(file_path)
    file_ext = file_ext.lower()
    
    # Chọn loader phù hợp
    loader = None
    if file_ext == '.pdf':
        print("Sử dụng PyPDFLoader...")
        loader = PyPDFLoader(file_path)
    elif file_ext in ['.docx', '.doc']:
        print("Sử dụng Docx2txtLoader...")
        loader = Docx2txtLoader(file_path)
    elif file_ext in ['.txt', '.md']:
        print("Sử dụng TextLoader...")
        loader = TextLoader(file_path)
    else:
        print(f"LỖI: Định dạng file {file_ext} không được hỗ trợ!")
        return False
    
    try:
        # Đọc tài liệu
        print(f"Đang đọc file {file_path}...")
        docs = loader.load()
        print(f"Đọc thành công, số trang/đoạn: {len(docs)}")
        
        # Thêm metadata vào tài liệu
        for doc in docs:
            doc.metadata['document_id'] = doc_id
            doc.metadata['source'] = file_path
        
        # Chia nhỏ tài liệu
        print(f"Đang chia nhỏ tài liệu (chunk_size={chunk_size}, chunk_overlap={chunk_overlap})...")
        text_splitter = RecursiveCharacterTextSplitter(
            chunk_size=chunk_size,
            chunk_overlap=chunk_overlap,
            length_function=len,
            separators=["\n\n", "\n", ". ", " ", ""]
        )
        chunks = text_splitter.split_documents(docs)
        print(f"Đã chia thành {len(chunks)} đoạn.")
        
        # Hiển thị ví dụ 2 đoạn đầu tiên
        if chunks:
            print(f"\nMẫu đoạn đầu tiên: {chunks[0].page_content[:150]}...")
            if len(chunks) > 1:
                print(f"Mẫu đoạn thứ hai: {chunks[1].page_content[:150]}...")
        
        # Tạo embedding model
        print("\nĐang tạo embedding model...")
        embedding = HuggingFaceEmbeddings(
            model_name="sentence-transformers/all-MiniLM-L6-v2", 
            model_kwargs={"device": "cuda" if torch.cuda.is_available() else "cpu"}
        )
        
        # Tạo thư mục lưu trữ vector
        vector_db_path = os.getenv("DB_FAISS_PATH", "vector_db")
        if not os.path.exists(vector_db_path):
            os.makedirs(vector_db_path, exist_ok=True)
            print(f"Đã tạo thư mục {vector_db_path}")
        
        # Thư mục lưu trữ vector cho tài liệu này
        document_vectors_path = os.path.join(vector_db_path, f"doc_{doc_id}")
        
        # Xóa thư mục cũ nếu tồn tại
        if os.path.exists(document_vectors_path):
            print(f"Thư mục {document_vectors_path} đã tồn tại, đang xóa...")
            shutil.rmtree(document_vectors_path)
        
        # Tạo thư mục mới
        os.makedirs(document_vectors_path, exist_ok=True)
        print(f"Đã tạo thư mục {document_vectors_path}")
        
        # Tạo và lưu vector database
        print("\nĐang tạo vector database...")
        db = FAISS.from_documents(chunks, embedding)
        print(f"Đang lưu vector database vào {document_vectors_path}...")
        db.save_local(document_vectors_path)
        
        # Kiểm tra thành công
        if os.path.exists(os.path.join(document_vectors_path, "index.faiss")) and os.path.exists(os.path.join(document_vectors_path, "index.pkl")):
            elapsed_time = time.time() - start_time
            print(f"\n Đã tạo vector thành công cho tài liệu ID {doc_id} trong {elapsed_time:.2f} giây!")
            print(f"   - Số đoạn: {len(chunks)}")
            print(f"   - Thư mục: {document_vectors_path}")
            
            # Kiểm tra kích thước file
            index_size = os.path.getsize(os.path.join(document_vectors_path, "index.faiss")) / (1024*1024)
            pkl_size = os.path.getsize(os.path.join(document_vectors_path, "index.pkl")) / (1024*1024)
            print(f"   - Kích thước index.faiss: {index_size:.2f} MB")
            print(f"   - Kích thước index.pkl: {pkl_size:.2f} MB")
            
            # Thử tìm kiếm đơn giản
            print("\nĐang thử tìm kiếm...")
            test_query = "kinh tế"
            results = db.similarity_search(test_query, k=1)
            print(f"Tìm kiếm '{test_query}' thành công, tìm thấy {len(results)} kết quả.")
            
            return True
        else:
            print(f"\n LỖI: Không tạo được vector database!")
            return False
            
    except Exception as e:
        import traceback
        print(f"\n LỖI: {str(e)}")
        print(traceback.format_exc())
        return False

if __name__ == "__main__":
    # Mặc định
    file_path = None
    doc_id = 22
    chunk_size = 1000
    chunk_overlap = 200



    
    # Đọc tham số dòng lệnh
    if len(sys.argv) < 3:
        print("Thiếu tham số! Sử dụng: python create_doc_vector.py [đường_dẫn_file] [id_tài_liệu] [chunk_size] [chunk_overlap]")
        print("Ví dụ: python create_doc_vector.py data/document.pdf 22 1000 200")
        sys.exit(1)
    
    file_path = sys.argv[1]
    doc_id = int(sys.argv[2])
    
    if len(sys.argv) > 3:
        chunk_size = int(sys.argv[3])
    
    if len(sys.argv) > 4:
        chunk_overlap = int(sys.argv[4])
    
    # Tạo vector
    create_vector_from_file(file_path, doc_id, chunk_size, chunk_overlap) 