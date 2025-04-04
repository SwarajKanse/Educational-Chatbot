from flask import Flask, request, jsonify
from transformers import pipeline
import logging
from functools import lru_cache
import time
import requests
import os
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

# Configure logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

app = Flask(__name__)

# Global variables for model and request tracking
emotion_pipeline = None
request_times = []
REQUEST_WINDOW = 60  # 1 minute window for rate limiting
MAX_REQUESTS = 100   # Max requests per minute
LANGCHAIN_AGENT_URL = os.getenv("LANGCHAIN_AGENT_URL", "http://localhost:5000/query")

def load_model():
    """Lazy loading of the model"""
    global emotion_pipeline
    if emotion_pipeline is None:
        logger.info("Loading emotion detection model...")
        try:
            # Load the emotion detection model with optimized settings
            emotion_pipeline = pipeline(
                "text-classification", 
                model="bhadresh-savani/bert-base-uncased-emotion",
                device=0 if hasattr(pipeline, "device") and pipeline.device.type == "cuda" else -1
            )
            logger.info("Model loaded successfully")
        except Exception as e:
            logger.error(f"Error loading model: {str(e)}")
            raise e
    return emotion_pipeline

@lru_cache(maxsize=100)
def detect_emotion_cached(text):
    """Cached emotion detection to avoid reprocessing identical texts"""
    pipeline = load_model()
    result = pipeline(text)
    return result

def check_rate_limit():
    """Basic rate limiting implementation"""
    current_time = time.time()
    # Remove requests older than the window
    global request_times
    request_times = [t for t in request_times if current_time - t < REQUEST_WINDOW]
    
    # Check if we've hit the limit
    if len(request_times) >= MAX_REQUESTS:
        return False
    
    # Add current request
    request_times.append(current_time)
    return True

def forward_to_langchain(message, emotion, session_id=None):
    """Forward processed emotion information to the LangChain agent"""
    try:
        # Format message to include emotion information
        enhanced_message = f"[User emotion: {emotion['label']} (confidence: {emotion['score']:.2f})] {message}"
        
        payload = {
            "message": enhanced_message,
            "session_id": session_id
        }
        
        # Send request to LangChain agent
        response = requests.post(
            LANGCHAIN_AGENT_URL,
            json=payload,
            timeout=10
        )
        
        if response.status_code == 200:
            return response.json()
        else:
            logger.error(f"Error from LangChain agent: {response.text}")
            return {"error": "Failed to get response from LangChain agent"}
    
    except Exception as e:
        logger.error(f"Error forwarding to LangChain: {str(e)}")
        return {"error": f"Communication error: {str(e)}"}

@app.route("/detect_emotion", methods=["POST"])
def detect_emotion():
    # Check rate limit
    if not check_rate_limit():
        return jsonify({"error": "Rate limit exceeded. Try again later."}), 429
    
    # Process request
    try:
        data = request.json
        text = data.get("text", "")
        session_id = data.get("session_id")
        forward_to_agent = data.get("forward_to_agent", True)
        
        if not text:
            return jsonify({"error": "No text provided"}), 400
        
        # Log request (truncate long text
        log_text = text[:50] + "..." if len(text) > 50 else text
        logger.info(f"Processing text: {log_text}")
        
        # Process text with caching
        start_time = time.time()
        result = detect_emotion_cached(text)
        processing_time = time.time() - start_time
        
        logger.info(f"Processing completed in {processing_time:.2f} seconds")
        return jsonify({
            "emotion": result,
            "processing_time": processing_time
        })
    
    except Exception as e:
        logger.error(f"Error processing request: {str(e)}")
        return jsonify({"error": str(e)}), 500

# Preload the model if environment variable is set
if __name__ == "__main__":
    # Load model at startup (optional)
    try:
        load_model()
    except Exception:
        logger.warning("Model preloading failed, will retry on first request")
    
    # Run the Flask app
    app.run(port=5001, host='0.0.0.0', threaded=True)