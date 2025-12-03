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
import traceback
from io import StringIO
from contextlib import redirect_stdout, redirect_stderr

# -----------------------------
# CONFIGURATION
# -----------------------------

# ✅ Set your API key here (use the same one in Laravel / other clients)
API_KEY = "2Yx6pqydyFpmf8K1RU4N1oOgYyAhdCJE"   # e.g. "cbp_92hd8hshs82hdhs8273"

# Flask app host/port (Cloudflare Tunnel will point to this)
HOST = "0.0.0.0"
PORT = 5005

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
    Runs auto_capture.main(), capturing all stdout/stderr.
    Returns (success: bool, output: str).
    """
    if auto_capture is None:
        return False, "auto_capture module not available (import failed)"

    logger.info("Starting auto_capture.main()")

    stdout_buffer = StringIO()
    stderr_buffer = StringIO()

    start_time = time.time()

    try:
        # Capture print() output from your script
        with redirect_stdout(stdout_buffer), redirect_stderr(stderr_buffer):
            # Call your script's main() function
            auto_capture.main()

        success = True
        logger.info("auto_capture.main() completed successfully")

    except Exception as e:
        success = False
        logger.error("Error while running auto_capture.main(): %s", e)
        logger.debug("Traceback:\n%s", traceback.format_exc())

    duration = time.time() - start_time

    # Merge stdout + stderr
    combined_output = ""
    out_text = stdout_buffer.getvalue()
    err_text = stderr_buffer.getvalue()

    if out_text:
        combined_output += "--- STDOUT ---\n" + out_text
    if err_text:
        combined_output += "\n--- STDERR ---\n" + err_text

    combined_output += f"\n\n[Finished in {duration:.2f} seconds]"

    return success, combined_output


@app.route("/run-local", methods=["GET", "POST"])
def run_local():
    """
    HTTP endpoint to trigger the AIA auto capture flow.

    - GET  /run-local
    - POST /run-local

    No body is required. Just hit it with the correct X-API-KEY.
    """
    logger.info("Received /run-local request from %s", request.remote_addr)

    success, output = run_auto_capture()

    if success:
        return jsonify({
            "status": "success",
            "output": output
        })
    else:
        return jsonify({
            "status": "error",
            "output": output
        }), 500


if __name__ == "__main__":
    logger.info("Starting agent on %s:%s", HOST, PORT)
    if API_KEY:
        logger.info("API key protection ENABLED")
    else:
        logger.warning("API key protection DISABLED (set API_KEY in agent.py!)")

    app.run(host=HOST, port=PORT)
