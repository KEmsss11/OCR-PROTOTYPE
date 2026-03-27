# PaddleOCR Integration Plan

The user wants to switch from Tesseract to **PaddleOCR** for better document extraction accuracy. PaddleOCR is a highly accurate library but requires **Python** to run.

## Proposed Changes

### 1. Prerequisites (User Action Required)
- Install **Python 3.10** from [python.org](https://www.python.org/downloads/).
- Ensure "Add Python to PATH" is checked during installation.

### 2. Dependencies (Automated)
- Install `paddlepaddle` and `paddleocr` via pip:
  ```powershell
  pip install paddlepaddle paddleocr
  ```

### 3. Backend (PHP/Python Bridge)
- **[NEW] `ocr_paddle.py`**: A Python script that:
  - Takes an image path as an argument.
  - Runs PaddleOCR on the image.
  - Prints the extracted text to stdout.
- **[MODIFY] `ocr.php`**:
  - Update `runTesseract` (or create `runPaddle`) to call the Python script instead of Tesseract.
- **[MODIFY] `config.php`**:
  - Add `PYTHON_BIN` and `PADDLE_SCRIPT` paths.

### 4. Verification
- Upload a PDF and confirm that Page 1 metadata is extracted correctly using the new PaddleOCR engine.

---

## Technical Details

- **Input**: PNG images (converted from PDF via Ghostscript).
- **Execution**: `shell_exec("python ocr_paddle.py image.png")`.
- **Performance**: PaddleOCR is slower than Tesseract on first run (loading models) but much more accurate for forms.

---

> [!IMPORTANT]
> **Please install Python 3.10** and then let me know so I can proceed with the integration.
