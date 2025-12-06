"""
-------------------------------------------
This file merges agent.py + auto_capture.py into one deployable script.

- Exposes:
    GET  /health       → health check
    GET  /auto-capture-process
    POST /auto-capture-process

- Security:
    Uses X-API-KEY header. If API_KEY is None or empty, auth is disabled.

- Logging:
    Logs to console and to agent.log in the same directory."""

from flask import Flask, request, jsonify
import logging
import os
import time
import platform
import subprocess
import traceback
from io import StringIO
from contextlib import redirect_stdout, redirect_stderr
import subprocess as sp
import datetime
import pathlib

# ---------------------------------------------------------
# CONFIGURATION
# ---------------------------------------------------------

# Set your API key here (use the same one in Laravel / other clients)
API_KEY = "2Yx6pqydyFpmf8K1RU4N1oOgYyAhdCJE"

# Flask app host/port (Cloudflare Tunnel will point to this)
HOST = "0.0.0.0"
PORT = 5000

# Log file name
LOG_FILE = "auto_capture_agent.log"

# -----------------------------
# LOGGING SETUP
# -----------------------------

os.makedirs(os.path.dirname(os.path.abspath(LOG_FILE)), exist_ok=True)

logger = logging.getLogger("auto_capture_agent")
logger.setLevel(logging.INFO)

formatter = logging.Formatter(
    "[%(asctime)s] [%(levelname)s] %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S"
)

ch = logging.StreamHandler()
ch.setFormatter(formatter)
logger.addHandler(ch)

fh = logging.FileHandler(LOG_FILE, encoding="utf-8")
fh.setFormatter(formatter)
logger.addHandler(fh)

# ---------------------------------------------------------
# AUTO_CAPTURE MODULE (MERGED HERE)
# ---------------------------------------------------------

class CaptureError(Exception):
    def __init__(self, code, message, details=None):
        self.code = code
        self.message = message
        self.details = details
        super().__init__(message)

# Main configuration
DEVICE = "192.168.31.177:5555"
PACKAGE = "com.yiyuan.skin"
CAMERA_ACTIVITY = "com.yiyuan.skin/.ui.activity.CameraActivity"

REMOTE_ROOT = "/sdcard/yiyuan/image"
LOCAL_ROOT = pathlib.Path.home() / "Desktop" / "revised AIA database"

AI_TAP_X = 539
AI_TAP_Y = 1789

CAPTURE_WAIT_SECONDS = 14


def run(cmd):
    try:
        return sp.check_output(cmd, shell=True, text=True)
    except sp.CalledProcessError as e:
        raise CaptureError(
            code="ADB_COMMAND_FAILED",
            message=f"ADB failed: {cmd}",
            details=str(e)
        )


def adb(cmd):
    return run(f"adb -s {DEVICE} {cmd}")


def connect_device():
    try:
        out = run(f"adb connect {DEVICE}")
        if "connected" not in out.lower():
            raise CaptureError(
                code="DEVICE_CONNECTION_FAILED",
                message="Could not connect to scanning device.",
                details=out
            )
        time.sleep(1)
    except Exception as e:
        raise CaptureError(
            code="DEVICE_CONNECTION_FAILED",
            message="Failed to connect to scanning device.",
            details=str(e)
        )


def open_camera():
    try:
        adb(f"shell am start -n {CAMERA_ACTIVITY}")
        time.sleep(2)
    except Exception as e:
        raise CaptureError(
            code="CAMERA_LAUNCH_FAILED",
            message="Unable to launch camera.",
            details=str(e)
        )


def trigger_ai_capture():
    try:
        adb(f"shell input tap {AI_TAP_X} {AI_TAP_Y}")
        time.sleep(CAPTURE_WAIT_SECONDS)
    except Exception as e:
        raise CaptureError(
            code="CAPTURE_TRIGGER_FAILED",
            message="Failed to trigger AI capture.",
            details=str(e)
        )


def get_latest_timestamp(today):
    try:
        result = adb(f"shell ls -t {REMOTE_ROOT}/{today}")
        files = result.strip().split("\n")

        if not files or files == ['']:
            raise CaptureError(
                code="NO_TIMESTAMP_FOUND",
                message="No image folder detected.",
                details="Device returned empty list."
            )

        ts = files[0].split("-")[0]
        return ts

    except Exception as e:
        raise CaptureError(
            code="TIMESTAMP_LOOKUP_FAILED",
            message="Failed to read timestamp.",
            details=str(e)
        )


def pull_images(today, ts):
    try:
        local_dir = LOCAL_ROOT / today / ts
        local_dir.mkdir(parents=True, exist_ok=True)

        mapping = {
            f"{ts}-image.jpg": "white.jpg",
            f"{ts}-image_positive.jpg": "positive.jpg",
            f"{ts}-image_negative.jpg": "negative.jpg",
            f"{ts}-image_uv.jpg": "uv.jpg",
            f"{ts}-image_woods.jpg": "woods.jpg",
            f"{ts}-image_blue.jpg": "blue.jpg"
        }

        for original, new in mapping.items():
            remote_path = f"{REMOTE_ROOT}/{today}/{original}"
            adb(f"pull {remote_path} '{local_dir}'")

            old_file = local_dir / original
            new_file = local_dir / new

            if old_file.exists():
                old_file.rename(new_file)
            else:
                raise CaptureError(
                    code="IMAGE_MISSING",
                    message=f"Missing: {original}",
                    details=f"{remote_path} not found."
                )

        return local_dir

    except Exception as e:
        raise CaptureError(
            code="IMAGE_PULL_FAILED",
            message="Failed pulling images from device.",
            details=str(e)
        )


def auto_capture_main():
    try:
        connect_device()
        open_camera()
        trigger_ai_capture()

        today = datetime.date.today().isoformat()
        ts = get_latest_timestamp(today)

        local_folder = pull_images(today, ts)
        return str(local_folder)

    except CaptureError as err:
        return {
            "error": True,
            "error_code": err.code,
            "message": err.message,
            "details": err.details
        }

    except Exception as e:
        return {
            "error": True,
            "error_code": "UNKNOWN_ERROR",
            "message": "Unexpected error occurred.",
            "details": str(e)
        }


# ---------------------------------------------------------
# FLASK AGENT
# ---------------------------------------------------------

app = Flask(__name__)


def check_api_key():
    if not API_KEY:
        return True
    key = request.headers.get("X-API-KEY")
    return key == API_KEY


@app.before_request
def before_request():
    if request.path == "/health":
        return None

    if not check_api_key():
        return jsonify({
            "status": "error",
            "message": "Unauthorized"
        }), 401


@app.route("/health", methods=["GET"])
def health():
    return jsonify({
        "status": "ok",
        "message": "auto capture agent running",
        "has_auto_capture": True
    })


def run_auto_capture():
    logger.info("Starting auto_capture_main()")

    stdout_buffer = StringIO()
    stderr_buffer = StringIO()

    folder_path = None
    error_obj = None
    start_time = time.time()

    try:
        with redirect_stdout(stdout_buffer), redirect_stderr(stderr_buffer):
            result = auto_capture_main()

        if isinstance(result, dict) and result.get("error"):
            logger.error("auto_capture failed: %s", result)
            return False, result, None

        folder_path = result
        logger.info("auto_capture succeeded.")

        # Auto open folder
        try:
            if platform.system() == "Windows":
                os.startfile(folder_path)
            elif platform.system() == "Darwin":
                subprocess.Popen(["open", folder_path])
            else:
                subprocess.Popen(["xdg-open", folder_path])
        except:
            pass

        success = True

    except Exception as e:
        success = False
        error_obj = {
            "error": True,
            "error_code": "AGENT_EXCEPTION",
            "message": "Unexpected failure.",
            "details": str(e)
        }
        logger.error("Unexpected agent error: %s", e)

    duration = time.time() - start_time

    logs = stdout_buffer.getvalue() + stderr_buffer.getvalue()
    if logs.strip():
        logs += f"\n[Finished in {duration:.2f} sec]"

    if success:
        return True, logs, folder_path

    if error_obj is None:
        error_obj = {
            "error": True,
            "error_code": "UNKNOWN_FAILURE",
            "message": "Unknown agent failure.",
            "details": logs
        }

    error_obj["logs"] = logs
    return False, error_obj, None


@app.route("/auto-capture-process", methods=["GET", "POST"])
def auto_capture_process():
    logger.info("Received /auto-capture-process request")

    success, output, folder_path = run_auto_capture()

    if success:
        return jsonify({
            "status": "success",
            "folder": folder_path,
            "output": output
        })

    return jsonify({
        "status": "error",
        "error_code": output.get("error_code"),
        "message": output.get("message"),
        "details": output.get("details"),
        "logs": output.get("logs")
    }), 500


# ---------------------------------------------------------
# ENTRY POINT
# ---------------------------------------------------------

if __name__ == "__main__":
    logger.info("Starting combined agent on %s:%s", HOST, PORT)
    app.run(host=HOST, port=PORT)
