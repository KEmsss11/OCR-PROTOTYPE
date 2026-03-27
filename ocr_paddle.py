import sys
import logging
import os
import json

# Disable PaddlePaddle's internal oneDNN to avoid incompatibility issues on Windows
os.environ['PADDLE_ONEDNN_ENABLE'] = '0'
os.environ['FLAGS_use_onednn'] = '0'

from paddleocr import PaddleOCR

# Disable logging to keep stdout clean for PHP
logging.disable(logging.CRITICAL)

def main():
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No image path provided."}))
        sys.exit(1)

    image_path = sys.argv[1]
    
    try:
        # Initialize PaddleOCR (En version) for version 2.x
        ocr = PaddleOCR(use_angle_cls=True, lang='en', show_log=False)
        
        # Run OCR
        result = ocr.ocr(image_path, cls=True)
        
        if not result or not result[0]:
            print(json.dumps([]))
            return

        # Prepare JSON results
        # result is a list of [res] where res is [[box, [text, score]], ...]
        final_results = []
        for line in result[0]:
            # Each line[0] is a list of 4 points: [[x1, y1], [x2, y2], [x3, y3], [x4, y4]]
            # Each line[1] is [text, score]
            final_results.append({
                "box": line[0],
                "text": line[1][0],
                "confidence": float(line[1][1])
            })
            
        print(json.dumps(final_results))
        
    except Exception as e:
        print(json.dumps({"error": str(e)}))
        sys.exit(1)

if __name__ == "__main__":
    main()
