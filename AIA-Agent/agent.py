"""
agent.py
Production-ready local agent to expose auto_capture.py over HTTP.

- Exposes:
    GET  /health       → health check
    GET  /run-local    → runs auto_capture.main()
    POST /run-local    → same as GET, can be used from Laravel / Postman

- Security:
    Uses X-API-KEY header. If API_KEY is None or empty, auth is disabled.

- Logging:
    Logs to console and to agent.log in the same directory.
"""

from flask import Flask, request, jsonify
import logging
import os
import time
import platform
import subprocess
import traceback
from io import StringIO
from contextlib import redirect_stdout, redirect_stderr

# IMAGES_BASE_DIR = r"C:\Users\shing\Downloads"   # YOUR FOLDER PATH

# -----------------------------
# CONFIGURATION
# -----------------------------

# ✅ Set your API key here (use the same one in Laravel / other clients)
API_KEY = "2Yx6pqydyFpmf8K1RU4N1oOgYyAhdCJE"   # e.g. "cbp_92hd8hshs82hdhs8273"

# Flask app host/port (Cloudflare Tunnel will point to this)
HOST = "0.0.0.0"
PORT = 5000

# Log file name
LOG_FILE = "agent.log"

# -----------------------------
# LOGGING SETUP
# -----------------------------

os.makedirs(os.path.dirname(os.path.abspath(LOG_FILE)), exist_ok=True)

logger = logging.getLogger("agent")
logger.setLevel(logging.INFO)

# Log format
formatter = logging.Formatter(
    "[%(asctime)s] [%(levelname)s] %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S"
)

# Console handler
ch = logging.StreamHandler()
ch.setFormatter(formatter)
logger.addHandler(ch)

# File handler
fh = logging.FileHandler(LOG_FILE, encoding="utf-8")
fh.setFormatter(formatter)
logger.addHandler(fh)

# -----------------------------
# IMPORT YOUR SCRIPT
# -----------------------------

try:
    import auto_capture  # auto_capture.py must be in the same folder
except ImportError as e:
    logger.error("Failed to import auto_capture.py: %s", e)
    auto_capture = None

# -----------------------------
# FLASK APP
# -----------------------------

app = Flask(__name__)


def check_api_key() -> bool:
    """
    Check API key from headers.
    Returns True if authorized.
    If API_KEY is empty/None, auth is disabled.
    """
    if not API_KEY:
        # Auth disabled
        return True

    key = request.headers.get("X-API-KEY")
    if key != API_KEY:
        logger.warning("Unauthorized access attempt from %s", request.remote_addr)
        return False

    return True


@app.before_request
def before_request():
    """
    Global auth check. Runs before each request.
    """
    if request.path == "/health":
        # Health endpoint is open (or you can protect it too if you want)
        return None

    if not check_api_key():
        return jsonify({
            "status": "error",
            "message": "Unauthorized"
        }), 401

# @app.route("/open-folder", methods=["GET"])
# def open_folder():
#     folder = IMAGES_BASE_DIR

#     try:
#         if platform.system() == "Windows":
#             os.startfile(folder)   # opens File Explorer
#         elif platform.system() == "Darwin":  # macOS
#             subprocess.Popen(["open", folder])
#         else:
#             subprocess.Popen(["xdg-open", folder])  # Linux support

#         return jsonify({
#             "status": "success",
#             "message": f"Opened folder: {folder}"
#         })
#     except Exception as e:
#         return jsonify({
#             "status": "error",
#             "message": str(e)
#         }), 500

@app.route("/health", methods=["GET"])
def health():
    """
    Simple health check endpoint.
    Useful for Cloudflare / uptime checks.
    """
    return jsonify({
        "status": "ok",
        "message": "agent is running",
        "has_auto_capture": bool(auto_capture)
    })


def run_auto_capture():
    """
    Runs auto_capture.main(), capturing stdout/stderr.

    - If success → returns (True, output, folder_path)
    - If structured error from auto_capture → returns (False, error_obj, None)
    - Auto-opens folder ONLY on success
    """
    if auto_capture is None:
        return False, {
            "error": True,
            "error_code": "IMPORT_FAILED",
            "message": "auto_capture module missing",
            "details": "auto_capture.py not found or import failed"
        }, None

    logger.info("Starting auto_capture.main()")

    stdout_buffer = StringIO()
    stderr_buffer = StringIO()

    folder_path = None
    error_obj = None

    start_time = time.time()

    try:
        with redirect_stdout(stdout_buffer), redirect_stderr(stderr_buffer):
            result = auto_capture.main()  # result = folder OR structured error dict

        # ---------------------------
        # CASE 1: Structured error returned
        # ---------------------------
        if isinstance(result, dict) and result.get("error"):
            logger.error("auto_capture failed: %s", result)
            return False, result, None

        # ---------------------------
        # CASE 2: Success →
        # result contains folder path
        # ---------------------------
        folder_path = result
        success = True
        logger.info("auto_capture.main() completed successfully")

        # AUTO-OPEN ONLY ON SUCCESS
        if folder_path:
            try:
                if platform.system() == "Windows":
                    os.startfile(folder_path)
                elif platform.system() == "Darwin":
                    subprocess.Popen(["open", folder_path])
                else:
                    subprocess.Popen(["xdg-open", folder_path])
            except Exception as e:
                logger.error("Failed to open folder: %s", e)

    except Exception as e:
        # UNEXPECTED exception (not structured)
        success = False
        error_obj = {
            "error": True,
            "error_code": "AGENT_EXCEPTION",
            "message": "Agent failed unexpectedly.",
            "details": str(e)
        }
        logger.error("Unexpected error: %s", e)
        logger.debug(traceback.format_exc())

    duration = time.time() - start_time

    # Collect stdout/stderr logs
    output_logs = stdout_buffer.getvalue() + stderr_buffer.getvalue()
    if output_logs.strip():
        output_logs += f"\n[Finished in {duration:.2f} seconds]"

    # SUCCESS
    if success:
        return True, output_logs, folder_path

    # FAILURE (structured or fallback)
    if error_obj is None:
        error_obj = {
            "error": True,
            "error_code": "UNKNOWN_FAILURE",
            "message": "Unknown failure occurred.",
            "details": output_logs
        }

    # Attach logs to error object for debugging
    error_obj["logs"] = output_logs

    return False, error_obj, None


@app.route("/run-local", methods=["GET", "POST"])
def run_local():
    logger.info("Received /run-local request from %s", request.remote_addr)

    success, output, folder_path = run_auto_capture()

    # SUCCESS RESPONSE
    if success:
        return jsonify({
            "status": "success",
            "folder": folder_path,
            "output": output
        }), 200

    # ERROR RESPONSE (Structured)
    return jsonify({
        "status": "error",
        "error_code": output.get("error_code"),
        "message": output.get("message"),
        "details": output.get("details"),
        "logs": output.get("logs")  # Optional debugging logs
    }), 500



if __name__ == "__main__":
    logger.info("Starting agent on %s:%s", HOST, PORT)
    if API_KEY:
        logger.info("API key protection ENABLED")
    else:
        logger.warning("API key protection DISABLED (set API_KEY in agent.py!)")

    app.run(host=HOST, port=PORT)
