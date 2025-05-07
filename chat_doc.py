import os
import time
import sys
from langchain_community.embeddings import HuggingFaceEmbeddings
from langchain_community.vectorstores import FAISS
import torch

# Hàm kiểm tra một tài liệu cụ thể
def test_document_vector(doc_id, query):
    print(f"\n{'='*50}")
    print(f"KIỂM TRA VECTOR TÀI LIỆU ID: {doc_id}")
    print(f"TRUY VẤN: {query}")
    print(f"{'='*50}\n")
    
    # Đường dẫn tới thư mục chứa vector
    vector_db_path = os.getenv("DB_FAISS_PATH", "vector_db")
    document_path = os.path.join(vector_db_path, f"doc_{doc_id}")
    
    print(f"Thư mục vector: {document_path}")
    
    # Kiểm tra thư mục có tồn tại không
    if not os.path.exists(document_path):
        print(f"LỖI: Không tìm thấy thư mục vector cho tài liệu ID {doc_id}")
        print(f"Các thư mục vector hiện có: {os.listdir(vector_db_path)}")
        return False
    
    # Kiểm tra các file vector
    index_path = os.path.join(document_path, "index.faiss")
    pkl_path = os.path.join(document_path, "index.pkl")
    
    if not os.path.exists(index_path) or not os.path.exists(pkl_path):
        print(f"LỖI: Thiếu file vector trong thư mục tài liệu ID {doc_id}")
        print(f"Các file trong thư mục: {os.listdir(document_path)}")
        return False
    
    # Thông tin về kích thước file
    index_size = os.path.getsize(index_path) / (1024*1024)  # MB
    pkl_size = os.path.getsize(pkl_path) / (1024*1024)      # MB
    print(f"Kích thước file index.faiss: {index_size:.2f} MB")
    print(f"Kích thước file index.pkl: {pkl_size:.2f} MB")
    
    try:
        # Tạo embedding
        print("\nĐang tạo embedding model...")
        embedding = HuggingFaceEmbeddings(
            model_name="sentence-transformers/all-MiniLM-L6-v2",
            model_kwargs={"device": "cuda" if torch.cuda.is_available() else "cpu"}
        )
        
        # Tải vector database
        print("Đang tải vector database...")
        doc_db = FAISS.load_local(document_path, embedding, allow_dangerous_deserialization=True)
        
        # Thử nhiều loại tìm kiếm khác nhau
        print("\n--- TÌM KIẾM VỚI SIMILARITY SEARCH ---")
        start_time = time.time()
        results_similarity = doc_db.similarity_search(query, k=5)
        similarity_time = time.time() - start_time
        print(f"Thời gian tìm kiếm: {similarity_time:.2f} giây")
        print(f"Số kết quả tìm thấy: {len(results_similarity)}")
        
        # In kết quả similarity search
        for i, doc in enumerate(results_similarity):
            print(f"\nKết quả #{i+1}:")
            print(f"Độ dài nội dung: {len(doc.page_content)} ký tự")
            print(f"Metadata: {doc.metadata}")
            preview = doc.page_content[:200] + "..." if len(doc.page_content) > 200 else doc.page_content
            print(f"Nội dung: {preview}")
        
        # Tìm kiếm với MMR
        print("\n\n--- TÌM KIẾM VỚI MMR SEARCH ---")
        start_time = time.time()
        retriever = doc_db.as_retriever(
            search_type="mmr",
            search_kwargs={
                "k": 5,
                "fetch_k": 20,
                "lambda_mult": 0.7,
                "score_threshold": 0.3
            }
        )
        results_mmr = retriever.get_relevant_documents(query)
        mmr_time = time.time() - start_time
        print(f"Thời gian tìm kiếm: {mmr_time:.2f} giây")
        print(f"Số kết quả tìm thấy: {len(results_mmr)}")
        
        # In kết quả MMR search
        for i, doc in enumerate(results_mmr):
            print(f"\nKết quả #{i+1}:")
            print(f"Độ dài nội dung: {len(doc.page_content)} ký tự")
            print(f"Metadata: {doc.metadata}")
            preview = doc.page_content[:200] + "..." if len(doc.page_content) > 200 else doc.page_content
            print(f"Nội dung: {preview}")
        
        return True
        
    except Exception as e:
        print(f"LỖI: {str(e)}")
        import traceback
        print(traceback.format_exc())
        return False

# Hàm kiểm tra tất cả vector database
def check_all_vectors():
    vector_db_path = os.getenv("DB_FAISS_PATH", "vector_db")
    
    print(f"\n{'='*50}")
    print(f"KIỂM TRA TẤT CẢ VECTOR DATABASE")
    print(f"{'='*50}\n")
    
    if not os.path.exists(vector_db_path):
        print(f"LỖI: Không tìm thấy thư mục vector database: {vector_db_path}")
        return
    
    # Kiểm tra vector database chính
    main_index = os.path.join(vector_db_path, "index.faiss")
    main_pkl = os.path.join(vector_db_path, "index.pkl")
    
    print("VECTOR DATABASE CHÍNH:")
    if os.path.exists(main_index) and os.path.exists(main_pkl):
        print(f"✅ Vector database chính tồn tại")
        print(f"   - Kích thước index.faiss: {os.path.getsize(main_index)/(1024*1024):.2f} MB")
        print(f"   - Kích thước index.pkl: {os.path.getsize(main_pkl)/(1024*1024):.2f} MB")
    else:
        print(f"❌ Vector database chính không đầy đủ hoặc không tồn tại")
    
    # Kiểm tra các vector database tài liệu
    print("\nVECTOR DATABASE TÀI LIỆU:")
    doc_dirs = []
    
    for item in os.listdir(vector_db_path):
        if item.startswith("doc_") and os.path.isdir(os.path.join(vector_db_path, item)):
            doc_id = item.replace("doc_", "")
            doc_path = os.path.join(vector_db_path, item)
            doc_index = os.path.join(doc_path, "index.faiss")
            doc_pkl = os.path.join(doc_path, "index.pkl")
            
            if os.path.exists(doc_index) and os.path.exists(doc_pkl):
                status = "✅"
                size_index = os.path.getsize(doc_index)/(1024*1024)
                size_pkl = os.path.getsize(doc_pkl)/(1024*1024)
                print(f"{status} Tài liệu ID {doc_id}: OK (index: {size_index:.2f} MB, pkl: {size_pkl:.2f} MB)")
            else:
                status = "❌"
                print(f"{status} Tài liệu ID {doc_id}: KHÔNG ĐẦY ĐỦ")
            
            doc_dirs.append((doc_id, status))
    
    print(f"\nTổng số tài liệu: {len(doc_dirs)}")
    return doc_dirs

if __name__ == "__main__":
    # Mặc định kiểm tra tài liệu ID 22
    doc_id = 22
    query = "Thông tin về kinh tế Việt Nam"
    
    # Nếu có tham số dòng lệnh
    if len(sys.argv) > 1:
        try:
            doc_id = int(sys.argv[1])
        except ValueError:
            print(f"ID tài liệu không hợp lệ: {sys.argv[1]}, sử dụng giá trị mặc định: {doc_id}")
    
    if len(sys.argv) > 2:
        query = sys.argv[2]
    
    # Kiểm tra tất cả vector trước
    check_all_vectors()
    
    # Kiểm tra vector của tài liệu cụ thể
    test_document_vector(doc_id, query)
    
    # In hướng dẫn sử dụng
    print("\n\nHƯỚNG DẪN SỬ DỤNG:")
    print("python chat_doc.py [doc_id] [query]")
    print("Ví dụ: python chat_doc.py 22 \"Kinh tế Việt Nam\"") 